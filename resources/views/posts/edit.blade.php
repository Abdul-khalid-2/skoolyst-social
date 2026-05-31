@extends('layouts.app', [
    'title' => 'Edit Post',
    'description' => 'Edit your scheduled or draft post.',
    'subtitle' => null,
])

@php
    $existingMediaUrl  = $existingMedia?->url;
    $existingMediaType = $existingMedia?->type;
    $isExistingVideo   = $existingMediaType === 'video';

    $alpine = [
        'updateUrl'                => $workspace ? url("/api/workspaces/{$workspace->id}/posts/{$post->id}") : '',
        'connectedSlugs'           => $connectedSlugs->all(),
        'accountsByPlatform'       => $accountsByPlatform ?? [],
        'existingTargetAccountIds' => $existingTargetAccountIds ?? [],
        'existingPlatformSlugs'    => $existingPlatformSlugs ?? [],
        'existingCaption'          => $post->caption ?? '',
        'existingScheduledAt'      => $post->scheduled_at ? $post->scheduled_at->format('Y-m-d\TH:i') : '',
        'existingMediaUrl'         => $existingMediaUrl,
        'existingMediaIsVideo'     => $isExistingVideo,
        'postStatus'               => $post->status,
        'workspaceName'            => $workspace?->name ?? '',
    ];

    $platforms = [
        (object) ['id' => 'facebook',  'name' => 'Facebook',    'letter' => 'f',  'gradientClass' => 'bg-gradient-to-br from-blue-600 to-blue-700',    'borderColor' => 'border-blue-500',   'textColor' => 'text-blue-600',   'pillColor' => 'bg-blue-600'],
        (object) ['id' => 'instagram', 'name' => 'Instagram',   'letter' => 'Ig', 'gradientClass' => 'bg-gradient-to-br from-purple-500 to-pink-500',  'borderColor' => 'border-pink-400',   'textColor' => 'text-pink-600',   'pillColor' => 'bg-pink-500'],
        (object) ['id' => 'linkedin',  'name' => 'LinkedIn',    'letter' => 'in', 'gradientClass' => 'bg-gradient-to-br from-indigo-600 to-indigo-700', 'borderColor' => 'border-indigo-500', 'textColor' => 'text-indigo-600', 'pillColor' => 'bg-indigo-600'],
        (object) ['id' => 'twitter',   'name' => 'X (Twitter)', 'letter' => 'X',  'gradientClass' => 'bg-gradient-to-br from-gray-800 to-gray-900',    'borderColor' => 'border-gray-700',   'textColor' => 'text-gray-900',   'pillColor' => 'bg-gray-800'],
    ];
@endphp

