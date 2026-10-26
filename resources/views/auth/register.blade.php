@extends('layouts.app')
@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card p-4">
            <h4 class="mb-4 text-center" style="color:var(--accent-text)"><i class="bi bi-person-plus"></i> Inscription</h4>
            <form method="POST" action="{{ route('register') }}">
                @csrf
                <div class="mb-3"><label class="form-label">Nom complet *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required></div>
                <div class="mb-3"><label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required></div>
                <div class="mb-3"><label class="form-label">Classe</label>
                    <input type="text" name="classe" class="form-control" placeholder="ex: 2nde A" value="{{ old('classe') }}"></div>
                <div class="mb-3"><label class="form-label">Mot de passe *</label>
                    <input type="password" name="password" class="form-control" required></div>
                <div class="mb-4"><label class="form-label">Confirmer le mot de passe *</label>
                    <input type="password" name="password_confirmation" class="form-control" required></div>
                @if(!empty(strip_tags($terms ?? '')))
                <div class="mb-3">
                    <div class="border rounded p-3 mb-2" style="max-height:160px;overflow-y:auto;font-size:.85rem;">
                        {!! $terms !!}
                    </div>
                    <div class="form-check">
                        <input class="form-check-input @error('terms_accepted') is-invalid @enderror"
                               type="checkbox" name="terms_accepted" id="terms_accepted" value="1"
                               {{ old('terms_accepted') ? 'checked' : '' }} required>
                        <label class="form-check-label" for="terms_accepted">
                            J'accepte les conditions d'utilisation
                        </label>
                        @error('terms_accepted')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
                @endif
                @if($errors->any())<div class="alert alert-danger small">{{ $errors->first() }}</div>@endif
                <button type="submit" class="btn btn-primary w-100">S'inscrire</button>
                <p class="text-center small mt-3" style="color:var(--text-muted)">Déjà un compte ? <a href="{{ route('login') }}">Se connecter</a></p>
            </form>
        </div>
    </div>
</div>
@endsection
