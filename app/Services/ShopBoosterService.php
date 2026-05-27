<?php

namespace OGame\Services;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OGame\Enums\DarkMatterTransactionType;
use OGame\Models\User;
use OGame\Models\UserBooster;

/**
 * Class ShopBoosterService.
 *
 * Manages the purchase, activation, and effect of shop booster items.
 * Boosters reduce construction time (Kraken: buildings, Detroid: shipyard,
 * Newtron: research) by 50% while active.
 */
class ShopBoosterService
{
    // Category refs matching the shop HTML
    private const CAT_ALL          = 'd8d49c315fa620d9c7f1f19963970dea59a0e3be';
    private const CAT_CONSTRUCTION = 'dc9ec90e5a2163cc063b8bb3e9fe392782f565c8';

    private const DURATION_SECONDS = [
        'gold'   => 21600,
        'silver' => 7200,
        'bronze' => 1800,
    ];

    // Maps each item ref to its booster type
    private const TYPE_MAP = [
        '929d5e15709cc51a4500de4499e19763c879f7f7' => 'kraken',
        '4a58d4978bbe24e3efb3b0248e21b3b4b1bfbd8a' => 'kraken',
        '40f6c78e11be01ad3389b7dccd6ab8efa9347f3c' => 'kraken',
        '0968999df2fe956aa4a07aea74921f860af7d97f' => 'detroid',
        '27cbcd52f16693023cb966e5026d8a1efbbfc0f9' => 'detroid',
        'd3d541ecc23e4daa0c698e44c32f04afd2037d84' => 'detroid',
        '8a4f9e8309e1078f7f5ced47d558d30ae15b4a1b' => 'newtron',
        'd26f4dab76fdc5296e3ebec11a1e1d2558c713ea' => 'newtron',
        'da4a2a1bb9afd410be07bc9736d87f1c8059e66d' => 'newtron',
    ];

    public function __construct(
        private readonly DarkMatterService $dmService,
    ) {}

    // ── Catalog ──────────────────────────────────────────────────────────────

    /**
     * Returns the static booster catalog.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getCatalog(): array
    {
        return [
            ['ref' => '929d5e15709cc51a4500de4499e19763c879f7f7', 'name_key' => 'kraken',  'tier_key' => 'gold',   'rarity' => 'rare',     'duration' => '6h',  'price' => 7000, 'price_label' => '7K',   'image_hash' => '40a1644e104985a3e72da28b76069197128f9fb5'],
            ['ref' => '0968999df2fe956aa4a07aea74921f860af7d97f', 'name_key' => 'detroid', 'tier_key' => 'gold',   'rarity' => 'rare',     'duration' => '6h',  'price' => 7000, 'price_label' => '7K',   'image_hash' => '55d4b1750985e4843023d7d0acd2b9bafb15f0b7'],
            ['ref' => '8a4f9e8309e1078f7f5ced47d558d30ae15b4a1b', 'name_key' => 'newtron', 'tier_key' => 'gold',   'rarity' => 'rare',     'duration' => '6h',  'price' => 7000, 'price_label' => '7K',   'image_hash' => 'd949732b01a7f7f6d92e814f2de99479a324e1e3'],
            ['ref' => '4a58d4978bbe24e3efb3b0248e21b3b4b1bfbd8a', 'name_key' => 'kraken',  'tier_key' => 'silver', 'rarity' => 'uncommon', 'duration' => '2h',  'price' => 2500, 'price_label' => '2.5K', 'image_hash' => '1ee55efe00bb03743ca031a9eaa1374bb936d863'],
            ['ref' => '27cbcd52f16693023cb966e5026d8a1efbbfc0f9', 'name_key' => 'detroid', 'tier_key' => 'silver', 'rarity' => 'uncommon', 'duration' => '2h',  'price' => 2500, 'price_label' => '2.5K', 'image_hash' => 'd0b8fb3d307b815b3182f3872e8eab654fe677df'],
            ['ref' => 'd26f4dab76fdc5296e3ebec11a1e1d2558c713ea', 'name_key' => 'newtron', 'tier_key' => 'silver', 'rarity' => 'uncommon', 'duration' => '2h',  'price' => 2500, 'price_label' => '2.5K', 'image_hash' => 'a92734028d1bf2e75c5c25ae134b4d298a5ca36e'],
            ['ref' => '40f6c78e11be01ad3389b7dccd6ab8efa9347f3c', 'name_key' => 'kraken',  'tier_key' => 'bronze', 'rarity' => 'common',   'duration' => '30m', 'price' => 700,  'price_label' => '700',  'image_hash' => '98629d11293c9f2703592ed0314d99f320f45845'],
            ['ref' => 'd3d541ecc23e4daa0c698e44c32f04afd2037d84', 'name_key' => 'detroid', 'tier_key' => 'bronze', 'rarity' => 'common',   'duration' => '30m', 'price' => 700,  'price_label' => '700',  'image_hash' => '56724c3a1dcae8036bb172f0be833a6f9a28bc27'],
            ['ref' => 'da4a2a1bb9afd410be07bc9736d87f1c8059e66d', 'name_key' => 'newtron', 'tier_key' => 'bronze', 'rarity' => 'common',   'duration' => '30m', 'price' => 700,  'price_label' => '700',  'image_hash' => '4bc4327a3fd508b5da84267e2cfd58d47f9e4dcb'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getItemByRef(string $ref): ?array
    {
        foreach (self::getCatalog() as $item) {
            if ($item['ref'] === $ref) {
                return $item;
            }
        }
        return null;
    }

    public static function getTypeForRef(string $ref): ?string
    {
        return self::TYPE_MAP[$ref] ?? null;
    }

    // ── User booster state ────────────────────────────────────────────────────

    public function getUserBooster(User $user, string $ref): ?UserBooster
    {
        return UserBooster::where('user_id', $user->id)->where('ref', $ref)->first();
    }

    /**
     * Returns the active UserBooster with the latest expires_at for a given type, or null.
     */
    public function getActiveBoosterByType(User $user, string $type): ?UserBooster
    {
        $refs = array_keys(array_filter(self::TYPE_MAP, fn($t) => $t === $type));
        return UserBooster::where('user_id', $user->id)
            ->whereIn('ref', $refs)
            ->where('expires_at', '>', now())
            ->orderBy('expires_at', 'desc')
            ->first();
    }

