@extends('emails.layout')
@section('title', 'Nouveau mot de passe')
@section('content')
    <p>Bonjour <strong>{{ $name }}</strong>,</p>
    <p>Une demande de réinitialisation du mot de passe de votre compte {{ setting('app_name', 'NovaCreneaux') }} a été faite. Cliquez sur le bouton pour en choisir un nouveau :</p>
    @include('emails._button', ['url' => $url, 'label' => 'Choisir un nouveau mot de passe'])
    <p style="color:#555;font-size:14px;">Ce lien est valable {{ $expiresInMinutes }} minutes et ne sert qu'une fois.</p>
    <p style="color:#555;font-size:14px;">Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe actuel reste valable.</p>
    <p style="color:#888;font-size:12px;word-break:break-all;margin-top:20px;">Si le bouton ne fonctionne pas, copiez cette adresse dans votre navigateur :<br>{{ $url }}</p>
@endsection
