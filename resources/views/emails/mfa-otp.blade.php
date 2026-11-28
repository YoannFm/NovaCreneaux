@extends('emails.layout')
@section('title', 'Code de vérification')
@section('content')
    <p>Voici votre code de double authentification. Il est valable <strong>{{ \App\Services\OtpService::EXPIRY_MINUTES }} minutes</strong>.</p>
    <div style="border:2px solid {{ \App\Support\Customization::mailColor() }};border-radius:6px;text-align:center;padding:18px;margin:20px 0;">
        <div style="font-size:34px;font-weight:bold;letter-spacing:8px;font-family:'Courier New',monospace;color:#1f2933;">{{ $code }}</div>
    </div>
    <p style="color:#555;font-size:14px;">Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre compte reste sécurisé.</p>
@endsection
