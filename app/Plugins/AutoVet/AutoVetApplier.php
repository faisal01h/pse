<?php

namespace App\Plugins\AutoVet;

use App\Catalog\ReliabilityLevel;
use App\Inference\InferenceRunner;
use App\Models\Business;
use App\Models\BusinessAffiliation;
use App\Models\Politician;
use App\Models\PublicRecord;
use App\Models\Topic;
use App\Settings\SettingsRepository;

class AutoVetApplier
{
    public function __construct(
        private SettingsRepository $settings,
        private InferenceRunner $inference,
    ) {}

    public function apply(PublicRecord|BusinessAffiliation $subject, VetResult $result, string $mode): string
    {
        $shouldAuto = $this->shouldAutoDecide($result, $mode);
        $action = 'draft';

        if (! $shouldAuto
            || $result->decision === 'needs_human'
            || ($result->decision === 'publish' && ! $result->allChecksPassed())
            || ($result->decision === 'publish' && $result->reliabilityLevel === null)
        ) {
            $subject->autovet_draft = $result->toArray();
            $subject->save();
        } else {
            $this->applyDecision($subject, $result);
            $subject->autovet_draft = null;
            $subject->save();
            $action = 'applied';
        }

        if ($subject instanceof PublicRecord) {
            $subject->addTopics([Topic::AI_CHECKED]);
        }

        return $action;
    }

    private function shouldAutoDecide(VetResult $result, string $mode): bool
    {
        return match ($mode) {
            'auto_decide' => true,
            'hybrid' => $result->confidence >= $this->settings->getFloat('autovet.hybrid_min_confidence', 0.8)
                && $result->decision !== 'needs_human',
            default => false,
        };
    }

    private function applyDecision(PublicRecord|BusinessAffiliation $subject, VetResult $result): void
    {
        $publish = $result->decision === 'publish';
        $reliability = ReliabilityLevel::clampForAuto($result->reliabilityLevel);

        $subject->fill([
            'review_note' => $result->reviewNote,
            'reviewed_at' => now(),
            'reviewed_by' => null,
            'check_source' => $result->checkSource,
            'check_wording' => $result->checkWording,
            'check_category' => $result->checkCategory,
            'check_duplicate' => $result->checkDuplicate,
            'check_legal_label' => $result->checkLegalLabel,
            'reliability_level' => $publish ? $reliability : $subject->reliability_level,
            'status' => $publish
                ? ($subject instanceof PublicRecord ? PublicRecord::STATUS_PUBLISHED : BusinessAffiliation::STATUS_PUBLISHED)
                : ($subject instanceof PublicRecord ? PublicRecord::STATUS_REJECTED : BusinessAffiliation::STATUS_REJECTED),
        ]);

        if ($subject instanceof BusinessAffiliation && $publish) {
            $subject->applyRepublishAfterDisputeRetract();
            $subject->dispute_retract_pending = false;
        }

        $subject->save();

        $politician = $subject->politician;
        if ($politician) {
            $politician->status = $this->politicianShouldBePublished($politician) ? 1 : 0;
            $politician->save();
        }

        if ($subject instanceof BusinessAffiliation && $publish) {
            $business = $subject->business;
            if ($business) {
                $business->status = Business::STATUS_PUBLISHED;
                $business->save();
            }
        }

        if ($subject instanceof PublicRecord && $publish) {
            $this->inference->run($subject, null, 'autovet');
        }
    }

    private function politicianShouldBePublished(Politician $politician): bool
    {
        return $politician->records()->where('status', PublicRecord::STATUS_PUBLISHED)->exists()
            || $politician->businessAffiliations()->where('status', BusinessAffiliation::STATUS_PUBLISHED)->exists();
    }
}
