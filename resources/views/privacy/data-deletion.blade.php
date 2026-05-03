@extends('layouts.guest', [
    'title' => 'User data deletion',
    'description' => 'How Skoolyst handles Facebook and Instagram data deletion.',
])

@section('content')
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 py-12 px-4">
        <div class="max-w-2xl mx-auto">
            <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold text-sm">
                        S
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">Skoolyst Social AI</h1>
                        <p class="text-sm text-gray-500">Facebook / Instagram data deletion</p>
                    </div>
                </div>

                @if ($code)
                    <div
                        class="mb-8 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
                        role="status"
                    >
                        <p class="font-medium">Deletion request completed</p>
                        <p class="mt-1 text-emerald-800">
                            Confirmation code:
                            <code class="rounded bg-white/80 px-2 py-0.5 font-mono text-xs border border-emerald-200">{{ e($code) }}</code>
                        </p>
                        <p class="mt-2 text-xs text-emerald-800/90">
                            If you started this from Facebook, you can share this code with support if anything still looks connected. Token and page data we held for publishing have been removed from Skoolyst.
                        </p>
                    </div>
                @endif

                <div class="prose prose-sm prose-gray max-w-none">
                    <h2 class="text-base font-semibold text-gray-900 mt-0">What we delete</h2>
                    <p class="text-gray-600 leading-relaxed">
                        When you trigger a deletion (from Facebook’s settings or from your Skoolyst account), we remove your stored Facebook user id, Facebook access tokens, API tokens issued for your Skoolyst user, and workspace-linked Facebook and Instagram accounts that were tied to that Meta user.
                    </p>

                    <h2 class="text-base font-semibold text-gray-900">Request deletion via Facebook</h2>
                    <p class="text-gray-600 leading-relaxed">
                        In your Facebook account settings, use the option to manage apps and remove data for this application. Facebook may send an automated request to our endpoint; we process it and return a confirmation you can view on this page.
                    </p>

                    <h2 class="text-base font-semibold text-gray-900">Delete from Skoolyst while logged in</h2>
                    <p class="text-gray-600 leading-relaxed">
                        Open <strong>Settings → Integrations</strong> and use <strong>Remove Facebook-connected data</strong>. Your Skoolyst account remains; only Facebook-sourced connection data is cleared.
                    </p>

                    <h2 class="text-base font-semibold text-gray-900">Meta callback URL (app admins)</h2>
                    <p class="text-gray-600 leading-relaxed">
                        In the Meta Developer Dashboard, set the user data deletion callback to:
                    </p>
                    <p class="mt-2">
                        <code class="block break-all rounded-lg bg-gray-100 px-3 py-2 text-xs text-gray-800 border border-gray-200">{{ e($metaCallbackUrl) }}</code>
                    </p>

                    <h2 class="text-base font-semibold text-gray-900">Contact</h2>
                    <p class="text-gray-600 leading-relaxed">
                        For questions about data we hold or to request full account deletion, contact your school or organization administrator or Skoolyst support using the channel they provide.
                    </p>
                </div>

                <p class="mt-8 text-center text-xs text-gray-400">
                    <a href="{{ url('/') }}" class="text-blue-600 hover:underline">Back to app</a>
                </p>
            </div>
        </div>
    </div>
@endsection
