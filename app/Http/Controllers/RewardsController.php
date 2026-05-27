<?php

namespace OGame\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use OGame\Services\RewardsService;

class RewardsController extends OGameController
{
    public function __construct(
        private readonly RewardsService $rewardsService,
    ) {}

    /**
     * Shows the rewards index page.
     */
    public function index(): View
    {
        $user    = Auth::user();
        $rewards = $this->rewardsService->getAllWithStatus($user);

        $available   = array_values(array_filter($rewards, fn($r) => $r['status'] === 'available'));
        $notReached  = array_values(array_filter($rewards, fn($r) => $r['status'] === 'not_reached'));
        $collected   = array_values(array_filter($rewards, fn($r) => $r['status'] === 'claimed'));

        return view('ingame.rewards.index', compact('user', 'available', 'notReached', 'collected'));
    }

    /**
     * Claims a reward for the authenticated user.
     */
    public function claim(Request $request, string $key): JsonResponse
    {
        $user = Auth::user();

        try {
            $receipt = $this->rewardsService->claim($user, $key);

            return response()->json([
                'error'   => false,
                'message' => $receipt,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
