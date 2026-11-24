<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ProfileUpdateRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (config('fortify.lowercase_usernames') && is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower($this->input('email'))]);
        }
    }

    /** @return array<string, mixed> what the profile takes over */
    public function profile(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();
        unset($data['current_password']);

        return $data;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        abort_unless($user instanceof User, 401);

        return [
            ...$this->profileRules($user->id),
            // A new address can reset the password: only with the current one.
            'current_password' => $this->input('email') === $user->email ? ['exclude'] : $this->currentPasswordRules(),
        ];
    }
}
