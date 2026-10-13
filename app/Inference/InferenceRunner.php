<?php

namespace App\Inference;

use App\Models\Audit;
use App\Models\PublicRecord;
use Throwable;

class InferenceRunner
{
    public function __construct(private ReportInference $inference) {}

    public function run(PublicRecord $record, ?int $userId, string $trigger): Audit
    {
        if ((int) $record->status !== PublicRecord::STATUS_PUBLISHED) {
            return $this->write($record, $userId, $trigger, Audit::STATUS_SKIPPED, 0, null, 'Catatan belum terbit.');
        }

        try {
            $created = $this->inference->refresh($record);

            return $this->write($record, $userId, $trigger, Audit::STATUS_RAN, $created, $this->inference->method(), null);
        } catch (Throwable $error) {
            return $this->write($record, $userId, $trigger, Audit::STATUS_FAILED, 0, $this->inference->method(), mb_substr($error->getMessage(), 0, 255));
        }
    }

    private function write(PublicRecord $record, ?int $userId, string $trigger, string $status, int $created, ?string $method, ?string $message): Audit
    {
        $name = $record->relationLoaded('politician')
            ? $record->politician?->name
            : $record->politician()->value('name');

        return Audit::query()->create([
            'user_id' => $userId,
            'action' => Audit::ACTION_INFERENCE,
            'subject' => 'Catatan: '.($name ?: '#'.$record->id),
            'auditable_type' => $record::class,
            'auditable_id' => $record->id,
            'public_record_id' => $record->id,
            'trigger' => $trigger,
            'method' => $method,
            'status' => $status,
            'proposals_created' => $created,
            'message' => $message,
        ]);
    }
}
