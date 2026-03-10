<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageQuotaUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public array $quota) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('message-quota'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.quota.updated';
    }

    public function broadcastWith(): array
    {
        return $this->quota;
    }
}
