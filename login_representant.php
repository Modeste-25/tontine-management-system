<?php
require_once 'config.php';

if (is_logged_in()) {
    $user = get_logged_user();
    if ($user && $user['type_utilisateur'] === 'representant') {
        header('Location: dashboard_representant.php');
        exit();
    } else {
        session_destroy();
    }
}

$error      = '';
$error_type = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = secure_input($_POST['email'] ?? '');
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ? AND type_utilisateur = 'representant'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['mot_de_passe'])) {
        if ($user['statut'] === 'en_attente') {
            $error_type = 'warning';
            $error      = "Votre compte est <strong>en attente de validation</strong> par l'administrateur. Vous serez notifié dès l'approbation.";
        } elseif ($user['statut'] === 'refuse') {
            $error_type = 'danger';
            $error      = "Votre demande d'inscription a été <strong>refusée</strong> par l'administrateur. Contactez-le pour plus d'informations.";
        } elseif ($user['statut'] === 'actif') {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_type'] = $user['type_utilisateur'];
            $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
            log_action('Connexion Représentant', "Représentant {$user['email']} connecté");
            header('Location: dashboard_representant.php');
            exit();
        } else {
            $error = "Votre compte est inactif. Contactez l'administrateur.";
        }
    } else {
        $error = "Email ou mot de passe incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Représentant – Afriton</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            min-height: 100vh;
            /* Fond vert forêt Afriton au lieu du vert clair */
            background: linear-gradient(135deg, #EDFAF0 0%, #2d6a4f 50%, #18392B 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 16px;
            font-family: Arial, sans-serif;
        }

        .login-card {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 440px;
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

        .card-body-form { padding: 32px 36px 36px; }

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

        /* Bouton vert forêt */
        .btn-submit {
            background: linear-gradient(135deg, #18392B, #2d6a4f);
            color: white;
            border: none;
            border-radius: 8px;
            width: 100%;
            padding: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .2s, box-shadow .2s;
        }
        .btn-submit:hover {
            opacity: 0.9;
            box-shadow: 0 4px 14px rgba(24,57,43,0.35);
        }

        .footer-link { text-align: center; margin-top: 18px; font-size: 0.85rem; color: #64748b; }
        /* Lien vert forêt */
        .footer-link a { color: #18392B; font-weight: 600; text-decoration: none; }
        .footer-link a:hover { text-decoration: underline; }

        /* Alerte attente (jaune) */
        .alert-warning-custom {
            background: #fffbeb;
            border: 1px solid #f59e0b;
            border-left: 4px solid #f59e0b;
            color: #78350f;
            border-radius: 10px;
            padding: 14px 16px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: .875rem;
            line-height: 1.55;
            margin-bottom: 20px;
        }

        /* Alerte refus (rouge) */
        .alert-danger-custom {
            background: #fff1f2;
            border: 1px solid #f87171;
            border-left: 4px solid #ef4444;
            color: #991b1b;
            border-radius: 10px;
            padding: 14px 16px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: .875rem;
            line-height: 1.55;
            margin-bottom: 20px;
        }

        .alert-warning-custom i,
        .alert-danger-custom i { flex-shrink: 0; font-size: 1.1rem; margin-top: 1px; }
    </style>
</head>
<body>
<div class="login-card">

    <!-- EN-TÊTE -->
    <div class="card-top">
        <div class="icon-wrap"><i class="bi bi-briefcase-fill"></i></div>
        <h2>Connexion Représentant</h2>
        <p>Connectez-vous pour gérer vos tontines</p>
    </div>

    <!-- FORMULAIRE -->
    <div class="card-body-form">

        <?php if ($error): ?>
            <?php if ($error_type === 'warning'): ?>
            <div class="alert-warning-custom">
                <i class="bi bi-hourglass-split"></i>
                <div><?= $error ?></div>
            </div>
            <?php else: ?>
            <div class="alert-danger-custom">
                <i class="bi bi-exclamation-circle-fill"></i>
                <div><?= $error ?></div>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Adresse email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope text-muted"></i></span>
                    <input type="email" class="form-control" name="email" required
                           placeholder="votre@email.com"
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Mot de passe</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock text-muted"></i></span>
                    <input type="password" class="form-control" name="password" required
                           placeholder="Votre mot de passe">
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
            </button>
        </form>

        <div class="footer-link">
            Pas encore de compte ? <a href="register.php">S'inscrire</a><br>
            <a href="index.php" class="text-muted" style="font-weight:400">
                <i class="bi bi-arrow-left me-1"></i>Retour à l'accueil
            </a>
        </div>

    </div>
</div>
</body>
</html>