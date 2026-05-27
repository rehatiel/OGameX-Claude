<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Date;
use OGame\Models\User;
use OGame\Models\UserBooster;
use OGame\Services\ShopBoosterService;
use Tests\AccountTestCase;

class ShopBoosterTest extends AccountTestCase
{
    // Bronze Kraken ref (30-min, 700 DM)
    private const KRAKEN_BRONZE  = '40f6c78e11be01ad3389b7dccd6ab8efa9347f3c';
    // Bronze Detroid ref (30-min, 700 DM)
    private const DETROID_BRONZE = 'd3d541ecc23e4daa0c698e44c32f04afd2037d84';
    // Bronze Newtron ref (30-min, 700 DM)
    private const NEWTRON_BRONZE = 'da4a2a1bb9afd410be07bc9736d87f1c8059e66d';
    // Gold Kraken ref (6-hour, 7000 DM)
    private const KRAKEN_GOLD    = '929d5e15709cc51a4500de4499e19763c879f7f7';

    private function setDarkMatter(int $amount): void
    {
        User::where('id', $this->currentUserId)->update(['dark_matter' => $amount]);
    }

    private function getDarkMatter(): int
    {
        return (int) User::find($this->currentUserId)->dark_matter;
    }

    // ── Page / AJAX ──────────────────────────────────────────────────────────

    public function testShopPageLoads(): void
    {
        $response = $this->get('/shop');
        $response->assertStatus(200);
        $response->assertSee('detail_button');
        $response->assertSee(self::KRAKEN_BRONZE);
    }

    public function testItemDetailReturnsHtml(): void
    {
        $response = $this->get('/ajax/shop/item-detail?type=' . self::KRAKEN_BRONZE);
        $response->assertStatus(200);
        $response->assertSee('itemDetails');
        $response->assertSee('build-it');
    }

    public function testItemDetailReturns404ForUnknownRef(): void
    {
        $response = $this->get('/ajax/shop/item-detail?type=unknownref');
        $response->assertStatus(404);
    }

    // ── Buy ──────────────────────────────────────────────────────────────────

    public function testBuyAddsToInventoryAndDeductsDM(): void
    {
        $this->setDarkMatter(5000);

        $response = $this->post('/shop/buy/' . self::KRAKEN_BRONZE, ['_token' => csrf_token()]);
        $response->assertStatus(200);
        $response->assertJson(['error' => false]);

        $data = $response->json();
        $this->assertFalse($data['error']);
        $this->assertArrayHasKey('item', $data);
        $this->assertArrayHasKey('newAjaxToken', $data);
        $this->assertEquals(1, $data['item']['amount']);

        $this->assertEquals(5000 - 700, $this->getDarkMatter());

        $booster = UserBooster::where('user_id', $this->currentUserId)
            ->where('ref', self::KRAKEN_BRONZE)
            ->first();
        $this->assertNotNull($booster);
        $this->assertEquals(1, $booster->amount);
    }

    public function testBuyStacksInventoryAmount(): void
    {
        $this->setDarkMatter(10000);

        $this->post('/shop/buy/' . self::KRAKEN_BRONZE, ['_token' => csrf_token()]);
        $response = $this->post('/shop/buy/' . self::KRAKEN_BRONZE, ['_token' => csrf_token()]);
        $response->assertJson(['error' => false]);

        $booster = UserBooster::where('user_id', $this->currentUserId)
            ->where('ref', self::KRAKEN_BRONZE)
            ->first();
        $this->assertEquals(2, $booster->amount);
        $this->assertEquals(10000 - 1400, $this->getDarkMatter());
    }

    public function testBuyFailsWithInsufficientDM(): void
    {
        $this->setDarkMatter(100);

        $response = $this->post('/shop/buy/' . self::KRAKEN_BRONZE, ['_token' => csrf_token()]);
        $response->assertStatus(200);
        $response->assertJson(['error' => true]);

        $this->assertEquals(100, $this->getDarkMatter());
        $this->assertNull(UserBooster::where('user_id', $this->currentUserId)->where('ref', self::KRAKEN_BRONZE)->first());
    }

    // ── Activate ─────────────────────────────────────────────────────────────

    public function testActivateStartsTimer(): void
    {
        $this->setDarkMatter(5000);
        $this->post('/shop/buy/' . self::KRAKEN_BRONZE, ['_token' => csrf_token()]);

        $response = $this->post('/shop/activate/' . self::KRAKEN_BRONZE, ['_token' => csrf_token()]);
        $response->assertStatus(200);
        $response->assertJson(['error' => false]);

        $data = $response->json();
        $this->assertArrayHasKey('reload', $data);
        $this->assertArrayHasKey('item', $data);

        $booster = UserBooster::where('user_id', $this->currentUserId)
            ->where('ref', self::KRAKEN_BRONZE)
            ->first();
        $this->assertEquals(0, $booster->amount);
        $this->assertTrue($booster->isActive());
        $this->assertGreaterThan(0, $booster->secondsLeft());
        $this->assertLessThanOrEqual(1800, $booster->secondsLeft());
    }

    public function testActivateFailsWithEmptyInventory(): void
    {
        $response = $this->post('/shop/activate/' . self::KRAKEN_BRONZE, ['_token' => csrf_token()]);
        $response->assertStatus(200);
        $response->assertJson(['error' => true]);
    }

