@extends('emails.layout')
@section('title', 'Adresse e-mail')
@section('content')
    <p>Bonjour <strong>{{ $name }}</strong>,</p>
    <p>Pour utiliser votre compte {{ setting('app_name', 'NovaCreneaux') }}, confirmez que cette adresse e-mail vous appartient :</p>
    @include('emails._button', ['url' => $url, 'label' => 'Confirmer mon adresse'])
    <p style="color:#555;font-size:14px;">Ce lien est valable {{ $expiresInMinutes }} minutes.</p>
    <p style="color:#555;font-size:14px;">Si vous n'avez pas de compte sur ce site, ignorez ce message.</p>
    <p style="color:#888;font-size:12px;word-break:break-all;margin-top:20px;">Si le bouton ne fonctionne pas, copiez cette adresse dans votre navigateur :<br>{{ $url }}</p>
@endsection
