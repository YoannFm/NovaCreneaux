@extends('errors.layout')
@section('title', 'Service indisponible')
@section('heading', 'Service momentanément indisponible')
@section('message', 'Le site revient dans quelques instants. Merci de votre patience.')
@section('actions')
    <a class="primary" href="{{ url('/') }}">Réessayer</a>
@endsection
