@extends('layouts.livewire')

@section('content')
<div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
    <div class="text-center sm:text-left space-y-1">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
            API Query Builder
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Powered by Spatie Laravel Query Builder: filter, sort, include relationships, sparse fieldsets, and pagination.
        </p>
    </div>

    <!-- Livewire Query Explorer -->
    <livewire:query-builder-explorer />
</div>
@endsection
