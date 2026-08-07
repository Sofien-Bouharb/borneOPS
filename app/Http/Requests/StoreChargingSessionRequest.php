<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreChargingSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'charging_station_id' => [
                'required',
                'integer',
                'exists:charging_stations,id',
            ],
            'connector_id' => [
                'required',
                'integer',
                'exists:connectors,id',
            ],
            'customer_user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
        ];
    }
}
