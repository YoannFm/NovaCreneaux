@extends('layouts.app')
@php $pageTitle = 'Reserver'; @endphp

@push('header-styles')
<style>
    /* ── Page header ── */
    .reserve-header {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 1.75rem; gap: 1rem; flex-wrap: wrap;
    }
    .reserve-header h1 { font-size: 1.4rem; font-weight: 800; margin: 0; }
    .reserve-header .subtitle { font-size: .85rem; color: var(--text-muted); margin-top: .15rem; }

    /* ── Date tabs ── */
    .date-tabs { display: flex; gap: .5rem; margin-bottom: 1.75rem; flex-wrap: wrap; }
    .date-tab {
        flex: 1; min-width: 140px;
        background: var(--card-bg);
        border: 2px solid rgba(255,255,255,.07);
        border-radius: 10px;
        padding: .9rem 1.25rem;
        cursor: pointer; text-decoration: none; color: var(--text-primary);
        transition: border-color .15s;
        display: flex; align-items: center; gap: .75rem;
    }
    [data-bs-theme="light"] .date-tab { border-color: rgba(0,0,0,.08); }
    .date-tab:hover { border-color: var(--accent); color: var(--text-primary); text-decoration: none; }
    .date-tab.active { border-color: var(--accent); background: color-mix(in srgb, var(--accent) 8%, var(--card-bg)); }
    .date-tab .dt-day  { font-size: 1.6rem; font-weight: 800; line-height: 1; color: var(--accent-text); }
    .date-tab .dt-label{ font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .6px; color: var(--text-muted); }
    .date-tab .dt-name { font-size: .9rem; font-weight: 600; text-transform: capitalize; }

    /* ── Resource card ── */
    .resource-card {
        background: var(--card-bg);
        border: 1px solid rgba(255,255,255,.06);
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 1.25rem;
    }
    [data-bs-theme="light"] .resource-card { border-color: rgba(0,0,0,.08); }
    .resource-card-header {
        padding: 1.25rem 1.5rem 1rem;
        border-bottom: 1px solid rgba(255,255,255,.06);
        display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;
    }
    [data-bs-theme="light"] .resource-card-header { border-bottom-color: rgba(0,0,0,.07); }
    .resource-name { font-size: 1.1rem; font-weight: 700; color: var(--accent-text); display: flex; align-items: center; gap: .5rem; }
    .resource-desc { font-size: .82rem; color: var(--text-muted); margin-top: .2rem; }
    .resource-hours { font-size: .78rem; color: var(--text-muted); white-space: nowrap; display: flex; align-items: center; gap: .3rem; flex-shrink: 0; }
    .resource-closed-badge {
        display: inline-flex; align-items: center; gap: .35rem;
        background: rgba(239,68,68,.1); color: #ef4444;
        border-radius: 6px; padding: .3rem .75rem;
        font-size: .78rem; font-weight: 600;
    }

    /* ── Slot grid ── */
    .slots-grid { padding: 1.25rem 1.5rem; display: grid; gap: .85rem; }
    @media (max-width: 575px) { .slots-grid { grid-template-columns: repeat(2, 1fr) !important; } }

    /* ── Slot card ── */
    .slot-item {
        border-radius: 10px;
        border: 1.5px solid rgba(255,255,255,.08);
        padding: 1rem 1.1rem;
        background: color-mix(in srgb, var(--card-bg) 50%, var(--body-bg));
        transition: border-color .15s, transform .1s;
        position: relative; overflow: hidden;
    }
    [data-bs-theme="light"] .slot-item { border-color: rgba(0,0,0,.08); background: var(--body-bg); }
    .slot-item.available:hover { border-color: var(--accent); transform: translateY(-2px); }
    .slot-item.full { opacity: .5; }

    .slot-time { font-size: .95rem; font-weight: 700; margin-bottom: .1rem; }
    .slot-name { font-size: .78rem; color: var(--text-muted); margin-bottom: .75rem; }

    /* Jauge */
    .slot-gauge-wrap { margin-bottom: .75rem; }
    .slot-gauge-bar {
        height: 5px; border-radius: 3px;
        background: rgba(255,255,255,.1);
        overflow: hidden;
    }
    [data-bs-theme="light"] .slot-gauge-bar { background: rgba(0,0,0,.08); }
    .slot-gauge-fill {
        height: 100%; border-radius: 3px;
        transition: width .4s ease;
    }
    .slot-gauge-labels { display: flex; justify-content: space-between; margin-top: .3rem; font-size: .72rem; }
    .slot-gauge-avail { font-weight: 700; }
    .slot-gauge-total { color: var(--text-muted); }

    /* Bouton submit dans slot */
    .slot-btn {
        width: 100%; padding: .45rem .6rem;
        border-radius: 7px; border: none; cursor: pointer;
        font-size: .8rem; font-weight: 700; letter-spacing: .3px;
        text-transform: uppercase;
        transition: filter .15s, opacity .15s;
    }
    .slot-btn.can-book { background: var(--accent); color: #212227; }
    .slot-btn.can-book:hover { filter: brightness(1.1); }
    .slot-btn.cant-book { background: rgba(255,255,255,.07); color: var(--text-muted); cursor: not-allowed; }
    [data-bs-theme="light"] .slot-btn.cant-book { background: rgba(0,0,0,.06); }

    /* ── Empty state ── */
    .empty-state { text-align: center; padding: 3rem 1rem; color: var(--text-muted); }
    .empty-state i { font-size: 3rem; opacity: .3; display: block; margin-bottom: 1rem; }

    @media (max-width: 575px) {
        .slots-grid { grid-template-columns: 1fr 1fr; gap: .6rem; padding: 1rem; }
        .resource-card-header { flex-direction: column; }
    }
</style>
@endpush

@section('content')

<div class="reserve-header">
    <div>
        <h1><i class="bi bi-calendar-plus me-2" style="color:var(--accent-text);"></i>Reserver un creneau</h1>
        <div class="subtitle">Selectionnez une date puis un creneau disponible</div>
    </div>
    @if($errors->any())
    <div class="alert alert-danger mb-0 py-2 px-3" style="font-size:.85rem;">
        <i class="bi bi-exclamation-circle me-1"></i> {{ $errors->first() }}
    </div>
    @endif
</div>

{{-- Selecteur de date --}}
@php
    $tomorrow    = now()->addDay()->toDateString();
    $visibleDates = array_slice($dates, 0, 7);
    $hiddenDates  = array_slice($dates, 7);
@endphp
<div class="date-tabs" id="date-tabs">
    @foreach($visibleDates as $d)
    @php
        $dt = \Carbon\Carbon::parse($d)->locale('fr');
        if ($d === $today)    $label = "Aujourd'hui";
        elseif ($d === $tomorrow) $label = 'Demain';
        else                      $label = $dt->isoFormat('dddd');
    @endphp
    <a href="#" class="date-tab {{ $loop->first ? 'active' : '' }}" data-date="{{ $d }}">
        <div>
            <div class="dt-day">{{ $dt->format('d') }}</div>
        </div>
        <div>
            <div class="dt-name">{{ $label }}</div>
            <div class="dt-label">{{ $dt->isoFormat('MMMM YYYY') }}</div>
        </div>
        @if($d === $today)
            <span style="margin-left:auto;font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--accent-text);">Aujourd'hui</span>
        @elseif($d === $tomorrow)
            <span style="margin-left:auto;font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);">Demain</span>
        @endif
    </a>
    @endforeach

    @foreach($hiddenDates as $d)
    @php $dt = \Carbon\Carbon::parse($d)->locale('fr'); @endphp
    <a href="#" class="date-tab extra-date d-none" data-date="{{ $d }}">
        <div>
            <div class="dt-day">{{ $dt->format('d') }}</div>
        </div>
        <div>
            <div class="dt-name">{{ $dt->isoFormat('dddd') }}</div>
            <div class="dt-label">{{ $dt->isoFormat('MMMM YYYY') }}</div>
        </div>
    </a>
    @endforeach
</div>
@if(count($hiddenDates) > 0)
<div class="mb-3 text-center">
    <button id="show-more-dates" class="btn btn-sm btn-outline-secondary" style="font-size:.8rem;">
        <i class="bi bi-chevron-down me-1"></i> Voir plus ({{ count($hiddenDates) }} jours supplémentaires)
    </button>
</div>
@endif

{{-- Ressources --}}
@forelse($resources as $resource)
<div class="resource-card">
    <div class="resource-card-header">
        <div>
            <div class="resource-name">
                <i class="bi bi-building"></i> {{ $resource->name }}
            </div>
            @if($resource->description)
                <div class="resource-desc">{{ $resource->description }}</div>
            @endif
        </div>
        @if($resource->open_time && $resource->close_time)
        <div class="resource-hours">
            <i class="bi bi-clock"></i>
            {{ substr($resource->open_time,0,5) }} – {{ substr($resource->close_time,0,5) }}
        </div>
        @endif
    </div>

    {{-- Panneau par date --}}
    @foreach($dates as $date)
    @php
        $check       = $resource->canReserveFor($date);
        $isFirst     = $loop->first;
        $totalSlots  = $resource->activeSlots->count();
        $availSlots  = $resource->activeSlots->filter(fn($s) => ($counts[$s->id][$date] ?? $s->max_capacity) > 0)->count();
        $totalPlaces = $resource->activeSlots->sum('max_capacity');
        $takenPlaces = $resource->activeSlots->sum(fn($s) => $s->max_capacity - ($counts[$s->id][$date] ?? $s->max_capacity));
        $fillPct     = $totalPlaces > 0 ? round($takenPlaces / $totalPlaces * 100) : 0;
        $gaugeColor  = $fillPct >= 90 ? '#ef4444' : ($fillPct >= 60 ? '#f59e0b' : 'var(--accent-text)');
    @endphp
    <div class="date-panel {{ $isFirst ? '' : 'd-none' }}" data-panel="{{ $date }}">

        @if(!$check['allowed'])
        <div class="px-4 py-3 d-flex align-items-center gap-2" style="border-bottom:1px solid rgba(255,255,255,.05);">
            <span class="resource-closed-badge">
                <i class="bi bi-lock"></i> {{ $check['reason'] ?? 'Reservations fermees' }}
            </span>
        </div>
        @else
        {{-- Recap occupancy --}}
        <div style="padding:.9rem 1.5rem .6rem;border-bottom:1px solid rgba(255,255,255,.05);">
            <div class="d-flex align-items-center justify-content-between mb-1" style="font-size:.78rem;">
                <span style="color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;">
                    Occupation globale
                </span>
                <span style="font-weight:700;color:{{ $gaugeColor }};">{{ $fillPct }}%</span>
            </div>
            <div style="height:6px;border-radius:3px;background:rgba(255,255,255,.08);overflow:hidden;">
                <div style="height:100%;width:{{ $fillPct }}%;background:{{ $gaugeColor }};border-radius:3px;transition:width .5s ease;"></div>
            </div>
            <div style="font-size:.72rem;color:var(--text-muted);margin-top:.35rem;">
                {{ $takenPlaces }} place{{ $takenPlaces > 1 ? 's' : '' }} occupee{{ $takenPlaces > 1 ? 's' : '' }}
                sur {{ $totalPlaces }} &middot;
                {{ $availSlots }} creneau{{ $availSlots > 1 ? 'x' : '' }} disponible{{ $availSlots > 1 ? 's' : '' }}
            </div>
        </div>
        @endif

        {{-- Grille des créneaux --}}
        @php $cols = min($resource->activeSlots->count(), 5); @endphp
        <div class="slots-grid" style="grid-template-columns: repeat({{ max($cols, 1) }}, 1fr);">

            @forelse($resource->activeSlots as $slot)
            @php
                $available  = $counts[$slot->id][$date] ?? $slot->max_capacity;
                $canBook    = $available > 0 && $check['allowed'];
                $slotFill   = $slot->max_capacity > 0
                    ? round(($slot->max_capacity - $available) / $slot->max_capacity * 100)
                    : 100;
                $slotColor  = $slotFill >= 90 ? '#ef4444' : ($slotFill >= 60 ? '#f59e0b' : 'var(--accent-text)');
            @endphp
            <div class="slot-item {{ $canBook ? 'available' : 'full' }}">
                <div class="slot-time">
                    {{ substr($slot->start_time,0,5) }} – {{ substr($slot->end_time,0,5) }}
                </div>
                <div class="slot-name">{{ $slot->name }}</div>

                {{-- Jauge --}}
                <div class="slot-gauge-wrap">
                    <div class="slot-gauge-bar">
                        <div class="slot-gauge-fill" style="width:{{ $slotFill }}%;background:{{ $slotColor }};"></div>
                    </div>
                    <div class="slot-gauge-labels">
                        <span class="slot-gauge-avail" style="color:{{ $slotColor }};">
                            {{ $available }} libre{{ $available > 1 ? 's' : '' }}
                        </span>
                        <span class="slot-gauge-total">/ {{ $slot->max_capacity }}</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('reservations.store') }}">
                    @csrf
                    <input type="hidden" name="slot_id" value="{{ $slot->id }}">
                    <input type="hidden" name="reservation_date" value="{{ $date }}">
                    <button type="submit" class="slot-btn {{ $canBook ? 'can-book' : 'cant-book' }}"
                            {{ !$canBook ? 'disabled' : '' }}>
                        @if($available <= 0)
                            <i class="bi bi-x-circle me-1"></i> Complet
                        @elseif(!$check['allowed'])
                            <i class="bi bi-lock me-1"></i> Ferme
                        @else
                            <i class="bi bi-check-circle me-1"></i> Reserver
                        @endif
                    </button>
                </form>
            </div>
            @empty
            <div class="empty-state" style="grid-column:1/-1">
                <i class="bi bi-calendar-x"></i>
                Aucun creneau configure pour cette ressource.
            </div>
            @endforelse
        </div>
    </div>
    @endforeach

</div>
@empty
<div class="empty-state">
    <i class="bi bi-building-slash"></i>
    <p>Aucune ressource disponible pour le moment.</p>
</div>
@endforelse

@endsection

@push('footer-scripts')
<script>
(function () {
    var tabs   = document.querySelectorAll('.date-tab');
    var panels = document.querySelectorAll('.date-panel');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function (e) {
            e.preventDefault();
            var date = this.dataset.date;

            tabs.forEach(function (t) { t.classList.remove('active'); });
            this.classList.add('active');

            panels.forEach(function (p) {
                p.classList.toggle('d-none', p.dataset.panel !== date);
            });
        });
    });

    // Bouton "Voir plus" pour les dates au-delà de 7
    var showMoreBtn = document.getElementById('show-more-dates');
    if (showMoreBtn) {
        showMoreBtn.addEventListener('click', function () {
            var extras = document.querySelectorAll('.date-tab.extra-date');
            extras.forEach(function (t) { t.classList.remove('d-none'); });
            this.style.display = 'none';
        });
    }
})();
</script>
@endpush
