<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Import de comptes depuis un CSV "Nom, Prenom, Classe, Email" (Admin > Utilisateurs > Import).
 *
 * Accepte ce que produit un tableur : separateur virgule, point-virgule (Excel en francais) ou tabulation,
 * encodage UTF-8 (avec ou sans marque BOM) ou Windows-1252 (Excel "CSV separateur virgule"). Tout ou rien :
 * une erreur annule l'import entier, aucun compte n'est cree a moitie. Chaque ligne ignoree garde son motif.
 */
class UserCsvImport
{
    private const DELIMITERS = [';', ',', "\t"];
    private const UTF8_BOM = "\xEF\xBB\xBF";

    /** @return array{created: int, skipped: list<string>} skipped : "ligne N : motif" */
    public function import(string $path): array
    {
        $rows = $this->rows((string) file_get_contents($path));
        $defaultRoleId = Role::defaultUser()?->id;
        $created = 0;
        $skipped = [];

        DB::transaction(function () use ($rows, $defaultRoleId, &$created, &$skipped) {
            foreach ($rows as $line => $row) {
                [$nom, $prenom, $classe, $email] = array_pad(array_map('trim', $row), 4, '');
                $email = User::normalizeEmail($email); // "Lea.Martin@..." et "lea.martin@..." sont le meme compte
                $reason = match (true) {
                    $email === '' => 'adresse e-mail absente',
                    !filter_var($email, FILTER_VALIDATE_EMAIL) => "adresse e-mail invalide ({$email})",
                    User::where('email', $email)->exists() => "adresse déjà utilisée ({$email})",
                    default => null,
                };
                if ($reason !== null) {
                    $skipped[] = "ligne {$line} : {$reason}";
                    continue;
                }

                User::create([
                    'name' => mb_substr(trim("{$prenom} {$nom}"), 0, 255),
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)),
                    'classe' => $classe !== '' ? mb_substr($classe, 0, User::CLASSE_MAX) : null,
                    'role_id' => $defaultRoleId,
                    'is_active' => true,
                ]);
                $created++;
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Lignes utiles, numerotees comme dans le fichier : sans ligne vide ni ligne d'en-tete.
     * @return array<int, list<string>>
     */
    private function rows(string $content): array
    {
        $content = $this->toUtf8($content);
        $delimiter = $this->delimiter($content);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];
        $line = 0;
        while (($row = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $line++;
            if ($row === [null] || implode('', array_map('trim', $row)) === '') {
                continue; // ligne vide
            }
            if ($rows === [] && $this->isHeader($row)) {
                continue;
            }
            $rows[$line] = $row;
        }
        fclose($stream);

        return $rows;
    }

    private function toUtf8(string $content): string
    {
        if (str_starts_with($content, self::UTF8_BOM)) {
            $content = substr($content, strlen(self::UTF8_BOM));
        }

        // Excel "CSV (separateur : virgule)" enregistre en Windows-1252 : "Léa" n'y est pas de l'UTF-8 valide
        return mb_check_encoding($content, 'UTF-8') ? $content : mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
    }

    /** Le separateur le plus frequent de la premiere ligne non vide (virgule par defaut). */
    private function delimiter(string $content): string
    {
        $first = strtok(ltrim($content), "\r\n") ?: '';
        $counts = array_map(fn (string $delimiter) => substr_count($first, $delimiter), self::DELIMITERS);

        return max($counts) > 0 ? self::DELIMITERS[array_search(max($counts), $counts, true)] : ',';
    }

    private function isHeader(array $row): bool
    {
        return Str::lower(Str::ascii(trim((string) ($row[0] ?? '')))) === 'nom';
    }
}
