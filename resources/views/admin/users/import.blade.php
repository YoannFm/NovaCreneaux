@extends('layouts.admin')
@php $pageTitle = 'Import CSV'; @endphp
@section('content')
<div class="card p-4" style="max-width:500px">
    <p class="small mb-3" style="color:var(--text-muted)">Format : <code>Nom,Prenom,Classe,Email</code> (avec ou sans ligne d'en-tête).</p>
    <form method="POST" action="{{ route('admin.users.import.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3"><label class="form-label">Fichier CSV *</label>
            <input type="file" name="csv" class="form-control" accept=".csv,.txt" required></div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Importer</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Annuler</a>
        </div>
        @if($errors->any())<div class="alert alert-danger mt-3">{{ $errors->first() }}</div>@endif
    </form>
</div>
@endsection
