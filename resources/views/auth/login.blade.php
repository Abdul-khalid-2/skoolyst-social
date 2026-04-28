@extends('layouts.guest', ['title' => $title ?? 'Sign in', 'description' => $description ?? 'Sign in to your account.'])

@php
    $baseInputClass = 'w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent';
@endphp

@section('content')
    <div class="min-h-screen bg-white lg:flex" x-data="{ showPassword: false, loading: false }">
        <section class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-blue-600 to-purple-700 text-white p-12 flex-col" aria-hidden="true">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-white/20 border border-white/30 flex items-center justify-center">
                    <svg class="text-white" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <p class="font-bold">Skoolyst Social AI</p>
            </div>

            <div class="flex-1 flex flex-col justify-center">
                <h1 class="text-4xl font-bold leading-tight max-w-md">Automate your social presence.</h1>
                <p class="mt-4 text-blue-200 max-w-md">
                    Publish once from Skoolyst and share your school updates across every connected social account.
                </p>
            </div>

            <div class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    @foreach (['Facebook', 'Instagram', 'LinkedIn', 'Twitter'] as $platform)
                        <span class="px-3 py-1.5 rounded-full text-xs bg-white/10 text-white">{{ $platform }}</span>
                    @endforeach
                </div>
                <p class="text-xs text-blue-300">Copyright 2026 Skoolyst. All rights reserved.</p>
            </div>
        </section>

        <section class="w-full lg:w-1/2 bg-white flex items-center justify-center min-h-screen">
            <div class="max-w-sm w-full px-4">
                <h2 class="text-2xl font-bold text-gray-900">Welcome back</h2>
                <p class="text-sm text-gray-500 mb-8 mt-1">Sign in to your account to continue</p>

                <form
                    class="space-y-4"
                    method="post"
                    action="{{ url('/login') }}"
                    x-on:submit="loading = true"
                >
                    @csrf
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-800 mb-1.5">Email address</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autocomplete="email"
                            class="{{ $baseInputClass }} @error('email') border-red-400 bg-red-50 @enderror"
                            placeholder="you@example.com"
                        >
                        @error('email')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-800 mb-1.5">Password</label>
                        <div class="relative">
                            <input
                                id="password"
                                name="password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                class="{{ $baseInputClass }} pr-11 @error('password') border-red-400 bg-red-50 @enderror"
                                placeholder="Enter your password"
                            >
                            <button
                                type="button"
                                x-on:click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                                :aria-pressed="showPassword.toString()"
                                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                            >
                                <span x-show="!showPassword" class="block">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M1 12s4-6 11-6 11 6 11 6-4 6-11 6S1 12 1 12z" stroke="currentColor" stroke-width="2" />
                                        <circle cx="12" cy="12" r="2.5" fill="currentColor" />
                                    </svg>
                                </span>
                                <span x-cloak x-show="showPassword" class="block">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M3 3l18 18" stroke="currentColor" stroke-width="2" />
                                        <path d="M9.5 9.5L4 4M14.1 4.1L20 8M2 8l1.1 1.1M1 12h3M21 3l-3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                    </svg>
                                </span>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end pt-1">
                        <button
                            type="button"
                            class="text-sm text-blue-600 hover:text-blue-700"
                            x-on:click="window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'info', message: 'Password reset is not configured yet. Contact your administrator.' } }))"
                        >
                            Forgot password?
                        </button>
                    </div>

                    <button
                        type="submit"
                        :disabled="loading"
                        class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold py-3 rounded-xl disabled:opacity-70 flex items-center justify-center gap-2"
                    >
                        <span x-show="!loading" class="inline">Sign in</span>
                        <span x-cloak x-show="loading" class="inline flex items-center gap-2">
                            <svg class="animate-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M12 2v4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            </svg>
                            Signing in...
                        </span>
                    </button>

                    <div class="flex items-center gap-3 my-2">
                        <div class="h-px flex-1 bg-gray-200"></div>
                        <span class="text-xs text-gray-400">or</span>
                        <div class="h-px flex-1 bg-gray-200"></div>
                    </div>
                    <button
                        type="button"
                        :disabled="loading"
                        x-on:click="window.location.assign('{{ url('/api/auth/facebook/redirect') }}')"
                        class="w-full border border-gray-200 bg-white text-gray-800 font-medium py-3 rounded-xl hover:bg-gray-50 flex items-center justify-center gap-2 disabled:opacity-70"
                    >
                        <span class="text-[#1877F2] font-bold text-lg leading-none" aria-hidden="true">f</span>
                        Continue with Facebook
                    </button>
                </form>

                <p class="text-sm text-gray-500 text-center mt-6">
                    Don't have an account?
                    <a href="{{ url('/register') }}" class="text-blue-600 hover:text-blue-700 font-medium">Sign up free</a>
                </p>
            </div>
        </section>
    </div>
@endsection
