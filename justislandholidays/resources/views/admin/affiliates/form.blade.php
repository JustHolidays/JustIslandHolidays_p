@extends('layouts.admin', ['title' => 'Add an affiliate'])

@section('content')
    <p><a href="{{ route('admin.affiliates.index') }}" class="inline-flex min-h-11 items-center underline">Back to affiliates</a></p>
    <h1 class="text-2xl font-semibold">Add an affiliate</h1>
    <p class="mt-1">They receive an email invitation to set a password, then manage their listings and see their clicks in their own account.</p>

    <form method="POST" action="{{ route('admin.affiliates.store') }}" class="mt-6 max-w-2xl rounded-lg bg-white p-5 shadow-sm">
        @csrf
        @include('admin.affiliates.fields')
        <x-button>Create and send invitation</x-button>
    </form>
@endsection
