@extends('layouts.app')
@php $pageTitle = 'Accueil'; @endphp

@push('header-styles')
<style>
    .home-hero {
        background: linear-gradient(135deg, var(--accent) 0%, color-mix(in srgb, var(--accent) 60%, #000) 100%);
        border-radius: 12px;
        padding: 2.5rem 2rem;
        color: #fff;
        margin-bottom: 2rem;
    }
    .home-hero h1 { font-size: 1.75rem; font-weight: 800; margin-bottom: .5rem; color: #fff; }
    .home-hero p  { opacity: .85; margin-bottom: 0; font-size: 1rem; }

    .quick-card {
        display: flex; flex-direction: column; align-items: flex-start;
        background: var(--card-bg);
        border: 1px solid rgba(255,255,255,.06);
        border-radius: 10px;
        padding: 1.5rem;
        text-decoration: none;
        color: var(--text-primary);
        transition: transform .15s, box-shadow .15s, border-color .15s;
        height: 100%;
    }
    [data-bs-theme="light"] .quick-card { border-color: rgba(0,0,0,.08); }
    .quick-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 24px rgba(0,0,0,.2);
        border-color: var(--accent);
        color: var(--text-primary);
        text-decoration: none;
    }
    .quick-card .qc-icon {
        width: 48px; height: 48px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem;
        margin-bottom: 1rem;
        flex-shrink: 0;
    }
    .quick-card .qc-title { font-weight: 700; font-size: 1rem; margin-bottom: .25rem; }
    .quick-card .qc-desc  { font-size: .82rem; color: var(--text-muted); margin: 0; }
    .quick-card .qc-arrow {
        margin-top: auto; padding-top: 1rem;
        font-size: .78rem; font-weight: 600; text-transform: uppercase;
        letter-spacing: .5px; color: var(--accent-text);
        display: flex; align-items: center; gap: .35rem;
    }

    .stat-card {
        background: var(--card-bg);
        border: 1px solid rgba(255,255,255,.06);
        border-radius: 10px;
        padding: 1.25rem 1.5rem;
        display: flex; align-items: center; gap: 1rem;
    }
    [data-bs-theme="light"] .stat-card { border-color: rgba(0,0,0,.08); }
    .stat-icon {
        width: 44px; height: 44px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.25rem; flex-shrink: 0;
    }
    .stat-value { font-size: 1.75rem; font-weight: 800; line-height: 1; color: #e2e8f0 !important; }
    [data-bs-theme="light"] .stat-value { color: #1a1a2e !important; }
    .stat-label { font-size: .78rem; color: var(--text-muted); margin-top: .15rem; }

    .upcoming-item {
        display: flex; align-items: center; gap: 1rem;
        padding: .75rem 0;
        border-bottom: 1px solid rgba(255,255,255,.06);
    }
    [data-bs-theme="light"] .upcoming-item { border-bottom-color: rgba(0,0,0,.07); }
    .upcoming-item:last-child { border-bottom: none; padding-bottom: 0; }
    .upcoming-date-badge {
        min-width: 52px; text-align: center;
        background: var(--accent-light, rgba(239,183,2,.12));
        border-radius: 8px; padding: .35rem .4rem;
        flex-shrink: 0;
    }
    .upcoming-date-badge .day  { font-size: 1.3rem; font-weight: 800; color: var(--accent-text); line-height: 1; }
    .upcoming-date-badge .mon  { font-size: .65rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: .5px; }
    .upcoming-slot  { font-weight: 600; font-size: .9rem; }
    .upcoming-res   { font-size: .78rem; color: var(--text-muted); margin-top: .1rem; }

    .resource-pill {
        display: inline-flex; align-items: center; gap: .5rem;
        background: var(--card-bg);
        border: 1px solid rgba(255,255,255,.07);
        border-radius: 8px;
        padding: .6rem 1rem;
        font-size: .85rem; font-weight: 500;
        color: var(--text-primary);
    }
    [data-bs-theme="light"] .resource-pill { border-color: rgba(0,0,0,.08); }
    .resource-pill .dot {
        width: 8px; height: 8px; border-radius: 50%;
        background: var(--accent); flex-shrink: 0;
    }
</style>
@endpush

@section('content')

{{-- Hero --}}
<div class="home-hero">
    <h1>Bonjour, {{ auth()->user()->name }} !</h1>
    @if(setting('home_message') && !empty(strip_tags(setting('home_message'))))
        <div style="opacity:.9;">{!! \App\Support\HtmlSanitizer::clean(setting('home_message')) !!}</div>
    @else
        <p>Bienvenue sur {{ setting('app_name','NovaCreneaux') }}. Reservez vos creneaux en quelques clics.</p>
    @endif
</div>

{{-- Stats --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,183,2,.15);color:var(--accent-text);">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div>
                <div class="stat-value">{{ $slotsAvailableToday }}</div>
                <div class="stat-label">Creneaux disponibles aujourd'hui</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,.12);color:#22c55e;">
                <i class="bi bi-bookmark-check"></i>
            </div>
            <div>
                <div class="stat-value">{{ $totalUpcoming }}</div>
                <div class="stat-label">Mes reservations a venir</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(99,102,241,.15);color:#818cf8;">
                <i class="bi bi-collection"></i>
            </div>
            <div>
                <div class="stat-value">{{ $resourcesPreview->count() }}</div>
                <div class="stat-label">Ressources disponibles</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">

    {{-- Acces rapides --}}
    <div class="col-lg-8">
        <h5 class="mb-3 fw-bold" style="font-size:.9rem;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);">
            <i class="bi bi-lightning-charge me-1"></i> Acces rapides
        </h5>
        <div class="row g-3">
            <div class="col-sm-6">
                <a href="{{ route('reservations.index') }}" class="quick-card">
                    <div class="qc-icon" style="background:rgba(239,183,2,.15);color:var(--accent-text);">
                        <i class="bi bi-calendar-plus"></i>
                    </div>
                    <div class="qc-title">Reserver un creneau</div>
                    <p class="qc-desc">Consultez les disponibilites et reservez votre place pour aujourd'hui ou demain.</p>
                    <div class="qc-arrow"><i class="bi bi-arrow-right"></i> Voir les creneaux</div>
                </a>
            </div>
            <div class="col-sm-6">
                <a href="{{ route('reservations.my') }}" class="quick-card">
                    <div class="qc-icon" style="background:rgba(34,197,94,.12);color:#22c55e;">
                        <i class="bi bi-bookmark-check"></i>
                    </div>
                    <div class="qc-title">Mes reservations</div>
                    <p class="qc-desc">Retrouvez l'historique de vos reservations et annulez si necessaire.</p>
                    <div class="qc-arrow"><i class="bi bi-arrow-right"></i> Voir mes reservations</div>
                </a>
            </div>
            <div class="col-sm-6">
                <a href="{{ route('profile.edit') }}" class="quick-card">
                    <div class="qc-icon" style="background:rgba(99,102,241,.15);color:#818cf8;">
                        <i class="bi bi-person-circle"></i>
                    </div>
                    <div class="qc-title">Mon profil</div>
                    <p class="qc-desc">Modifiez vos informations personnelles et vos preferences de theme.</p>
                    <div class="qc-arrow"><i class="bi bi-arrow-right"></i> Gerer mon profil</div>
                </a>
            </div>
            @if(auth()->user()->isAdmin())
            <div class="col-sm-6">
                <a href="{{ route('admin.dashboard') }}" class="quick-card">
                    <div class="qc-icon" style="background:rgba(239,68,68,.1);color:#ef4444;">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div class="qc-title">Panel administrateur</div>
                    <p class="qc-desc">Gerez les ressources, utilisateurs, reservations et parametres du site.</p>
                    <div class="qc-arrow"><i class="bi bi-arrow-right"></i> Acceder au panel</div>
                </a>
            </div>
            @endif
        </div>

        {{-- Ressources disponibles --}}
        @if($resourcesPreview->isNotEmpty())
        <div class="mt-4">
            <h5 class="mb-3 fw-bold" style="font-size:.9rem;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);">
                <i class="bi bi-collection me-1"></i> Ressources ouvertes aujourd'hui
            </h5>
            <div class="d-flex flex-wrap gap-2">
                @foreach($resourcesPreview as $resource)
                    <a href="{{ route('reservations.index') }}" class="resource-pill" style="text-decoration:none;">
                        <span class="dot"></span>
                        {{ $resource->name }}
                        <small style="color:var(--text-muted);">
                            {{ $resource->open_time ? substr($resource->open_time,0,5) : '' }}
                            @if($resource->close_time) – {{ substr($resource->close_time,0,5) }} @endif
                        </small>
                    </a>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Prochaines reservations --}}
    <div class="col-lg-4">
        <h5 class="mb-3 fw-bold" style="font-size:.9rem;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);">
            <i class="bi bi-clock me-1"></i> A venir
        </h5>
        <div class="stat-card" style="flex-direction:column;align-items:stretch;padding:1.25rem;">
            @forelse($upcoming as $resa)
            <div class="upcoming-item">
                <div class="upcoming-date-badge">
                    <div class="day">{{ \Carbon\Carbon::parse($resa->reservation_date)->format('d') }}</div>
                    <div class="mon">{{ \Carbon\Carbon::parse($resa->reservation_date)->translatedFormat('M') }}</div>
                </div>
                <div>
                    <div class="upcoming-slot">{{ $resa->slot->name ?? '-' }}</div>
                    <div class="upcoming-res">
                        {{ $resa->slot->resource->name ?? '' }}
                        @if($resa->slot)
                            &middot; {{ substr($resa->slot->start_time,0,5) }}–{{ substr($resa->slot->end_time,0,5) }}
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <div class="text-center py-3" style="color:var(--text-muted);">
                <i class="bi bi-calendar-x d-block mb-2" style="font-size:2rem;opacity:.4;"></i>
                <span style="font-size:.85rem;">Aucune reservation a venir</span>
            </div>
            @endforelse

            @if($totalUpcoming > 3)
            <a href="{{ route('reservations.my') }}" class="d-block text-center mt-3"
               style="font-size:.8rem;font-weight:600;color:var(--accent-text);text-decoration:none;">
                Voir toutes les reservations ({{ $totalUpcoming }})
            </a>
            @elseif($upcoming->isEmpty())
            <a href="{{ route('reservations.index') }}" class="btn btn-primary btn-sm w-100 mt-2">
                <i class="bi bi-calendar-plus me-1"></i> Faire une reservation
            </a>
            @endif
        </div>
    </div>

</div>
@endsection
