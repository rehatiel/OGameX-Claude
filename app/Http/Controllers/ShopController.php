<?php

namespace OGame\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use OGame\Services\ShopBoosterService;

class ShopController extends OGameController
{
    public function __construct(
        private readonly ShopBoosterService $boosterService,
    ) {}

    /**
     * Shows the shop index page.
     */
    public function index(): View
    {
        $this->setBodyId('shop');

        return view('ingame.shop.index', [
            'shopItems' => ShopBoosterService::getCatalog(),
        ]);
    }

    /**
     * Returns the item-detail panel HTML for the GFSlider (AJAX GET).
     * Called via loadDetails(ref) when a .slideIn item is clicked.
     */
    public function ajaxItemDetail(Request $request): View
    {
        $ref  = (string) $request->input('type', '');
        $item = ShopBoosterService::getItemByRef($ref);

        if ($item === null) {
            abort(404);
        }

        $user    = Auth::user();
        $booster = $this->boosterService->getUserBooster($user, $ref);
        $type    = ShopBoosterService::getTypeForRef($ref);

        // Check if a booster of the same type is already active (from any ref)
        $activeSameType = $type ? $this->boosterService->getActiveBoosterByType($user, $type) : null;
        $isActive       = $activeSameType !== null;
        $timeLeft       = $isActive ? $activeSameType->secondsLeft() : 0;

        return view('ingame.shop.partials.item-detail', [
            'item'      => $item,
            'booster'   => $booster,
            'isActive'  => $isActive,
            'timeLeft'  => $timeLeft,
            'user'      => $user,
            'buyUrl'    => route('shop.buy', ['ref' => $ref]),
            'activateUrl' => route('shop.activate', ['ref' => $ref]),
            'buyActivateUrl' => route('shop.buyAndActivate', ['ref' => $ref]),
        ]);
    }

    /**
     * Purchases a booster and adds it to the player's inventory.
     * Returns JSON consumed by inventoryObj.initShopDetails().
     */
    public function buy(Request $request, string $ref): JsonResponse
    {
        $item = ShopBoosterService::getItemByRef($ref);
        if ($item === null) {
            return response()->json(['error' => true, 'message' => 'Unknown item.', 'newAjaxToken' => csrf_token()]);
        }

        $user = Auth::user();

        try {
            $booster = $this->boosterService->purchase($user, $ref);
            $user->refresh();

            return response()->json([
                'error'        => false,
                'message'      => __('t_ingame.shop.purchased'),
                'item'         => $this->boosterService->buildItemData($user, $item),
                'newAjaxToken' => csrf_token(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error'        => true,
                'message'      => $e->getMessage(),
                'newAjaxToken' => csrf_token(),
            ]);
        }
    }

    /**
     * Activates a booster from inventory, starting/extending the active timer.
     */
    public function activate(Request $request, string $ref): JsonResponse
    {
        $item = ShopBoosterService::getItemByRef($ref);
        if ($item === null) {
            return response()->json(['error' => true, 'message' => 'Unknown item.', 'newAjaxToken' => csrf_token()]);
        }

        $user = Auth::user();

        try {
            $this->boosterService->activate($user, $ref);
            $user->refresh();

            return response()->json([
                'error'        => false,
                'message'      => __('t_ingame.shop.activated'),
                'item'         => $this->boosterService->buildItemData($user, $item),
                'reload'       => false,
                'newAjaxToken' => csrf_token(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error'        => true,
                'message'      => $e->getMessage(),
                'newAjaxToken' => csrf_token(),
            ]);
        }
    }

    /**
     * Purchases and immediately activates a booster.
     */
    public function buyAndActivate(Request $request, string $ref): JsonResponse
    {
        $item = ShopBoosterService::getItemByRef($ref);
        if ($item === null) {
            return response()->json(['error' => true, 'message' => 'Unknown item.', 'newAjaxToken' => csrf_token()]);
        }

        $user = Auth::user();

        try {
            $this->boosterService->buyAndActivate($user, $ref);
            $user->refresh();

            return response()->json([
                'error'        => false,
                'message'      => __('t_ingame.shop.activated'),
                'item'         => $this->boosterService->buildItemData($user, $item),
                'reload'       => false,
                'newAjaxToken' => csrf_token(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error'        => true,
                'message'      => $e->getMessage(),
                'newAjaxToken' => csrf_token(),
            ]);
        }
    }
}
