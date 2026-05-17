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
        'updateUrl'             => $workspace ? url("/api/workspaces/{$workspace->id}/posts/{$post->id}") : '',
        'connectedSlugs'        => $connectedSlugs->all(),
        'existingPlatformSlugs' => $existingPlatformSlugs,
        'existingCaption'       => $post->caption ?? '',
        'existingScheduledAt'   => $post->scheduled_at ? $post->scheduled_at->format('Y-m-d\TH:i') : '',
        'existingMediaUrl'      => $existingMediaUrl,
        'existingMediaIsVideo'  => $isExistingVideo,
        'postStatus'            => $post->status,
        'workspaceName'         => $workspace?->name ?? '',
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
        errors: { caption: null, schedule: null, media: null, server: null },

        // mediaState: 'existing' | 'removed' | 'replaced' | 'none'
        mediaState: config.existingMediaUrl ? 'existing' : 'none',
        existingMediaUrl: config.existingMediaUrl || '',
        existingMediaIsVideo: config.existingMediaIsVideo || false,
        newMediaFile: null,
        newMediaPreview: '',
        newMediaName: '',
        isDragging: false,

        connectedList: [...(config.connectedSlugs || [])],
        selectedPlatforms: [...(config.existingPlatformSlugs || config.connectedSlugs || [])],

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

        init() { this.minDateTime = new Date().toISOString().slice(0, 16); },

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
            if (!this.connectedList.includes(id)) {
                window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'info', message: 'Connect this platform in Accounts first.' } }));
                return;
            }
            if (this.selectedPlatforms.includes(id)) {
                this.selectedPlatforms = this.selectedPlatforms.filter(x => x !== id);
            } else {
                this.selectedPlatforms = [...this.selectedPlatforms, id];
            }
        },

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

        validate(action) {
            let ok = true;
            if (!(this.caption || '').trim()) {
                this.errors.caption = 'Caption is required.'; ok = false;
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
            return ok;
        },

        get csrf() {
            return document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
        },

        async save(action) {
            this.errors = { caption: null, schedule: null, media: null, server: null };
            if (!this.validate(action)) return;

            this.submitting = true;
            const form = new FormData();
            form.append('_method', 'PATCH');
            form.append('caption', this.caption);
            form.append('action', action);

            if (action === 'reschedule' && this.scheduledAt) {
                form.append('scheduled_at', new Date(this.scheduledAt).toISOString());
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
                        this.errors.media    = data.errors.media?.[0] || null;
                        this.errors.server   = data.message || null;
                    } else {
                        this.errors.server = data.message || 'Update failed.';
                    }
                    window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: this.errors.server || 'Update failed.' } }));
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

            {{-- Existing media --}}
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

            {{-- Removed placeholder --}}
            <template x-if="mediaState === 'removed'">
                <div class="mt-3 border-2 border-dashed border-gray-200 rounded-xl p-6 text-center">
                    <p class="text-sm text-gray-400 mb-2">{{ __('Media removed. Upload a replacement or leave empty.') }}</p>
                    <button type="button" @click="$refs.fileInput.click()"
                        class="text-xs px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
                        {{ __('Upload new media') }}
                    </button>
                </div>
            </template>

            {{-- New file preview --}}
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

            {{-- Upload zone (no media) --}}
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
                :disabled="submitting"
                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white rounded-xl py-3 text-sm font-semibold disabled:opacity-60 transition-colors"
                x-text="submitting ? '{{ __('Saving...') }}' : (scheduleMode === 'later' ? '{{ __('Save & Reschedule') }}' : '{{ __('Publish Now') }}')"
            ></button>
        </div>

    </div>
</div>
@endsection
