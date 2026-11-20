{{-- Enquiry form for owner and agent listings. Expects $listing. --}}
<section id="enquiry" class="rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="enquiry-heading">
    <h2 id="enquiry-heading" class="text-xl font-semibold">{{ $isAgent ? __('Contact the agent') : __('Contact the owner') }}</h2>
    <p class="mt-1 text-sm text-slate-700">{{ $isAgent ? __('Your message goes straight to the agent, who will reply by email.') : __('Your message goes straight to the owner, who will reply by email.') }}</p>

    @if (session('enquiry_sent'))
        <p role="status" class="mt-4 rounded border border-green-300 bg-green-50 p-4">{{ $isAgent ? __('Thank you. Your enquiry has been sent to the agent.') : __('Thank you. Your enquiry has been sent to the owner.') }} @if (session('enquiry_ref')) {{ __('Your reference is :ref.', ['ref' => session('enquiry_ref')]) }}@endif</p>
    @endif

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-300 bg-red-50 p-4">
            <p class="font-semibold">{{ __('Please check the form.') }}</p>
            <ul class="mt-2 list-disc pl-5 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('enquiries.store') }}" class="mt-4" novalidate>
        @csrf
        <input type="hidden" name="listing_ref" value="{{ $listing->public_ref }}">

        {{-- Honeypot: hidden from people and screen readers; bots tend to fill it in. --}}
        <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
            <label for="enq-website">Website</label>
            <input id="enq-website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>

        <x-field name="guest_name" :label="__('Your name')" autocomplete="name" required />
        <x-field name="guest_email" :label="__('Email')" type="email" autocomplete="email" required />
        <x-field name="guest_phone" :label="__('Phone (optional)')" type="tel" autocomplete="tel" />

        <div class="grid grid-cols-2 gap-3">
            <x-field name="check_in" :label="__('Arrive')" type="date" />
            <x-field name="check_out" :label="__('Leave')" type="date" />
            <x-field name="adults" :label="__('Adults')" type="number" min="1" max="40" inputmode="numeric" />
            <x-field name="children" :label="__('Children')" type="number" min="0" max="40" inputmode="numeric" />
        </div>

        <div class="mb-4">
            <label for="message" class="mb-1 block font-medium">{{ __('Message') }}</label>
            <textarea id="message" name="message" rows="5" maxlength="4000" required
                      @error('message') aria-invalid="true" @enderror
                      class="block w-full rounded border border-slate-400 bg-white px-3 py-2">{{ old('message') }}</textarea>
        </div>

        <div class="mb-4 flex items-start gap-2">
            <input id="consent" name="consent" type="checkbox" value="1" class="mt-1 h-5 w-5 shrink-0" @checked(old('consent')) required>
            <label for="consent" class="text-sm">
                {{ $isAgent ? __('I agree that my details are sent to the agent and kept by Just Island Holidays as described in the') : __('I agree that my details are sent to the owner and kept by Just Island Holidays as described in the') }}
                <a href="{{ route('pages.show', 'privacy') }}" class="underline">{{ __('privacy notice') }}</a>.
            </label>
        </div>

        <div class="mb-4">
            <altcha-widget challenge="{{ route('altcha.challenge') }}" name="altcha"></altcha-widget>
        </div>

        <button type="submit" class="min-h-11 w-full rounded bg-[var(--colour-button)] px-5 font-medium text-white">{{ __('Send enquiry') }}</button>
    </form>
</section>
