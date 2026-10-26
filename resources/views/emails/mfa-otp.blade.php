<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family: system-ui, -apple-system, sans-serif; background:#111113; color:#e2e8f0; margin:0; padding:40px 20px; }
.wrap { max-width:480px; margin:0 auto; background:#212227; border:1px solid rgba(255,255,255,.06); border-radius:8px; padding:2.5rem 2rem; }
.brand { font-size:1.5rem; font-weight:800; color:#efb703; margin-bottom:1.5rem; }
h2 { font-size:1.1rem; margin:0 0 .5rem; }
p { color:#9ca3af; font-size:.9rem; line-height:1.6; }
.code-box { background:#111113; border:2px solid #efb703; border-radius:6px; text-align:center; padding:1.5rem; margin:1.5rem 0; }
.code { font-size:2.5rem; font-weight:800; letter-spacing:.5rem; color:#efb703; font-family:monospace; }
.expiry { font-size:.78rem; color:#9ca3af; margin-top:.5rem; }
.footer { margin-top:2rem; padding-top:1.5rem; border-top:1px solid rgba(255,255,255,.06); font-size:.75rem; color:#6b7280; }
</style>
</head>
<body>
<div class="wrap">
    <div class="brand">{{ setting('app_name','NovaCreneaux') }}</div>
    <h2>Code de verification</h2>
    <p>Voici votre code de double authentification. Il est valable <strong style="color:#e2e8f0;">15 minutes</strong>.</p>
    <div class="code-box">
        <div class="code">{{ $code }}</div>
        <div class="expiry">Expire dans 15 minutes</div>
    </div>
    <p>Si vous n'etes pas a l'origine de cette demande, ignorez cet email. Votre compte reste securise.</p>
    <div class="footer">
        Envoye par {{ setting('app_name','NovaCreneaux') }} &mdash; Ne pas repondre a cet email.
    </div>
</div>
</body>
</html>
