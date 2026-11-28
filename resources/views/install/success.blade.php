<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation réussie - Mnemo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --accent: #6366f1;
            --card-bg: #1a1a1a;
            --text-primary: #e5e7eb;
            --text-muted: #9ca3af;
            --card-border: #374151;
            --success: #10b981;
        }

        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .wizard-container {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 3rem;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        .success-icon {
            font-size: 3rem;
            color: var(--success);
            margin-bottom: 1rem;
            animation: bounce 0.6s ease-in-out;
        }

        @keyframes bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .wizard-header h1 {
            font-size: 1.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--success);
        }

        .wizard-header p {
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        .info-box {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 8px;
            padding: 1.5rem;
            margin: 2rem 0;
            color: #a7f3d0;
        }

        .account-details {
            text-align: left;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--card-border);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .detail-value {
            color: var(--text-primary);
            font-weight: 500;
        }

        .btn-primary-custom {
            background: var(--accent);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .btn-primary-custom:hover {
            background: #4f46e5;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
            color: white;
        }

        .progress-bar-install {
            height: 4px;
            background: var(--card-border);
            border-radius: 2px;
            margin: 2rem 0;
            overflow: hidden;
        }

        .progress-bar-install > div {
            height: 100%;
            background: var(--success);
            width: 100%;
            transition: width 0.3s ease;
        }

        .features-list {
            text-align: left;
            margin: 1.5rem 0;
        }

        .feature-item {
            display: flex;
            align-items: center;
            padding: 0.5rem 0;
            color: var(--text-muted);
        }

        .feature-item i {
            color: var(--success);
            margin-right: 0.75rem;
            font-size: 1.25rem;
        }
    </style>
</head>
<body>
    <div class="wizard-container">
        <div class="success-icon">
            <i class="bi bi-check-circle-fill"></i>
        </div>

        <div class="wizard-header">
            <h1>Installation réussie !</h1>
            <p>Mnemo est maintenant configuré et prêt à l'emploi</p>
        </div>

        <div class="progress-bar-install">
            <div></div>
        </div>

        <div class="info-box">
            <strong>✓ Configuration complète</strong><br>
            Tous les services sont opérationnels et votre compte administrateur a été créé.
        </div>

        <div class="account-details">
            <div class="detail-row">
                <span class="detail-label">Compte créé</span>
                <span class="detail-value">{{ $user->name }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Adresse e-mail</span>
                <span class="detail-value">{{ $user->email }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Rôle</span>
                <span class="detail-value">Administrateur</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date de création</span>
                <span class="detail-value">{{ $user->created_at->format('d/m/Y H:i') }}</span>
            </div>
        </div>

        <div class="features-list">
            <p style="color: var(--text-muted); margin-bottom: 1rem; font-size: 0.9rem;">
                Vous pouvez maintenant :
            </p>
            <div class="feature-item">
                <i class="bi bi-plus-circle-fill"></i>
                <span>Créer des modules d'apprentissage</span>
            </div>
            <div class="feature-item">
                <i class="bi bi-plus-circle-fill"></i>
                <span>Ajouter des items avec photos</span>
            </div>
            <div class="feature-item">
                <i class="bi bi-plus-circle-fill"></i>
                <span>Générer automatiquement des QCM</span>
            </div>
            <div class="feature-item">
                <i class="bi bi-plus-circle-fill"></i>
                <span>Accéder au mode Anki</span>
            </div>
        </div>

        <a href="{{ route('install.complete') }}" class="btn-primary-custom" style="margin-top: 1.5rem;">
            Terminer l'installation →
        </a>

        <div style="text-align: center; margin-top: 2rem;">
            <small style="color: var(--text-muted);">
                Étape 5/5 - Installation terminée
            </small>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</body>
</html>
