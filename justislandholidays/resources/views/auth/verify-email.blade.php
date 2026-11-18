@extends('layouts.app', ['title' => 'Confirm your email'])

@section('content')
    <h1 class="mb-6 text-2xl font-semibold">Confirm your email</h1>
    <p class="mb-6">We have sent a link to your email address. Open it to confirm your account. Check your spam folder if you cannot see it.</p>

    @if (session('status') === 'verification-link-sent')
        <p role="status" class="mb-6 rounded border border-green-300 bg-green-50 p-4">A new link is on its way.</p>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <x-button>Send the link again</x-button>
    </form>
@endsection
