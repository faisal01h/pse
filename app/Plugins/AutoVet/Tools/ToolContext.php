<?php

namespace App\Plugins\AutoVet\Tools;

class ToolContext
{
    /**
     * @param  list<string>  $allowedHosts
     */
    public function __construct(
        public readonly array $allowedHosts,
        public readonly string $sourceUrl,
        public readonly ?int $recordId,
        public readonly ?int $affiliationId,
        public readonly bool $terminalEnabled,
        public readonly bool $browserEnabled,
        public readonly int $depth = 0,
    ) {}

    public function withDepth(int $depth): self
    {
        return new self(
            $this->allowedHosts,
            $this->sourceUrl,
            $this->recordId,
            $this->affiliationId,
            $this->terminalEnabled,
            $this->browserEnabled,
            $depth,
        );
    }
}
