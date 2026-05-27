<?php

namespace OGame\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use OGame\Enums\DarkMatterTransactionType;
use OGame\Factories\PlayerServiceFactory;
use OGame\Models\Resources;
use OGame\Models\User;
use OGame\Models\UserBooster;
use OGame\Models\UserReward;

/**
 * Class RewardsService.
 *
 * Manages the 7-day Starter Aid reward system for new players.
 * One reward becomes available per day starting on day 2 after registration.
 */
class RewardsService
{
    private const CATALOG = [
        [
            'key'         => 'day_1',
            'day'         => 1,
            'title'       => "Let's go Emperor",
            'description' => "Your colony ship supplies have been unloaded and are ready to help develop your world. The perfect time to drive forward the improvements to your empire!",
            'type'        => 'resources',
            'data'        => ['metal' => 10000, 'crystal' => 10000, 'deuterium' => 0],
            'image_class' => 'rewardlistimg_1',
            'icon'        => '2251eaefdfdf075833e5247781a4ac.png',
        ],
        [
            'key'         => 'day_2',
            'day'         => 2,
            'title'       => 'The colony is growing!',
            'description' => 'Your subordinates want to make themselves useful. A KRAKEN robot has been provided to help accelerate improvements to your colony. Increase your production and your empire will bloom!',
            'type'        => 'booster',
            'data'        => ['ref' => '40f6c78e11be01ad3389b7dccd6ab8efa9347f3c', 'amount' => 1], // Kraken Bronze
            'image_class' => 'rewardlistimg_2',
            'icon'        => '2251eaefdfdf075833e5247781a4ac.png',
        ],
        [
            'key'         => 'day_3',
            'day'         => 3,
            'title'       => 'Supply and demand',
            'description' => 'When the metal storage is overflowing and the assembly line stands still, it is a good time to visit the resource merchant. Here is some Dark Matter to get you started.',
            'type'        => 'dark_matter',
            'data'        => ['amount' => 500],
            'image_class' => 'rewardlistimg_4',
            'icon'        => '2251eaefdfdf075833e5247781a4ac.png',
        ],
        [
            'key'         => 'day_4',
            'day'         => 4,
            'title'       => 'Progress through technology',
            'description' => 'Your colony has to be protected from enemy emperors. Here is some Dark Matter to help you build up your defenses and protect your new home!',
            'type'        => 'dark_matter',
            'data'        => ['amount' => 500],
            'image_class' => 'rewardlistimg_8',
            'icon'        => '2251eaefdfdf075833e5247781a4ac.png',
        ],
        [
            'key'         => 'day_5',
            'day'         => 5,
            'title'       => 'Progress through technology',
            'description' => 'Our scientists are racking their brains. A NEWTRON robot should be of good use to them — it will accelerate research across your empire.',
            'type'        => 'booster',
            'data'        => ['ref' => 'da4a2a1bb9afd410be07bc9736d87f1c8059e66d', 'amount' => 1], // Newtron Bronze
            'image_class' => 'rewardlistimg_16',
            'icon'        => '2251eaefdfdf075833e5247781a4ac.png',
        ],
        [
            'key'         => 'day_6',
            'day'         => 6,
            'title'       => 'Conquer outer space',
            'description' => 'We need to strengthen our forces. The DETROID robot can accelerate production in the shipyard so your fleet will be ready to go at all times!',
            'type'        => 'booster',
            'data'        => ['ref' => 'd3d541ecc23e4daa0c698e44c32f04afd2037d84', 'amount' => 1], // Detroid Bronze
            'image_class' => 'rewardlistimg_32',
            'icon'        => '2251eaefdfdf075833e5247781a4ac.png',
        ],
        [
            'key'         => 'day_7',
            'day'         => 7,
            'title'       => 'Expansion of the empire',
            'description' => 'The foundations for a powerful empire are set. The Commanding Staff are now available to you for 3 days to support you in the consolidation of your empire.',
            'type'        => 'officers',
            'data'        => ['days' => 3],
            'image_class' => 'rewardlistimg_64',
            'icon'        => '2251eaefdfdf075833e5247781a4ac.png',
        ],
    ];

    public function __construct(
        private readonly PlayerServiceFactory $playerServiceFactory,
        private readonly DarkMatterService $dmService,
    ) {}