    public function hasActiveBooster(User $user, string $type): bool
    {
        return $this->getActiveBoosterByType($user, $type) !== null;
    }

    // ── Gameplay multipliers ──────────────────────────────────────────────────

    public function getBuildTimeMultiplier(User $user): float
    {
        return $this->hasActiveBooster($user, 'kraken') ? 0.5 : 1.0;
    }

    public function getShipTimeMultiplier(User $user): float
    {
        return $this->hasActiveBooster($user, 'detroid') ? 0.5 : 1.0;
    }

    public function getResearchTimeMultiplier(User $user): float
    {
        return $this->hasActiveBooster($user, 'newtron') ? 0.5 : 1.0;
    }

    // ── Business logic ────────────────────────────────────────────────────────

    /**
     * Purchase a booster: debit DM and add one to inventory.
     *
     * @throws Exception
     */
    public function purchase(User $user, string $ref): UserBooster
    {
        $item = self::getItemByRef($ref);
        if ($item === null) {
            throw new Exception('Unknown booster ref.');
        }

        return DB::transaction(function () use ($user, $item, $ref) {
            $this->dmService->debit(
                $user,
                $item['price'],
                DarkMatterTransactionType::SPEEDUP->value,
                'Shop purchase: ' . strtoupper($item['name_key']) . ' ' . ucfirst($item['tier_key']),
            );

            $booster = UserBooster::firstOrNew(['user_id' => $user->id, 'ref' => $ref]);
            $booster->amount = ($booster->amount ?? 0) + 1;
            $booster->save();

            return $booster->fresh();
        });
    }

    /**
     * Activate a booster from inventory: deduct one from inventory and start/extend timer.
     *
     * @throws Exception
     */
    public function activate(User $user, string $ref): UserBooster
    {
        $item = self::getItemByRef($ref);
        if ($item === null) {
            throw new Exception('Unknown booster ref.');
        }

        return DB::transaction(function () use ($user, $item, $ref) {
            $booster = UserBooster::where('user_id', $user->id)
                ->where('ref', $ref)
                ->lockForUpdate()
                ->first();

            if (!$booster || $booster->amount < 1) {
                throw new Exception('Item not found in inventory.');
            }

            $booster->amount      -= 1;
            $booster->activated_at = now();
            $booster->expires_at   = $this->calcNewExpiry($user, $item);
            $booster->save();

            return $booster->fresh();
        });
    }

    /**
     * Purchase and immediately activate (no intermediate inventory step).
     *
     * @throws Exception
     */
    public function buyAndActivate(User $user, string $ref): UserBooster
    {
        $item = self::getItemByRef($ref);
        if ($item === null) {
            throw new Exception('Unknown booster ref.');
        }

        return DB::transaction(function () use ($user, $item, $ref) {
            $this->dmService->debit(
                $user,
                $item['price'],
                DarkMatterTransactionType::SPEEDUP->value,
                'Shop purchase+activate: ' . strtoupper($item['name_key']) . ' ' . ucfirst($item['tier_key']),
            );

            $booster = UserBooster::firstOrNew(['user_id' => $user->id, 'ref' => $ref]);
            $booster->activated_at = now();
            $booster->expires_at   = $this->calcNewExpiry($user, $item);
            $booster->save();

            return $booster->fresh();
        });
    }

