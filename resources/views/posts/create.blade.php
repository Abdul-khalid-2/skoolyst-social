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
        (object) ['id' => 'facebook', 'name' => 'Facebook', 'letter' => 'f', 'gradientClass' => 'bg-gradient-to-br from-blue-600 to-blue-700', 'borderColor' => 'border-blue-500', 'textColor' => 'text-blue-600', 'pillColor' => 'bg-blue-600'],
        (object) ['id' => 'instagram', 'name' => 'Instagram', 'letter' => 'Ig', 'gradientClass' => 'bg-gradient-to-br from-purple-500 to-pink-500', 'borderColor' => 'border-pink-400', 'textColor' => 'text-pink-600', 'pillColor' => 'bg-pink-500'],
        (object) ['id' => 'linkedin', 'name' => 'LinkedIn', 'letter' => 'in', 'gradientClass' => 'bg-gradient-to-br from-indigo-600 to-indigo-700', 'borderColor' => 'border-indigo-500', 'textColor' => 'text-indigo-600', 'pillColor' => 'bg-indigo-600'],
        (object) ['id' => 'twitter', 'name' => 'X (Twitter)', 'letter' => 'X', 'gradientClass' => 'bg-gradient-to-br from-gray-800 to-gray-900', 'borderColor' => 'border-gray-700', 'textColor' => 'text-gray-900', 'pillColor' => 'bg-gray-800'],
    ];
    $alpine = [
        'postUrl' => $workspace ? url("/api/workspaces/{$workspace->id}/posts") : '',
        'connectedSlugs' => $connectedSlugs->all(),
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
                selectedPlatforms: [],
                scheduleMode: 'now',
                scheduledAt: '',
                submitting: false,
                saveDraftLoading: false,
                minDateTime: '',
                errors: { caption: null, platforms: null, platform_slugs: null, schedule: null, media: null, server: null },
                connectedList: [],

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
                get twitterSelected() { return this.selectedPlatforms.includes('twitter'); },
                get charLabel() {
                    if (this.twitterSelected) { return this.charCount + ' / 280'; }
                    return this.charCount + '/2200';
                },
                get activeNames() {
                    return this.selectedPlatforms
                        .map((id) => this.platformList.find((p) => p.id === id))
                        .filter(Boolean)
                        .map((p) => ({
                            name: p.name,
                            pill: this.connectedList.includes(p.id) ? p.pillColor : 'bg-gray-400',
                        }));
                },

                init() {
                    this.connectedList = [...(config.connectedSlugs || [])];
                    this.selectedPlatforms = [...(config.connectedSlugs || [])];
                },

                initMinDate() { this.minDateTime = new Date().toISOString().slice(0, 16); },

                canUse(id) { return this.connectedList.includes(id); },
                isSelected(id) { return this.selectedPlatforms.includes(id); },
                btnPlatformClass(p) {
                    const selected = this.isSelected(p.id);
                    const can = this.canUse(p.id);
                    if (selected) {
                        return (can ? p.borderColor : 'border-gray-200') + ' ' + (can ? 'bg-gray-50' : 'bg-gray-100/80 opacity-70');
                    }
                    return 'border-gray-200 hover:border-gray-300';
                },

                togglePlatform(id) {
                    if (! this.connectedList.includes(id)) {
                        window.dispatchEvent(
                            new CustomEvent('toast', { detail: { type: 'info', message: 'Connect this platform in Accounts first, or it is not available for this workspace.' } }),
                        );
                        return;
                    }
                    if (this.selectedPlatforms.includes(id)) {
                        this.selectedPlatforms = this.selectedPlatforms.filter((x) => x !== id);
                    } else {
                        this.selectedPlatforms = [...this.selectedPlatforms, id];
                    }
                    this.errors.platforms = null;
                    this.errors.platform_slugs = null;
                },

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

                validate(mode) {
                    const next = { caption: null, platforms: null, platform_slugs: null, schedule: null, media: null };
                    if (! (this.caption || '').trim()) {
                        next.caption = 'Caption is required.';
                    } else if (this.twitterSelected && this.charCount > 280) {
                        next.caption = 'X (Twitter) is selected: caption may not exceed 280 characters.';
                    } else if (this.charCount > 2200) {
                        next.caption = 'Caption is too long (max 2200 characters).';
                    }
                    if (mode === 'now' || mode === 'schedule') {
                        const ok = this.selectedPlatforms.filter((s) => this.connectedList.includes(s));
                        if (ok.length === 0) {
                            next.platforms = 'Select at least one platform with a connected account, or use Save as Draft only.';
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
                    return ! (next.caption || next.platforms || next.platform_slugs || next.schedule);
                },

                buildFormData(mode) {
                    const form = new FormData();
                    form.append('mode', mode);
                    form.append('caption', this.caption);
                    form.append('ai_generated', this.aiGenerated ? '1' : '0');
                    (this.selectedPlatforms || []).forEach((slug) => {
                        if (this.connectedList.includes(slug)) {
                            form.append('platform_slugs[]', slug);
                        }
                    });
                    if (mode === 'schedule' && this.scheduledAt) {
                        form.append('scheduled_at', new Date(this.scheduledAt).toISOString());
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
                    this.errors = { caption: null, platforms: null, platform_slugs: null, schedule: null, media: null, server: null };
                    const label = mode === 'draft' ? 'draft' : 'post';
                    if (mode === 'draft' && this.connectedList.length === 0) {
                        this.selectedPlatforms = [];
                    }
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
                                const ps = data.errors['platform_slugs'] || data.errors.platform_slugs;
                                if (ps) { this.errors.platform_slugs = Array.isArray(ps) ? ps[0] : ps; this.errors.platforms = this.errors.platform_slugs; }
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

                    <div class="bg-white rounded-xl border p-5 shadow-sm">
                        <div class="flex items-start justify-between mb-3">
                            <label class="text-sm font-semibold text-gray-900">Platforms</label>
                            <span class="text-xs text-gray-500">At least one for publish/schedule (when connected)</span>
                        </div>
                        <p class="text-xs text-red-600 mb-2" x-show="errors.platforms || errors.platform_slugs" x-text="errors.platforms || errors.platform_slugs"></p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($platforms as $p)
                                <button
                                    type="button"
                                    @click="togglePlatform('{{ $p->id }}')"
                                    class="relative border-2 rounded-xl p-4 text-left transition-all"
                                    :class="btnPlatformClass(@js($p))"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-10 h-10 rounded-xl text-white text-sm font-semibold flex items-center justify-center"
                                            :class="canUse('{{ $p->id }}') ? '{{ $p->gradientClass }}' : 'bg-gray-300'"
                                        >
                                            <span>{{ $p->letter }}</span>
                                        </div>
                                        <span class="text-sm font-semibold text-gray-800">
                                            {{ $p->name }}<span x-show="! canUse('{{ $p->id }}')" class="ml-1 text-[10px] text-gray-400">(not connected)</span>
                                        </span>
                                    </div>
                                    <span
                                        x-show="isSelected('{{ $p->id }}') && canUse('{{ $p->id }}')"
                                        class="absolute top-2 right-2 w-5 h-5 rounded-full text-[10px] text-white flex items-center justify-center {{ $p->gradientClass }}"
                                    >ok</span>
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
                            :disabled="saveDraftLoading || submitting"
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
        </div>
    @endif
@endsection
