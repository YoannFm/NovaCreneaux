@extends('emails.layout')
@section('title', 'Adresse e-mail modifiée')
@section('content')
    <p>Bonjour <strong>{{ $name }}</strong>,</p>
    <p>L'adresse e-mail de votre compte {{ setting('app_name', 'NovaCreneaux') }} vient d'être remplacée par <strong>{{ $newEmail }}</strong>. Les prochains messages du site y seront envoyés.</p>
    <p>Si vous n'êtes pas à l'origine de ce changement, prévenez {{ \App\Support\Customization::get('contact_label') }} sans attendre : quelqu'un a peut-être utilisé votre compte.</p>
@endsection