    // ── JS data building ──────────────────────────────────────────────────────

    /**
     * Build the JS item-data object for a single catalog item + user state.
     *
     * @param array<string, mixed> $item  Catalog entry from getCatalog()
     * @return array<string, mixed>
     */
    public function buildItemData(User $user, array $item): array
    {
        $ref     = $item['ref'];
        $booster = $this->getUserBooster($user, $ref);
        $amount  = $booster ? (int) $booster->amount : 0;

        $type       = self::getTypeForRef($ref) ?? '';
        $activeRow  = $type !== '' ? $this->getActiveBoosterByType($user, $type) : null;
        $timeLeft   = $activeRow?->secondsLeft() ?: null;
        $extendable = $timeLeft !== null;

        $hasEnough               = $user->dark_matter >= $item['price'];
        $canBeActivated          = $amount > 0;
        $canBeBoughtAndActivated = $amount === 0 && $hasEnough;

        $tierLabel = __('t_ingame.shop.tier_' . $item['tier_key']);
        $nameTitle = strtoupper(__('t_resources.' . $item['name_key'] . '.title')) . ' ' . $tierLabel;
        $duration  = self::DURATION_SECONDS[$item['tier_key']];

        return [
            'ref'                    => $ref,
            'title'                  => $nameTitle,
            'rarity'                 => $item['rarity'],
            'imageLarge'             => $item['image_hash'],
            'costs'                  => $item['price'],
            'currency'               => 'DM',
            'price_label'            => $item['price_label'],
            'amount'                 => $amount,
            'timeLeft'               => $timeLeft,
            'totalTime'              => $duration,
            'extendable'             => $extendable,
            'isAnUpgrade'            => false,
            'canBeActivated'         => $canBeActivated,
            'canBeBoughtAndActivated' => $canBeBoughtAndActivated,
            'hasEnoughCurrency'      => $hasEnough,
            'isReduced'              => false,
            'activationTitle'        => $nameTitle,
            'buyTitle'               => $nameTitle,
            'category'               => [self::CAT_ALL, self::CAT_CONSTRUCTION],
            'hide'                   => false,
        ];
    }

    /**
     * Build JS items_shop keyed by ref.
     *
     * @return array<string, mixed>
     */
    public function buildAllItemsData(User $user): array
    {
        $result = [];
        foreach (self::getCatalog() as $item) {
            $result[$item['ref']] = $this->buildItemData($user, $item);
        }
        return $result;
    }

    /**
     * Build JS items_inventory (only items with amount > 0 or currently active).
     *
     * @return array<string, mixed>
     */
    public function buildInventoryItemsData(User $user): array
    {
        $result = [];
        foreach (self::getCatalog() as $item) {
            $data = $this->buildItemData($user, $item);
            if ($data['amount'] > 0 || $data['timeLeft'] !== null) {
                $result[$item['ref']] = $data;
            }
        }
        return $result;
    }

    /**
     * Build item_orders[category][ref] = position for JS changeCategory().
     *
     * @return array<string, array<string, int>>
     */
    public function buildItemOrders(): array
    {
        $order = [];
        foreach (self::getCatalog() as $i => $item) {
            $order[$item['ref']] = $i;
        }

        return [
            'c18170d3125b9941ef3a86bd28dded7bf2066a6a' => [],     // special offers (empty)
            self::CAT_ALL                               => $order,
            'e71139e15ee5b6f472e2c68a97aa4bae9c80e9da' => [],     // resources (empty)
            'cccaafe693a53e8d1e791f06327974539da5978f' => [],     // buddy items (empty)
            self::CAT_CONSTRUCTION                      => $order,
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Calculate new expires_at when activating an item.
     * Extends any existing active time for the same booster type.
     *
     * @param array<string, mixed> $item
     */
    private function calcNewExpiry(User $user, array $item): Carbon
    {
        $duration = self::DURATION_SECONDS[$item['tier_key']] ?? 1800;
        $type     = self::getTypeForRef($item['ref']);

        if ($type !== null) {
            $active = $this->getActiveBoosterByType($user, $type);
            if ($active) {
                // Extend from the existing active booster's remaining time
                return $active->expires_at->addSeconds($duration);
            }
        }

        return now()->addSeconds($duration);
    }
}
