@php
    $amount     = $booster ? (int)$booster->amount : 0;
    $canAfford  = $user->dark_matter >= $item['price'];
    $tierLabel  = __('t_ingame.shop.tier_' . $item['tier_key']);
    $itemName   = strtoupper(__('t_resources.' . $item['name_key'] . '.title')) . ' ' . $tierLabel;
    $itemDesc   = __('t_resources.' . $item['name_key'] . '.description', ['duration' => '<b>' . $item['duration'] . '</b>']);
    $buyClass   = $canAfford ? 'build-it' : 'build-it_disabled showGetMoreDmPopup';
    $actClass   = ($amount > 0) ? 'build-it' : 'build-it_disabled';
    $baClass    = ($amount === 0 && $canAfford) ? 'build-it' : 'build-it_disabled showGetMoreDmPopup';
@endphp

<div id="itemDetails" data-uuid="{{ $item['ref'] }}" class="detail_screen">
    <a class="close_details" ref="{{ $item['ref'] }}" href="javascript:void(0);"></a>

    <div class="detail_header">
        <div class="item_img r_{{ $item['rarity'] }}"
             style="background-image: url(/img/icons/{{ $item['image_hash'] }}-100x.png);"></div>
        <h2>{{ $itemName }}</h2>
    </div>

    <div class="detail_content">
        <p class="detail_description">{!! $itemDesc !!}</p>

        <div class="dm_detail">
            <table>
                <tr>
                    <td>{{ __('t_ingame.shop.dark_matter') }}:</td>
                    <td class="{{ $canAfford ? '' : 'text-red' }}">{{ number_format($user->dark_matter, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>{{ __('t_ingame.shop.item_price') }}:</td>
                    <td>{{ number_format($item['price'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>{{ __('t_ingame.shop.item_in_inventory') }}:</td>
                    <td><span class="amount">{{ $amount }}</span></td>
                </tr>
                @if ($isActive && $timeLeft > 0)
                    <tr>
                        <td>{{ __('t_ingame.shop.item_duration') }}:</td>
                        <td>{{ gmdate('H:i:s', $timeLeft) }}</td>
                    </tr>
                @endif
            </table>
        </div>

        {{-- Buy button: always visible, adds 1 to inventory --}}
        <a class="item {{ $buyClass }}" rel="{{ $buyUrl }}" href="javascript:void(0);"
           title="{{ $itemName }}">
            <span>{{ __('t_ingame.shop.btn_buy', ['price' => number_format($item['price'], 0, ',', '.')]) }}</span>
        </a>

        {{-- Activate button: shown when inventory > 0 --}}
        <a class="activateItem {{ $actClass }}" rel="{{ $activateUrl }}" href="javascript:void(0);"
           style="{{ $amount > 0 ? '' : 'display:none;' }}"
           title="{{ $itemName }}">
            <span>{{ $isActive ? __('t_ingame.shop.loca_extend') : __('t_ingame.shop.loca_activate') }}</span>
        </a>

        {{-- Buy + Activate button: shown when inventory == 0 --}}
        <a class="buyAndActivate {{ $baClass }}" rel="{{ $buyActivateUrl }}" href="javascript:void(0);"
           style="{{ $amount === 0 ? '' : 'display:none;' }}"
           title="{{ $itemName }}">
            <span>{{ $isActive ? __('t_ingame.shop.loca_buy_extend') : __('t_ingame.shop.loca_buy_activate') }}</span>
        </a>
    </div>
</div>
