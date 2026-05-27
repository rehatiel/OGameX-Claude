<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use OGame\Models\User;
use OGame\Models\UserBooster;
use Tests\AccountTestCase;

/**
 * Tests for the 7-day Starter Aid rewards system (Phase 2d).
 *
 * Test time is frozen at 2024-01-01 00:00:00 (from AccountTestCase).
 * The test user's created_at is therefore 2024-01-01.
 */
class RewardsTest extends AccountTestCase
{
    // Reward keys from RewardsService catalog
    private const KEY_DAY1 = 'day_1'; // resources  — available at created_at + 1 day
    private const KEY_DAY2 = 'day_2'; // booster    — available at created_at + 2 days
    private const KEY_DAY3 = 'day_3'; // dark_matter — available at created_at + 3 days
    private const KEY_DAY7 = 'day_7'; // officers   — available at created_at + 7 days

    // Bronze booster refs
    private const KRAKEN_BRONZE  = '40f6c78e11be01ad3389b7dccd6ab8efa9347f3c';
    private const NEWTRON_BRONZE = 'da4a2a1bb9afd410be07bc9736d87f1c8059e66d';
    private const DETROID_BRONZE = 'd3d541ecc23e4daa0c698e44c32f04afd2037d84';

    private function getDarkMatter(): int
    {
        return (int) User::find($this->currentUserId)->dark_matter;
    }

    private function setRegisteredDaysAgo(int $days): void
    {
        User::where('id', $this->currentUserId)
            ->update(['created_at' => now()->subDays($days)]);
        $this->reloadApplication();
    }

    // ── Page load ─────────────────────────────────────────────────────────────

    public function testRewardsPageLoads(): void
    {
        $response = $this->get('/rewards');
        $response->assertStatus(200);
        $response->assertSee('rewardscomponent');
    }

    public function testRewardsPageShowsNotReachedForNewUser(): void
    {
        $response = $this->get('/rewards');
        $response->assertStatus(200);
        $response->assertSee('Awards not yet reached');
        $response->assertDontSee('New awards');
        $response->assertDontSee('Collected awards');
    }

    // ── Availability by day ───────────────────────────────────────────────────

    public function testRewardBecomesAvailableAfterOneDayPasses(): void
    {
        $this->setRegisteredDaysAgo(1);

        $response = $this->get('/rewards');
        $response->assertStatus(200);
        $response->assertSee('New awards');
        $response->assertSee("Let's go Emperor");
    }

    public function testRewardNotAvailableBeforeDayPasses(): void
    {
        // Still on day 0 — no rewards yet
        $response = $this->get('/rewards');
        $response->assertStatus(200);
        $response->assertDontSee('New awards');
        $response->assertSee('The colony is growing');
    }

    // ── Claim: resources ──────────────────────────────────────────────────────

    public function testClaimDay1GivesResourcesToHomePlanet(): void
    {
        $this->setRegisteredDaysAgo(1);

        $metalBefore = (int) DB::table('planets')
            ->where('id', $this->currentPlanetId)
            ->value('metal');

        $response = $this->post('/rewards/claim/' . self::KEY_DAY1);
        $response->assertStatus(200);
        $response->assertJson(['error' => false]);

        $metalAfter = (int) DB::table('planets')
            ->where('id', $this->currentPlanetId)
            ->value('metal');

        $this->assertGreaterThan($metalBefore, $metalAfter);
    }

    // ── Claim: booster ────────────────────────────────────────────────────────

    public function testClaimDay2AddsKrakenBronzeToInventory(): void
    {
        $this->setRegisteredDaysAgo(2);

        $response = $this->post('/rewards/claim/' . self::KEY_DAY2);
        $response->assertStatus(200);
        $response->assertJson(['error' => false]);

        $booster = UserBooster::where('user_id', $this->currentUserId)
            ->where('ref', self::KRAKEN_BRONZE)
            ->first();

        $this->assertNotNull($booster);
        $this->assertEquals(1, $booster->amount);
    }

    public function testClaimBoosterDoesNotDeductDarkMatter(): void
    {
        $this->setRegisteredDaysAgo(2);
        $dmBefore = $this->getDarkMatter();

        $this->post('/rewards/claim/' . self::KEY_DAY2);

        $this->assertEquals($dmBefore, $this->getDarkMatter());
    }

    // ── Claim: dark matter ────────────────────────────────────────────────────

    public function testClaimDay3GivesDarkMatter(): void
    {
        $this->setRegisteredDaysAgo(3);
        $dmBefore = $this->getDarkMatter();

        $response = $this->post('/rewards/claim/' . self::KEY_DAY3);
        $response->assertStatus(200);
        $response->assertJson(['error' => false]);

        $this->assertEquals($dmBefore + 500, $this->getDarkMatter());
    }

    // ── Claim: officers ───────────────────────────────────────────────────────

    public function testClaimDay7GrantsCommandingStaff(): void
    {
        $this->setRegisteredDaysAgo(7);

        $response = $this->post('/rewards/claim/' . self::KEY_DAY7);
        $response->assertStatus(200);
        $response->assertJson(['error' => false]);

        $user = User::find($this->currentUserId);
        $now  = time();

        $this->assertGreaterThan($now, $user->officer_commander);
        $this->assertGreaterThan($now, $user->officer_admiral);
        $this->assertGreaterThan($now, $user->officer_engineer);
        $this->assertGreaterThan($now, $user->officer_geologist);
        $this->assertGreaterThan($now, $user->officer_technocrat);
    }

    // ── Idempotency ───────────────────────────────────────────────────────────

    public function testCannotClaimSameRewardTwice(): void
    {
        $this->setRegisteredDaysAgo(3);

        $this->post('/rewards/claim/' . self::KEY_DAY3);
        $response = $this->post('/rewards/claim/' . self::KEY_DAY3);

        $response->assertStatus(400);
        $response->assertJson(['error' => true]);
    }

    public function testClaimedRewardAppearsInHistory(): void
    {
        $this->setRegisteredDaysAgo(1);

        $this->post('/rewards/claim/' . self::KEY_DAY1);

        $response = $this->get('/rewards');
        $response->assertStatus(200);
        $response->assertSee('Collected awards');
        $response->assertSee("Let's go Emperor");
    }

    // ── Early claim guard ─────────────────────────────────────────────────────

    public function testCannotClaimRewardBeforeItIsAvailable(): void
    {
        // User just registered — no rewards available yet
        $response = $this->post('/rewards/claim/' . self::KEY_DAY1);
        $response->assertStatus(400);
        $response->assertJson(['error' => true]);
    }

    public function testUnknownRewardKeyReturns400(): void
    {
        $response = $this->post('/rewards/claim/invalid_key');
        $response->assertStatus(400);
        $response->assertJson(['error' => true]);
    }
}
