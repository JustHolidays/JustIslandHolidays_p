@extends('layouts.admin', ['title' => 'Content pages'])

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Content pages</h1>
        <a href="{{ route('admin.pages.create') }}" class="inline-flex min-h-11 items-center rounded bg-slate-900 px-4 font-medium text-white">Add a page</a>
    </div>

    <form method="GET" class="mt-4 flex items-end gap-3 rounded-lg bg-white p-4 shadow-sm" aria-label="Search pages">
        <div><label for="q" class="block text-sm font-medium">Title or web address</label><input id="q" name="q" type="search" value="{{ request('q') }}" class="min-h-11 rounded border border-slate-400 px-2"></div>
        <button class="min-h-11 rounded bg-slate-900 px-4 font-medium text-white">Search</button>
    </form>

    <div class="mt-4 overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Content pages</caption>
            <thead class="border-b border-slate-300"><tr><th scope="col" class="p-2">Title</th><x-admin.sort key="slug" label="Web address" /><th scope="col" class="p-2">Island</th><th scope="col" class="p-2">Kind</th><th scope="col" class="p-2">Published</th><th scope="col" class="p-2">Languages</th><x-admin.sort key="updated" label="Updated" default="updated" /></tr></thead>
            <tbody>
                @forelse ($pages as $page)
                    <tr class="border-b border-slate-200">
                        <td class="p-2">
                            @can('update', $page)
                                <a href="{{ route('admin.pages.edit', $page) }}" class="font-medium underline">{{ $page->translations->firstWhere('lang', 'en')?->title ?? $page->slug }}</a>
                            @else
                                {{ $page->translations->firstWhere('lang', 'en')?->title ?? $page->slug }}
                            @endcan
                        </td>
                        <td class="p-2">/{{ $page->slug }}</td>
                        <td class="p-2">{{ $page->island?->translate('name', 'en') ?? 'Site-wide' }}</td>
                        <td class="p-2">{{ $page->kind === 'guide' ? 'Guide' : 'Static' }}</td>
                        <td class="p-2">{{ $page->is_published ? 'Yes' : 'No' }}</td>
                        <td class="p-2">{{ $page->translations->pluck('lang')->map(fn ($l) => strtoupper($l))->implode(', ') }}</td>
                        <td class="p-2">{{ $page->updated_at->format('j M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-4">No pages yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $pages->links() }}</div>
@endsection
