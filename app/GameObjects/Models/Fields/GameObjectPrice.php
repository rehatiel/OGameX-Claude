<?php

namespace OGame\GameObjects\Models\Fields;

use OGame\Models\Resources;

class GameObjectPrice
{
    public readonly Resources $resources;

    public function __construct(int $metal, int $crystal, int $deuterium, int $energy, public readonly float $factor = 1, public readonly bool $roundNearest100 = false)
    {
        $resources = new Resources($metal, $crystal, $deuterium, $energy);
        $this->resources = $resources;
    }
}
