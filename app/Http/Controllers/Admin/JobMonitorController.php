<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

class JobMonitorController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        if (! in_array($status, [JobRun::QUEUED, JobRun::RUNNING, JobRun::FINISHED, JobRun::FAILED], true)) {
            $status = null;
        }
        $q = trim((string) $request->query('q', ''));

        $counts = JobRun::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $jobs = JobRun::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('name', 'like', $like)
                        ->orWhere('uuid', 'like', $like)
                        ->orWhere('queue', 'like', $like);
                });
            })
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->map(fn (JobRun $run) => $this->present($run))
            ->values()
            ->all();

        return Inertia::render('Admin/Settings/Jobs', [
            'jobs' => $jobs,
            'counts' => [
                'queued' => (int) ($counts[JobRun::QUEUED] ?? 0),
                'running' => (int) ($counts[JobRun::RUNNING] ?? 0),
                'finished' => (int) ($counts[JobRun::FINISHED] ?? 0),
                'failed' => (int) ($counts[JobRun::FAILED] ?? 0),
            ],
            'filters' => [
                'status' => $status,
                'q' => $q,
            ],
            'connection' => (string) config('queue.default'),
        ]);
    }

    public function retry(JobRun $jobRun)
    {
        abort_unless($jobRun->status === JobRun::FAILED, 404);

        $failed = DB::table('failed_jobs')->where('uuid', $jobRun->uuid)->first();
        if ($failed === null) {
            return back()->with('error', 'This failure is no longer in the failed-job store, so it cannot be retried.');
        }

        if ($jobRun->connection === 'sync') {
            return $this->retrySynchronously($jobRun, $failed);
        }

        try {
            Artisan::call('queue:retry', ['id' => [$jobRun->uuid]]);
        } catch (Throwable $exception) {
            return back()->with('error', 'The retry ran and failed again: '.$exception->getMessage());
        }

        $jobRun->refresh();
        if ($jobRun->status === JobRun::FAILED) {
            $jobRun->forceFill([
                'status' => JobRun::QUEUED,
                'exception' => null,
                'finished_at' => null,
                'duration_ms' => null,
                'queued_at' => now(),
            ])->save();
        }

        return back()->with('success', 'Retry queued for '.class_basename($jobRun->name).'.');
    }

    private function retrySynchronously(JobRun $jobRun, object $failed)
    {
        $payload = json_decode((string) $failed->payload, true);
        $command = is_array($payload) ? ($payload['data']['command'] ?? null) : null;
        if (! is_string($command) || $command === '') {
            return back()->with('error', 'This failed job has no command to run again.');
        }

        try {
            $job = unserialize($command, ['allowed_classes' => true]);
        } catch (Throwable $exception) {
            return back()->with('error', 'This failed job could not be read: '.$exception->getMessage());
        }

        if (! is_object($job)) {
            return back()->with('error', 'This failed job could not be read.');
        }

        DB::table('failed_jobs')->where('uuid', $jobRun->uuid)->delete();
        $name = class_basename($jobRun->name);
        $jobRun->delete();

        try {
            dispatch($job);
        } catch (Throwable $exception) {
            return back()->with('error', 'The retry ran and failed again: '.$exception->getMessage());
        }

        return back()->with('success', 'Retried '.$name.'.');
    }

    public function destroy(JobRun $jobRun)
    {
        $jobRun->delete();

        return back()->with('success', 'Removed that run from the monitor.');
    }

    public function clearFinished()
    {
        $deleted = JobRun::query()->where('status', JobRun::FINISHED)->delete();

        return back()->with('success', 'Cleared '.$deleted.' finished '.($deleted === 1 ? 'job' : 'jobs').'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(JobRun $run): array
    {
        return [
            'id' => $run->id,
            'uuid' => $run->uuid,
            'name' => class_basename($run->name),
            'class' => $run->name,
            'connection' => $run->connection,
            'queue' => $run->queue,
            'status' => $run->status,
            'attempts' => $run->attempts,
            'max_tries' => $run->max_tries,
            'timeout' => $run->timeout,
            'summary' => $run->summary ?? [],
            'exception' => $run->exception,
            'queued_at' => optional($run->queued_at)->toIso8601String(),
            'started_at' => optional($run->started_at)->toIso8601String(),
            'finished_at' => optional($run->finished_at)->toIso8601String(),
            'duration_ms' => $run->duration_ms,
        ];
    }
}
