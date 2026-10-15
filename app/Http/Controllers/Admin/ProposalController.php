<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IdeologyPosition;
use App\Models\Issue;
use App\Models\IssueStance;
use App\Models\PoliticianLink;
use App\Models\RelationshipProposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProposalController extends Controller
{
    public function accept(Request $request, RelationshipProposal $proposal): RedirectResponse
    {
        abort_unless($proposal->status === RelationshipProposal::STATUS_PENDING, 404);
        $proposal->load('record.politician');
        $record = $proposal->record;
        abort_unless($record && $record->politician, 404);

        $excerpt = trim($proposal->excerpt);
        $note = mb_strlen($excerpt) >= 40
            ? $excerpt
            : trim((string) $request->validate([
                'note' => 'required|string|min:40|max:5000',
            ], [
                'note.required' => 'Catatan sumber wajib diisi.',
                'note.min' => 'Catatan sumber minimal 40 karakter.',
            ])['note']);

        $this->write($proposal, $record->politician_id, $note, $request->user()->id);
        $proposal->update([
            'status' => RelationshipProposal::STATUS_ACCEPTED,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return back();
    }

    public function dismiss(Request $request, RelationshipProposal $proposal): RedirectResponse
    {
        abort_unless($proposal->status === RelationshipProposal::STATUS_PENDING, 404);
        $proposal->update([
            'status' => RelationshipProposal::STATUS_DISMISSED,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return back();
    }

    private function write(RelationshipProposal $proposal, int $politicianId, string $note, int $userId): void
    {
        $record = $proposal->record;
        $source = [
            'source_url' => $record->source_url,
            'source_type' => $record->source_type,
            'occurred_on' => $record->occurred_on,
            'user_id' => $userId,
        ];
        $payload = $proposal->payload;

        if ($proposal->kind === RelationshipProposal::KIND_AXIS) {
            $position = IdeologyPosition::query()
                ->where('politician_id', $politicianId)
                ->where('axis', $payload['axis'])
                ->first() ?? new IdeologyPosition;
            $incoming = (int) $payload['score'];
            $blended = $position->exists
                ? IdeologyPosition::blendScore((int) $position->score, (int) ($position->samples ?: 1), $incoming)
                : ['score' => max(0, min(100, $incoming)), 'samples' => 1];
            $position->fill([
                'politician_id' => $politicianId,
                'party_id' => null,
                'axis' => $payload['axis'],
                'score' => $blended['score'],
                'samples' => $blended['samples'],
                'note' => $note,
                ...$source,
            ]);
            $position->save();
            $position->basisRecords()->syncWithoutDetaching([$record->id]);

            return;
        }

        if ($proposal->kind === RelationshipProposal::KIND_STANCE) {
            $issue = Issue::findOrCreateByName((string) $payload['issue_name']);
            $stance = IssueStance::query()
                ->where('politician_id', $politicianId)
                ->where('issue_id', $issue->id)
                ->first() ?? new IssueStance;
            $stance->fill([
                'issue_id' => $issue->id,
                'politician_id' => $politicianId,
                'party_id' => null,
                'position' => $payload['position'],
                'note' => $note,
                ...$source,
            ]);
            $stance->save();

            return;
        }

        $otherId = (int) $payload['other_politician_id'];
        $type = (string) $payload['type'];
        [$a, $b] = PoliticianLink::endpoints($type, $politicianId, $otherId);
        $exists = PoliticianLink::query()
            ->where('politician_a_id', $a)
            ->where('politician_b_id', $b)
            ->where('type', $type)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'other_id' => 'Hubungan ini sudah tercatat.',
            ]);
        }

        PoliticianLink::create([
            'politician_a_id' => $a,
            'politician_b_id' => $b,
            'type' => $type,
            'summary' => $note,
            ...$source,
        ]);
    }
}
