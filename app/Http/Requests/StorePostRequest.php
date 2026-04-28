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
            $caption = (string) $this->input('caption', '');

            if (in_array('twitter', $slugs, true) && mb_strlen($caption) > 280) {
                $v->errors()->add('caption', __('When X (Twitter) is selected, the caption may not exceed 280 characters.'));
            }

            if ($mode === 'draft') {
                return;
            }

            if (count($slugs) < 1) {
                $v->errors()->add('platform_slugs', __('Select at least one social platform.'));

                return;
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
