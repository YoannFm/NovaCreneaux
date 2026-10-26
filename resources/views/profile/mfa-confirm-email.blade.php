@extends('layouts.app')
@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6 col-xl-5 mx-auto">
        <h2 class="mb-4"><i class="bi bi-envelope-check me-2"></i>Confirmation par email</h2>
        <div class="card">
            <div class="card-body">
                @if(session('status') === 'code-sent')
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-1"></i> Code envoyé à {{ auth()->user()->email }}
                    </div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                <p class="text-muted small mb-3">
                    Un code à 6 chiffres a été envoyé à votre adresse email. Saisissez-le pour activer la MFA par email.
                </p>
                <form method="POST" action="{{ route('mfa.email.confirm.post') }}">
                    @csrf
                    <div class="mb-4">
                        <label class="form-label">Code de vérification</label>
                        <input type="text" name="code" class="form-control font-monospace @error('code') is-invalid @enderror"
                               inputmode="numeric" pattern="\d{6}" maxlength="6" autofocus
                               placeholder="000000"
                               style="max-width:200px;font-size:1.3rem;letter-spacing:.3em;text-align:center;">
                        @error('code')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-shield-check me-1"></i> Confirmer
                        </button>
                        <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
