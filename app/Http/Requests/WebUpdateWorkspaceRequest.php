<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebUpdateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'workspace_name' => ['required', 'string', 'min:2', 'max:120'],
        ];
    }
}
