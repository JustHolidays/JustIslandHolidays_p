@extends('layouts.admin', ['title' => 'Maintenance mode'])

@section('content')
    <h1 class="text-2xl font-semibold">Maintenance mode</h1>
    <p class="mt-1 max-w-3xl">While maintenance mode is on, visitors see a maintenance page with your message instead of the website. Search engines are told the site is down for a short while, so it keeps its place in search results. Admins who are signed in still see and use the whole site, and the admin area works as normal. Owners and agents can still sign in to their accounts; payments and calendars on owners' own websites keep working.</p>

    <section class="mt-6 max-w-3xl rounded-lg p-5 shadow-sm {{ $settings['on'] ? 'border-2 border-amber-500 bg-amber-50' : 'bg-white' }}" data-maintenance="{{ $settings['on'] ? 'on' : 'off' }}">
        <h2 class="text-lg font-semibold">
            @if ($settings['on'])
                Maintenance mode is <span class="text-amber-800">on</span>
            @else
                Maintenance mode is <span class="text-green-800">off</span>: the site is open
            @endif
        </h2>
        @if ($settings['on'] && $settings['started_at'])
            <p class="mt-1 text-sm">On since {{ \Carbon\Carbon::parse($settings['started_at'])->format('j M Y, H:i') }}.</p>
        @endif
        <p class="mt-2 text-sm"><a href="{{ route('home', ['en', 'maintenance' => 'preview']) }}" target="_blank" rel="noopener" class="font-medium underline">Preview the maintenance page</a> (opens in a new tab)</p>
    </section>

    <form method="POST" action="{{ route('admin.maintenance.update') }}" class="mt-6 max-w-3xl space-y-4 rounded-lg bg-white p-5 shadow-sm"
          onsubmit="if (this.querySelector('[name=on]:checked').value === '1' && {{ $settings['on'] ? 'false' : 'true' }}) { return confirm('Switch maintenance mode on? Visitors will see the maintenance page until you switch it off.'); } return true;">
        @csrf @method('PUT')

        <fieldset>
            <legend class="mb-1 font-medium">Maintenance mode</legend>
            @foreach (['0' => 'Off: the site is open to everyone', '1' => 'On: visitors see the maintenance page'] as $value => $label)
                <div class="flex min-h-11 items-center gap-2">
                    <input id="on_{{ $value }}" name="on" type="radio" value="{{ $value }}" class="h-5 w-5" @checked((string) (int) old('on', $settings['on']) === $value)>
                    <label for="on_{{ $value }}">{{ $label }}</label>
                </div>
            @endforeach
        </fieldset>

        <x-field name="title" label="Heading" :value="old('title', $settings['title'] === \App\Support\Maintenance::DEFAULT_TITLE ? '' : $settings['title'])" :hint="'Leave empty for: '.\App\Support\Maintenance::DEFAULT_TITLE" maxlength="120" />

        <div>
            <label for="message" class="mb-1 block font-medium">Message</label>
            <textarea id="message" name="message" rows="5" maxlength="1500" class="block w-full rounded border border-slate-400 px-3 py-2" aria-describedby="message-hint" placeholder="{{ \App\Support\Maintenance::DEFAULT_MESSAGE }}">{{ old('message', $settings['message'] === \App\Support\Maintenance::DEFAULT_MESSAGE ? '' : $settings['message']) }}</textarea>
            <p id="message-hint" class="mt-1 text-sm text-slate-600">Leave empty for the friendly message shown greyed in the box. A blank line starts a new paragraph.</p>
            @error('message')<p class="mt-1 text-sm font-medium text-red-800">{{ $message }}</p>@enderror
        </div>

        <x-field name="until" label="Back by (optional)" type="datetime-local" :value="old('until', $settings['until']?->format('Y-m-d\TH:i'))" :hint="'Shown as “We expect to be back by …”, and tells search engines when to come back. It does not switch the site back on by itself.'" />

        <x-button>Save</x-button>
    </form>
@endsection
