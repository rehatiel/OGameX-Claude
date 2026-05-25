@php
    $tooltipKey = match($type) {
        2  => 't_ingame.premium.hire_commander_tooltip',
        3  => 't_ingame.premium.hire_admiral_tooltip',
        4  => 't_ingame.premium.hire_engineer_tooltip',
        5  => 't_ingame.premium.hire_geologist_tooltip',
        6  => 't_ingame.premium.hire_technocrat_tooltip',
        12 => 't_ingame.premium.info_commanding_staff',
        default => '',
    };
    $officerClass = match($type) {
        2  => 'commander',
        3  => 'admiral',
        4  => 'engineer',
        5  => 'geologist',
        6  => 'technocrat',
        12 => 'allOfficers',
        default => '',
    };
    [$tooltipTitle, $tooltipBody] = array_pad(explode('|', __($tooltipKey), 2), 2, '');
    $canAfford = $darkMatter >= $cost;
@endphp

<div id="premiumContent" class="detail_screen">
    <div class="detail_header">
        <div class="officers100 {{ $officerClass }}"></div>
        <h2>{{ $tooltipTitle ?: $label }}</h2>
    </div>

    <div class="detail_content">
        @if ($tooltipBody)
            <div class="detail_description">
                {!! nl2br($tooltipBody) !!}
            </div>
        @endif

        <div class="dm_detail">
            <table>
                <tr>
                    <td>{{ __('t_ingame.shop.dark_matter') }}:</td>
                    <td class="{{ $canAfford ? '' : 'text-red' }}">{{ number_format($darkMatter, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>{{ __('t_ingame.shop.item_price') }}:</td>
                    <td>{{ number_format($cost, 0, ',', '.') }}</td>
                </tr>
                @if ($isActive)
                    <tr>
                        <td>{{ __('t_ingame.premium.remaining_officers', ['current' => 1, 'max' => 1]) }}:</td>
                        <td>{{ date('d.m.Y', $expiry) }}</td>
                    </tr>
                @endif
            </table>
        </div>

        @if ($isActive)
            <p class="officer_active_info">
                Active until {{ date('d.m.Y H:i', $expiry) }}
            </p>
        @endif

        @if (!$isActive || true)
            {{-- Always allow extending --}}
            <form method="POST" action="{{ route('premium.buy-officer') }}">
                @csrf
                <input type="hidden" name="officer_type" value="{{ $type }}">
                <button type="submit"
                        class="btn_blue {{ !$canAfford ? 'disabled' : '' }}"
                        {{ !$canAfford ? 'disabled' : '' }}>
                    @if ($isActive)
                        {{ __('t_ingame.shop.loca_extend') }} ({{ number_format($cost, 0, ',', '.') }} DM)
                    @else
                        {{ __('t_ingame.shop.loca_buy_activate') }} ({{ number_format($cost, 0, ',', '.') }} DM)
                    @endif
                </button>
            </form>
        @endif
    </div>
</div>
