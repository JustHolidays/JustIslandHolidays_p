@extends('layouts.admin', ['title' => 'Account closures'])

@section('content')
    <h1 class="text-2xl font-semibold">Account closures</h1>
    <p class="mt-1 max-w-3xl">Owners, agents and visitors who pressed "Close my account", oldest first. <strong>Close account</strong> signs them out, cancels their own PayPal plan, takes their listings down (archived) and deletes the account; their personal details are removed 30 days later and they are emailed. <strong>Decline</strong> keeps the account open and emails them your reason. Both are written to the audit log.</p>
    @error('close')<p role="alert" class="mt-4 rounded border border-red-300 bg-red-50 p-3 font-medium text-red-800">{{ $message }}</p>@enderror

    @if ($requests->isEmpty())
        <p class="mt-6 rounded-lg bg-white p-5 shadow-sm">No requests waiting.</p>
    @else
        <ul class="mt-6 space-y-4">
            @foreach ($requests as $person)
                @php($subscription = $person->role !== \App\Enums\Role::Visitor ? $plans->currentSubscription($plans->accountFor($person)) : null)
                <li class="rounded-lg bg-white p-5 shadow-sm" data-closure="{{ $person->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">{{ $person->name }}</h2>
                            <p class="text-sm">{{ $person->email }} · {{ $person->role->label() }} · asked {{ $person->closure_requested_at->format('j M Y, H:i') }}
                                @if ($person->closure_requested_at->lt(now()->subDays(7)))<span class="ml-1 rounded bg-amber-200 px-2 py-0.5 text-xs font-semibold">Over a week</span>@endif
                            </p>
                            <p class="mt-1 text-sm">
                                {{ trans_choice(':count listing still up|:count listings still up', (int) ($listings[$person->id] ?? 0)) }}
                                @if ($subscription && $subscription->status === 'active' && $subscription->paypal_subscription_id) · <strong>Paid plan: it will be cancelled in PayPal</strong>@endif
                                @if ($person->role === \App\Enums\Role::Agent && $person->agency_id) · Agency plan stays with the agency @endif
                            </p>
                            @if ($person->closure_reason)
                                <blockquote class="mt-2 border-l-4 border-slate-300 pl-3 text-sm italic">{{ $person->closure_reason }}</blockquote>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('admin.closures.close', $person) }}" onsubmit="return confirm(@js('Close '.$person->name.'\'s account? Their listings are taken down and the account is deleted. This cannot be undone.'))">
                            @csrf
                            <button type="submit" class="min-h-11 rounded bg-red-800 px-4 font-medium text-white hover:bg-red-900">Close account<span class="sr-only"> for {{ $person->name }}</span></button>
                        </form>
                    </div>
                    @php($bag = $errors->getBag('decline'.$person->id))
                    <details class="mt-3" @if ($bag->any()) open @endif>
                        <summary class="flex min-h-11 cursor-pointer items-center font-medium">Decline with a reason</summary>
                        <form method="POST" action="{{ route('admin.closures.decline', $person) }}" class="mt-2 max-w-xl">
                            @csrf
                            <label for="reason-{{ $person->id }}" class="mb-1 block text-sm font-medium">Reason (emailed to them)</label>
                            <textarea id="reason-{{ $person->id }}" name="reason" rows="3" maxlength="500" class="block w-full rounded border border-slate-400 px-3 py-2" @if ($bag->any()) aria-invalid="true" aria-describedby="reason-{{ $person->id }}-error" @endif>{{ $bag->any() ? old('reason') : '' }}</textarea>
                            @if ($bag->any())<p id="reason-{{ $person->id }}-error" class="mt-1 text-sm font-medium text-red-800">{{ $bag->first('reason') }}</p>@endif
                            <button type="submit" class="mt-2 min-h-11 rounded border border-slate-400 px-4">Decline<span class="sr-only"> {{ $person->name }}'s request</span></button>
                        </form>
                    </details>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
