<?php

namespace App\Http\Requests;

use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $p = $this->input('platform_slugs');
        if ($p !== null && ! is_array($p)) {
            $this->merge(['platform_slugs' => [$p]]);
        }

        if ($this->has('social_account_ids')) {
            $ids = $this->input('social_account_ids');
            if ($ids === '' || $ids === null) {
                $this->merge(['social_account_ids' => []]);
            } elseif (! is_array($ids)) {
                $this->merge(['social_account_ids' => [$ids]]);
            }

            $normalized = [];
            foreach ((array) $this->input('social_account_ids') as $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                if (is_numeric($value)) {
                    $normalized[] = (int) $value;
                }
            }
            $this->merge(['social_account_ids' => array_values(array_unique($normalized))]);
        }
    }

    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $workspace = $this->route('workspace');
        if (! $workspace instanceof Workspace) {
            return false;
        }

        return Gate::forUser($user)->allows('create', [\App\Models\Post::class, $workspace]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', Rule::in(['draft', 'schedule', 'now'])],
            'caption' => ['required', 'string', 'min:1', 'max:2200'],
            'scheduled_at' => [
                'nullable',
                'date',
                Rule::requiredIf(fn () => $this->input('mode') === 'schedule'),
                'after:now',
            ],
            'platform_slugs' => ['nullable', 'array'],
            'platform_slugs.*' => ['string', Rule::in(['facebook', 'instagram', 'linkedin', 'twitter'])],
            'social_account_ids' => ['nullable', 'array'],
            'social_account_ids.*' => ['integer', 'min:1'],
            'ai_generated' => ['sometimes', 'boolean'],
            'media' => [
                'nullable',
                'file',
                'max:51200',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,video/mp4',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $mode = (string) $this->input('mode');
            $slugs = $this->input('platform_slugs', []);
            $slugs = is_array($slugs) ? $slugs : [];
            $accountIds = $this->input('social_account_ids', []);
            $accountIds = is_array($accountIds) ? $accountIds : [];
            $caption = (string) $this->input('caption', '');

            // Treat selection as present if either slugs or account_ids are provided.
            $hasSelection = count($slugs) > 0 || count($accountIds) > 0;

            // If account ids are supplied, derive platform slugs from them for Twitter caption check.
            $derivedSlugs = $slugs;
            $workspace = $this->route('workspace');
            if (count($accountIds) > 0 && $workspace instanceof Workspace) {
                $accountSlugs = \App\Models\SocialAccount::query()
                    ->where('workspace_id', $workspace->id)
                    ->whereIn('id', $accountIds)
                    ->with('platform:id,slug')
                    ->get()
                    ->map(fn ($a) => (string) $a->platform?->slug)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                $derivedSlugs = array_values(array_unique(array_merge($slugs, $accountSlugs)));
            }

            if (in_array('twitter', $derivedSlugs, true) && mb_strlen($caption) > 280) {
                $v->errors()->add('caption', __('When X (Twitter) is selected, the caption may not exceed 280 characters.'));
            }

            if ($mode === 'draft') {
                return;
            }

            if (! $hasSelection) {
                $v->errors()->add('social_account_ids', __('Please select at least one account or page to post to.'));

                return;
            }

            // If granular account_ids were sent, validate that each belongs to this workspace
            // and is connected + active. Anything else blocks submission.
            if (count($accountIds) > 0 && $workspace instanceof Workspace) {
                $valid = \App\Models\SocialAccount::query()
                    ->where('workspace_id', $workspace->id)
                    ->whereIn('id', $accountIds)
                    ->where('is_connected', true)
                    ->where('is_active', true)
                    ->pluck('id')
                    ->all();

                $invalid = array_diff($accountIds, $valid);
                if (! empty($invalid)) {
                    $v->errors()->add(
                        'social_account_ids',
                        __('One or more selected accounts are no longer connected or active. Please review your targeting.')
                    );

                    return;
                }
            }

            if ($mode === 'schedule' && ! $this->filled('scheduled_at')) {
                $v->errors()->add('scheduled_at', __('Please choose a date and time in the future to schedule.'));
            }

            if ($mode === 'schedule' && $this->filled('scheduled_at') && ! Carbon::parse($this->input('scheduled_at'))->isFuture()) {
                $v->errors()->add('scheduled_at', __('The scheduled time must be in the future.'));
            }

            if ($mode === 'now' && $this->filled('scheduled_at')) {
                $v->errors()->add('scheduled_at', __('Remove the scheduled time when posting immediately, or use Schedule mode.'));
            }
        });
    }
}
