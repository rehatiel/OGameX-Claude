<?php

namespace OGame\Http\Requests\Galaxy;

use Illuminate\Foundation\Http\FormRequest;

class MissileAttackRequest extends FormRequest
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
            'galaxy'          => 'required|integer|min:1',
            'system'          => 'required|integer|min:1',
            'position'        => 'required|integer|min:1|max:15',
            'type'            => 'required|integer',
            'missile_count'   => 'required|integer|min:0',
            'target_priority' => 'required|integer|min:0|max:7',
        ];
    }
}
