<?php

namespace App\Auditing;

use App\Models\Audit;
use App\Models\Issue;
use App\Models\Page;
use App\Models\Party;
use App\Models\Politician;
use App\Models\PublicRecord;
use App\Models\PublicRecordTopic;
use App\Models\Topic;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ChangeLog
{
    private static bool $recording = false;

    public function created(Model $model): void
    {
        $this->write(Audit::ACTION_CREATED, $model, $this->snapshot($model));
    }

    public function updated(Model $model): void
    {
        $changes = [];

        foreach ($model->getChanges() as $key => $value) {
            if ($this->skip($model, $key)) {
                continue;
            }

            if ($key === 'password') {
                $changes['password'] = ['changed' => true];

                continue;
            }

            $changes[$key] = [
                'from' => $this->plain($model->getOriginal($key)),
                'to' => $this->plain($value),
            ];
        }

        $this->write(Audit::ACTION_UPDATED, $model, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->write(Audit::ACTION_DELETED, $model, $this->snapshot($model));
    }

    private function write(string $action, Model $model, array $changes): void
    {
        if ($changes === [] || self::$recording || $model instanceof Audit) {
            return;
        }

        self::$recording = true;

        try {
            Audit::query()->create([
                'user_id' => Auth::id(),
                'action' => $action,
                'subject' => $this->subject($model),
                'auditable_type' => $model::class,
                'auditable_id' => $model->getKey(),
                'trigger' => Audit::TRIGGER_DATA,
                'status' => Audit::STATUS_RECORDED,
                'proposals_created' => 0,
                'changes' => $changes,
            ]);
        } finally {
            self::$recording = false;
        }
    }

    private function snapshot(Model $model): array
    {
        $values = [];

        foreach ($model->getAttributes() as $key => $value) {
            if ($this->skip($model, $key)) {
                continue;
            }

            if ($key === 'password') {
                $values['password'] = ['changed' => true];

                continue;
            }

            $values[$key] = $this->plain($value);
        }

        return $values;
    }

    private function skip(Model $model, string $key): bool
    {
        if ($key === 'password') {
            return false;
        }

        return in_array($key, ['id', 'created_at', 'updated_at', 'remember_token', 'tracking_code_hash'], true)
            || in_array($key, $model->getHidden(), true);
    }

    private function plain(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null || is_array($value)) {
            return $value;
        }

        $text = (string) $value;

        return mb_strlen($text) > 500 ? mb_substr($text, 0, 500).'…' : $text;
    }

    private function subject(Model $model): string
    {
        return match ($model::class) {
            Politician::class => 'Politisi: '.$model->name,
            Party::class => 'Partai: '.$model->name,
            Page::class => 'Halaman: '.$model->title,
            Issue::class => 'Isu: '.$model->name,
            Topic::class => 'Topik: '.$model->name,
            PublicRecordTopic::class => 'Topik catatan: '.($model->topic?->name ?? '#'.$model->topic_id),
            User::class => 'Pengguna: '.$model->name,
            PublicRecord::class => 'Catatan: '.($this->politicianName($model) ?? '#'.$model->getKey()),
            default => class_basename($model).' #'.$model->getKey(),
        };
    }

    private function politicianName(PublicRecord $record): ?string
    {
        if ($record->relationLoaded('politician')) {
            return $record->politician?->name;
        }

        return Politician::query()->whereKey($record->politician_id)->value('name');
    }
}
