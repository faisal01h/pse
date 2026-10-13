<?php

namespace App\Auditing;

use App\Models\Audit;
use Illuminate\Support\Collection;

class InferenceNotice
{
    /**
     * @return array{type: string, message: string}
     */
    public static function fromAudit(Audit $audit): array
    {
        if ($audit->status === Audit::STATUS_FAILED) {
            return [
                'type' => 'failure',
                'message' => $audit->message ? 'Inferensi gagal. '.$audit->message : 'Inferensi gagal.',
            ];
        }

        if ($audit->status === Audit::STATUS_SKIPPED) {
            return [
                'type' => 'failure',
                'message' => $audit->message ?: 'Inferensi dilewati.',
            ];
        }

        $created = (int) $audit->proposals_created;

        return [
            'type' => 'success',
            'message' => $created === 0
                ? 'Inferensi selesai. Tidak ada usulan baru.'
                : 'Inferensi selesai. '.$created.' usulan dibuat.',
        ];
    }

    /**
     * @param  iterable<int, Audit>  $audits
     * @return array{type: string, message: string}
     */
    public static function fromAudits(iterable $audits): array
    {
        $audits = $audits instanceof Collection ? $audits : collect($audits);

        if ($audits->isEmpty()) {
            return [
                'type' => 'failure',
                'message' => 'Tidak ada catatan terbit untuk diinferensi.',
            ];
        }

        $failed = $audits->filter(fn (Audit $audit) => in_array($audit->status, [Audit::STATUS_FAILED, Audit::STATUS_SKIPPED], true))->count();
        $done = $audits->count() - $failed;

        if ($failed === 0) {
            return [
                'type' => 'success',
                'message' => 'Inferensi selesai untuk '.$done.' catatan.',
            ];
        }

        if ($done === 0) {
            return [
                'type' => 'failure',
                'message' => 'Inferensi gagal untuk '.$failed.' catatan.',
            ];
        }

        return [
            'type' => 'failure',
            'message' => 'Inferensi selesai untuk '.$done.' catatan, gagal untuk '.$failed.'.',
        ];
    }
}