    // ── Buy + Activate ───────────────────────────────────────────────────────

    public function testBuyAndActivateDeductsDMAndStartsTimer(): void
    {
        $this->setDarkMatter(5000);

        $response = $this->post('/shop/buy-and-activate/' . self::KRAKEN_BRONZE, ['_token' => csrf_token()]);
        $response->assertStatus(200);
        $response->assertJson(['error' => false]);

        $this->assertEquals(5000 - 700, $this->getDarkMatter());

        $booster = UserBooster::where('user_id', $this->currentUserId)
            ->where('ref', self::KRAKEN_BRONZE)
            ->first();
        $this->assertNotNull($booster);
        $this->assertTrue($booster->isActive());
        $this->assertEquals(0, $booster->amount);
    }

    // ── Timer extension ──────────────────────────────────────────────────────

    public function testActivatingExtendsBronzeOntoGoldTimer(): void
    {
        $this->setDarkMatter(50000);

        // Activate gold Kraken (6h = 21600s)
        $this->post('/shop/buy-and-activate/' . self::KRAKEN_GOLD, ['_token' => csrf_token()]);

        $goldBooster = UserBooster::where('user_id', $this->currentUserId)
            ->where('ref', self::KRAKEN_GOLD)
            ->first();
        $goldExpiry = $goldBooster->expires_at->timestamp;

        // Buy bronze Kraken and activate it — should extend gold timer by 1800s
        $this->post('/shop/buy/' . self::KRAKEN_BRONZE, ['_token' => csrf_token()]);
        $this->post('/shop/activate/' . self::KRAKEN_BRONZE, ['_token' => csrf_token()]);

        $bronzeBooster = UserBooster::where('user_id', $this->currentUserId)
            ->where('ref', self::KRAKEN_BRONZE)
            ->first();

        // The bronze booster's expires_at should be goldExpiry + 1800
        $this->assertEquals($goldExpiry + 1800, $bronzeBooster->expires_at->timestamp);
    }

    // ── Service multipliers ───────────────────────────────────────────────────

    public function testKrakenMultiplierIsOneWhenInactive(): void
    {
        $user = User::find($this->currentUserId);
        $service = app(ShopBoosterService::class);
        $this->assertEquals(1.0, $service->getBuildTimeMultiplier($user));
    }

    public function testKrakenMultiplierIsHalfWhenActive(): void
    {
        $this->setDarkMatter(5000);
        $this->post('/shop/buy-and-activate/' . self::KRAKEN_BRONZE, ['_token' => csrf_token()]);

        $user = User::find($this->currentUserId);
        $service = app(ShopBoosterService::class);
        $this->assertEquals(0.5, $service->getBuildTimeMultiplier($user));
    }

    public function testDetroidMultiplierIsHalfWhenActive(): void
    {
        $this->setDarkMatter(5000);
        $this->post('/shop/buy-and-activate/' . self::DETROID_BRONZE, ['_token' => csrf_token()]);

        $user = User::find($this->currentUserId);
        $service = app(ShopBoosterService::class);
        $this->assertEquals(1.0, $service->getBuildTimeMultiplier($user));
        $this->assertEquals(0.5, $service->getShipTimeMultiplier($user));
        $this->assertEquals(1.0, $service->getResearchTimeMultiplier($user));
    }

    public function testNewtronMultiplierIsHalfWhenActive(): void
    {
        $this->setDarkMatter(5000);
        $this->post('/shop/buy-and-activate/' . self::NEWTRON_BRONZE, ['_token' => csrf_token()]);

        $user = User::find($this->currentUserId);
        $service = app(ShopBoosterService::class);
        $this->assertEquals(1.0, $service->getBuildTimeMultiplier($user));
        $this->assertEquals(1.0, $service->getShipTimeMultiplier($user));
        $this->assertEquals(0.5, $service->getResearchTimeMultiplier($user));
    }

    public function testMultiplierReturnsOneAfterExpiry(): void
    {
        $this->setDarkMatter(5000);
        $this->post('/shop/buy-and-activate/' . self::KRAKEN_BRONZE, ['_token' => csrf_token()]);

        // Travel past the 30-minute bronze duration
        $this->travel(31)->minutes();

        $user = User::find($this->currentUserId);
        $service = app(ShopBoosterService::class);
        $this->assertEquals(1.0, $service->getBuildTimeMultiplier($user));
    }

    // ── hasActiveBooster ─────────────────────────────────────────────────────

    public function testHasActiveBoosterReturnsFalseInitially(): void
    {
        $user = User::find($this->currentUserId);
        $service = app(ShopBoosterService::class);
        $this->assertFalse($service->hasActiveBooster($user, 'kraken'));
        $this->assertFalse($service->hasActiveBooster($user, 'detroid'));
        $this->assertFalse($service->hasActiveBooster($user, 'newtron'));
    }

    public function testHasActiveBoosterReturnsTrueAfterActivation(): void
    {
        $this->setDarkMatter(5000);
        $this->post('/shop/buy-and-activate/' . self::KRAKEN_BRONZE, ['_token' => csrf_token()]);

        $user = User::find($this->currentUserId);
        $service = app(ShopBoosterService::class);
        $this->assertTrue($service->hasActiveBooster($user, 'kraken'));
        $this->assertFalse($service->hasActiveBooster($user, 'detroid'));
    }
}
