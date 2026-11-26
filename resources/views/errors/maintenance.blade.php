@extends('errors.layout')
@section('title', 'Maintenance')
@section('heading', 'Site en maintenance')
@section('message', $message !== '' ? $message : 'Le service est temporairement indisponible.')
@section('actions')
    <a href="{{ route('login') }}">Connexion administrateur</a>
@endsection
