@extends('layouts.app', [
    'title'       => $title,
    'description' => $description,
])

@section('content')
<div class="p-6 min-h-full">

    <div class="max-w-2xl">

        @if (session('success'))
            <div class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        @if ($workspaces->isEmpty())
            <div class="bg-white border border-gray-200 rounded-xl min-h-[300px] flex items-center justify-center">
                <div class="text-center px-4">
                    <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <p class="text-sm text-gray-500">No workspaces found.</p>
                </div>
            </div>
        @else
            <div class="bg-white border border-gray-200 rounded-xl divide-y divide-gray-100 overflow-hidden">
                @foreach ($workspaces as $ws)
                    @php
                        $roleColors = [
                            'owner'  => ['bg' => 'bg-purple-50', 'text' => 'text-purple-700'],
                            'admin'  => ['bg' => 'bg-blue-50',   'text' => 'text-blue-700'],
                            'editor' => ['bg' => 'bg-gray-100',  'text' => 'text-gray-600'],
                            'viewer' => ['bg' => 'bg-gray-100',  'text' => 'text-gray-500'],
                        ];
                        $rc = $roleColors[$ws['role']] ?? $roleColors['viewer'];
                    @endphp
                    <div class="flex items-center gap-4 px-5 py-4 {{ $ws['is_current'] ? 'bg-blue-50/40' : 'hover:bg-gray-50' }} transition-colors">

                        {{-- Logo / Initials --}}
                        <div class="shrink-0 w-11 h-11 rounded-xl overflow-hidden border border-gray-200 flex items-center justify-center bg-gradient-to-br from-blue-500 to-cyan-400">
                            @if ($ws['logo'])
                                <img src="{{ $ws['logo'] }}" alt="{{ $ws['name'] }}" class="w-full h-full object-cover">
                            @else
                                <span class="text-white font-bold text-sm">{{ strtoupper(substr($ws['name'], 0, 2)) }}</span>
                            @endif
                        </div>

                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $ws['name'] }}</p>
                                @if ($ws['is_current'])
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 bg-blue-100 px-2 py-0.5 rounded-full">
                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                        Active
                                    </span>
                                @endif
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $rc['bg'] }} {{ $rc['text'] }}">
                                    {{ ucfirst($ws['role']) }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $ws['posts_count'] }} post{{ $ws['posts_count'] === 1 ? '' : 's' }}
                                &middot;
                                {{ $ws['accounts'] }} connected account{{ $ws['accounts'] === 1 ? '' : 's' }}
                                @if ($ws['plan'])
                                    &middot; {{ ucfirst($ws['plan']) }} plan
                                @endif
                            </p>
                        </div>

                        {{-- Switch button --}}
                        @if ($ws['is_current'])
                            <span class="shrink-0 text-xs text-blue-500 font-medium px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 cursor-default">
                                Current
                            </span>
                        @else
                            <form method="POST" action="{{ route('workspace.switch.set', $ws['id']) }}" class="shrink-0">
                                @csrf
                                <button
                                    type="submit"
                                    class="text-xs font-medium text-gray-700 px-3 py-1.5 rounded-lg border border-gray-200 hover:border-blue-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                                >
                                    Switch
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>

            <p class="text-xs text-gray-400 mt-3 text-center">
                {{ $workspaces->count() }} workspace{{ $workspaces->count() === 1 ? '' : 's' }} available
            </p>
        @endif

    </div>
</div>
@endsection
