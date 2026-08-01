<?php

namespace App\Services;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\ChargingStation;
use App\Models\Connector;

class ConnectorService
{
    /**
     * Create a new connector under the given charging station.
     *
     * Physical connector creation is only allowed while the parent station
     * is in 'commissioning'. current_type is never trusted from the client —
     * it is derived here from 'standard' via Connector::CURRENT_TYPE_BY_STANDARD.
     * operational_status and administrative_status are always forced to their
     * creation defaults, regardless of what $data may contain, since the
     * Store Form Request never includes them in the first place.
     *
     * @param  ChargingStation  $station
     * @param  array{connector_number: int, standard: string, max_power_kw: float|string}  $data
     * @return Connector
     *
     * @throws InvalidStateTransitionException
     */
    public function create(ChargingStation $station, array $data): Connector
    {
        if ($station->trashed()) {
            throw new InvalidStateTransitionException(
                'Une borne supprimée ne peut pas recevoir de nouveau connecteur.'
            );
        }

        if ($station->administrative_status !== 'commissioning') {
            throw new InvalidStateTransitionException(
                "Impossible d'ajouter un connecteur à une borne dans l'état '{$station->administrative_status}'. "
                . "Seul l'état 'commissioning' autorise l'ajout de connecteurs."
            );
        }

        $currentType = Connector::CURRENT_TYPE_BY_STANDARD[$data['standard']];

        return $station->connectors()->create([
            'connector_number' => $data['connector_number'],
            'standard' => $data['standard'],
            'current_type' => $currentType,
            'max_power_kw' => $data['max_power_kw'],
            'operational_status' => 'disconnected',
            'administrative_status' => 'enabled',
        ]);
    }

    /**
     * Update a connector's physical configuration fields.
     *
     * Physical fields (connector_number, standard, max_power_kw) are only
     * editable while the parent station is 'commissioning'. current_type is
     * re-derived from 'standard' BEFORE fill()/isDirty() is evaluated, so the
     * dirty-check always sees the complete, correct post-update state — not
     * a partial one. $data uses 'sometimes' validation upstream, so any of
     * its keys may be absent; absent keys are left untouched.
     *
     * @param  Connector  $connector
     * @param  array{connector_number?: int, standard?: string, max_power_kw?: float|string}  $data
     * @return Connector
     *
     * @throws InvalidStateTransitionException
     */
    public function update(Connector $connector, array $data): Connector
    {
        if ($connector->trashed()) {
            throw new InvalidStateTransitionException(
                'Un connecteur supprimé ne peut pas être modifié.'
            );
        }

        // Deliberately NOT $connector->chargingStation here: accessing a
        // relation as a property caches it on the model the first time it's
        // read, and every later read returns that same stale object even if
        // the underlying row changes elsewhere. Querying by foreign key
        // guarantees a fresh read of the station's current state every call.
        $station = ChargingStation::find($connector->charging_station_id);

        if ($station->trashed()) {
            throw new InvalidStateTransitionException(
                "Le connecteur d'une borne supprimée ne peut pas être modifié."
            );
        }

        if ($station->administrative_status !== 'commissioning') {
            throw new InvalidStateTransitionException(
                "Impossible de modifier un connecteur d'une borne dans l'état '{$station->administrative_status}'. "
                . "Seul l'état 'commissioning' autorise la modification des connecteurs."
            );
        }

        // Derive current_type from the standard that will be in effect after
        // this update — either the incoming one, or the connector's existing
        // one if 'standard' wasn't part of this update. This MUST happen
        // before fill()/isDirty() below, so isDirty() evaluates the complete
        // correct state rather than a stale current_type.
        $effectiveStandard = $data['standard'] ?? $connector->standard;
        $data['current_type'] = Connector::CURRENT_TYPE_BY_STANDARD[$effectiveStandard];

        $connector->fill($data);

        if (!$connector->isDirty()) {
            return $connector;
        }

        $connector->save();

        return $connector;
    }

