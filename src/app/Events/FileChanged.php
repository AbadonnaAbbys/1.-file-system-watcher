<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FileChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $path;
    public string $type;
    public string $source;

    /**
     * Create a new event instance.
     */
    public function __construct(string $path, string $type, string $source)
    {
        $this->path = $path;
        $this->type = $type; // 'created', 'modified', 'deleted'
        $this->source = $source; // 'external' or 'internal'
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [];
    }
}
