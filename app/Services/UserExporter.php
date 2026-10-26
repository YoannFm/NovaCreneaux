<?php
namespace App\Services;
use App\Support\CsvCell;
use Illuminate\Support\Collection;

/** Export CSV des utilisateurs (ZIP complet et selection multiple). Les utilisateurs doivent avoir `role` et `reservations_count`. */
class UserExporter {
    public function csv(Collection $users): string {
        $csv = implode(',', ['Nom', 'Email', 'Classe', 'Rôle', 'Actif', 'Réservations', 'Inscrit le']) . "\n";
        foreach ($users as $u) {
            $csv .= implode(',', [
                $this->text($u->name),
                $this->text($u->email),
                $this->text($u->classe),
                $this->text($u->role?->label ?? 'Aucun'),
                $u->is_active ? 'Oui' : 'Non',
                $u->reservations_count,
                $u->created_at->format('d/m/Y'),
            ]) . "\n";
        }

        return $csv;
    }

    private function text(?string $value): string {
        return '"' . str_replace('"', '""', CsvCell::safe($value)) . '"';
    }
}
