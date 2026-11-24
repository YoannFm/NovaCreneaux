@extends('emails.layout')
@section('title', 'Réservation annulée')
@section('content')
    <p>Bonjour <strong>{{ $reservation->user->name }}</strong>,</p>
    <p>Votre réservation a été annulée{{ $byAdmin ? ' par un administrateur' : '' }} :</p>
    @include('emails._reservation')
    @if($reason)
        <p style="margin-top:16px;"><strong>Motif :</strong> {{ $reason }}</p>
    @endif
@endsection
