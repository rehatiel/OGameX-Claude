<?php

namespace OGame\GameObjects\Models\Fields;

class GameObjectPropertyDetails
{
    /*
     * $breakdown = [
            'rawValue' => $rawValue,
            'bonuses' => [
                [
                    'type' => 'Research bonus',
                    'value' => $bonusValue,
                    'percentage' => $bonusPercentage,
                ],
            ],
            'totalValue' => $totalValue,
        ];
     */

    /**
     * @param int $rawValue
     * @param int $bonusValue
     * @param int $totalValue
     * @param array<string,array<int, array<string, float|int|string>>|float|int> $breakdown
     */
    public function __construct(public readonly int $rawValue, public readonly int $bonusValue, public readonly int $totalValue, public readonly array $breakdown = [])
    {
    }
}
