<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\UserCsvImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** L'import accepte les CSV des tableurs : point-virgule, Windows-1252, marque BOM, lignes vides. */
class UserCsvImportTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private function upload(string $content)
    {
        return $this->actingAs($this->admin())->post(route('admin.users.import.store'), [
            'csv' => UploadedFile::fake()->createWithContent('eleves.csv', $content),
        ]);
    }

    public function test_an_excel_csv_with_semicolons_is_imported(): void
    {
        $this->upload("Nom;Prenom;Classe;Email\r\nMartin;Lea;2nde 3;lea.martin@exemple.fr\r\n")
            ->assertSessionHas('success', '1 utilisateur(s) importé(s), 0 ignoré(s).');

        $this->assertSame('Lea Martin', User::where('email', 'lea.martin@exemple.fr')->value('name'));
    }

    public function test_a_windows_1252_file_keeps_its_accents(): void
    {
        $this->upload(mb_convert_encoding("Martin,Léa,2nde 3,lea.martin@exemple.fr\nDupré,Zoé,1ère G,zoe.dupre@exemple.fr\n", 'Windows-1252', 'UTF-8'))
            ->assertSessionHas('success', '2 utilisateur(s) importé(s), 0 ignoré(s).');

        $this->assertSame('Léa Martin', User::where('email', 'lea.martin@exemple.fr')->value('name'));
        $this->assertSame('1ère G', User::where('email', 'zoe.dupre@exemple.fr')->value('classe'));
    }

    public function test_the_bom_header_and_empty_lines_are_not_counted_as_ignored(): void
    {
        $this->upload("\xEF\xBB\xBFNom;Prénom;Classe;Email\n\nMartin;Léa;2nde 3;lea.martin@exemple.fr\n;;;\n")
            ->assertSessionHas('success', '1 utilisateur(s) importé(s), 0 ignoré(s).');
    }

    public function test_each_ignored_line_says_why(): void
    {
        $this->student(['email' => 'pris@exemple.fr']);

        $this->upload("Nom,Prenom,Classe,Email\nA,B,C,pas-une-adresse\nD,E,F,pris@exemple.fr\nG,H,I,\n")
            ->assertSessionHas('success', "0 utilisateur(s) importé(s), 3 ignoré(s). Ligne 2 : adresse e-mail invalide (pas-une-adresse) ; ligne 3 : adresse déjà utilisée (pris@exemple.fr) ; ligne 4 : adresse e-mail absente.");
    }

    public function test_an_error_in_the_middle_creates_no_account_at_all(): void
    {
        $calls = 0;
        Event::listen('eloquent.creating: ' . User::class, function () use (&$calls) {
            if (++$calls === 2) {
                throw new \RuntimeException('Incorrect string value');
            }
        });
        $file = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($file, "Martin,Lea,2nde,lea@exemple.fr\nDupre,Zoe,1ere,zoe@exemple.fr\n");

        try {
            app(UserCsvImport::class)->import($file);
            $this->fail('exception attendue');
        } catch (\RuntimeException) {
            $this->assertFalse(User::where('email', 'lea@exemple.fr')->exists(), 'la premiere ligne est annulee avec le reste');
        } finally {
            unlink($file);
        }
    }
}
