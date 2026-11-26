<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\AllowedEmailDomain;
use App\Support\ProfileFields;
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
        $rules = [
            'name'   => ['required', 'string', 'max:255'],
            'classe' => User::classeRules(),
            'email'  => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
        ];
        // Champ verrouille par l'administrateur : ni valide ni enregistre, meme si une valeur est envoyee
        $rules = array_filter($rules, fn (string $field) => ProfileFields::editable($field), ARRAY_FILTER_USE_KEY);

        // Changer d'adresse redemande le mot de passe : sinon une session restee ouverte (poste partage) suffirait
        // a mettre son adresse puis a prendre le compte par "Mot de passe oublie". Un compte existant hors domaine
        // peut garder son adresse : le domaine n'est controle qu'au changement.
        if ($this->emailChanges()) {
            $rules['email'][] = new AllowedEmailDomain;
            $rules['current_password'] = ['required', 'current_password'];
        }

        return $rules;
    }

    public function emailChanges(): bool
    {
        return ProfileFields::editable('email') && $this->input('email') !== $this->user()->email;
    }
}
