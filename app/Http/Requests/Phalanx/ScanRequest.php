<?php

namespace OGame\Http\Requests\Phalanx;

use Illuminate\Foundation\Http\FormRequest;
use OGame\GameConstants\UniverseConstants;

class ScanRequest extends FormRequest
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
            'galaxy'   => 'required|integer|min:1',
            'system'   => 'required|integer|min:1|max:' . UniverseConstants::MAX_SYSTEM_COUNT,
            'position' => 'required|integer|min:1|max:' . UniverseConstants::MAX_PLANET_POSITION,
        ];
    }
}
