@extends('layouts.admin')
@section('pageTitle', 'Pages')
@section('content')

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width:60px">#</th>
                    <th>Titre</th>
                    <th>Slug</th>
                    <th>Active</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pages as $page)
                    <tr>
                        <td class="text-muted small">{{ $page->id }}</td>
                        <td>{{ $page->title }}</td>
                        <td>
                            <a href="{{ setting('site_url', url('/')) }}/p/{{ $page->slug }}"
                               target="_blank" class="text-primary small">{{ $page->slug }}</a>
                        </td>
                        <td>
                            @if($page->is_enabled)
                                <span class="badge bg-success">Oui</span>
                            @else
                                <span class="badge bg-secondary">Non</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.pages.edit', $page) }}" class="btn btn-sm btn-outline-secondary me-1">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                onclick="document.getElementById('confirmDeleteForm').action='{{ route('admin.pages.destroy', $page) }}';new bootstrap.Modal(document.getElementById('confirmDeleteModal')).show();">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <i class="bi bi-file-earmark d-block mb-2" style="font-size:2rem;opacity:.3"></i>
                            Aucune page.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <a href="{{ route('admin.pages.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Ajouter
        </a>
    </div>
</div>

@if($pages->hasPages())
<div class="mt-4">{{ $pages->links() }}</div>
@endif

@endsection
