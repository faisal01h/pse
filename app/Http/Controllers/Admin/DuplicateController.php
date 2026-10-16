<?php

namespace App\Http\Controllers\Admin;

use App\Duplicates\DuplicateDetector;
use App\Duplicates\NameNormalizer;
use App\Duplicates\RecordMerger;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Party;
use App\Models\Politician;
use App\Models\RecordMerge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class DuplicateController extends Controller
{
    private const LABELS = [
        NameNormalizer::POLITICIAN => 'Politisi',
        NameNormalizer::PARTY => 'Partai',
        NameNormalizer::BUSINESS => 'Bisnis',
    ];

    public function index(Request $request, DuplicateDetector $detector): Response
    {
        $type = $this->type($request->query('type'));

        return Inertia::render('Admin/Duplicates', [
            'type' => $type,
            'types' => collect(self::LABELS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'pairs' => collect($detector->pairs($type))->map(fn (array $pair) => [
                'kind' => $pair['kind'],
                'score' => $pair['score'],
                'a' => $this->summary($type, $pair['a']),
                'b' => $this->summary($type, $pair['b']),
            ])->values(),
            'history' => RecordMerge::query()
                ->where('entity_type', $type)
                ->latest('id')
                ->limit(20)
                ->get(['id', 'source_id', 'canonical_id', 'source_name', 'summary', 'created_at']),
        ]);
    }

    public function show(Request $request, RecordMerger $merger): Response|RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(DuplicateDetector::types())],
            'a' => 'required|integer',
            'b' => 'required|integer|different:a',
        ]);

        $model = DuplicateDetector::modelFor($validated['type']);
        $a = $model::query()->find($validated['a']);
        $b = $model::query()->find($validated['b']);

        if (! $a || ! $b) {
            return redirect()->route('admin.duplicates.index', ['type' => $validated['type']])
                ->withErrors(['merge' => 'Salah satu data sudah tidak ada.']);
        }

        return Inertia::render('Admin/DuplicateMerge', [
            'type' => $validated['type'],
            'typeLabel' => self::LABELS[$validated['type']],
            'candidates' => [
                $this->summary($validated['type'], $a, $merger),
                $this->summary($validated['type'], $b, $merger),
            ],
        ]);
    }

    public function merge(Request $request, RecordMerger $merger): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(DuplicateDetector::types())],
            'canonical_id' => 'required|integer',
            'source_id' => 'required|integer|different:canonical_id',
        ], [
            'source_id.different' => 'Pilih dua data yang berbeda.',
        ]);

        $model = DuplicateDetector::modelFor($validated['type']);
        $canonical = $model::query()->find($validated['canonical_id']);
        $source = $model::query()->find($validated['source_id']);

        if (! $canonical || ! $source) {
            return redirect()->route('admin.duplicates.index', ['type' => $validated['type']])
                ->withErrors(['merge' => 'Salah satu data sudah tidak ada.']);
        }

        try {
            $merger->merge($validated['type'], $canonical, $source, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['merge' => $e->getMessage()]);
        }

        return redirect()->route('admin.duplicates.index', ['type' => $validated['type']])
            ->with('toast', '"'.$source->name.'" digabung ke "'.$canonical->name.'".');
    }

    private function type(mixed $type): string
    {
        return in_array($type, DuplicateDetector::types(), true) ? $type : NameNormalizer::POLITICIAN;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(string $type, Model $row, ?RecordMerger $merger = null): array
    {
        $data = ['id' => $row->getKey(), 'name' => $row->name];

        if ($row instanceof Politician) {
            $data += [
                'detail' => trim($row->position.' · '.$row->institution),
                'party' => $row->party?->name,
                'records' => $row->records()->count(),
            ];
        } elseif ($row instanceof Party) {
            $data += ['detail' => null, 'members' => $row->politicians()->count()];
        } elseif ($row instanceof Business) {
            $data += ['detail' => $row->sector, 'affiliations' => $row->affiliations()->count()];
        }

        if ($merger) {
            $data['moves'] = $merger->preview($type, $row);
        }

        return $data;
    }
}
