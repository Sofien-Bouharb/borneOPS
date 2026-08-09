<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ChargingSessionResource extends JsonResource
{
    /**
     * This is the single canonical shape for a charging session, used both
     * by REST responses and by the ChargingSessionUpdated WebSocket event
     * (Module 5 roadmap §15) — there is deliberately no separate shape for
     * either surface.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'charging_station_id' => $this->charging_station_id,
            'connector_id' => $this->connector_id,
            'customer_user_id' => $this->customer_user_id,
            'status' => $this->status,
            'reason_code' => $this->reason_code,
            'reason_detail' => $this->reason_detail,
            'meter_start_wh' => $this->meter_start_wh,
            'latest_meter_wh' => $this->latest_meter_wh,
            'meter_stop_wh' => $this->meter_stop_wh,
            'energy_consumed_wh' => $this->computeEnergyConsumedWh(),
            'energy_consumed_kwh' => $this->computeEnergyConsumedKwh(),
            'total_price' => $this->total_price,
            'currency' => $this->currency,
            'ocpp_transaction_id' => $this->ocpp_transaction_id,
            'started_at' => $this->started_at,
            'paused_at' => $this->paused_at,
            'total_paused_seconds' => $this->total_paused_seconds,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
            'duration_seconds' => $this->computeDurationSeconds(),
            'charging_seconds' => $this->computeChargingSeconds(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'charging_station' => $this->whenLoaded('chargingStation', fn () => [
                'id' => $this->chargingStation->id,
                'name' => $this->chargingStation->name,
                'reference' => $this->chargingStation->reference,
            ]),
            'connector' => $this->whenLoaded('connector', fn () => [
                'id' => $this->connector->id,
                'connector_number' => $this->connector->connector_number,
                'standard' => $this->connector->standard,
            ]),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
            ]),
        ];
    }

    /**
     * Energy consumed is always derived from monotonic meter readings, never
     * stored (Module 5 roadmap §6). Prefers meter_stop_wh once the session
     * has ended, falls back to the latest live reading otherwise.
     */
    protected function computeEnergyConsumedWh(): ?int
    {
        if ($this->meter_start_wh === null) {
            return null;
        }

        $latestReading = $this->meter_stop_wh ?? $this->latest_meter_wh;

        if ($latestReading === null) {
            return 0;
        }

        return max(0, $latestReading - $this->meter_start_wh);
    }

    protected function computeEnergyConsumedKwh(): ?float
    {
        $wh = $this->computeEnergyConsumedWh();

        if ($wh === null) {
            return null;
        }

        return round($wh / 1000, 3);
    }

    /**
     * Wall-clock duration since the session started, up to whichever
     * terminal timestamp applies, or up to now for a still-open session
     * (Module 5 roadmap §7). Never client-editable.
     */
    protected function computeDurationSeconds(): ?int
    {
        if ($this->started_at === null) {
            return null;
        }

        $endPoint = $this->completed_at ?? $this->cancelled_at ?? Carbon::now();

        return max(0, (int) $this->started_at->diffInSeconds($endPoint));
    }

    /**
     * Duration minus paused time (Module 5 roadmap §7). If the session is
     * currently paused, the ongoing pause window (since paused_at) is added
     * on top of total_paused_seconds so the figure stays accurate for a
     * session that has not yet been resumed.
     */
    protected function computeChargingSeconds(): ?int
    {
        $durationSeconds = $this->computeDurationSeconds();

        if ($durationSeconds === null) {
            return null;
        }

        $pausedSeconds = $this->total_paused_seconds ?? 0;

        if ($this->status === 'paused' && $this->paused_at !== null) {
            $pausedSeconds += (int) $this->paused_at->diffInSeconds(Carbon::now());
        }

        return max(0, $durationSeconds - $pausedSeconds);
    }
}
