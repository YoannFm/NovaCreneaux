{{--
    Modele commun des e-mails : bandeau a la couleur de Parametres > E-mail (sinon l'accent du site),
    nom du site, contenu (sections title et content), puis la signature reglable.
    Styles en ligne : la plupart des messageries ignorent les feuilles de style.
--}}
@php
    $mailColor = \App\Support\Customization::mailColor();
    $signature = trim((string) setting('mail_signature', ''));
    $appName = setting('app_name', 'NovaCreneaux');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('title')</title></head>
<body style="margin:0;padding:24px 12px;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1f2933;">
<div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">
    <div style="height:6px;background:{{ $mailColor }};"></div>
    <div style="padding:24px 28px;">
        <div style="font-size:13px;font-weight:bold;letter-spacing:.04em;text-transform:uppercase;color:#6b7280;margin-bottom:14px;">{{ $appName }}</div>
        <h2 style="margin:0 0 16px;font-size:20px;color:#1f2933;">@yield('title')</h2>
        <div style="font-size:15px;line-height:1.55;">
            @yield('content')
        </div>
    </div>
    <div style="padding:16px 28px;background:#f9fafb;border-top:1px solid #e5e7eb;font-size:12px;line-height:1.5;color:#6b7280;">
        @if($signature !== '')
            <div style="margin-bottom:8px;color:#374151;">{!! nl2br(e($signature)) !!}</div>
        @endif
        Message envoyé automatiquement par {{ $appName }} : merci de ne pas y répondre.
    </div>
</div>
</body>
</html>
