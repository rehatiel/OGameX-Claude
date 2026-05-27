@extends('ingame.layouts.main')

@section('content')

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <div id="eventboxContent" style="display: none">
        <img height="16" width="16" src="/img/icons/3f9884806436537bdec305aa26fc60.gif">
    </div>

    <div id="inhalt">
        <div id="planet">
            <div id="detailWrapper">
                <div id="header_text">
                    <h2>{{ __('t_ingame.shop.page_title') }}</h2>
                </div>
                <div id="detail" class="detail_screen small">
                    <div id="techDetailLoading"></div>
                </div>
            </div>
        </div>
        <div class="c-left"></div>
        <div class="c-right"></div>

        <div id="buttonz">
            <div class="header">
                <h2>{{ __('t_ingame.shop.page_title') }}</h2>
            </div>
            <div class="content">
                <button class="to_shop active tooltip js_hideTipOnMobile" title="{{ __('t_ingame.shop.tooltip_shop') }}">
                    <span class="to_shop_icon">{{ __('t_ingame.shop.btn_shop') }}</span>
                </button>
                <button class="to_inventory tooltip js_hideTipOnMobile" title="{{ __('t_ingame.shop.tooltip_inventory') }}">
                    <span class="to_inventory_icon">{{ __('t_ingame.shop.btn_inventory') }}</span>
                </button>

                <div id="itemBox" class="border5px">
                    <div class="aside">
                        <ul class="listfilter border5px categoryFilter">
                            <li class="border5px inShop inInventory active">
                                <a href="javascript:void(0);" rel="{{ 'd8d49c315fa620d9c7f1f19963970dea59a0e3be' }}" class="active">
                                    <span>{{ __('t_ingame.shop.category_all') }} (<span class="amount">{{ count($shopItems) }}</span>)</span>
                                </a>
                            </li>
                            <li class="border5px inShop inInventory">
                                <a href="javascript:void(0);" rel="{{ 'dc9ec90e5a2163cc063b8bb3e9fe392782f565c8' }}">
                                    <span>{{ __('t_ingame.shop.category_construction') }} (<span class="amount">{{ count($shopItems) }}</span>)</span>
                                </a>
                            </li>
                        </ul>
                        <div class="btn_wrap">
                            <a role="button" tabindex="1" class="btn btn_confirm detail_button slideIn"
                               ref="ffffffffffffffffffffffffffffffffffffffff">
                                {{ __('t_ingame.shop.btn_purchase_dark_matter') }}
                            </a>
                        </div>
                    </div>

                    <div id="js_shopSliderBox" class="shop_slider">
                        <ul id="js_shopSlider">
                            <li class="panel activePage">
                                @foreach($shopItems as $item)
                                    @php
                                        $itemName = strtoupper(__('t_resources.' . $item['name_key'] . '.title')) . ' ' . __('t_ingame.shop.tier_' . $item['tier_key']);
                                        $itemDesc = __('t_resources.' . $item['name_key'] . '.description', ['duration' => '<b>' . $item['duration'] . '</b>']);
                                        $tooltip  = $itemName . '|' . $itemDesc . '<br /><br />' .
                                                    __('t_ingame.shop.item_price') . ': ' . number_format($item['price'], 0, '', '.') . ' ' . __('t_ingame.shop.dark_matter');
                                    @endphp
                                    <div class="item_img r_{{ $item['rarity'] }}" style="background-image: url(/img/icons/{{ $item['image_hash'] }}-100x.png);">
                                        <div class="item_img_box">
                                            <div class="activation disabled"></div>
                                            <a href="javascript:void(0);" tabindex="1" title="{{ $tooltip }}"
                                               class="detail_button tooltipHTML js_hideTipOnMobile slideIn" ref="{{ $item['ref'] }}" data-type="{{ $item['ref'] }}">
                                                <div class="sale_badge disabled"></div>
                                                <span class="ecke"><span class="level price">{{ $item['price_label'] }} DM</span></span>
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </li>
                        </ul>
                    </div>

                    <div id="js_inventorySliderBox" class="inventory_slider" style="display:none;"></div>
                </div>

                <div class="footer"></div>
            </div>
        </div>
    </div>

    <script>
        var detailUrl = '{{ route('shop.item-detail') }}';

        $(document).ready(function () {
            loca = $.extend({}, loca, { buyDMDecision: '{{ __('t_ingame.shop.loca_buy_dm') }}' });
            gfSlider = new GFSlider(getElementByIdWithCache('detailWrapper'));
            inventoryObj.initShopDetails();
        });
    </script>

@endsection
