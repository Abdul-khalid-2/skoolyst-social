@extends('layouts.app', [
    'title' => 'Create Post',
    'description' => 'Compose and schedule social posts.',
    'subtitle' => null,
])

@php
    $mockCaptions = [
        'Exciting news from Skoolyst! We are making it easier than ever for parents to find the perfect school. Explore thousands of verified schools. Try it today!',
        'The future of school discovery is here. Skoolyst connects parents with top-rated schools, complete with reviews and direct contact. Sign up free!',
        'Looking for a school that fits your child? Skoolyst lets you compare schools side by side and read real parent reviews. Find the perfect match today!',
    ];
    $platforms = [
        (object) ['id' => 'facebook',  'name' => 'Facebook',    'letter' => 'f',  'gradientClass' => 'bg-gradient-to-br from-blue-600 to-blue-700',    'borderColor' => 'border-blue-500',   'textColor' => 'text-blue-600',   'pillColor' => 'bg-blue-600'],
        (object) ['id' => 'instagram', 'name' => 'Instagram',   'letter' => 'Ig', 'gradientClass' => 'bg-gradient-to-br from-purple-500 to-pink-500',  'borderColor' => 'border-pink-400',   'textColor' => 'text-pink-600',   'pillColor' => 'bg-pink-500'],
        (object) ['id' => 'linkedin',  'name' => 'LinkedIn',    'letter' => 'in', 'gradientClass' => 'bg-gradient-to-br from-indigo-600 to-indigo-700', 'borderColor' => 'border-indigo-500', 'textColor' => 'text-indigo-600', 'pillColor' => 'bg-indigo-600'],
        (object) ['id' => 'twitter',   'name' => 'X (Twitter)', 'letter' => 'X',  'gradientClass' => 'bg-gradient-to-br from-gray-800 to-gray-900',    'borderColor' => 'border-gray-700',   'textColor' => 'text-gray-900',   'pillColor' => 'bg-gray-800'],
    ];
    $alpine = [
        'postUrl' => $workspace ? url("/api/workspaces/{$workspace->id}/posts") : '',
        'connectedSlugs' => $connectedSlugs->all(),
        'pausedSlugs' => ($pausedSlugs ?? collect())->all(),
        'accountsByPlatform' => $accountsByPlatform ?? [],
        'workspaceName' => $workspace?->name ?? '',
    ];
@endphp

