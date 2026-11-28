{{-- Bouton d'un e-mail, a la couleur des e-mails, texte lisible sur ce fond. Parametres : $url, $label --}}
@php $mailColor = \App\Support\Customization::mailColor(); @endphp
<p style="text-align:center;margin:26px 0;">
    <a href="{{ $url }}" style="background:{{ $mailColor }};color:{{ \App\Support\Color::contrastText($mailColor) }};text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:4px;display:inline-block;">{{ $label }}</a>
</p>
