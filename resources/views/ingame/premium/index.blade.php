@extends('ingame.layouts.main')

@section('content')

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div id="eventboxContent" style="display: none">
        <img height="16" width="16" src="/img/icons/3f9884806436537bdec305aa26fc60.gif">
    </div>

    <div id="inhalt" class="officers">
        <div id="planet">
            <div id="detailWrapper">
                <div id="header_text">
                    <h2>{{ __('t_ingame.premium.recruit_officers') }}</h2>
                </div>

                <div id="detail" class="detail_screen small">
                    <div id="techDetailLoading"></div>
                </div>
            </div>
        </div>	<div class="c-left"></div>
        <div class="c-right"></div>
        <div id="buttonz">
            <div class="header">
                <h2>{{ __('t_ingame.premium.your_officers') }}</h2>
            </div>
            <div class="content">
                <p class="stimulus">
                    {{ __('t_ingame.premium.intro_text') }}</p>

                <ul id="building">
                    <li class="on button" id="button1">
                        <div class="premium1">
                            <div class="officers100  darkMatter">
                                <a tabindex="1" href="javascript:void(0);" title="{{ __('t_ingame.premium.info_dark_matter') }}" class="detail_button tooltip js_hideTipOnMobile slideIn" ref="1" data-type="1">
                        <span class="ecke">
                            <span class="level">
                                {{ number_format($darkMatter, 0, ',', '.') }}
                            </span>
                        </span>
                                </a>
                            </div>
                        </div>			</li>

                    @foreach([
                        ['id' => 2, 'class' => 'commander',  'key' => 'info_commander',  'col' => 'commander'],
                        ['id' => 3, 'class' => 'admiral',    'key' => 'info_admiral',    'col' => 'admiral'],
                        ['id' => 4, 'class' => 'engineer',   'key' => 'info_engineer',   'col' => 'engineer'],
                        ['id' => 5, 'class' => 'geologist',  'key' => 'info_geologist',  'col' => 'geologist'],
                        ['id' => 6, 'class' => 'technocrat', 'key' => 'info_technocrat', 'col' => 'technocrat'],
                    ] as $officer)
                    @php
                        $expiry   = $officers[$officer['col']] ?? 0;
                        $isActive = $expiry > 0;
                        $daysLeft = $isActive ? ceil(($expiry - time()) / 86400) : 0;
                    @endphp
                    <li class="{{ $isActive ? 'on' : '' }} button" id="button{{ $officer['id'] }}">
                        <div class="premium">
                            <div class="officers100  {{ $officer['class'] }}">
                                <a tabindex="{{ $officer['id'] }}" href="javascript:void(0);"
                                   title="{{ __('t_ingame.premium.' . $officer['key']) }}"
                                   ref="{{ $officer['id'] }}"
                                   data-type="{{ $officer['id'] }}"
                                   class="detail_button tooltip js_hideTipOnMobile slideIn">
                        <span class="ecke">
                            <span class="level">
                                @if ($isActive)
                                    {{ $daysLeft }}d
                                @else
                                    <img src="/img/icons/aa2ad16d1e00956f7dc8af8be3ca52.gif" width="12" height="11">
                                @endif
                            </span>
                        </span>
                                </a>
                            </div>
                        </div>
                    </li>
                    @endforeach

                    @php
                        $bundleActive = ($officers['commander'] ?? 0) > 0
                            && ($officers['admiral'] ?? 0) > 0
                            && ($officers['engineer'] ?? 0) > 0
                            && ($officers['geologist'] ?? 0) > 0
                            && ($officers['technocrat'] ?? 0) > 0;
                    @endphp
                    <li class="{{ $bundleActive ? 'on' : '' }} button" id="button12">
                        <div class="premium">
                            <div class="officers100  allOfficers">
                                <a tabindex="12" href="javascript:void(0);" title="{{ __('t_ingame.premium.info_commanding_staff') }}" ref="12" data-type="12" class="detail_button tooltip js_hideTipOnMobile slideIn">
                        <span class="ecke">
                            <span class="level">
                                <img src="/img/icons/aa2ad16d1e00956f7dc8af8be3ca52.gif" width="12" height="11">
                            </span>
                        </span>
                                </a>
                            </div>
                            <div class="remaining tooltip " title="">
                                <span class="remDate">{{ __('t_ingame.premium.remaining_officers', ['current' => $activeCount, 'max' => 5]) }}</span>
                            </div>
                        </div>
                    </li>

                    <li class="allOfficers {{ $bundleActive ? 'on' : 'off' }}">
                        <span title="{{ __('t_ingame.premium.benefit_fleet_slots_title') }}" class="tooltipCustom tooltipTop">{{ __('t_ingame.premium.benefit_fleet_slots') }}</span><span title="{{ __('t_ingame.premium.benefit_energy_title') }}" class="tooltipCustom tooltipTop">{{ __('t_ingame.premium.benefit_energy') }}</span><span title="{{ __('t_ingame.premium.benefit_mines_title') }}" class="tooltipCustom tooltipTop">{{ __('t_ingame.premium.benefit_mines') }}</span><span title="{{ __('t_ingame.premium.benefit_espionage_title') }}" class="tooltipCustom tooltipTop">{{ __('t_ingame.premium.benefit_espionage') }}</span>            </li>
                </ul>
                <br class="clearfloat">
                <div class="footer"></div>
            </div>
        </div>
    </div>

    <script>
        var detailUrl = '{{ route('premium.officer-detail') }}';

        $(document).ready(function () {
            gfSlider = new GFSlider(getElementByIdWithCache('detailWrapper'));
        });
    </script>

@endsection
