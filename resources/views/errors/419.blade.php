@extends('errors.layout')
@section('title', 'Page expirée')
@section('code', '419')
@section('heading', 'Page expirée')
@section('message', "La page est restée ouverte trop longtemps : par sécurité, le formulaire n'a pas été envoyé.\nRevenez en arrière, rechargez la page puis recommencez.")
@section('actions')
    <a class="primary" href="{{ url()->previous() }}">Revenir en arrière</a>
    <a href="{{ url('/') }}">Accueil</a>
@endsection
