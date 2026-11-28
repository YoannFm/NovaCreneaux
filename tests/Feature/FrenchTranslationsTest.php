<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FrenchTranslationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_messages_are_translated_not_raw_keys(): void
    {
        $errors = Validator::make(['email' => 'pas-un-mail'], ['email' => 'email', 'name' => 'required'])->errors();

        $this->assertSame('Le champ adresse e-mail doit être une adresse e-mail valide.', $errors->first('email'));
        $this->assertSame('Le champ nom est obligatoire.', $errors->first('name'));
    }

    public function test_pagination_labels_are_translated(): void
    {
        $this->assertSame('Suivant &raquo;', trans('pagination.next'));
    }

    public function test_api_validation_details_are_readable(): void
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('t', ['resources:read'])->plainTextToken;

        $this->getJson('/api/v1/availability?date=xx', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(422)->assertJsonPath('error.details.date.0', 'Le champ date ne correspond pas au format Y-m-d.');
    }
}
