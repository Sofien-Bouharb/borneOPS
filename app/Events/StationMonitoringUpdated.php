<?php

namespace App\Events;

use App\Http\Resources\SupervisionStationResource;
use App\Models\ChargingStation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StationMonitoringUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ChargingStation $station;

    public function __construct(ChargingStation $station)
    {
        $this->station = $station;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('supervision'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'station.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'station' => (new SupervisionStationResource($this->station))->resolve(),
        ];
    }
}
