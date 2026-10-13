<?php

namespace App\Http\Controllers\Admin;

use App\Auditing\InferenceNotice;
use App\Http\Controllers\Controller;
use App\Inference\InferenceRunner;
use App\Models\Audit;
use App\Models\Page;
use App\Models\Party;
use App\Models\Politician;
use App\Models\PublicRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function __construct(private InferenceRunner $runner) {}

    public function index(): Response
    {
        $records = PublicRecord::query()
            ->with(['politician:id,name'])
            ->withMax('audits', 'created_at')
            ->where('status', PublicRecord::STATUS_PUBLISHED)
            ->latest('id')
            ->get(['id', 'politician_id', 'category', 'quote', 'summary', 'status']);

        $audits = Audit::query()
            ->with(['user:id,name', 'record.politician:id,name'])
            ->latest('id')
            ->limit(80)
            ->get();
        $audits->loadMorph('auditable', [
            Politician::class => [],
            Party::class => [],
            Page::class => [],
            PublicRecord::class => [],
        ]);

        return Inertia::render('Admin/Audits', [
            'records' => $records->map(fn (PublicRecord $record) => [
                'id' => $record->id,
                'politician' => $record->politician?->name,
                'category' => $record->category,
                'excerpt' => $this->excerpt($record),
                'last_run_at' => $record->audits_max_created_at,
            ])->values(),
            'audits' => $audits->map(fn (Audit $audit) => [
                'id' => $audit->id,
                'action' => $audit->action ?: Audit::ACTION_INFERENCE,
                'subject' => $audit->subject ?: ($audit->record?->politician?->name ? 'Catatan: '.$audit->record->politician->name : 'Inferensi'),
                'detail' => $this->detail($audit),
                'href' => $this->href($audit),
                'created_at' => $audit->created_at,
                'user' => $audit->user?->name,
            ])->values(),
        ]);
    }

    public function runAll(Request $request): RedirectResponse
    {
        $audits = [];
        PublicRecord::query()
            ->where('status', PublicRecord::STATUS_PUBLISHED)
            ->orderBy('id')
            ->each(function (PublicRecord $record) use ($request, &$audits) {
                $audits[] = $this->runner->run($record, $request->user()->id, Audit::TRIGGER_MANUAL);
            });

        return redirect()
            ->route('admin.audits.index')
            ->with('toast', InferenceNotice::fromAudits($audits));
    }

    public function run(Request $request, PublicRecord $record): RedirectResponse
    {
        abort_unless((int) $record->status === PublicRecord::STATUS_PUBLISHED, 404);
        $audit = $this->runner->run($record, $request->user()->id, Audit::TRIGGER_MANUAL);

        return back()->with('toast', InferenceNotice::fromAudit($audit));
    }

    private function detail(Audit $audit): string
    {
        if (($audit->action ?: Audit::ACTION_INFERENCE) === Audit::ACTION_INFERENCE) {
            $status = match ($audit->status) {
                Audit::STATUS_RAN => 'Selesai',
                Audit::STATUS_SKIPPED => 'Dilewati',
                Audit::STATUS_FAILED => 'Gagal',
                default => (string) $audit->status,
            };
            $trigger = $audit->trigger === Audit::TRIGGER_MANUAL ? 'Manual' : 'Terbit';
            $parts = array_filter([$trigger, $this->methodLabel($audit->method), $status, $audit->proposals_created.' usulan']);
            $text = implode(' · ', $parts);

            return $audit->message ? $text.' · '.$audit->message : $text;
        }

        $changes = $audit->changes ?? [];

        if ($audit->action === Audit::ACTION_UPDATED) {
            return collect($changes)->take(4)->map(function ($change, $key) {
                return $key.': '.$this->plain($change['from'] ?? null).' → '.$this->plain($change['to'] ?? null);
            })->implode('; ');
        }

        return collect($changes)->take(4)->map(function ($value, $key) {
            return $key.': '.$this->plain($value);
        })->implode('; ');
    }

    private function href(Audit $audit): ?string
    {
        if ($audit->public_record_id && $audit->record) {
            return route('reports.review', $audit->public_record_id);
        }

        if (! $audit->auditable) {
            return null;
        }

        return match ($audit->auditable_type) {
            Politician::class => route('admin.politicians.edit', $audit->auditable_id),
            Party::class => route('admin.parties.edit', $audit->auditable_id),
            Page::class => route('admin.pages.edit', $audit->auditable_id),
            PublicRecord::class => route('reports.review', $audit->auditable_id),
            default => null,
        };
    }

    private function methodLabel(?string $method): ?string
    {
        if ($method === null || $method === '') {
            return null;
        }

        if ($method === 'local') {
            return 'Lokal';
        }

        if (str_starts_with($method, 'http:')) {
            $model = substr($method, 5);

            return $model === '' ? 'HTTP' : 'HTTP · '.$model;
        }

        if (str_starts_with($method, 'laya:')) {
            $model = substr($method, 5);

            return $model === '' ? 'Laya' : 'Laya · '.$model;
        }

        return match ($method) {
            'http' => 'HTTP',
            'laya' => 'Laya',
            default => $method,
        };
    }

    private function plain(mixed $value): string
    {
        if (is_array($value)) {
            if (($value['changed'] ?? false) === true) {
                return 'diubah';
            }

            return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if ($value === null || $value === '') {
            return 'kosong';
        }

        if (is_bool($value)) {
            return $value ? 'ya' : 'tidak';
        }

        return (string) $value;
    }

    private function excerpt(PublicRecord $record): string
    {
        $text = trim((string) ($record->category === PublicRecord::CATEGORY_STATEMENT ? $record->quote : $record->summary));

        return mb_strlen($text) > 90 ? mb_substr($text, 0, 90).'…' : $text;
    }
}
