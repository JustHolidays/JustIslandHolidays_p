{{--
    Booking calendar months. Expects $months (App\Calendar\Availability::months) and optionally
    $toggleAction (owner's editor: each future day is a button that books or frees that night).

    Each day is split corner to corner: the top-left half is the morning (guests leaving), the
    bottom-right half is the afternoon (guests arriving). A check-in or check-out day therefore
    shows as a half day. Booked is orange, free is pale green; every day also has a text label,
    so colour is not the only clue.
--}}
@php
    $free = '#ecfdf5';
    $booked = '#fdba74';
    $bg = fn ($d) => match (true) {
        $d['am'] && $d['pm'] => $booked,
        $d['am'] => "linear-gradient(135deg, {$booked} 50%, {$free} 50%)",
        $d['pm'] => "linear-gradient(135deg, {$free} 50%, {$booked} 50%)",
        default => $free,
    };
    $toggleAction ??= null;
@endphp

<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-calendar>
    @foreach ($months as $month)
        <div>
            <h3 class="mb-2 font-semibold">{{ $month['title'] }}</h3>
            <table class="w-full table-fixed border-separate border-spacing-0.5 text-center text-sm">
                <caption class="sr-only">{{ __('Availability, :month', ['month' => $month['title']]) }}</caption>
                <thead>
                    <tr>
                        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)
                            <th scope="col" class="py-1 text-xs font-medium text-slate-600"><abbr title="{{ __($dayName) }}" class="no-underline">{{ __(substr($dayName, 0, 2)) }}</abbr></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($month['weeks'] as $week)
                        <tr>
                            @foreach ($week as $d)
                                @if (! $d['inMonth'])
                                    <td aria-hidden="true"></td>
                                @else
                                    @php
                                        $label = \App\Calendar\Availability::describe($d);
                                        $state = $d['am'] && $d['pm'] ? 'booked' : ($d['am'] ? 'am' : ($d['pm'] ? 'pm' : 'free'));
                                    @endphp
                                    <td class="p-0" data-day="{{ $d['date']->toDateString() }}" data-state="{{ $state }}">
                                        @if ($toggleAction && ! $d['past'])
                                            <button type="submit" form="calendar-toggle" name="date" value="{{ $d['date']->toDateString() }}"
                                                    title="{{ $label }}. {{ $d['pm'] ? __('Press to free the night from this day') : __('Press to book the night from this day') }}"
                                                    class="flex h-11 w-full items-center justify-center rounded border border-slate-300 font-medium text-[var(--colour-text)] hover:ring-2 hover:ring-slate-500"
                                                    style="background: {{ $bg($d) }}">
                                                <span aria-hidden="true">{{ $d['date']->day }}</span>
                                                <span class="sr-only">{{ $label }}. {{ $d['pm'] ? __('Free the night from this day') : __('Book the night from this day') }}</span>
                                            </button>
                                        @else
                                            <span class="flex h-11 items-center justify-center rounded border border-slate-200 {{ $d['past'] ? 'opacity-50' : '' }}" style="background: {{ $bg($d) }}" title="{{ $label }}">
                                                <span aria-hidden="true">{{ $d['date']->day }}</span>
                                                <span class="sr-only">{{ $label }}</span>
                                            </span>
                                        @endif
                                    </td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>

<ul class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm" aria-label="{{ __('Key') }}">
    <li class="flex items-center gap-2"><span class="h-5 w-5 rounded border border-slate-300" style="background: {{ $free }}" aria-hidden="true"></span>{{ __('Available') }}</li>
    <li class="flex items-center gap-2"><span class="h-5 w-5 rounded border border-slate-300" style="background: {{ $booked }}" aria-hidden="true"></span>{{ __('Booked') }}</li>
    <li class="flex items-center gap-2"><span class="h-5 w-5 rounded border border-slate-300" style="background: linear-gradient(135deg, {{ $booked }} 50%, {{ $free }} 50%)" aria-hidden="true"></span>{{ __('Changeover: guests leave in the morning, free from the afternoon') }}</li>
    <li class="flex items-center gap-2"><span class="h-5 w-5 rounded border border-slate-300" style="background: linear-gradient(135deg, {{ $free }} 50%, {{ $booked }} 50%)" aria-hidden="true"></span>{{ __('Changeover: free in the morning, guests arrive in the afternoon') }}</li>
</ul>