@push('head')
<script>
function editPostForm(config, platformList) {
    return {
        config,
        platformList,

        caption: config.existingCaption || '',
        errors: { caption: null, targeting: null, schedule: null, media: null, server: null },

        // mediaState: 'existing' | 'removed' | 'replaced' | 'none'
        mediaState: config.existingMediaUrl ? 'existing' : 'none',
        existingMediaUrl: config.existingMediaUrl || '',
        existingMediaIsVideo: config.existingMediaIsVideo || false,
        newMediaFile: null,
        newMediaPreview: '',
        newMediaName: '',
        isDragging: false,

        connectedList: [...(config.connectedSlugs || [])],

        // Per-account/per-page targeting state. Restored from existing post_targets so the
        // user sees exactly what their post will hit when the scheduler fires.
        accountsByPlatform: {},
        selectedAccountIds: [],

        // Modal state.
        modalOpen: false,
        modalPlatform: '',
        modalDraftIds: [],

        scheduleMode: config.existingScheduledAt ? 'later' : 'now',
        scheduledAt: config.existingScheduledAt || '',
        minDateTime: '',
        submitting: false,

        get charCount() { return (this.caption || '').length; },
        get percentage() { return Math.min((this.charCount / 2200) * 100, 100); },
        get tone() {
            const p = this.percentage;
            if (p >= 90) return { text: 'text-red-600', bar: 'bg-red-500' };
            if (p >= 70) return { text: 'text-amber-600', bar: 'bg-amber-500' };
            return { text: 'text-gray-500', bar: 'bg-blue-500' };
        },
        get isNewVideo() {
            return this.newMediaFile && this.newMediaFile.type && this.newMediaFile.type.startsWith('video/');
        },
        get twitterSelected() {
            return this.accountsFor('twitter').some((a) => this.selectedAccountIds.includes(a.id));
        },
        get hasAnyTarget() { return this.selectedAccountIds.length > 0; },
        get selectedPlatformSlugs() {
            const slugs = new Set();
            for (const slug of Object.keys(this.accountsByPlatform || {})) {
                if ((this.accountsByPlatform[slug] || []).some((a) => this.selectedAccountIds.includes(a.id))) {
                    slugs.add(slug);
                }
            }
            return [...slugs];
        },

        init() {
            this.minDateTime = new Date().toISOString().slice(0, 16);
            this.accountsByPlatform = config.accountsByPlatform || {};

            // Restore the saved targeting from existing post_targets. Filter out any
            // ids that no longer correspond to a connected account (edge case: an account
            // was removed/disconnected after the post was scheduled).
            const validIds = new Set();
            for (const slug of Object.keys(this.accountsByPlatform)) {
                for (const a of this.accountsByPlatform[slug] || []) {
                    validIds.add(a.id);
                }
            }
            const restored = (config.existingTargetAccountIds || [])
                .map((id) => parseInt(id, 10))
                .filter((id) => validIds.has(id));

            // If the post had no targets yet (e.g. draft created before this feature),
            // fall back to selecting every connected+active account.
            if (restored.length === 0 && (config.existingTargetAccountIds || []).length === 0) {
                const defaults = [];
                for (const slug of Object.keys(this.accountsByPlatform)) {
                    for (const a of this.accountsByPlatform[slug] || []) {
                        if (a.is_active) { defaults.push(a.id); }
                    }
                }
                this.selectedAccountIds = defaults;
            } else {
                this.selectedAccountIds = restored;
            }
        },

        // ---------- Targeting helpers (mirrors create.blade.php) ----------

        accountsFor(slug) { return this.accountsByPlatform[slug] || []; },
        connectedCountFor(slug) {
            return this.accountsFor(slug).filter((a) => a.is_active).length;
        },
        activeCountFor(slug) {
            return this.accountsFor(slug)
                .filter((a) => a.is_active && this.selectedAccountIds.includes(a.id))
                .length;
        },
        hasMultipleAccounts(slug) { return this.connectedCountFor(slug) > 1; },

        chipState(slug) {
            const connected = this.connectedCountFor(slug);
            if (connected === 0) { return 'disabled'; }
            const active = this.activeCountFor(slug);
            if (active === connected) { return 'all'; }
            if (active === 0) { return 'none'; }
            return 'partial';
        },
        chipBadgeText(slug) {
            const connected = this.connectedCountFor(slug);
            if (connected === 0) { return 'Not connected'; }
            if (this.hasMultipleAccounts(slug)) {
                return this.activeCountFor(slug) + '/' + connected + ' pages';
            }
            return this.chipState(slug) === 'all' ? 'Active' : 'Inactive';
        },
        chipClass(slug) {
            const state = this.chipState(slug);
            if (state === 'disabled') {
                return 'border-gray-200 bg-gray-50 text-gray-400 opacity-75 cursor-not-allowed';
            }
            if (state === 'all') {
                return 'border-blue-500 bg-blue-50 text-blue-700 hover:bg-blue-100';
            }
            if (state === 'partial') {
                return 'border-amber-400 bg-amber-50 text-amber-800 hover:bg-amber-100';
            }
            return 'border-gray-300 bg-gray-100 text-gray-500 line-through hover:bg-gray-200';
        },

        // ---------- Modal ----------

        openModal(slug) {
            if (this.chipState(slug) === 'disabled') {
                window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'info', message: 'Connect this platform in Accounts first.' } }));
                return;
            }
            this.modalPlatform = slug;
            this.modalDraftIds = this.accountsFor(slug)
                .filter((a) => this.selectedAccountIds.includes(a.id))
                .map((a) => a.id);
            this.modalOpen = true;
        },
        closeModal() {
            this.modalOpen = false;
            this.modalPlatform = '';
            this.modalDraftIds = [];
        },
        applyModal() {
            if (! this.modalPlatform) { this.closeModal(); return; }
            const slug = this.modalPlatform;
            const platformIds = this.accountsFor(slug).map((a) => a.id);
            const others = this.selectedAccountIds.filter((id) => ! platformIds.includes(id));
            this.selectedAccountIds = [...others, ...this.modalDraftIds];
            this.errors.targeting = null;
            this.closeModal();
        },
        toggleModalAccount(id, account) {
            if (account && account.is_active === false) {
                window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'info', message: 'This account is paused. Enable it in Accounts to publish.' } }));
                return;
            }
            if (this.modalDraftIds.includes(id)) {
                this.modalDraftIds = this.modalDraftIds.filter((x) => x !== id);
            } else {
                this.modalDraftIds = [...this.modalDraftIds, id];
            }
        },
        isModalAccountOn(id) { return this.modalDraftIds.includes(id); },
        modalMasterState() {
            if (! this.modalPlatform) { return 'off'; }
            const usable = this.accountsFor(this.modalPlatform).filter((a) => a.is_active);
            if (usable.length === 0) { return 'off'; }
            const on = usable.filter((a) => this.modalDraftIds.includes(a.id)).length;
            if (on === 0) { return 'off'; }
            if (on === usable.length) { return 'on'; }
            return 'indeterminate';
        },
        toggleModalMaster() {
            if (! this.modalPlatform) { return; }
            const usable = this.accountsFor(this.modalPlatform).filter((a) => a.is_active);
            const usableIds = usable.map((a) => a.id);
            const state = this.modalMasterState();
            if (state === 'on') {
                this.modalDraftIds = this.modalDraftIds.filter((id) => ! usableIds.includes(id));
            } else {
                const merged = new Set([...this.modalDraftIds, ...usableIds]);
                this.modalDraftIds = [...merged];
            }
        },
        modalPlatformLabel() {
            const p = this.platformList.find((pl) => pl.id === this.modalPlatform);
            return p ? p.name : '';
        },
        modalPlatformPillColor() {
            const p = this.platformList.find((pl) => pl.id === this.modalPlatform);
            return p ? p.pillColor : 'bg-gray-500';
        },

        // ---------- Media (unchanged) ----------

        removeExistingMedia() {
            this.mediaState = 'removed';
            this.existingMediaUrl = '';
        },
        onFileChange(e) {
            const file = e.target.files && e.target.files[0];
            if (file) this.setNewFile(file);
        },
        onDrop(e) {
            this.isDragging = false;
            const file = e.dataTransfer.files && e.dataTransfer.files[0];
            if (file) this.setNewFile(file);
        },
        setNewFile(file) {
            const name = (file.name || '').toLowerCase();
            const ok = /\.(jpe?g|png|gif|webp|mp4)$/.test(name)
                || (file.type && /^image\/(jpeg|png|gif|webp)$/.test(file.type))
                || (file.type && /^video\/mp4$/.test(file.type));
            if (!ok) { this.errors.media = 'Use JPEG, PNG, GIF, WebP, or MP4 (max 50MB).'; return; }
            if (file.size > 50 * 1024 * 1024) { this.errors.media = 'File must be 50MB or smaller.'; return; }
            this.errors.media = null;
            this.newMediaFile = file;
            this.newMediaName = file.name;
            this.mediaState = 'replaced';
            const reader = new FileReader();
            reader.onload = () => { this.newMediaPreview = String(reader.result || ''); };
            reader.readAsDataURL(file);
        },
        clearNewMedia() {
            this.newMediaFile = null;
            this.newMediaPreview = '';
            this.newMediaName = '';
            this.mediaState = config.existingMediaUrl ? 'existing' : 'none';
            this.existingMediaUrl = config.existingMediaUrl || '';
            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },

        // ---------- Validation & save ----------

        validate(action) {
            let ok = true;
            this.errors.caption = null;
            this.errors.schedule = null;
            this.errors.targeting = null;

            if (!(this.caption || '').trim()) {
                this.errors.caption = 'Caption is required.'; ok = false;
            } else if (this.twitterSelected && this.charCount > 280) {
                this.errors.caption = 'X (Twitter) is selected: caption may not exceed 280 characters.'; ok = false;
            } else if (this.charCount > 2200) {
                this.errors.caption = 'Caption is too long (max 2200 characters).'; ok = false;
            }
            if (action === 'reschedule') {
                if (!this.scheduledAt) {
                    this.errors.schedule = 'Please choose a date and time.'; ok = false;
                } else if (new Date(this.scheduledAt).getTime() <= Date.now()) {
                    this.errors.schedule = 'Scheduled time must be in the future.'; ok = false;
                }
            }
            if (action === 'reschedule' || action === 'publish_now') {
                if (this.selectedAccountIds.length === 0) {
                    this.errors.targeting = 'Please select at least one account or page to post to.'; ok = false;
                }
            }
            return ok;
        },

        get csrf() {
            return document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
        },

        async save(action) {
            this.errors = { caption: null, targeting: null, schedule: null, media: null, server: null };
            if (!this.validate(action)) return;

            this.submitting = true;
            const form = new FormData();
            form.append('_method', 'PATCH');
            form.append('caption', this.caption);
            form.append('action', action);

            // Always sync targeting on save so edits made in the modal are persisted.
            (this.selectedAccountIds || []).forEach((id) => {
                form.append('social_account_ids[]', String(id));
            });
            // Send an empty marker when nothing is selected so the backend sees the field
            // and clears the targeting (Laravel drops absent arrays).
            if ((this.selectedAccountIds || []).length === 0) {
                form.append('social_account_ids', '');
            }

            if (action === 'reschedule' && this.scheduledAt) {
                form.append('scheduled_at', this.scheduledAt);
            }

            if (this.mediaState === 'removed') {
                form.append('remove_media', '1');
            } else if (this.mediaState === 'replaced' && this.newMediaFile) {
                form.append('remove_media', '1');
                form.append('media', this.newMediaFile, this.newMediaName || this.newMediaFile.name);
            }

            try {
                const res = await fetch(config.updateUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: form,
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));

                if (!res.ok) {
                    if (res.status === 422 && data.errors) {
                        this.errors.caption  = data.errors.caption?.[0] || null;
                        this.errors.schedule = data.errors.scheduled_at?.[0] || null;
                        const targetingErr = data.errors.social_account_ids;
                        if (targetingErr) {
                            this.errors.targeting = Array.isArray(targetingErr) ? targetingErr[0] : targetingErr;
                        }
                        this.errors.media    = data.errors.media?.[0] || null;
                        this.errors.server   = data.message || null;
                    } else {
                        this.errors.server = data.message || 'Update failed.';
                    }
                    window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: this.errors.server || this.errors.targeting || 'Update failed.' } }));
                    return;
                }

                const msg = action === 'draft' ? 'Draft saved.'
                    : action === 'reschedule' ? 'Post rescheduled successfully.'
                    : 'Post queued for publishing.';

                window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: msg } }));
                window.location.assign(@json(route('posts.index')));

            } catch (e) {
                this.errors.server = 'Request failed. Please try again.';
                window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: this.errors.server } }));
            } finally {
                this.submitting = false;
            }
        },
    };
}
</script>
@endpush

