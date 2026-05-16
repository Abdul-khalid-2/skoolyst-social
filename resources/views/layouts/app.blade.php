<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <x-seo :title="$title ?? null" :description="$description ?? null" />

    {{-- Appearance: apply saved preferences before render to prevent FOUC --}}
    <script>
    (function () {
        try {
            var prefs = JSON.parse(localStorage.getItem('skoolyst_appearance') || '{}');
            var theme = prefs.theme || 'light';
            if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            if (prefs.fontSize === 'large') document.documentElement.classList.add('text-base-large');
            if (prefs.density === 'compact') document.documentElement.classList.add('density-compact');
            if (prefs.accent) document.documentElement.setAttribute('data-accent', prefs.accent);
        } catch (e) {}
    })();
    </script>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none !important;}</style>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/focus@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @stack('head')
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased">
    <div x-data="{
        collapsed: (function() {
            try { return JSON.parse(localStorage.getItem('skoolyst_appearance') || '{}').sidebar === 'compact'; } catch { return false; }
        })()
    }" class="min-h-screen flex bg-gray-50">
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
    <script>
    function appearanceSettings() {
        const KEY = 'skoolyst_appearance';
        function load() { try { return JSON.parse(localStorage.getItem(KEY) || '{}'); } catch { return {}; } }
        function save(p) { try { localStorage.setItem(KEY, JSON.stringify(p)); } catch {} }

        function applyTheme(theme) {
            const isDark = theme === 'dark' ||
                (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', isDark);
        }
        function applyAccent(accent) {
            document.documentElement.setAttribute('data-accent', accent);
        }
        function applyFontSize(size) {
            document.documentElement.classList.toggle('text-base-large', size === 'large');
        }
        function applyDensity(density) {
            document.documentElement.classList.toggle('density-compact', density === 'compact');
        }

        return {
            theme: 'light', accent: 'blue', sidebar: 'expanded', fontSize: 'normal', density: 'comfortable',

            init() {
                const p = load();
                this.theme    = p.theme    || 'light';
                this.accent   = p.accent   || 'blue';
                this.sidebar  = p.sidebar  || 'expanded';
                this.fontSize = p.fontSize || 'normal';
                this.density  = p.density  || 'comfortable';
                applyTheme(this.theme);
                applyAccent(this.accent);
                applyFontSize(this.fontSize);
                applyDensity(this.density);
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                    if (this.theme === 'system') applyTheme('system');
                });
            },

            setTheme(val)    { this.theme    = val; applyTheme(val);    const p = load(); p.theme    = val; save(p); },
            setAccent(val)   { this.accent   = val; applyAccent(val);   const p = load(); p.accent   = val; save(p); },
            setFontSize(val) { this.fontSize = val; applyFontSize(val); const p = load(); p.fontSize = val; save(p); },
            setDensity(val)  { this.density  = val; applyDensity(val);  const p = load(); p.density  = val; save(p); },

            setSidebar(val) {
                this.sidebar = val;
                const p = load(); p.sidebar = val; save(p);
                // Sync the root layout's collapsed state
                const root = document.querySelector('[x-data*="collapsed"]');
                if (root && root._x_dataStack) {
                    const data = root._x_dataStack[0];
                    if (data && 'collapsed' in data) data.collapsed = (val === 'compact');
                }
            },

            resetAll() {
                try { localStorage.removeItem(KEY); } catch {}
                this.theme = 'light'; this.accent = 'blue';
                this.sidebar = 'expanded'; this.fontSize = 'normal'; this.density = 'comfortable';
                applyTheme('light'); applyAccent('blue'); applyFontSize('normal'); applyDensity('comfortable');
                const root = document.querySelector('[x-data*="collapsed"]');
                if (root && root._x_dataStack) {
                    const data = root._x_dataStack[0];
                    if (data && 'collapsed' in data) data.collapsed = false;
                }
            },
        };
    }
    </script>
</body>
</html>
