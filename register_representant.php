<?php
require_once 'config.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom              = secure_input($_POST['nom']);
    $prenom           = secure_input($_POST['prenom']);
    $email            = secure_input($_POST['email']);
    $telephone        = secure_input($_POST['telephone']);
    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($nom) || empty($prenom) || empty($email) || empty($telephone) || empty($password)) {
        $error = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email invalide.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Cet email est déjà utilisé.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            // statut = 'en_attente' — l'admin doit valider avant l'accès
            $stmt = $pdo->prepare("
                INSERT INTO utilisateurs (nom, prenom, email, telephone, mot_de_passe, type_utilisateur, statut)
                VALUES (?, ?, ?, ?, ?, 'representant', 'en_attente')
            ");
            if ($stmt->execute([$nom, $prenom, $email, $telephone, $hashed_password])) {
                set_flash('success', "Inscription réussie ! Votre compte est en attente de validation par l'administrateur.");
                header('Location: login_representant.php');
                exit();
            } else {
                $error = "Erreur lors de l'inscription. Veuillez réessayer.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Représentant – Afriton</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            min-height: 100vh;
            /* Fond vert forêt Afriton */
            background: linear-gradient(135deg, #EDFAF0 0%, #2d6a4f 50%, #18392B 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 16px;
            font-family: Arial, sans-serif;
        }

        .register-card {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 520px;
            /* Ombre verte forêt */
            box-shadow: 0 24px 60px rgba(24,57,43,0.25);
            overflow: hidden;
        }

        /* En-tête vert forêt */
        .card-top {
            background: linear-gradient(135deg, #18392B, #2d6a4f);
            padding: 32px 36px 28px;
            text-align: center;
            color: white;
        }
        .card-top .icon-wrap {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: rgba(255,255,255,0.12);
            border: 2px solid rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 14px;
        }
        .card-top h2 { font-size: 1.4rem; font-weight: 700; margin-bottom: 4px; }
        .card-top p  { font-size: 0.88rem; opacity: 0.8; margin: 0; }

        /* Bannière info validation — reste jaune car c'est une alerte */
        .info-banner {
            background: #fffbeb;
            border-bottom: 1px solid #f59e0b;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: .825rem;
            color: #78350f;
        }
        .info-banner i { color: #f59e0b; font-size: 1rem; flex-shrink: 0; }

        .card-body-form { padding: 28px 36px 36px; }

        /* Séparateur de section */
        .separateur {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #94a3b8;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 4px 0 18px;
        }
        .separateur::before,
        .separateur::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        .form-label { font-size: 0.83rem; font-weight: 600; color: #334155; margin-bottom: 5px; }

        .form-control {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.9rem;
            color: #1e293b;
            transition: border-color .2s, box-shadow .2s;
        }
        /* Focus vert forêt */
        .form-control:focus {
            border-color: #18392B;
            box-shadow: 0 0 0 3px rgba(24,57,43,0.15);
            outline: none;
        }
        .input-group-text { border-color: #e2e8f0; background: white; }
        .form-text { font-size: 0.77rem; color: #94a3b8; }

        /* Bouton vert forêt */
        .btn-submit {
            background: linear-gradient(135deg, #18392B, #2d6a4f);
            color: white;
            border: none;
            border-radius: 8px;
            width: 100%;
            padding: 13px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: opacity .2s, box-shadow .2s;
        }
        .btn-submit:hover {
            opacity: 0.9;
            box-shadow: 0 4px 14px rgba(24,57,43,0.35);
        }

        .footer-link { text-align: center; margin-top: 20px; font-size: 0.85rem; color: #64748b; }
        /* Lien vert forêt */
        .footer-link a { color: #18392B; font-weight: 600; text-decoration: none; }
        .footer-link a:hover { text-decoration: underline; }

        /* Responsive : padding réduit sur mobile */
        @media (max-width: 480px) {
            .card-top, .card-body-form { padding-left: 20px; padding-right: 20px; }
        }
    </style>
</head>
<body>
<div class="register-card">

    <!-- EN-TÊTE -->
    <div class="card-top">
        <div class="icon-wrap"><i class="bi bi-briefcase-fill"></i></div>
        <h2>Inscription Représentant</h2>
        <p>Créez votre compte pour gérer vos tontines</p>
    </div>

    <!-- BANNIÈRE INFO -->
    <div class="info-banner">
        <i class="bi bi-info-circle-fill"></i>
        <span>Après inscription, votre compte sera <strong>validé par l'administrateur</strong> avant de pouvoir vous connecter.</span>
    </div>

    <!-- FORMULAIRE -->
    <div class="card-body-form">

        <!-- Message d'erreur -->
        <?php if ($error): ?>
        <div class="alert alert-danger py-2 mb-4" style="font-size:.88rem">
            <i class="bi bi-exclamation-circle-fill me-2"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST">

            <!-- Informations personnelles -->
            <div class="separateur">Informations personnelles</div>

            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label">Nom</label>
                    <input type="text" class="form-control" name="nom" required
                           value="<?= isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : '' ?>">
                </div>
                <div class="col-6">
                    <label class="form-label">Prénom</label>
                    <input type="text" class="form-control" name="prenom" required
                           value="<?= isset($_POST['prenom']) ? htmlspecialchars($_POST['prenom']) : '' ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Adresse email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope text-muted"></i></span>
                    <input type="email" class="form-control" name="email" required
                           placeholder="votre@email.com"
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Téléphone</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-telephone text-muted"></i></span>
                    <input type="text" class="form-control" name="telephone" required
                           placeholder="6XX XXX XXX"
                           value="<?= isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : '' ?>">
                </div>
            </div>

            <!-- Sécurité -->
            <div class="separateur">Sécurité du compte</div>

            <div class="mb-3">
                <label class="form-label">Mot de passe</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock text-muted"></i></span>
                    <input type="password" class="form-control" name="password" required
                           placeholder="Minimum 6 caractères">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Confirmer le mot de passe</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock-fill text-muted"></i></span>
                    <input type="password" class="form-control" name="confirm_password" required
                           placeholder="Répétez le mot de passe">
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="bi bi-person-check-fill me-2"></i>Créer mon compte
            </button>

        </form>

        <div class="footer-link">
            Déjà inscrit ? <a href="login_representant.php">Connectez-vous ici</a>
        </div>

    </div>
</div>
</body>
</html>