@section('content')
<div
    class="bg-gray-50 min-h-full -m-6 p-6"
    x-data="editPostForm(@js($alpine), @js($platforms))"
    x-init="init()"
>
    <div class="max-w-4xl mx-auto space-y-5">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-lg font-bold text-gray-900">{{ __('Edit Post') }}</h1>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ __('Workspace:') }} <span class="font-medium text-gray-700">{{ e($workspace->name) }}</span>
                    &nbsp;·&nbsp;
                    {{ __('Status:') }} <span class="font-medium text-gray-700 capitalize">{{ $post->status }}</span>
                </p>
            </div>
            <a href="{{ route('posts.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                ← {{ __('Back to posts') }}
            </a>
        </div>

        {{-- Server error --}}
        <p class="text-sm text-red-600 bg-red-50 rounded-lg px-4 py-2" x-show="errors.server" x-text="errors.server"></p>

        {{-- Caption --}}
        <div class="bg-white rounded-xl border p-5 shadow-sm">
            <label for="ep-caption" class="text-sm font-semibold text-gray-900">{{ __('Caption') }}</label>
            <div class="mt-3 relative">
                <textarea
                    id="ep-caption"
                    rows="6"
                    maxlength="2200"
                    x-model="caption"
                    @input="errors.caption = null"
                    class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                    placeholder="{{ __('Write your post caption...') }}"
                ></textarea>
                <span class="absolute bottom-3 right-3 text-xs" :class="tone.text" x-text="charCount + '/2200'"></span>
            </div>
            <div class="mt-2 h-1 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full transition-all" :class="tone.bar" :style="'width:' + percentage + '%'"></div>
            </div>
            <p class="mt-1 text-xs text-red-600" x-show="errors.caption" x-text="errors.caption"></p>
        </div>

        {{-- Media --}}
        <div class="bg-white rounded-xl border p-5 shadow-sm">
            <label class="text-sm font-semibold text-gray-900">{{ __('Media') }}</label>
            <p class="mt-1 text-xs text-red-600" x-show="errors.media" x-text="errors.media"></p>

            <template x-if="mediaState === 'existing'">
                <div class="mt-3">
                    <template x-if="existingMediaIsVideo">
                        <video :src="existingMediaUrl" class="w-full max-h-64 object-cover rounded-xl" controls muted></video>
                    </template>
                    <template x-if="!existingMediaIsVideo">
                        <img :src="existingMediaUrl" alt="{{ __('Current media') }}" class="w-full max-h-64 object-cover rounded-xl" />
                    </template>
                    <div class="mt-2 flex gap-2">
                        <button type="button" @click="$refs.fileInput.click()"
                            class="text-xs px-3 py-1.5 rounded-lg border border-blue-300 text-blue-600 hover:bg-blue-50 transition-colors">
                            {{ __('Replace') }}
                        </button>
                        <button type="button" @click="removeExistingMedia()"
                            class="text-xs px-3 py-1.5 rounded-lg border border-red-200 text-red-500 hover:bg-red-50 transition-colors">
                            {{ __('Remove') }}
                        </button>
                    </div>
                </div>
            </template>

            <template x-if="mediaState === 'removed'">
                <div class="mt-3 border-2 border-dashed border-gray-200 rounded-xl p-6 text-center">
                    <p class="text-sm text-gray-400 mb-2">{{ __('Media removed. Upload a replacement or leave empty.') }}</p>
                    <button type="button" @click="$refs.fileInput.click()"
                        class="text-xs px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
                        {{ __('Upload new media') }}
                    </button>
                </div>
            </template>

            <template x-if="mediaState === 'replaced' && newMediaPreview">
                <div class="mt-3 relative">
                    <template x-if="isNewVideo">
                        <video :src="newMediaPreview" class="w-full max-h-64 object-cover rounded-xl" controls muted></video>
                    </template>
                    <template x-if="!isNewVideo">
                        <img :src="newMediaPreview" alt="{{ __('New media') }}" class="w-full max-h-64 object-cover rounded-xl" />
                    </template>
                    <button type="button" @click="clearNewMedia()"
                        class="absolute top-2 right-2 w-7 h-7 rounded-full bg-white/90 text-gray-700 flex items-center justify-center shadow">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M18 6L6 18M6 6l12 12" stroke-linecap="round"/>
                        </svg>
                    </button>
                    <div class="absolute bottom-2 left-2 right-2 bg-black/50 text-white text-xs px-2 py-1 rounded-md truncate" x-text="newMediaName"></div>
                </div>
            </template>

            <template x-if="mediaState === 'none'">
                <button
                    type="button"
                    @click="$refs.fileInput.click()"
                    @dragover.prevent="isDragging = true"
                    @dragleave="isDragging = false"
                    @drop.prevent="onDrop($event)"
                    :class="isDragging
                        ? 'mt-3 w-full border-2 border-dashed rounded-xl p-10 flex flex-col items-center gap-3 border-blue-400 bg-blue-50'
                        : 'mt-3 w-full border-2 border-dashed rounded-xl p-10 flex flex-col items-center gap-3 border-gray-300 hover:border-gray-400'"
                >
                    <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <path d="M21 15l-5-5L5 21"/>
                        </svg>
                    </div>
                    <p class="text-sm text-gray-700">{{ __('Drag and drop or browse files') }}</p>
                    <p class="text-xs text-gray-400">{{ __('JPEG, PNG, GIF, WebP, MP4 up to 50MB') }}</p>
                </button>
            </template>

            <input x-ref="fileInput" type="file" accept="image/*,video/*" class="hidden" @change="onFileChange" />
        </div>

        {{-- Accounts & pages targeting --}}
        <div class="bg-white rounded-xl border p-5 shadow-sm">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <label class="text-sm font-semibold text-gray-900">{{ __('Post to accounts & pages') }}</label>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ __('Click an account to choose which pages will receive this post.') }}
                    </p>
                </div>
                <span class="text-xs text-gray-500 shrink-0 hidden sm:inline">
                    <span x-text="selectedAccountIds.length"></span> selected
                </span>
            </div>
            <p class="text-xs text-red-600 mb-2" x-show="errors.targeting" x-text="errors.targeting"></p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach ($platforms as $p)
                    <button
                        type="button"
                        @click="openModal('{{ $p->id }}')"
                        class="relative border-2 rounded-xl p-4 text-left transition-all"
                        :class="chipClass('{{ $p->id }}')"
                        :aria-disabled="chipState('{{ $p->id }}') === 'disabled'"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 rounded-xl text-white text-sm font-semibold flex items-center justify-center"
                                :class="chipState('{{ $p->id }}') === 'disabled' ? 'bg-gray-300' : '{{ $p->gradientClass }}'"
                            >
                                <span>{{ $p->letter }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 truncate">{{ $p->name }}</div>
                                <div class="text-[11px] text-gray-500 mt-0.5 flex items-center gap-1.5">
                                    <span
                                        class="inline-block w-1.5 h-1.5 rounded-full"
                                        :class="{
                                            'bg-blue-500': chipState('{{ $p->id }}') === 'all',
                                            'bg-amber-500': chipState('{{ $p->id }}') === 'partial',
                                            'bg-gray-400': chipState('{{ $p->id }}') === 'none',
                                            'bg-gray-300': chipState('{{ $p->id }}') === 'disabled',
                                        }"
                                    ></span>
                                    <span x-text="chipBadgeText('{{ $p->id }}')"></span>
                                </div>
                            </div>
                            <svg
                                x-show="chipState('{{ $p->id }}') !== 'disabled'"
                                class="w-4 h-4 text-gray-400 shrink-0"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"
                            >
                                <polyline points="9 18 15 12 9 6" />
                            </svg>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Schedule --}}
        <div class="bg-white rounded-xl border p-5 shadow-sm">
            <div class="text-sm font-semibold text-gray-900">{{ __('When to post') }}</div>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button type="button" @click="scheduleMode = 'now'; errors.schedule = null"
                    class="inline-flex items-center justify-center gap-2 border rounded-lg py-2.5 text-sm font-medium"
                    :class="scheduleMode === 'now' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-200 text-gray-600'">
                    {{ __('Post now') }}
                </button>
                <button type="button" @click="scheduleMode = 'later'"
                    class="inline-flex items-center justify-center gap-2 border rounded-lg py-2.5 text-sm font-medium"
                    :class="scheduleMode === 'later' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-200 text-gray-600'">
                    {{ __('Schedule for later') }}
                </button>
            </div>
            <div class="mt-3" x-show="scheduleMode === 'later'">
                <label for="ep-sched" class="text-xs text-gray-600 block mb-1">{{ __('Date & time') }}</label>
                <input id="ep-sched" type="datetime-local" :min="minDateTime" x-model="scheduledAt"
                    @input="errors.schedule = null"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                <p class="mt-1 text-xs text-red-600" x-show="errors.schedule" x-text="errors.schedule"></p>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex flex-col sm:flex-row gap-3">
            <button type="button" @click="save('draft')" :disabled="submitting"
                class="flex-1 border border-gray-300 rounded-xl py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60 transition-colors">
                {{ __('Save as Draft') }}
            </button>
            <button type="button"
                @click="save(scheduleMode === 'later' ? 'reschedule' : 'publish_now')"
                :disabled="submitting || ! hasAnyTarget"
                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white rounded-xl py-3 text-sm font-semibold disabled:opacity-60 transition-colors"
                x-text="submitting ? '{{ __('Saving...') }}' : (scheduleMode === 'later' ? '{{ __('Save & Reschedule') }}' : '{{ __('Publish Now') }}')"
            ></button>
        </div>

    </div>

    {{-- Account Targeting Modal --}}
    <div
        x-show="modalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6"
        role="dialog"
        aria-modal="true"
        aria-labelledby="targeting-modal-title-edit"
    >
        <div class="absolute inset-0 bg-gray-900/40" @click="closeModal()"></div>

        <div
            class="relative w-full max-w-md bg-white rounded-xl border border-gray-200 shadow-xl flex flex-col max-h-[85vh]"
            @keydown.escape.window="closeModal()"
        >
            <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-gray-100">
                <div class="flex items-center gap-3 min-w-0">
                    <span
                        class="inline-block w-2.5 h-2.5 rounded-full shrink-0"
                        :class="modalPlatformPillColor()"
                    ></span>
                    <div class="min-w-0">
                        <h2 id="targeting-modal-title-edit" class="text-base font-semibold text-gray-900 truncate">
                            <span x-text="modalPlatformLabel()"></span>
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ __('Choose which accounts & pages will receive this post.') }}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="closeModal()"
                    class="text-gray-400 hover:text-gray-600 transition-colors"
                    aria-label="Close modal"
                >
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M18 6L6 18M6 6l12 12" stroke-linecap="round" />
                    </svg>
                </button>
            </div>

            <div
                x-show="modalPlatform && accountsFor(modalPlatform).filter(a => a.is_active).length > 1"
                class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between"
            >
                <div>
                    <p class="text-sm font-medium text-gray-900">{{ __('Post to all pages') }}</p>
                    <p class="text-[11px] text-gray-500 mt-0.5">{{ __('Toggle every page on or off at once.') }}</p>
                </div>
                <button
                    type="button"
                    @click="toggleModalMaster()"
                    :aria-pressed="modalMasterState() === 'on'"
                    :class="modalMasterState() === 'on'
                        ? 'bg-blue-600'
                        : (modalMasterState() === 'indeterminate' ? 'bg-amber-400' : 'bg-gray-300')"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    <span
                        class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform"
                        :class="modalMasterState() === 'on' ? 'translate-x-5' : 'translate-x-1'"
                    ></span>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-3 space-y-2">
                <template x-if="modalPlatform && accountsFor(modalPlatform).length === 0">
                    <div class="text-center py-6 text-sm text-gray-500">
                        {{ __('No connected accounts for this platform yet.') }}
                        <a href="{{ route('accounts') }}" class="block mt-1 text-blue-600 hover:underline text-xs">{{ __('Connect in Accounts') }} &rarr;</a>
                    </div>
                </template>

                <template x-for="account in accountsFor(modalPlatform)" :key="account.id">
                    <div
                        class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg border border-gray-100 hover:border-gray-200 transition-colors"
                        :class="account.is_active ? '' : 'bg-gray-50'"
                    >
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="w-9 h-9 rounded-full bg-gray-100 text-gray-500 text-xs font-semibold flex items-center justify-center shrink-0 overflow-hidden">
                                <template x-if="account.avatar">
                                    <img :src="account.avatar" :alt="account.account_name" class="w-full h-full object-cover" />
                                </template>
                                <template x-if="! account.avatar">
                                    <span x-text="(account.account_name || '?').slice(0, 2).toUpperCase()"></span>
                                </template>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate" x-text="account.account_name"></p>
                                <p class="text-[11px] text-gray-500 truncate">
                                    <span x-show="account.account_handle" x-text="account.account_handle"></span>
                                    <span x-show="! account.is_active" class="text-amber-600 ml-1">{{ __('(paused)') }}</span>
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            :disabled="! account.is_active"
                            @click="toggleModalAccount(account.id, account)"
                            :aria-pressed="isModalAccountOn(account.id)"
                            :class="isModalAccountOn(account.id) ? 'bg-blue-600' : 'bg-gray-300'"
                            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span
                                class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform"
                                :class="isModalAccountOn(account.id) ? 'translate-x-5' : 'translate-x-1'"
                            ></span>
                        </button>
                    </div>
                </template>
            </div>

            <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-100">
                <button
                    type="button"
                    @click="closeModal()"
                    class="px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >{{ __('Cancel') }}</button>
                <button
                    type="button"
                    @click="applyModal()"
                    class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700"
                >{{ __('Apply') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection
