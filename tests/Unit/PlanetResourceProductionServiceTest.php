<?php

namespace Tests\Unit;

use OGame\Models\Enums\PlanetType;
use OGame\Models\Planet;
use OGame\Models\Resources;
use OGame\Services\PlanetResourceProductionService;
use OGame\Services\PlayerService;
use Tests\TestCase;

/**
 * Tests PlanetResourceProductionService directly, independent of PlanetService.
 */
class PlanetResourceProductionServiceTest extends TestCase
{
    private PlanetResourceProductionService $service;
    private PlayerService $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = resolve(PlanetResourceProductionService::class);
        $this->player = resolve(PlayerService::class, ['player_id' => 0]);
    }

    public function testGetProductionForPositionBonuses_knownPositions(): void
    {
        // Position 8 → metal 1.35, crystal 1, deuterium 1
        $bonus = $this->service->getProductionForPositionBonuses(8);
        $this->assertEqualsWithDelta(1.35, $bonus['metal'], 0.001);
        $this->assertEqualsWithDelta(1.0, $bonus['crystal'], 0.001);
        $this->assertEqualsWithDelta(1.0, $bonus['deuterium'], 0.001);

        // Position 1 → metal 1, crystal 1.4, deuterium 1
        $bonus = $this->service->getProductionForPositionBonuses(1);
        $this->assertEqualsWithDelta(1.0, $bonus['metal'], 0.001);
        $this->assertEqualsWithDelta(1.4, $bonus['crystal'], 0.001);
        $this->assertEqualsWithDelta(1.0, $bonus['deuterium'], 0.001);
    }

    public function testGetProductionForPositionBonuses_unlisted_returnsDefaults(): void
    {
        // Positions 4 and 5 are not in config → all 1.0
        $bonus = $this->service->getProductionForPositionBonuses(5);
        $this->assertEqualsWithDelta(1.0, $bonus['metal'], 0.001);
        $this->assertEqualsWithDelta(1.0, $bonus['crystal'], 0.001);
        $this->assertEqualsWithDelta(1.0, $bonus['deuterium'], 0.001);
    }

    public function testEnergyProduction(): void
    {
        $planet = Planet::factory()->make(['energy_max' => 1500, 'energy_used' => 800]);
        $planet->id = 1;

        $this->assertEquals(1500.0, $this->service->energyProduction($planet)->get());
    }

    public function testEnergyConsumption(): void
    {
        $planet = Planet::factory()->make(['energy_max' => 1500, 'energy_used' => 800]);
        $planet->id = 1;

        $this->assertEquals(800.0, $this->service->energyConsumption($planet)->get());
    }

    public function testGetResourceProductionFactor_noConsumption_returns100(): void
    {
        $planet = Planet::factory()->make(['energy_max' => 0, 'energy_used' => 0]);
        $planet->id = 1;

        $this->assertEquals(100, $this->service->getResourceProductionFactor($planet));
    }

    public function testGetResourceProductionFactor_noProduction_returns0(): void
    {
        $planet = Planet::factory()->make(['energy_max' => 0, 'energy_used' => 500]);
        $planet->id = 1;

        $this->assertEquals(0, $this->service->getResourceProductionFactor($planet));
    }

    public function testGetResourceProductionFactor_calculated(): void
    {
        // 750 production / 1000 consumption = 75%
        $planet = Planet::factory()->make(['energy_max' => 750, 'energy_used' => 1000]);
        $planet->id = 1;

        $this->assertEquals(75, $this->service->getResourceProductionFactor($planet));
    }

    public function testGetResourceProductionFactor_cappedAt100(): void
    {
        // More production than consumption → capped at 100
        $planet = Planet::factory()->make(['energy_max' => 2000, 'energy_used' => 1000]);
        $planet->id = 1;

        $this->assertEquals(100, $this->service->getResourceProductionFactor($planet));
    }

    public function testGetPlanetBasicIncome_moon_returnsZero(): void
    {
        $planet = Planet::factory()->make(['planet_type' => PlanetType::Moon->value, 'planet' => 5]);
        $planet->id = 1;

        $income = $this->service->getPlanetBasicIncome($planet, $this->player);

        $this->assertEquals(0, $income->metal->get());
        $this->assertEquals(0, $income->crystal->get());
        $this->assertEquals(0, $income->deuterium->get());
        $this->assertEquals(0, $income->energy->get());
    }

    public function testGetPlanetBasicIncome_normalPlanet_returnsNonZero(): void
    {
        // Position 5 has no bonuses — income should be > 0 at any economy speed
        $planet = Planet::factory()->make(['planet_type' => PlanetType::Planet->value, 'planet' => 5]);
        $planet->id = 1;

        $income = $this->service->getPlanetBasicIncome($planet, $this->player);

        $this->assertGreaterThan(0, $income->metal->get());
        $this->assertGreaterThan(0, $income->crystal->get());
    }

    public function testCalculatePlanetProductionBonuses_appliesMultiplier(): void
    {
        $planet = Planet::factory()->make(['planet_type' => PlanetType::Planet->value, 'planet' => 8]);
        $planet->id = 1;

        $base = new Resources(1000, 1000, 1000, 0);
        $result = $this->service->calculatePlanetProductionBonuses($planet, $base);

        // Position 8 → metal 1.35, crystal 1, deuterium 1
        $this->assertEquals(1350, $result->metal->get());
        $this->assertEquals(1000, $result->crystal->get());
        $this->assertEquals(1000, $result->deuterium->get());
    }
}
