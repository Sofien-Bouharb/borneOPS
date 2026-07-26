<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChargingStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'reference' => ['required', 'string', 'max:100', 'unique:charging_stations,reference'],
            'serial_number' => ['required', 'string', 'max:100', 'unique:charging_stations,serial_number'],
            'model' => ['required', 'string', 'max:255'],
            'manufacturer' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'firmware_version' => ['nullable', 'string', 'max:50'],
            'ocpp_version' => ['required', 'in:1.6,2.0.1'],
            'ocpp_identifier' => ['nullable', 'string', 'max:255', 'unique:charging_stations,ocpp_identifier'],
            'declared_connector_count' => ['required', 'integer', 'min:1'],
            'power_kw' => ['required', 'numeric', 'gt:0'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
        ];
    }
}
