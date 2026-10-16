<?php

namespace Tests\Feature;

use App\Models\JobRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Tests\TestCase;

class JobMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_monitor_requires_settings_permission(): void
    {
        $this->get(route('admin.settings.jobs'))->assertRedirect(route('login'));

        $this->actingAs($this->makePlainUser())
            ->get(route('admin.settings.jobs'))
            ->assertForbidden();
    }

    public function test_finished_and_failed_jobs_appear_with_details(): void
    {
        MonitorSuccessJob::dispatch('hello-monitor');

        MonitorFailJob::$shouldFail = true;
        try {
            MonitorFailJob::dispatch('broken-probe');
            $this->fail('The failing probe was expected to throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Monitor probe failed on purpose.', $exception->getMessage());
        }

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.jobs'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings/Jobs')
                ->where('counts.finished', 1)
                ->where('counts.failed', 1)
                ->where('connection', 'sync'));

        $failed = JobRun::query()->where('status', JobRun::FAILED)->first();
        $this->assertNotNull($failed);
        $this->assertSame('broken-probe', $failed->summary['label'] ?? null);
        $this->assertStringContainsString('Monitor probe failed on purpose.', (string) $failed->exception);
        $this->assertSame(MonitorFailJob::class, $failed->name);

        $this->actingAs($admin)
            ->get(route('admin.settings.jobs', ['status' => 'failed']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('jobs', 1)
                ->where('jobs.0.status', 'failed')
                ->where('jobs.0.summary.label', 'broken-probe')
                ->where('filters.status', 'failed'));
    }

    public function test_failed_job_can_be_retried_and_removed(): void
    {
        MonitorFailJob::$shouldFail = true;
        try {
            MonitorFailJob::dispatch('retry-me');
        } catch (RuntimeException) {
            // recorded as failed
        }

        $admin = $this->makeAdmin();
        $run = JobRun::query()->where('status', JobRun::FAILED)->firstOrFail();

        // The sync queue never writes to failed_jobs, so seed the row a database queue would leave.
        DB::table('failed_jobs')->insert([
            'uuid' => $run->uuid,
            'connection' => 'sync',
            'queue' => 'default',
            'payload' => json_encode(['data' => ['command' => serialize(new MonitorFailJob('retry-me'))]]),
            'exception' => 'RuntimeException: Monitor probe failed on purpose.',
            'failed_at' => now(),
        ]);

        MonitorFailJob::$shouldFail = false;
        $this->actingAs($admin)
            ->post(route('admin.settings.jobs.retry', $run))
            ->assertRedirect();

        $finished = JobRun::query()->where('status', JobRun::FINISHED)->get()
            ->first(fn (JobRun $row) => ($row->summary['label'] ?? null) === 'retry-me');
        $this->assertNotNull($finished);
        $this->assertNull($finished->exception);

        $this->actingAs($admin)
            ->delete(route('admin.settings.jobs.destroy', $finished))
            ->assertRedirect();

        $this->assertDatabaseMissing('job_runs', ['id' => $finished->id]);
    }
}

class MonitorSuccessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $label) {}

    public function handle(): void {}
}

class MonitorFailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public static bool $shouldFail = true;

    public function __construct(public string $label) {}

    public function handle(): void
    {
        if (self::$shouldFail) {
            throw new RuntimeException('Monitor probe failed on purpose.');
        }
    }
}
