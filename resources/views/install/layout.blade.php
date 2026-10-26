<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - NovaCreneaux</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --accent:#2978FF; --card-bg:#212227; --body-bg:#111113; --card-border:#2E2E34; --text-primary:#e2e8f0; --text-muted:#9ca3af; }
        body { font-family:'Rubik',sans-serif; background:var(--body-bg); color:var(--text-primary); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem 1rem; }
        .wizard { background:var(--card-bg); border:1px solid var(--card-border); border-radius:12px; padding:2.5rem; max-width:580px; width:100%; box-shadow:0 20px 40px rgba(0,0,0,.4); }
        .wizard-logo { font-size:1.8rem; font-weight:800; color:var(--accent); }
        .step-indicator { display:flex; gap:.5rem; margin-bottom:2rem; }
        .step { flex:1; height:4px; border-radius:2px; background:var(--card-border); }
        .step.active { background:var(--accent); }
        .step.done { background:#22c55e; }
        .form-control,.form-select { background:var(--body-bg); border-color:var(--card-border); color:var(--text-primary); }
        .form-control:focus,.form-select:focus { background:var(--body-bg); border-color:var(--accent); color:var(--text-primary); box-shadow:0 0 0 3px rgba(41,120,255,.12); }
        .btn-accent { background:var(--accent); color:#fff; border:none; } .btn-accent:hover { background:#1e5fd6; color:#fff; }
        a { color:var(--accent); }
    </style>
</head>
<body>
<div class="wizard">
    <div class="wizard-logo mb-1"><i class="bi bi-calendar-check"></i> NovaCreneaux</div>
    <p style="color:var(--text-muted)" class="small mb-3">Assistant d'installation</p>
    @yield('steps')
    @yield('content')
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