@push('head')
    <script>
        function createPostForm(config, platformList) {
            const mockCaptions = @json($mockCaptions);
            const mockHashtags = '#Education #Skoolyst #Schools #EdTech #Learning #Pakistan';

            return {
                config,
                platformList,
                caption: '',
                aiGenerated: false,
                captionLoading: false,
                hashtagLoading: false,
                isDragging: false,
                mediaPreview: '',
                mediaName: '',
                mediaFile: null,
                scheduleMode: 'now',
                scheduledAt: '',
                submitting: false,
                saveDraftLoading: false,
                minDateTime: '',
                errors: { caption: null, targeting: null, schedule: null, media: null, server: null },
                connectedList: [],
                pausedList: [],

                // Per-account/per-page targeting state:
                //   accountsByPlatform = { facebook: [ {id, account_name, ...}, ... ], ... }
                //   selectedAccountIds = ids of accounts that will receive the post when published.
                accountsByPlatform: {},
                selectedAccountIds: [],

                // Modal state.
                modalOpen: false,
                modalPlatform: '',          // e.g. 'facebook'
                modalDraftIds: [],          // staged ids while the modal is open

                get charCount() { return (this.caption || '').length; },
                get maxChars() { return 2200; },
                get percentage() { return Math.min((this.charCount / this.maxChars) * 100, 100); },
                get tone() {
                    const p = this.percentage;
                    if (p >= 90) { return { text: 'text-red-600', bar: 'bg-red-500' }; }
                    if (p >= 70) { return { text: 'text-amber-600', bar: 'bg-amber-500' }; }
                    return { text: 'text-gray-500', bar: 'bg-blue-500' };
                },
                get isVideo() {
                    return this.mediaFile && this.mediaFile.type && this.mediaFile.type.startsWith('video/');
                },
                get twitterSelected() {
                    return this.accountsFor('twitter').some((a) => this.selectedAccountIds.includes(a.id));
                },
                get charLabel() {
                    if (this.twitterSelected) { return this.charCount + ' / 280'; }
                    return this.charCount + '/2200';
                },
                get selectedPlatformSlugs() {
                    const slugs = new Set();
                    for (const slug of Object.keys(this.accountsByPlatform || {})) {
                        if ((this.accountsByPlatform[slug] || []).some((a) => this.selectedAccountIds.includes(a.id))) {
                            slugs.add(slug);
                        }
                    }
                    return [...slugs];
                },
                get activeNames() {
                    return this.selectedPlatformSlugs
                        .map((id) => this.platformList.find((p) => p.id === id))
                        .filter(Boolean)
                        .map((p) => ({ name: p.name, pill: p.pillColor }));
                },
                get hasAnyTarget() { return this.selectedAccountIds.length > 0; },

                init() {
                    this.connectedList = [...(config.connectedSlugs || [])];
                    this.pausedList    = [...(config.pausedSlugs || [])];
                    this.accountsByPlatform = config.accountsByPlatform || {};

                    // Default state: every connected + active account is opted in.
                    // (Paused accounts on the Accounts page are excluded since the backend
                    // would reject them at publish time anyway.)
                    const defaults = [];
                    for (const slug of Object.keys(this.accountsByPlatform)) {
                        for (const a of this.accountsByPlatform[slug] || []) {
                            if (a.is_active) {
                                defaults.push(a.id);
                            }
                        }
                    }
                    this.selectedAccountIds = defaults;
                },

                initMinDate() { this.minDateTime = new Date().toISOString().slice(0, 16); },

                // ---------- Targeting helpers ----------

                accountsFor(slug) {
                    return this.accountsByPlatform[slug] || [];
                },
                connectedCountFor(slug) {
                    return this.accountsFor(slug).filter((a) => a.is_active).length;
                },
                activeCountFor(slug) {
                    return this.accountsFor(slug)
                        .filter((a) => a.is_active && this.selectedAccountIds.includes(a.id))
                        .length;
                },
                hasMultipleAccounts(slug) {
                    return this.connectedCountFor(slug) > 1;
                },

                /**
                 * Visual state for a platform chip:
                 *   'disabled' — platform has no connected accounts (or only paused ones)
                 *   'all'      — every connected account is selected
                 *   'partial'  — some accounts selected
                 *   'none'     — connected accounts exist but none selected
                 */
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
                    if (connected === 0) {
                        if ((this.pausedList || []).includes(slug)) { return 'Paused'; }
                        return 'Not connected';
                    }
                    if (this.hasMultipleAccounts(slug)) {
                        return this.activeCountFor(slug) + '/' + connected + ' pages';
                    }
                    const state = this.chipState(slug);
                    if (state === 'all') { return 'Active'; }
                    return 'Inactive';
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
                    // 'none'
                    return 'border-gray-300 bg-gray-100 text-gray-500 line-through hover:bg-gray-200';
                },

                // ---------- Modal ----------

                openModal(slug) {
                    if (this.chipState(slug) === 'disabled') {
                        const msg = (this.pausedList || []).includes(slug)
                            ? 'This account is paused. Enable it in Accounts to publish.'
                            : 'Connect this platform in Accounts first.';
                        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'info', message: msg } }));
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
                    // Remove all current ids for this platform; replace with the staged ids.
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

                /**
                 * Modal master "post to all" toggle.
                 * Returns 'on' | 'off' | 'indeterminate'.
                 */
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
                        // turn all off (just the ids for this platform)
                        this.modalDraftIds = this.modalDraftIds.filter((id) => ! usableIds.includes(id));
                    } else {
                        // 'off' or 'indeterminate' -> turn all on
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

                onFileChange(e) {
                    const file = e.target.files && e.target.files[0];
                    if (file) { this.setFile(file); }
                },
                onDrop(e) {
                    this.isDragging = false;
                    const file = e.dataTransfer.files && e.dataTransfer.files[0];
                    if (file) { this.setFile(file); }
                },
                setFile(file) {
                    const name = (file.name || '').toLowerCase();
                    const ok =
                        /(\.jpe?g|\.png|\.gif|\.webp|\.mp4)$/.test(name) ||
                        (file.type && file.type.match(/^image\/(jpeg|png|gif|webp)$/) && true) ||
                        (file.type && file.type.match(/^video\/(mp4)$/) && true);
                    if (! ok) {
                        this.errors.media = 'Use JPEG, PNG, GIF, WebP, or MP4 (max 50MB).';
                        return;
                    }
                    if (file.size > 50 * 1024 * 1024) {
                        this.errors.media = 'File must be 50MB or smaller.';
                        return;
                    }
                    this.errors.media = null;
                    this.mediaFile = file;
                    this.mediaName = file.name;
                    const reader = new FileReader();
                    reader.onload = () => { this.mediaPreview = String(reader.result || ''); };
                    reader.readAsDataURL(file);
                },
                clearMedia() {
                    this.mediaFile = null;
                    this.mediaPreview = '';
                    this.mediaName = '';
                    if (this.$refs.fileInput) {
                        this.$refs.fileInput.value = '';
                    }
                },

                // ---------- AI helpers (unchanged) ----------

                generateCaption() {
                    this.captionLoading = true;
                    setTimeout(() => {
                        this.caption = mockCaptions[Math.floor(Math.random() * mockCaptions.length)] || mockCaptions[0];
                        this.aiGenerated = true;
                        this.captionLoading = false;
                        this.errors.caption = null;
                        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: 'Caption generated (for demo, not AI).' } }));
                    }, 1500);
                },
                suggestHashtags() {
                    this.hashtagLoading = true;
                    setTimeout(() => {
                        const hasSpace = this.caption.trim().length > 0;
                        this.caption = (this.caption.trim() + (hasSpace ? ' ' : '') + mockHashtags).trim().slice(0, 2200);
                        this.hashtagLoading = false;
                        this.errors.caption = null;
                        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'info', message: 'Hashtags added (demo).' } }));
                    }, 1200);
                },

                // ---------- Validation & submit ----------

                validate(mode) {
                    const next = { caption: null, targeting: null, schedule: null, media: null };
                    if (! (this.caption || '').trim()) {
                        next.caption = 'Caption is required.';
                    } else if (this.twitterSelected && this.charCount > 280) {
                        next.caption = 'X (Twitter) is selected: caption may not exceed 280 characters.';
                    } else if (this.charCount > 2200) {
                        next.caption = 'Caption is too long (max 2200 characters).';
                    }
                    if (mode === 'now' || mode === 'schedule') {
                        if (this.selectedAccountIds.length === 0) {
                            next.targeting = 'Please select at least one account or page to post to.';
                        }
                    }
                    if (mode === 'schedule') {
                        if (! this.scheduledAt) {
                            next.schedule = 'Please choose a scheduled date and time.';
                        } else {
                            const t = new Date(this.scheduledAt);
                            if (t.getTime() <= Date.now()) {
                                next.schedule = 'Scheduled time must be in the future.';
                            }
                        }
                    }
                    this.errors = { ...this.errors, ...next };
                    return ! (next.caption || next.targeting || next.schedule);
                },

                buildFormData(mode) {
                    const form = new FormData();
                    form.append('mode', mode);
                    form.append('caption', this.caption);
                    form.append('ai_generated', this.aiGenerated ? '1' : '0');

                    (this.selectedAccountIds || []).forEach((id) => {
                        form.append('social_account_ids[]', String(id));
                    });
                    // Send platform_slugs[] derived from targeted accounts for any downstream
                    // consumers that still inspect it (e.g. analytics). The backend prefers
                    // social_account_ids[] when both are present.
                    this.selectedPlatformSlugs.forEach((slug) => {
                        form.append('platform_slugs[]', slug);
                    });

                    if (mode === 'schedule' && this.scheduledAt) {
                        form.append('scheduled_at', this.scheduledAt);
                    }
                    if (this.mediaFile) {
                        form.append('media', this.mediaFile, this.mediaName || this.mediaFile.name);
                    }
                    return form;
                },

                get csrf() {
                    return document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
                },

                async runSubmit(mode) {
                    this.errors = { caption: null, targeting: null, schedule: null, media: null, server: null };
                    const label = mode === 'draft' ? 'draft' : 'post';
                    if (! this.validate(mode)) { return; }
                    const config = this.config;
                    if (label === 'draft') { this.saveDraftLoading = true; } else { this.submitting = true; }
                    const form = this.buildFormData(mode);
                    const dashboardUrl = @json(route('dashboard'));
                    try {
                        const res = await fetch(config.postUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': this.csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                                Accept: 'application/json',
                            },
                            body: form,
                            credentials: 'same-origin',
                        });
                        const data = await res.json().catch(() => ({}));
                        if (! res.ok) {
                            if (res.status === 422 && data.errors) {
                                this.errors.caption = (data.errors.caption && data.errors.caption[0]) || this.errors.caption;
                                this.errors.schedule = (data.errors.scheduled_at && data.errors.scheduled_at[0]) || this.errors.schedule;
                                const targetingErr = data.errors['social_account_ids'] || data.errors['platform_slugs'];
                                if (targetingErr) {
                                    this.errors.targeting = Array.isArray(targetingErr) ? targetingErr[0] : targetingErr;
                                }
                                this.errors.media = (data.errors.media && data.errors.media[0]) || this.errors.media;
                            }
                            const msg = data.message || 'Request failed.';
                            window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: msg } }));
                            return;
                        }
                        const successMsg = mode === 'draft' ? 'Draft saved successfully.' : mode === 'schedule' ? 'Post scheduled successfully.' : 'Post queued for publishing.';
                        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: successMsg } }));
                        window.location.assign(dashboardUrl);
                    } catch (e) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: 'Request failed. Please try again.' } }));
                    } finally {
                        this.saveDraftLoading = false;
                        this.submitting = false;
                    }
                },
            };
        }
    </script>
