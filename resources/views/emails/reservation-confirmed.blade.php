@extends('emails.layout')
@section('title', 'Réservation confirmée')
@section('content')
    <p>Bonjour <strong>{{ $reservation->user->name }}</strong>,</p>
    <p>Votre réservation est confirmée :</p>
    @include('emails._reservation')
@endsection
