<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\AllowedEmailDomain;
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
        $emailRules = [
            'required',
            'string',
            'lowercase',
            'email',
            'max:255',
            Rule::unique(User::class)->ignore($this->user()->id),
        ];
        // Un compte existant hors domaine peut garder son adresse : on ne controle que le changement
        if ($this->input('email') !== $this->user()->email) {
            $emailRules[] = new AllowedEmailDomain;
        }

        return [
            'name'   => ['required', 'string', 'max:255'],
            'classe' => ['nullable', 'string', 'max:100'],
            'email'  => $emailRules,
        ];
    }
}
