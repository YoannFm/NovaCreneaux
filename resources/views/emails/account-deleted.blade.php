@extends('emails.layout')
@section('title', 'Compte supprimé')
@section('content')
    <p>Bonjour <strong>{{ $name }}</strong>,</p>
    @if($byAdmin)
        <p>Votre compte {{ setting('app_name', 'NovaCreneaux') }} a été supprimé par un administrateur. Vos réservations ont été supprimées avec lui.</p>
        <p>Si vous pensez qu'il s'agit d'une erreur, contactez {{ \App\Support\Customization::get('contact_label') }}.</p>
    @else
        <p>Votre compte {{ setting('app_name', 'NovaCreneaux') }} a bien été supprimé, à votre demande. Vos réservations ont été supprimées avec lui.</p>
        <p>Si vous n'êtes pas à l'origine de cette suppression, prévenez {{ \App\Support\Customization::get('contact_label') }} sans attendre.</p>
    @endif
@endsection
