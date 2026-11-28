<?php
namespace App\Services;
use App\Models\Reservation;

/** Places libres par creneau et par date, calculees en une seule requete (site et API). */
class AvailabilityService {
    /**
     * @param iterable<\App\Models\Slot> $slots
     * @param list<string> $dates dates au format Y-m-d
     * @return array<int, array<string, int>> [id du creneau][date] => places libres
     */
    public function freeSpots(iterable $slots, array $dates): array {
        $slots = collect($slots)->keyBy('id');
        if ($slots->isEmpty() || $dates === []) return [];

        $taken = [];
        Reservation::whereIn('slot_id', $slots->keys())
            ->whereIn('reservation_date', $dates)
            ->selectRaw('slot_id, reservation_date, COUNT(*) as taken')
            ->groupBy('slot_id', 'reservation_date')
            ->get()
            ->each(function ($row) use (&$taken) {
                $taken[$row->slot_id][$row->reservation_date->toDateString()] = (int) $row->taken;
            });

        $free = [];
        foreach ($slots as $slot) {
            foreach ($dates as $date) {
                $free[$slot->id][$date] = max(0, $slot->max_capacity - ($taken[$slot->id][$date] ?? 0));
            }
        }
        return $free;
    }
}
