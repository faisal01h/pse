<?php

namespace App\Plugins\AutoVet\Jobs;

use App\Models\BusinessAffiliation;
use App\Models\PublicRecord;
use App\Plugins\AutoVet\AutoVetService;
use App\Plugins\PluginManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoVetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $subjectType,
        public int $subjectId,
    ) {}

    public function handle(AutoVetService $service, PluginManager $plugins): void
    {
        if (! $plugins->isEnabled('autovet')) {
            return;
        }

        $subject = $this->resolve();
        if ($subject === null) {
            return;
        }

        if ((int) $subject->status !== 0) {
            return;
        }

        $service->runFor($subject);
    }

    private function resolve(): PublicRecord|BusinessAffiliation|null
    {
        if ($this->subjectType === PublicRecord::class || $this->subjectType === (new PublicRecord)->getMorphClass()) {
            return PublicRecord::query()->find($this->subjectId);
        }

        if ($this->subjectType === BusinessAffiliation::class || $this->subjectType === (new BusinessAffiliation)->getMorphClass()) {
            return BusinessAffiliation::query()->with(['politician', 'business'])->find($this->subjectId);
        }

        return null;
    }
}