    /**
     * Returns the full catalog with status for a given user.
     *
     * @param User $user
     * @return array<int, array<string, mixed>>
     */
    public function getAllWithStatus(User $user): array
    {
        $claimedKeys = UserReward::where('user_id', $user->id)
            ->pluck('claimed_at', 'reward_key');

        $registeredAt = $user->created_at;
        $now          = now();

        return array_map(function (array $reward) use ($claimedKeys, $registeredAt, $now) {
            if (isset($claimedKeys[$reward['key']])) {
                $reward['status']     = 'claimed';
                $reward['claimed_at'] = $claimedKeys[$reward['key']];
            } elseif ($registeredAt->copy()->addDays($reward['day'])->lte($now)) {
                $reward['status'] = 'available';
            } else {
                $reward['status']        = 'not_reached';
                $reward['available_at']  = $registeredAt->copy()->addDays($reward['day']);
            }

            return $reward;
        }, self::CATALOG);
    }

    /**
     * Claims a reward for the user and delivers the reward contents.
     *
     * @throws Exception
     */
    public function claim(User $user, string $rewardKey): string
    {
        $reward = $this->findByKey($rewardKey);
        if ($reward === null) {
            throw new Exception('Unknown reward.');
        }

        $registeredAt = $user->created_at;
        $availableAt  = $registeredAt->copy()->addDays($reward['day']);

        if ($availableAt->gt(now())) {
            throw new Exception('Reward is not yet available.');
        }

        return DB::transaction(function () use ($user, $reward, $rewardKey) {
            $inserted = DB::table('user_rewards')->insertOrIgnore([
                'user_id'    => $user->id,
                'reward_key' => $rewardKey,
                'claimed_at' => now(),
            ]);

            if ($inserted === 0) {
                throw new Exception('Reward already claimed.');
            }

            return $this->deliver($user, $reward);
        });
    }

    /**
     * Delivers the reward and returns a human-readable receipt.
     */
    private function deliver(User $user, array $reward): string
    {
        return match ($reward['type']) {
            'resources'   => $this->deliverResources($user, $reward['data']),
            'booster'     => $this->deliverBooster($user, $reward['data']),
            'dark_matter' => $this->deliverDarkMatter($user, $reward['data']),
            'officers'    => $this->deliverOfficers($user, $reward['data']),
            default       => throw new Exception('Unknown reward type.'),
        };
    }

    private function deliverResources(User $user, array $data): string
    {
        $player    = $this->playerServiceFactory->make($user->id, true);
        $homeworld = $player->planets->first();

        if ($homeworld === null) {
            throw new Exception('No homeworld found to deliver resources.');
        }

        $homeworld->addResources(new Resources(
            $data['metal'],
            $data['crystal'],
            $data['deuterium'],
            0,
        ));

        return 'Received ' . number_format($data['metal']) . ' Metal and ' . number_format($data['crystal']) . ' Crystal.';
    }

    private function deliverBooster(User $user, array $data): string
    {
        $booster         = UserBooster::firstOrNew(['user_id' => $user->id, 'ref' => $data['ref']]);
        $booster->amount = ($booster->amount ?? 0) + $data['amount'];
        $booster->save();

        $item = \OGame\Services\ShopBoosterService::getItemByRef($data['ref']);
        $name = $item ? strtoupper($item['name_key']) . ' ' . ucfirst($item['tier_key']) : 'booster';

        return 'Received 1x ' . $name . ' added to your inventory.';
    }

    private function deliverDarkMatter(User $user, array $data): string
    {
        $this->dmService->credit(
            $user,
            $data['amount'],
            DarkMatterTransactionType::INITIAL_BONUS->value,
            'Starter Aid reward',
        );

        return 'Received ' . number_format($data['amount']) . ' Dark Matter.';
    }

    private function deliverOfficers(User $user, array $data): string
    {
        $durationSeconds = $data['days'] * 86400;
        $now             = time();

        $columns = [
            'officer_commander',
            'officer_admiral',
            'officer_engineer',
            'officer_geologist',
            'officer_technocrat',
        ];

        foreach ($columns as $col) {
            $current      = $user->{$col} ?? 0;
            $user->{$col} = max($now, $current) + $durationSeconds;
        }

        $user->save();

        return 'Commanding Staff activated for ' . $data['days'] . ' days.';
    }

    private function findByKey(string $key): ?array
    {
        foreach (self::CATALOG as $reward) {
            if ($reward['key'] === $key) {
                return $reward;
            }
        }
        return null;
    }
}
