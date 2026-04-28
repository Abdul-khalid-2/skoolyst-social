<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <x-seo :title="$title ?? null" :description="$description ?? null" />

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none !important;}</style>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/focus@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @stack('head')
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased">
    <div x-data="{ collapsed: false }" class="min-h-screen flex bg-gray-50">
        <x-sidebar />

        <div class="flex-1 min-w-0 flex flex-col">
            <x-topbar
                :title="$title ?? 'Dashboard'"
                :subtitle="$subtitle ?? null"
                :action-label="$actionLabel ?? null"
                :action-href="$actionHref ?? null"
            />

            <main class="flex-1 min-w-0 p-6 overflow-y-auto">
                @yield('content')
            </main>
        </div>

        <x-toast />
    </div>
    <script>if (location.hash === '#_=_') { history.replaceState(null, document.title, location.pathname + location.search); }</script>
    @stack('scripts')
</body>
</html>
