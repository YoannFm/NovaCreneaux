<?php

namespace App\Services\Configuration;

/** Bilan d'un import de configuration : elements crees ou mis a jour par type, elements laisses de cote. */
final class ImportReport
{
    /** type => [singulier, pluriel, feminin] */
    private const NOUNS = [
        'setting' => ['réglage', 'réglages', false],
        'role' => ['rôle', 'rôles', false],
        'resource' => ['ressource', 'ressources', true],
        'slot' => ['créneau', 'créneaux', false],
    ];

    /** @var array<string, array{created: int, updated: int}> */
    private array $counts = [];

    /** @var list<string> */
    private array $skipped = [];

    public function record(string $type, string $action): void
    {
        $this->counts[$type][$action] = ($this->counts[$type][$action] ?? 0) + 1;
    }

    public function skip(string $reason): void
    {
        $this->skipped[] = $reason;
    }

    public function skipped(): array
    {
        return $this->skipped;
    }

    public function changed(): bool
    {
        return $this->counts !== [];
    }

    /** Ex. "2 ressources créées, 1 rôle mis à jour" ; "aucun changement" si rien n'a bouge. */
    public function summary(): string
    {
        $parts = [];
        foreach (self::NOUNS as $type => [$singular, $plural, $feminine]) {
            foreach (['created' => 'créé', 'updated' => 'mis à jour'] as $action => $verb) {
                $n = $this->counts[$type][$action] ?? 0;
                if ($n === 0) {
                    continue;
                }
                if ($action === 'created') {
                    $verb .= ($feminine ? 'e' : '') . ($n > 1 ? 's' : '');
                } elseif ($feminine) {
                    $verb = 'mise' . ($n > 1 ? 's' : '') . ' à jour';
                }
                $parts[] = "{$n} " . ($n > 1 ? $plural : $singular) . " {$verb}";
            }
        }

        return $parts === [] ? 'aucun changement' : implode(', ', $parts);
    }
}
