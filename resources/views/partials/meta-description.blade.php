{{-- Description du site (Parametres > General) pour les moteurs de recherche, sur les pages publiques. --}}
@php($siteDescription = (string) setting('site_description', ''))
@if($siteDescription !== '')
    <meta name="description" content="{{ $siteDescription }}">
@endif
