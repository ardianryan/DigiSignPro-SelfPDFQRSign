<?php

namespace App\Http\Requests;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $settings = AppSetting::first();
        $maxLen = $settings?->max_prefix_length ?: 3;
        if ($maxLen < 2) {
            $maxLen = 2;
        }
        if ($maxLen > 9) {
            $maxLen = 9;
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'position' => ['nullable', 'string', 'max:100'],
            'signature_prefix' => [
                'required',
                'string',
                'min:2',
                'max:' . $maxLen,
                'regex:/^[A-Za-z]+$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'signature_prefix.regex' => 'Prefix harus berupa huruf kapital (A-Z).',
            'signature_prefix.min' => 'Prefix terlalu pendek.',
            'signature_prefix.max' => 'Prefix terlalu panjang.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('signature_prefix')) {
            $this->merge([
                'signature_prefix' => strtoupper(trim((string) $this->input('signature_prefix'))),
            ]);
        }
    }
}