    /**
     * Update a connector's operational_status.
     *
     * Unlike create()/update(), which are locked to 'commissioning' only,
     * operational-state changes are blocked only when the parent station is
     * 'decommissioned' — an 'active' or 'disabled' parent still permits them,
     * per the roadmap's explicit rule that operational/availability changes
     * have a different (much looser) lock than physical configuration.
     * No same-state check here: resubmitting the same operational_status is
     * allowed and simply results in isDirty() being false, no write, no error.
     *
     * @param  Connector  $connector
     * @param  string  $operationalStatus
     * @return Connector
     *
     * @throws InvalidStateTransitionException
     */
    public function updateOperationalStatus(Connector $connector, string $operationalStatus): Connector
    {
        if ($connector->trashed()) {
            throw new InvalidStateTransitionException(
                "L'état opérationnel d'un connecteur supprimé ne peut pas être modifié."
            );
        }

        $station = ChargingStation::find($connector->charging_station_id);

        if ($station->administrative_status === 'decommissioned') {
            throw new InvalidStateTransitionException(
                "Impossible de modifier l'état opérationnel d'un connecteur dont la borne est décommissionnée."
            );
        }

        $connector->operational_status = $operationalStatus;

        if (!$connector->isDirty()) {
            return $connector;
        }

        $connector->save();

        return $connector;
    }

    /**
     * Update a connector's administrative_status (enabled / disabled).
     *
     * Same parent-station lock as updateOperationalStatus(): blocked only
     * when the station is 'decommissioned'. Unlike operational status,
     * availability DOES have an explicit same-state check per the roadmap —
     * resubmitting the connector's current administrative_status returns a
     * 409 rather than silently succeeding as a no-op.
     *
     * @param  Connector  $connector
     * @param  string  $administrativeStatus
     * @return Connector
     *
     * @throws InvalidStateTransitionException
     */
    public function updateAvailability(Connector $connector, string $administrativeStatus): Connector
    {
        if ($connector->trashed()) {
            throw new InvalidStateTransitionException(
                "La disponibilité d'un connecteur supprimé ne peut pas être modifiée."
            );
        }

        $station = ChargingStation::find($connector->charging_station_id);

        if ($station->administrative_status === 'decommissioned') {
            throw new InvalidStateTransitionException(
                "Impossible de modifier la disponibilité d'un connecteur dont la borne est décommissionnée."
            );
        }

        if ($connector->administrative_status === $administrativeStatus) {
            throw new InvalidStateTransitionException(
                "Le connecteur est déjà dans l'état '{$administrativeStatus}'."
            );
        }

        $connector->administrative_status = $administrativeStatus;
        $connector->save();

        return $connector;
    }

    /**
     * Soft-delete a connector.
     *
     * Same lock as create()/update(): only allowed while the parent station
     * is 'commissioning'. This is a physical-configuration-level operation,
     * not an operational one, so it uses the strict lock, not the looser
     * decommissioned-only lock used by updateOperationalStatus() and
     * updateAvailability(). Connector::delete() performs a soft delete
     * (sets deleted_at) since the model uses the SoftDeletes trait — the row
     * and its full history remain in the database.
     *
     * @param  Connector  $connector
     * @return void
     *
     * @throws InvalidStateTransitionException
     */
    public function delete(Connector $connector): void
    {
        if ($connector->trashed()) {
            throw new InvalidStateTransitionException(
                'Ce connecteur est déjà supprimé.'
            );
        }

        $station = ChargingStation::find($connector->charging_station_id);

        if ($station->administrative_status !== 'commissioning') {
            throw new InvalidStateTransitionException(
                "Impossible de supprimer un connecteur d'une borne dans l'état '{$station->administrative_status}'. "
                . "Seul l'état 'commissioning' autorise la suppression des connecteurs."
            );
        }

        $connector->delete();
    }
}
