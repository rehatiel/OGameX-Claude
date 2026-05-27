<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use OGame\Models\Resources;
use Tests\AccountTestCase;

/**
 * Tests for PlanetService::addResourcesAtomic() and the updated addResources() delegate.
 */
class PlanetResourceAtomicTest extends AccountTestCase
{
    // ── addResourcesAtomic ────────────────────────────────────────────────────

    public function testAddResourcesAtomicAddsAllThreeResources(): void
    {
        DB::table('planets')->where('id', $this->currentPlanetId)
            ->update(['metal' => 0, 'crystal' => 0, 'deuterium' => 0]);

        $this->planetService->addResourcesAtomic(new Resources(100, 200, 300, 0));

        $row = DB::table('planets')->where('id', $this->currentPlanetId)->first();
        $this->assertEquals(100, (int) $row->metal);
        $this->assertEquals(200, (int) $row->crystal);
        $this->assertEquals(300, (int) $row->deuterium);
    }

    public function testAddResourcesAtomicDoesNotGoNegative(): void
    {
        DB::table('planets')->where('id', $this->currentPlanetId)
            ->update(['metal' => 10, 'crystal' => 10, 'deuterium' => 10]);

        // Adding a negative value should floor at 0 (GREATEST(0, ...))
        $this->planetService->addResourcesAtomic(new Resources(-50, -50, -50, 0));

        $row = DB::table('planets')->where('id', $this->currentPlanetId)->first();
        $this->assertEquals(0, (int) $row->metal);
        $this->assertEquals(0, (int) $row->crystal);
        $this->assertEquals(0, (int) $row->deuterium);
    }

    public function testAddResourcesAtomicIsNoopForZeroResources(): void
    {
        DB::table('planets')->where('id', $this->currentPlanetId)
            ->update(['metal' => 500, 'crystal' => 500, 'deuterium' => 500]);

        $this->planetService->addResourcesAtomic(new Resources(0, 0, 0, 0));

        $row = DB::table('planets')->where('id', $this->currentPlanetId)->first();
        $this->assertEquals(500, (int) $row->metal);
        $this->assertEquals(500, (int) $row->crystal);
        $this->assertEquals(500, (int) $row->deuterium);
    }

    // ── addResources() delegates to atomic path when save_planet=true ─────────

    public function testAddResourcesWithSavePlanetTruePersistsToDb(): void
    {
        DB::table('planets')->where('id', $this->currentPlanetId)
            ->update(['metal' => 0, 'crystal' => 0, 'deuterium' => 0]);

        $this->planetService->addResources(new Resources(1000, 2000, 3000, 0), true);

        $row = DB::table('planets')->where('id', $this->currentPlanetId)->first();
        $this->assertEquals(1000, (int) $row->metal);
        $this->assertEquals(2000, (int) $row->crystal);
        $this->assertEquals(3000, (int) $row->deuterium);
    }

    public function testAddResourcesWithSavePlanetFalseDoesNotPersist(): void
    {
        DB::table('planets')->where('id', $this->currentPlanetId)
            ->update(['metal' => 0, 'crystal' => 0, 'deuterium' => 0]);

        $this->planetService->addResources(new Resources(999, 999, 999, 0), false);

        // DB should still be 0 — caller hasn't saved yet
        $row = DB::table('planets')->where('id', $this->currentPlanetId)->first();
        $this->assertEquals(0, (int) $row->metal);
    }

    public function testAddResourcesAccumulatesCorrectly(): void
    {
        DB::table('planets')->where('id', $this->currentPlanetId)
            ->update(['metal' => 100, 'crystal' => 100, 'deuterium' => 100]);

        $this->planetService->addResources(new Resources(50, 50, 50, 0));
        $this->planetService->addResources(new Resources(50, 50, 50, 0));

        $row = DB::table('planets')->where('id', $this->currentPlanetId)->first();
        $this->assertEquals(200, (int) $row->metal);
        $this->assertEquals(200, (int) $row->crystal);
        $this->assertEquals(200, (int) $row->deuterium);
    }
}
