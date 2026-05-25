<?php

namespace OGame\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;

class FleetSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'galaxy'      => 'required|integer',
            'system'      => 'required|integer',
            'position'    => 'required|integer',
            'type'        => 'required|integer',
            'mission'     => 'required|integer',
            'speed'       => 'required|numeric',
            'metal'       => 'nullable|integer',
            'crystal'     => 'nullable|integer',
            'deuterium'   => 'nullable|integer',
            'holdingtime' => 'nullable|integer',
            'union'       => 'nullable|integer',
        ];
    }
}
