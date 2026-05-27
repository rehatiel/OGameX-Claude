@extends('ingame.layouts.main')

@section('content')

    <div id="rewardscomponent" class="maincontent">
        <div id="content">
            <div id="inhalt">
                <div id="planet" style="background-image:url(/img/headers/rewards/rewards.jpg);height:250px;">
                    <div id="header_text">
                        <h2>Rewards</h2>
                    </div>
                </div>
                <div id="buttonz">
                    <div class="header">
                        <h2>Rewards</h2>
                    </div>
                    <div class="content">

                        @if (session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif

                        <div class="rewardhint rewardnotifyhidden">
                            <img class="rewardwarningicon" src="/img/icons/04be50e8afc747846a55a646381a16.png">
                            <span class="rewardwarningtext"></span>
                        </div>

                        <div class="rewardlist">
                            <a class="tooltipLeft fright questionIcons" style="display: inline-block"
                               title="Rewards are dispatched daily and can be collected manually. From the 8th day on, no further rewards will be sent out. The first reward will be available on the 2nd day of registration.">
                                <span class="rewardDetail"></span>
                            </a>
                            <br>

                            {{-- Available to claim --}}
                            @if (count($available) > 0)
                                <h3>New awards</h3>
                                @foreach ($available as $reward)
                                    <div class="rewardlist-item">
                                        <div class="rewardlistimg {{ $reward['image_class'] }} rewardnotclaim">
                                            <div class="rewardlist-item-icon">
                                                <img src="/img/icons/{{ $reward['icon'] }}">
                                            </div>
                                            <div class="rewardlist-item-text">
                                                <h3>{{ $reward['title'] }}</h3>
                                                <div class="rewardlist-item-wrapper">
                                                    <p>Greetings, emperor {{ $user->username }}!<br><br>
                                                        {{ $reward['description'] }}<br><br>
                                                        Good luck!<br>
                                                        The OGame Starter Aid</p>
                                                    <a class="reward-button js-claim-reward"
                                                       href="javascript:void(0)"
                                                       data-key="{{ $reward['key'] }}"
                                                       data-url="{{ route('rewards.claim', $reward['key']) }}">
                                                        Collect
                                                    </a>
                                                </div>
                                                <div class="rewardlist-item-bottom"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <br>
                                @endforeach
                            @endif

                            {{-- Not yet available --}}
                            @if (count($notReached) > 0)
                                <h3>Awards not yet reached</h3>
                                @foreach ($notReached as $reward)
                                    <div class="rewardlist-item">
                                        <div class="rewardlistimg {{ $reward['image_class'] }} rewardnotclaim">
                                            <div class="rewardlist-item-icon">
                                                <img src="/img/icons/{{ $reward['icon'] }}">
                                            </div>
                                            <div class="rewardlist-item-text">
                                                <h3>{{ $reward['title'] }}</h3>
                                                <div class="rewardlist-item-wrapper">
                                                    <p>Greetings, emperor {{ $user->username }}!<br><br>
                                                        {{ $reward['description'] }}<br><br>
                                                        Good luck!<br>
                                                        The OGame Starter Aid</p>
                                                    <a class="reward-button disabled" href="javascript:void(0)">Not fulfilled</a>
                                                </div>
                                                <div class="rewardlist-item-bottom"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <br>
                                @endforeach
                            @endif

                            {{-- Collected / history --}}
                            @if (count($collected) > 0)
                                <h3>Collected awards</h3>
                                @foreach ($collected as $reward)
                                    <div class="rewardlist-item">
                                        <div class="rewardlistimg {{ $reward['image_class'] }} rewardclaimed">
                                            <div class="rewardlist-item-icon">
                                                <img src="/img/icons/{{ $reward['icon'] }}">
                                            </div>
                                            <div class="rewardlist-item-text">
                                                <h3>{{ $reward['title'] }}</h3>
                                                <div class="rewardlist-item-wrapper">
                                                    <p>{{ $reward['description'] }}</p>
                                                    <span class="reward-collected-date">
                                                        Collected {{ $reward['claimed_at']->format('d.m.Y H:i') }}
                                                    </span>
                                                </div>
                                                <div class="rewardlist-item-bottom"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <br>
                                @endforeach
                            @endif

                            @if (count($available) === 0 && count($notReached) === 0 && count($collected) === 0)
                                <p>No rewards available.</p>
                            @endif

                        </div>{{-- .rewardlist --}}
                    </div>{{-- .content --}}
                </div>{{-- #buttonz --}}
            </div>{{-- #inhalt --}}
        </div>{{-- #content --}}
    </div>{{-- #rewardscomponent --}}

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.js-claim-reward').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var url = btn.dataset.url;
                    btn.classList.add('disabled');
                    btn.textContent = 'Claiming...';

                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                    })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data.error) {
                            btn.textContent = 'Collect';
                            btn.classList.remove('disabled');
                            alert(data.message);
                        } else {
                            location.reload();
                        }
                    })
                    .catch(function () {
                        btn.textContent = 'Collect';
                        btn.classList.remove('disabled');
                        alert('An error occurred. Please try again.');
                    });
                });
            });
        });
    </script>

@endsection