@endpush

@section('content')
    @if (! $workspace)
        <div class="min-h-full -m-6 p-6 flex items-center justify-center bg-gray-50">
            <div class="max-w-md bg-white border border-red-100 rounded-xl p-5 shadow-sm text-center">
                <svg class="w-9 h-9 text-red-500 mx-auto mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                <p class="text-sm text-gray-800">
                    No workspace found. Create an account to get a workspace, then try again.
                </p>
                <a
                    href="{{ route('accounts') }}"
                    class="mt-3 inline-block text-sm text-blue-600 font-medium hover:underline"
                >Open Accounts</a>
            </div>
        </div>
    @else
        <div
            class="bg-gray-50 min-h-full -m-6 p-6"
            x-data="createPostForm(@js($alpine), @js($platforms))"
            x-init="initMinDate()"
        >
            <div class="max-w-6xl mx-auto grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-3 -mt-2 text-xs text-gray-500">
                    {{ __('Workspace:') }} <span class="font-medium text-gray-800">{{ e($workspace->name) }}</span>
                </div>

                <div
                    class="xl:col-span-3 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 text-amber-900 px-3 py-2.5 text-sm"
                    x-show="connectedList.length === 0"
                    x-cloak
                >
                    <svg class="w-4 h-4 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    <p>
                        No social accounts are connected to this workspace yet. You can still <strong>save a draft</strong> with
                        caption and media. Connect Facebook, Instagram, LinkedIn, or X in Accounts to enable publishing.
                    </p>
                </div>

                <div class="xl:col-span-2 space-y-5">
                    <div class="bg-white rounded-xl border p-5 shadow-sm">
                        <label for="cp-caption" class="text-sm font-semibold text-gray-900">Caption</label>
                        <div class="mt-3 relative">
                            <textarea
                                id="cp-caption"
                                rows="6"
                                maxlength="2200"
                                x-model="caption"
                                @input="errors.caption = null"
                                class="w-full border border-gray-200 rounded-xl px-4 py-3 pr-20 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                placeholder="Write your post caption..."
                            ></textarea>
                            <span class="absolute bottom-3 right-3 text-xs" :class="tone.text" x-text="charCount + '/2200'"></span>
                        </div>
                        <div class="mt-2 h-1 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full transition-all" :class="tone.bar" :style="'width:' + percentage + '%'"></div>
                        </div>
                        <p class="mt-2 text-xs text-red-600" x-show="errors.caption" x-text="errors.caption"></p>

                        <div class="mt-4 flex flex-wrap gap-2.5">
                            <button
                                type="button"
                                @click="generateCaption"
                                :disabled="captionLoading"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gradient-to-r from-purple-600 to-purple-700 text-white text-sm font-medium disabled:opacity-70"
                            >
                                <template x-if="captionLoading">
                                    <svg class="h-[15px] w-[15px] shrink-0 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" /></svg>
                                </template>
                                <template x-if="! captionLoading">
                                    <svg class="h-[15px] w-[15px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3a6 6 0 0 0 6 6c0 3-3 3-3 7h-6c0-4-3-4-3-7a6 6 0 0 0 6-6Z" /><path d="M9 17h6" /></svg>
                                </template>
                                {{ __('Generate Caption') }}
                            </button>
                            <button
                                type="button"
                                @click="suggestHashtags"
                                :disabled="hashtagLoading"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-indigo-300 text-indigo-700 text-sm font-medium disabled:opacity-70"
                            >
                                <template x-if="hashtagLoading">
                                    <svg class="h-[15px] w-[15px] shrink-0 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"></svg>
                                </template>
                                <template x-if="! hashtagLoading">
                                    <svg class="h-[15px] w-[15px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="9" x2="20" y2="9" /><line x1="4" y1="15" x2="20" y2="15" /><line x1="10" y1="3" x2="8" y2="21" /><line x1="16" y1="3" x2="14" y2="21" /></svg>
                                </template>
                                {{ __('Suggest Hashtags') }}
                            </button>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl border p-5 shadow-sm">
                        <label class="text-sm font-semibold text-gray-900">Media (optional)</label>
                        <p class="mt-2 text-xs text-red-600" x-show="errors.media" x-text="errors.media"></p>

                        <template x-if="! mediaPreview">
                            <button
                                type="button"
                                @click="$refs.fileInput.click()"
                                @dragover.prevent="isDragging = true"
                                @dragleave="isDragging = false"
                                @drop.prevent="onDrop($event)"
                                :class="isDragging
                                    ? 'mt-3 w-full border-2 border-dashed rounded-xl p-10 flex flex-col items-center gap-3 transition-colors border-blue-400 bg-blue-50'
                                    : 'mt-3 w-full border-2 border-dashed rounded-xl p-10 flex flex-col items-center gap-3 transition-colors border-gray-300 hover:border-gray-400'"
                            >
                                <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center">
                                    <svg class="w-5 h-5 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <rect x="3" y="3" width="18" height="18" rx="2" />
                                        <circle cx="8.5" cy="8.5" r="1.5" />
                                        <path d="M21 15l-5-5L5 21" />
                                    </svg>
                                </div>
                                <p class="text-sm text-gray-700">Drag and drop or browse files</p>
                                <p class="text-xs text-gray-400">JPEG, PNG, GIF, WebP, MP4 up to 50MB</p>
                            </button>
                        </template>

                        <template x-if="mediaPreview">
                            <div class="mt-3 relative">
                                <template x-if="isVideo">
                                    <video :src="mediaPreview" class="w-full max-h-64 object-cover rounded-xl" controls muted></video>
                                </template>
                                <template x-if="! isVideo">
                                    <img :src="mediaPreview" alt="Preview" class="w-full max-h-64 object-cover rounded-xl" />
                                </template>
                                <button type="button" @click="clearMedia" class="absolute top-2 right-2 w-7 h-7 rounded-full bg-white/90 text-gray-700 flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M18 6L6 18M6 6l12 12" stroke-linecap="round" />
                                    </svg>
                                </button>
                                <div class="absolute bottom-2 left-2 right-2 bg-black/50 text-white text-xs px-2 py-1 rounded-md truncate" x-text="mediaName"></div>
                            </div>
                        </template>

                        <input x-ref="fileInput" type="file" accept="image/*,video/*" class="hidden" @change="onFileChange" />
                    </div>

                    {{-- Accounts & pages targeting --}}
                    <div class="bg-white rounded-xl border p-5 shadow-sm">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <label class="text-sm font-semibold text-gray-900">Post to accounts &amp; pages</label>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    All connected accounts are selected by default. Click an account to choose specific pages.
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

                    <div class="bg-white rounded-xl border p-5 shadow-sm">
                        <div class="text-sm font-semibold text-gray-900">When to post</div>

                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <button
                                type="button"
                                @click="scheduleMode = 'now'; errors.schedule = null"
                                class="inline-flex items-center justify-center gap-2 border rounded-lg py-2.5 text-sm font-medium"
                                :class="scheduleMode === 'now' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-200 text-gray-600'"
                            >
                                <svg class="h-[15px] w-[15px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <line x1="22" y1="2" x2="11" y2="13" />
                                    <polygon points="22 2 15 22 11 13 2 9 22 2" />
                                </svg>
                                Post now
                            </button>
                            <button
                                type="button"
                                @click="scheduleMode = 'later'"
                                class="inline-flex items-center justify-center gap-2 border rounded-lg py-2.5 text-sm font-medium"
                                :class="scheduleMode === 'later' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-200 text-gray-600'"
                            >
                                <svg class="h-[15px] w-[15px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10" />
                                    <path d="M12 6v6l4 2" />
                                </svg>
                                Schedule for later
                            </button>
                        </div>

                        <div class="mt-3" x-show="scheduleMode === 'later'">
                            <label for="cp-sched" class="text-xs text-gray-600 block mb-1">Date & time</label>
                            <input
                                id="cp-sched"
                                type="datetime-local"
                                :min="minDateTime"
                                x-model="scheduledAt"
                                @input="errors.schedule = null"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                            <p class="mt-2 text-xs text-red-600" x-show="errors.schedule" x-text="errors.schedule"></p>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <button
                            type="button"
                            :disabled="saveDraftLoading || submitting"
                            @click="runSubmit('draft')"
                            class="flex-1 border-2 border-gray-300 text-gray-700 rounded-xl py-3 font-semibold disabled:opacity-60"
                        >
                            <span x-show="saveDraftLoading" class="inline-flex items-center justify-center gap-2">
                                <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"></svg>
                                Saving&hellip;
                            </span>
                            <span x-show="! saveDraftLoading">Save as draft</span>
                        </button>
                        <button
                            type="button"
                            :disabled="saveDraftLoading || submitting || ! hasAnyTarget"
                            @click="runSubmit(scheduleMode === 'later' ? 'schedule' : 'now')"
                            class="flex-1 bg-blue-600 text-white rounded-xl py-3 font-semibold disabled:opacity-60"
                        >
                            <span x-show="submitting" class="inline-flex items-center justify-center gap-2">
                                <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"></svg>
                                Working&hellip;
                            </span>
                            <span x-show="! submitting && scheduleMode === 'later'">Schedule post</span>
                            <span x-show="! submitting && scheduleMode === 'now'">Post now</span>
                        </button>
                    </div>
                </div>

                <div class="xl:col-span-1">
                    <div class="sticky top-6 bg-white rounded-xl border p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-900">Live preview</h3>

                        <div class="mt-4 mx-auto w-52 bg-gray-900 rounded-[2.5rem] p-2.5 shadow-xl">
                            <div class="bg-white rounded-[2rem] overflow-hidden min-h-96">
                                <div class="bg-gray-900 h-6 rounded-t-[2rem] flex items-center justify-center">
                                    <div class="w-16 h-3 bg-gray-800 rounded-full"></div>
                                </div>
                                <div class="p-3 space-y-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-blue-600 to-cyan-500"></div>
                                        <div>
                                            <p class="text-[10px] font-semibold text-gray-900">Skoolyst</p>
                                            <p class="text-[9px] text-gray-400">Just now</p>
                                        </div>
                                    </div>

                                    <p
                                        class="text-xs text-gray-800 line-clamp-4"
                                        :class="caption ? '' : 'italic text-gray-400'"
                                        x-text="caption || 'Your caption preview appears here.'"
                                    ></p>

                                    <template x-if="! mediaPreview">
                                        <div class="w-full h-28 rounded-lg bg-gray-100 flex items-center justify-center">
                                            <svg class="w-[18px] h-[18px] text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <circle cx="8.5" cy="8.5" r="1.5" />
                                                <path d="M21 15l-5-5L5 21" />
                                            </svg>
                                        </div>
                                    </template>
                                    <template x-if="mediaPreview && isVideo">
                                        <video :src="mediaPreview" class="w-full h-28 object-cover rounded-lg" muted></video>
                                    </template>
                                    <template x-if="mediaPreview && ! isVideo">
                                        <img :src="mediaPreview" alt="Media" class="w-full h-28 object-cover rounded-lg" />
                                    </template>

                                    <div class="text-[9px] text-gray-500">Like · Comment · Share</div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4" x-show="activeNames.length > 0">
                            <p class="text-xs text-gray-500 mb-2">Target platforms:</p>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="(n, idx) in activeNames" :key="idx + n.name">
                                    <span class="text-white text-xs px-2.5 py-1 rounded-full" :class="n.pill" x-text="n.name"></span>
                                </template>
                            </div>
                        </div>

                        <div class="mt-4" x-show="charCount > 0">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-gray-500">Characters</span>
                                <span class="text-xs" :class="tone.text" x-text="charLabel"></span>
                            </div>
                            <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                <div
                                    class="h-full transition-all"
                                    :class="tone.bar"
                                    :style="'width:' + (twitterSelected ? Math.min((charCount / 280) * 100, 100) : percentage) + '%'"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Account Targeting Modal --}}
            <div
                x-show="modalOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6"
                role="dialog"
                aria-modal="true"
                aria-labelledby="targeting-modal-title"
            >
                <div class="absolute inset-0 bg-gray-900/40" @click="closeModal()"></div>

                <div
                    class="relative w-full max-w-md bg-white rounded-xl border border-gray-200 shadow-xl flex flex-col max-h-[85vh]"
                    @keydown.escape.window="closeModal()"
                >
                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-gray-100">
                        <div class="flex items-center gap-3 min-w-0">
                            <span
                                class="inline-block w-2.5 h-2.5 rounded-full shrink-0"
                                :class="modalPlatformPillColor()"
                            ></span>
                            <div class="min-w-0">
                                <h2 id="targeting-modal-title" class="text-base font-semibold text-gray-900 truncate">
                                    <span x-text="modalPlatformLabel()"></span>
                                </h2>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Choose which accounts &amp; pages will receive this post.
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

                    {{-- Master toggle --}}
                    <div
                        x-show="modalPlatform && accountsFor(modalPlatform).filter(a => a.is_active).length > 1"
                        class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between"
                    >
                        <div>
                            <p class="text-sm font-medium text-gray-900">Post to all pages</p>
                            <p class="text-[11px] text-gray-500 mt-0.5">Toggle every page on or off at once.</p>
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

                    {{-- Account list --}}
                    <div class="flex-1 overflow-y-auto px-5 py-3 space-y-2">
                        <template x-if="modalPlatform && accountsFor(modalPlatform).length === 0">
                            <div class="text-center py-6 text-sm text-gray-500">
                                No connected accounts for this platform yet.
                                <a href="{{ route('accounts') }}" class="block mt-1 text-blue-600 hover:underline text-xs">Connect in Accounts &rarr;</a>
                            </div>
                        </template>

                        <template x-for="account in accountsFor(modalPlatform)" :key="account.id">
                            <div
                                class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg border border-gray-100 hover:border-gray-200 transition-colors"
                                :class="account.is_active ? '' : 'bg-gray-50'"
                            >
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <div
                                        class="w-9 h-9 rounded-full bg-gray-100 text-gray-500 text-xs font-semibold flex items-center justify-center shrink-0 overflow-hidden"
                                    >
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
                                            <span x-show="! account.is_active" class="text-amber-600 ml-1">(paused)</span>
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

                    {{-- Footer actions --}}
                    <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-100">
                        <button
                            type="button"
                            @click="closeModal()"
                            class="px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >Cancel</button>
                        <button
                            type="button"
                            @click="applyModal()"
                            class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700"
                        >Apply</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
