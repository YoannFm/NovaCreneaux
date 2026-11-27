@extends('emails.layout')
@section('title', 'Horaire modifié')
@section('content')
    <p>Bonjour <strong>{{ $reservation->user->name }}</strong>,</p>
    <p>L'horaire d'un créneau que vous avez réservé a changé. Votre réservation est conservée :</p>
    @include('emails._reservation')
    <p style="margin-top:16px;">
        <strong>Nouvel horaire :</strong> {{ substr($reservation->slot->start_time, 0, 5) }} - {{ substr($reservation->slot->end_time, 0, 5) }}<br>
        <span style="color:#6b7280;">Ancien horaire : {{ $previous }}</span>
    </p>
    <p>Si ce nouvel horaire ne vous convient pas, vous pouvez annuler votre réservation depuis le site.</p>
@endsection
