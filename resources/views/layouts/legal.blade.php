@php
    $appUrl = rtrim((string) config('app.url'), '/');
    $productName = $productName ?? 'Skoolyst Social';
    $businessName = $businessName ?? 'Skoolyst App';
    $contactEmail = $contactEmail ?? 'abdulkhalidmasood@gmail.com';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">

    <x-seo
        :title="($pageTitle ?? 'Legal') . ' — ' . $productName"
        :description="$metaDescription ?? $productName . ' legal information for schools and educational institutions.'"
    />

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        @media print {
            .legal-no-print { display: none !important; }
            body { background: #fff !important; }
            .legal-article { box-shadow: none !important; border: none !important; }
        }
    </style>
    @stack('head')
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 text-gray-900 antialiased">
    <a
        href="#main-content"
        class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-blue-600 focus:px-4 focus:py-2 focus:text-white"
    >
        Skip to main content
    </a>

    <header class="legal-no-print border-b border-gray-200/80 bg-white/90 backdrop-blur-sm sticky top-0 z-40">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-4">
            <a href="{{ $appUrl }}" class="flex items-center gap-3 min-w-0 group" aria-label="{{ $productName }} home">
                <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white font-bold text-sm shadow-sm"
                    aria-hidden="true"
                >S</span>
                <span class="min-w-0">
                    <span class="block font-semibold text-gray-900 text-sm tracking-tight truncate group-hover:text-blue-700 transition-colors">
                        {{ $productName }}
                    </span>
                    <span class="block text-xs text-gray-500 truncate">{{ $businessName }}</span>
                </span>
            </a>
            <a
                href="{{ $appUrl }}"
                class="shrink-0 text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline"
            >
                Back to app
            </a>
        </div>
    </header>

    <main id="main-content" class="max-w-3xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
        <article class="legal-article rounded-2xl border border-gray-200 bg-white p-6 sm:p-10 shadow-sm">
            @isset($lastUpdated)
                <p class="text-sm text-gray-500 mb-6 pb-6 border-b border-gray-100">
                    <time datetime="2026-05-15">{{ $lastUpdated }}</time>
                    <span class="mx-2 text-gray-300" aria-hidden="true">·</span>
                    Last updated
                </p>
            @endisset

            @yield('content')
        </article>

        <nav
            class="legal-no-print mt-8 flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm text-gray-600"
            aria-label="Legal pages"
        >
            <a href="{{ route('legal.privacy') }}" class="hover:text-blue-600 hover:underline">Privacy Policy</a>
            <a href="{{ route('legal.terms') }}" class="hover:text-blue-600 hover:underline">Terms of Service</a>
            <a href="{{ route('data-deletion') }}" class="hover:text-blue-600 hover:underline">Data Deletion</a>
        </nav>
    </main>

    <footer class="legal-no-print border-t border-gray-200/80 bg-white/60 py-8 text-center text-xs text-gray-500">
        <p>&copy; {{ date('Y') }} {{ $businessName }}. All rights reserved.</p>
        <p class="mt-2">
            Questions?
            <a href="mailto:{{ $contactEmail }}" class="text-blue-600 hover:underline">{{ $contactEmail }}</a>
        </p>
    </footer>

    @stack('scripts')
</body>
</html>
