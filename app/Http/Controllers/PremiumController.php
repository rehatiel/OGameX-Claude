<?php

namespace OGame\Http\Controllers;

use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use OGame\Enums\DarkMatterTransactionType;
use OGame\Services\DarkMatterService;
use OGame\Services\PlayerService;
use OGame\Services\SettingsService;

class PremiumController extends OGameController
{
    private const OFFICER_MAP = [
        2  => ['column' => 'officer_commander',  'label' => 'Commander'],
        3  => ['column' => 'officer_admiral',    'label' => 'Admiral'],
        4  => ['column' => 'officer_engineer',   'label' => 'Engineer'],
        5  => ['column' => 'officer_geologist',  'label' => 'Geologist'],
        6  => ['column' => 'officer_technocrat', 'label' => 'Technocrat'],
        12 => ['column' => null,                 'label' => 'Commanding Staff'],
    ];

    public function __construct(
        private readonly SettingsService $settingsService,
    ) {}

    /**
     * Shows the premium/officers index page.
     */
    public function index(): View
    {
        $this->setBodyId('premium');

        $user = Auth::user();
        $now  = time();

        $officers = [
            'commander'  => $user->officer_commander  > $now ? $user->officer_commander  : 0,
            'admiral'    => $user->officer_admiral    > $now ? $user->officer_admiral    : 0,
            'engineer'   => $user->officer_engineer   > $now ? $user->officer_engineer   : 0,
            'geologist'  => $user->officer_geologist  > $now ? $user->officer_geologist  : 0,
            'technocrat' => $user->officer_technocrat > $now ? $user->officer_technocrat : 0,
        ];

        return view('ingame.premium.index', [
            'darkMatter'  => $user->dark_matter ?? 0,
            'officers'    => $officers,
            'activeCount' => count(array_filter($officers)),
            'officerCost' => $this->settingsService->officerCost(),
            'bundleCost'  => $this->settingsService->commandingStaffCost(),
        ]);
    }

    /**
     * Returns HTML for the officer detail panel (loaded via AJAX slideIn).
     */
    public function ajaxOfficerDetail(Request $request): View
    {
        $type = (int)$request->input('type', 0);

        if (!isset(self::OFFICER_MAP[$type])) {
            abort(404);
        }

        $user   = Auth::user();
        $now    = time();
        $info   = self::OFFICER_MAP[$type];
        $cost   = ($type === 12)
            ? $this->settingsService->commandingStaffCost()
            : $this->settingsService->officerCost();

        $column   = $info['column'];
        $expiry   = $column ? ($user->{$column} ?? 0) : 0;
        $isActive = $expiry > $now;

        if ($type === 12) {
            $isActive = $user->officer_commander  > $now
                     && $user->officer_admiral    > $now
                     && $user->officer_engineer   > $now
                     && $user->officer_geologist  > $now
                     && $user->officer_technocrat > $now;

            $expiry = $isActive ? min(
                $user->officer_commander,
                $user->officer_admiral,
                $user->officer_engineer,
                $user->officer_geologist,
                $user->officer_technocrat,
            ) : 0;
        }

        return view('ingame.premium.partials.officer-detail', [
            'type'       => $type,
            'label'      => $info['label'],
            'isActive'   => $isActive,
            'expiry'     => $expiry,
            'cost'       => $cost,
            'darkMatter' => $user->dark_matter ?? 0,
        ]);
    }

    /**
     * Purchases an officer or commanding staff bundle.
     */
    public function buyOfficer(
        Request $request,
        DarkMatterService $dmService,
    ): RedirectResponse {
        $request->validate(['officer_type' => 'required|integer|in:2,3,4,5,6,12']);

        $type = (int)$request->input('officer_type');
        $user = Auth::user();
        $cost = ($type === 12)
            ? $this->settingsService->commandingStaffCost()
            : $this->settingsService->officerCost();

        if (!$dmService->canAfford($user, $cost)) {
            return redirect()->route('premium.index')
                ->with('error', __('t_ingame.premium.not_enough_dark_matter'));
        }

        $durationSeconds = $this->settingsService->officerDurationDays() * 86400;
        $now             = time();
        $info            = self::OFFICER_MAP[$type];

        try {
            $dmService->debit(
                $user,
                $cost,
                DarkMatterTransactionType::COMMANDING_STAFF->value,
                $info['label'] . ' hired',
            );

            // Re-fetch user to get the updated dark_matter (debit locks + saves internally)
            $user->refresh();

            if ($type === 12) {
                foreach ([2, 3, 4, 5, 6] as $t) {
                    $col            = self::OFFICER_MAP[$t]['column'];
                    $current        = $user->{$col} ?? 0;
                    $user->{$col}   = max($now, $current) + $durationSeconds;
                }
            } else {
                $col          = $info['column'];
                $current      = $user->{$col} ?? 0;
                $user->{$col} = max($now, $current) + $durationSeconds;
            }

            $user->save();
        } catch (Exception $e) {
            return redirect()->route('premium.index')
                ->with('error', __('t_ingame.premium.purchase_failed'));
        }

        return redirect()->route('premium.index')
            ->with('status', __('t_ingame.premium.officer_hired', ['officer' => $info['label']]));
    }
}
