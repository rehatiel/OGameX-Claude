<?php

namespace OGame\Services;

use OGame\Models\Enums\PlanetType;
use OGame\Models\Planet;
use OGame\Models\Resource;
use OGame\Models\Resources;

/**
 * Handles resource production calculations for a planet.
 * Methods accept Planet and PlayerService as explicit parameters so this service
 * is stateless and can be resolved as a singleton without per-planet state.
 */
class PlanetResourceProductionService
{
    public function __construct(private SettingsService $settingsService)
    {
    }

    /**
     * Returns basic income (resources) for a planet, accounting for position bonuses.
     */
    public function getPlanetBasicIncome(Planet $planet, PlayerService $player): Resources
    {
        // Moons do not have mines and therefore also do not have basic income.
        if (PlanetType::from($planet->planet_type) === PlanetType::Moon) {
            return new Resources(0, 0, 0, 0);
        }

        // Players in vacation mode have zero basic income.
        if ($player->isInVacationMode()) {
            return new Resources(0, 0, 0, 0);
        }

        $multiplier = $this->settingsService->economySpeed();

        $baseIncome = new Resources(
            $this->settingsService->basicIncomeMetal() * $multiplier,
            $this->settingsService->basicIncomeCrystal() * $multiplier,
            $this->settingsService->basicIncomeDeuterium() * $multiplier,
            $this->settingsService->basicIncomeEnergy() * $multiplier
        );

        return $this->calculatePlanetBonuses($planet, $baseIncome);
    }

    /**
     * Apply all position-based production bonuses to the given base income.
     */
    public function calculatePlanetBonuses(Planet $planet, Resources $baseIncome): Resources
    {
        return $this->calculatePlanetProductionBonuses($planet, $baseIncome);
    }

    /**
     * Apply planet-position production multipliers to the given base income.
     */
    public function calculatePlanetProductionBonuses(Planet $planet, Resources $baseIncome): Resources
    {
        $bonus = $this->getProductionForPositionBonuses($planet->planet);

        $baseIncome->metal->set($baseIncome->metal->get() * $bonus['metal']);
        $baseIncome->crystal->set($baseIncome->crystal->get() * $bonus['crystal']);
        $baseIncome->deuterium->set($baseIncome->deuterium->get() * $bonus['deuterium']);

        return $baseIncome;
    }

    /**
     * Returns production bonus multipliers for a given planet position.
     *
     * @return array{metal: float, crystal: float, deuterium: float}
     */
    public function getProductionForPositionBonuses(int $position): array
    {
        $bonuses = config('game.position_bonuses', []);
        return $bonuses[$position] ?? ['metal' => 1, 'crystal' => 1, 'deuterium' => 1];
    }

    /**
     * Get planet energy production.
     */
    public function energyProduction(Planet $planet): Resource
    {
        return new Resource((float)($planet->energy_max ?? 0));
    }

    /**
     * Get planet energy consumption.
     */
    public function energyConsumption(Planet $planet): Resource
    {
        return new Resource((float)($planet->energy_used ?? 0));
    }

    /**
     * Returns the resource production factor as a percentage (0–100).
     * Indicates how efficiently mines are functioning based on available energy.
     */
    public function getResourceProductionFactor(Planet $planet): int
    {
        $consumption = $this->energyConsumption($planet)->get();

        if (empty($consumption)) {
            return 100;
        }

        $production = $this->energyProduction($planet)->get();

        if (empty($production)) {
            return 0;
        }

        $factor = (int) floor($production / $consumption * 100);

        return max(0, min(100, $factor));
    }
}
