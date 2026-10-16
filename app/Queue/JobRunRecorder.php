<?php

namespace App\Queue;

use App\Models\JobRun;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use ReflectionObject;
use ReflectionProperty;
use Throwable;

class JobRunRecorder
{
    public function queued(JobQueued $event): void
    {
        $this->safely(function () use ($event) {
            $payload = $event->payload();
            $uuid = $payload['uuid'] ?? null;
            if (! is_string($uuid) || $uuid === '') {
                return;
            }

            $run = $this->findOrNew($uuid, $payload, (string) $event->connectionName, $event->queue);
            $run->status = JobRun::QUEUED;
            $run->exception = null;
            $run->finished_at = null;
            $run->duration_ms = null;
            if ($run->queued_at === null) {
                $run->queued_at = now();
            }
            $run->save();
        });
    }

    public function processing(JobProcessing $event): void
    {
        $this->safely(function () use ($event) {
            $payload = $event->job->payload();
            $run = $this->findOrNew($event->job->uuid(), $payload, (string) $event->connectionName, $event->job->getQueue());
            $run->status = JobRun::RUNNING;
            $run->attempts = max(1, (int) $event->job->attempts());
            $run->exception = null;
            $run->finished_at = null;
            $run->duration_ms = null;
            if ($run->started_at === null || $run->getOriginal('status') === JobRun::FAILED || $run->getOriginal('status') === JobRun::FINISHED) {
                $run->started_at = now();
            }
            $run->save();
        });
    }

    public function processed(JobProcessed $event): void
    {
        $this->safely(function () use ($event) {
            $payload = $event->job->payload();
            $run = $this->findOrNew($event->job->uuid(), $payload, (string) $event->connectionName, $event->job->getQueue());
            $run->status = JobRun::FINISHED;
            $run->attempts = max((int) $run->attempts, (int) $event->job->attempts());
            $run->exception = null;
            $run->finished_at = now();
            $run->duration_ms = $this->durationMs($event->duration, $run);
            $run->save();
        });
    }

    public function failed(JobFailed $event): void
    {
        $this->safely(function () use ($event) {
            $payload = $event->job->payload();
            $run = $this->findOrNew($event->job->uuid(), $payload, (string) $event->connectionName, $event->job->getQueue());
            $run->status = JobRun::FAILED;
            $run->attempts = max((int) $run->attempts, (int) $event->job->attempts());
            $run->finished_at = now();
            $run->duration_ms = $this->durationMs(null, $run);
            $run->exception = $this->exceptionText($event->exception);
            $run->save();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findOrNew(string $uuid, array $payload, string $connection, ?string $queue): JobRun
    {
        $run = JobRun::query()->firstOrNew(['uuid' => $uuid]);
        $run->name = (string) ($payload['displayName'] ?? $payload['data']['commandName'] ?? 'Job');
        $run->connection = $connection !== '' ? $connection : (string) config('queue.default');
        $run->queue = $queue !== null && $queue !== '' ? $queue : 'default';
        $run->max_tries = isset($payload['maxTries']) ? (int) $payload['maxTries'] : $run->max_tries;
        $run->timeout = isset($payload['timeout']) ? (int) $payload['timeout'] : $run->timeout;
        $summary = $this->summary($payload);
        if ($summary !== []) {
            $run->summary = $summary;
        }

        return $run;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function summary(array $payload): array
    {
        $command = $payload['data']['command'] ?? null;
        if (! is_string($command) || $command === '') {
            $name = $payload['data']['commandName'] ?? null;

            return is_string($name) ? ['command' => $name] : [];
        }

        try {
            $object = unserialize($command, ['allowed_classes' => true]);
        } catch (Throwable) {
            return [];
        }

        if (! is_object($object)) {
            return [];
        }

        $summary = [];
        foreach ((new ReflectionObject($object))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $name = $property->getName();
            if ($this->sensitive($name)) {
                $summary[$name] = '[redacted]';

                continue;
            }
            $summary[$name] = $this->present($property->getValue($object));
        }

        return $summary;
    }

    private function present(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return mb_strlen($value) > 500 ? mb_substr($value, 0, 500).'…' : $value;
        }

        if (is_array($value)) {
            $encoded = json_encode($value);
            if (! is_string($encoded)) {
                return '[array]';
            }

            return strlen($encoded) > 500 ? substr($encoded, 0, 500).'…' : json_decode($encoded, true);
        }

        if (is_object($value)) {
            return $value::class;
        }

        return '['.gettype($value).']';
    }

    private function sensitive(string $name): bool
    {
        return (bool) preg_match('/password|secret|token|api_key|authorization|credential/i', $name);
    }

    private function durationMs(mixed $reported, JobRun $run): ?int
    {
        if (is_numeric($reported)) {
            return max(0, (int) round((float) $reported));
        }

        if ($run->started_at === null) {
            return null;
        }

        return max(0, (int) round(abs($run->started_at->diffInMilliseconds(now()))));
    }

    private function exceptionText(Throwable $exception): string
    {
        $text = $exception::class.': '.$exception->getMessage()
            ."\n".$exception->getFile().':'.$exception->getLine()
            ."\n".$exception->getTraceAsString();

        return mb_substr($text, 0, 20000);
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
