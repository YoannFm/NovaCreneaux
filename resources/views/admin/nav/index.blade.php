@extends('layouts.admin')
@php $pageTitle = 'Navigation'; @endphp

@push('header-styles')
<style>
/* Les lignes s'intègrent dans la carte - séparateur fin, pas de fond propre */
#nav-list { border-radius: 6px; overflow: hidden; }

.nav-row {
    display: flex;
    align-items: center;
    gap: .85rem;
    padding: .9rem 1.1rem;
    border-bottom: 1px solid rgba(255,255,255,.06);
    cursor: grab;
    user-select: none;
    transition: background .1s;
}
[data-bs-theme="light"] .nav-row { border-bottom-color: rgba(0,0,0,.07); }
.nav-row:last-child { border-bottom: none; }
.nav-row:hover { background: rgba(255,255,255,.025); }
[data-bs-theme="light"] .nav-row:hover { background: rgba(0,0,0,.018); }
.nav-row:active { cursor: grabbing; }
.nav-row.dragging { opacity: .3; }
.nav-row.drag-over { background: color-mix(in srgb, var(--accent) 8%, transparent); }

.drag-handle { color: var(--text-muted); font-size: 1rem; flex-shrink: 0; opacity: .4; }
.nav-row-icon { font-size: .95rem; flex-shrink: 0; width: 18px; text-align: center; color: var(--text-muted); }
.nav-row-label { font-size: .9rem; font-weight: 600; flex: 1; min-width: 0; }

.nav-row-badges { display: flex; gap: .3rem; flex-shrink: 0; }
.nav-badge {
    font-size: .65rem; font-weight: 600; padding: 1px 7px; border-radius: 30px;
    background: rgba(255,255,255,.07); color: var(--text-muted);
}
[data-bs-theme="light"] .nav-badge { background: rgba(0,0,0,.06); }

.nav-row-type {
    font-size: .78rem; color: var(--text-muted); flex-shrink: 0;
    display: none; min-width: 130px; text-align: right;
}
@media (min-width: 768px) { .nav-row-type { display: block; } }

.nav-row-actions { display: flex; gap: .3rem; flex-shrink: 0; }
.nav-action-btn {
    width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    background: none; border: none; border-radius: 5px;
    font-size: .82rem; cursor: pointer; text-decoration: none;
    color: var(--text-muted); transition: background .1s, color .1s;
}
.nav-action-btn.edit:hover  { background: rgba(96,165,250,.15); color: #60a5fa; }
.nav-action-btn.del:hover   { background: rgba(239,68,68,.12); color: #ef4444; }
</style>
@endpush

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible mb-4">
        <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-list-ul me-2"></i>Navbar</span>
        <span class="text-muted small">{{ $items->count() }} élément(s)</span>
    </div>

    <div id="nav-list">
        @forelse($items as $item)
        <div class="nav-row" data-id="{{ $item->id }}" draggable="true">
            <i class="bi bi-grip-vertical drag-handle"></i>
            <i class="bi {{ $item->icon }} nav-row-icon"></i>
            <span class="nav-row-label">{{ $item->label }}</span>

            <div class="nav-row-badges">
                @if(!$item->is_active)
                    <span class="nav-badge">masqué</span>
                @endif
                @if($item->admin_only)
                    <span class="nav-badge">admin</span>
                @endif
                @if($item->target === '_blank')
                    <span class="nav-badge" title="Ouvre dans un nouvel onglet"><i class="bi bi-box-arrow-up-right"></i></span>
                @endif
            </div>

            <span class="nav-row-type">{{ $item->typeLabel() }}</span>

            <div class="nav-row-actions">
                <a href="{{ route('admin.nav.edit', $item->id) }}" class="nav-action-btn edit" title="Modifier">
                    <i class="bi bi-pencil"></i>
                </a>
                <form method="POST" action="{{ route('admin.nav.destroy', $item->id) }}" style="display:contents;">
                    @csrf @method('DELETE')
                    <button type="submit" class="nav-action-btn del" title="Supprimer"
                            onclick="return confirm({{ \Illuminate\Support\Js::from('Supprimer « ' . $item->label . ' » ?') }})">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </div>
        </div>
        @empty
        <div class="text-center py-5 text-muted" style="font-size:.85rem;">
            <i class="bi bi-link-45deg d-block mb-2" style="font-size:1.8rem;opacity:.3;"></i>
            Aucun élément. Cliquez sur <strong>+ Ajouter</strong>.
        </div>
        @endforelse
    </div>

    <div class="card-footer d-flex gap-2">
        <button id="btn-save-order" class="btn btn-success btn-sm" onclick="saveOrder()" disabled>
            <i class="bi bi-floppy me-1"></i> Sauvegarder l'ordre
        </button>
        <a href="{{ route('admin.nav.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Ajouter
        </a>
    </div>
</div>

<script>
(function () {
    var list    = document.getElementById('nav-list');
    var saveBtn = document.getElementById('btn-save-order');
    if (!list) return;
    var dragged = null;

    list.addEventListener('dragstart', function (e) {
        dragged = e.target.closest('.nav-row');
        if (dragged) dragged.classList.add('dragging');
    });
    list.addEventListener('dragend', function () {
        if (dragged) dragged.classList.remove('dragging');
        list.querySelectorAll('.nav-row').forEach(function (r) { r.classList.remove('drag-over'); });
        saveBtn.disabled = false;
    });
    list.addEventListener('dragover', function (e) {
        e.preventDefault();
        var over = e.target.closest('.nav-row');
        if (!over || over === dragged) return;
        list.querySelectorAll('.nav-row').forEach(function (r) { r.classList.remove('drag-over'); });
        over.classList.add('drag-over');
        var rect = over.getBoundingClientRect();
        list.insertBefore(dragged, e.clientY < rect.top + rect.height / 2 ? over : over.nextSibling);
    });

    window.saveOrder = function () {
        var ids = Array.from(list.querySelectorAll('.nav-row')).map(function (r) {
            return parseInt(r.dataset.id);
        });
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="bi bi-hourglass me-1"></i> Sauvegarde…';
        fetch('{{ route('admin.nav.reorder') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            body: JSON.stringify({ ids: ids }),
        }).then(function () {
            saveBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Ordre sauvegardé';
            setTimeout(function () { saveBtn.innerHTML = '<i class="bi bi-floppy me-1"></i> Sauvegarder l\'ordre'; }, 2000);
        }).catch(function () { saveBtn.disabled = false; saveBtn.innerHTML = '<i class="bi bi-floppy me-1"></i> Sauvegarder l\'ordre'; });
    };
})();
</script>
@endsection
