<?php

namespace App\Plugins\RealPolitik\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PowerSignalsCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<int>  $signalIds
     */
    public function __construct(public array $signalIds) {}

    public function broadcastOn(): Channel
    {
        return new Channel('realpolitik');
    }

    public function broadcastAs(): string
    {
        return 'signals.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'signal_ids' => $this->signalIds,
            'count' => count($this->signalIds),
        ];
    }
}
