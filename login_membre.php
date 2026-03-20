<?php
require_once 'config.php';

if (is_logged_in()) {
    $user = get_logged_user();
    if ($user['type_utilisateur'] === 'membre') {
        header('Location: dashboard_membre.php');
        exit();
    } else {
        session_destroy();
    }
}

$error   = '';
$attente = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email       = secure_input($_POST['email']);
    $password    = $_POST['password'];
    $code_membre = secure_input($_POST['code_membre']);

    $stmt = $pdo->prepare("
        SELECT * FROM utilisateurs
        WHERE email = ? AND type_utilisateur = 'membre' AND statut = 'actif'
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['mot_de_passe']) && $user['code_membre'] === $code_membre) {

        $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM membres_tontines WHERE membre_id = ? AND statut = 'actif'");
        $stmt2->execute([$user['id']]);
        $dans_tontine = $stmt2->fetchColumn();

        if ($dans_tontine > 0) {
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user_type']   = $user['type_utilisateur'];
            $_SESSION['user_name']   = $user['prenom'] . ' ' . $user['nom'];
            $_SESSION['code_membre'] = $user['code_membre'];
            log_action('Connexion Membre', "Membre {$user['email']} connecté");
            header('Location: dashboard_membre.php');
            exit();
        } else {
            $attente = true;
        }

    } else {
        $error = "Email, mot de passe ou code membre incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Membre – Afriton</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            min-height: 100vh;
            /* Fond doré au lieu du bleu */
            background: linear-gradient(135deg, #FFF8EB 0%, #E8B94A 50%, #BF8C2A 100%);
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
            /* Ombre dorée */
            box-shadow: 0 24px 60px rgba(191,140,42,0.2);
            overflow: hidden;
        }

        /* En-tête doré */
        .card-top {
            background: linear-gradient(135deg, #BF8C2A, #E8B94A);
            padding: 32px 36px 28px;
            text-align: center;
            color: white;
        }
        .card-top .icon-wrap {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 14px;
        }
        .card-top h2 { font-size: 1.4rem; font-weight: 700; margin-bottom: 4px; }
        .card-top p  { font-size: 0.88rem; opacity: 0.85; margin: 0; }

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
        /* Focus doré */
        .form-control:focus {
            border-color: #BF8C2A;
            box-shadow: 0 0 0 3px rgba(191,140,42,0.2);
            outline: none;
        }
        .input-group-text { border-color: #e2e8f0; background: white; }

        /* Bouton doré */
        .btn-submit {
            background: linear-gradient(135deg, #BF8C2A, #E8B94A);
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
            box-shadow: 0 4px 14px rgba(191,140,42,0.4);
        }

        .footer-link { text-align: center; margin-top: 18px; font-size: 0.85rem; color: #64748b; }
        /* Lien doré */
        .footer-link a { color: #BF8C2A; font-weight: 600; text-decoration: none; }
        .footer-link a:hover { text-decoration: underline; }

        /* Message attente */
        .attente-box { text-align: center; padding: 28px 16px; }
        .attente-icon {
            width: 72px; height: 72px; border-radius: 50%;
            background: #FEF9C3; border: 2px solid #FDE047;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; margin: 0 auto 16px;
        }
        .attente-box h5 { font-weight: 700; color: #1e293b; margin-bottom: 10px; }
        .attente-box p  { color: #64748b; font-size: 0.88rem; line-height: 1.7; }
    </style>
</head>
<body>
<div class="login-card">

    <!-- EN-TÊTE -->
    <div class="card-top">
        <div class="icon-wrap"><i class="bi bi-people-fill"></i></div>
        <h2>Connexion Membre</h2>
        <p>Accédez à votre espace personnel</p>
    </div>

    <!-- FORMULAIRE -->
    <div class="card-body-form">

        <?php if ($attente): ?>
        <!-- MESSAGE D'ATTENTE -->
        <div class="attente-box">
            <div class="attente-icon"><i class="bi bi-hourglass-split text-warning"></i></div>
            <h5>En attente de confirmation</h5>
            <p>
                Votre compte a bien été créé.<br><br>
                Le représentant doit encore vous ajouter à une tontine.
                Une fois accepté, vous recevrez un <strong>email avec votre code membre</strong>.
            </p>
            <a href="index.php" class="btn btn-outline-secondary btn-sm mt-2">
                <i class="bi bi-arrow-left me-1"></i>Retour à l'accueil
            </a>
        </div>

        <?php else: ?>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2 mb-4" style="font-size:.88rem">
            <i class="bi bi-exclamation-circle-fill me-2"></i><?= $error ?>
        </div>
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

            <div class="mb-3">
                <label class="form-label">Code Membre</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person-badge text-muted"></i></span>
                    <input type="text" class="form-control" name="code_membre" required placeholder="Ex: MBR-XXXX">
                </div>
                <div style="font-size:.75rem; color:#94a3b8; margin-top:4px;">
                    <i class="bi bi-info-circle me-1"></i>Reçu par email après confirmation de votre adhésion.
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Mot de passe</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock text-muted"></i></span>
                    <input type="password" class="form-control" name="password" required placeholder="Votre mot de passe">
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
            </button>
        </form>

        <div class="footer-link">
            Pas encore de compte ? <a href="register.php">S'inscrire</a>
            <br>
            <a href="index.php" class="text-muted" style="font-weight:400">
                <i class="bi bi-arrow-left me-1"></i>Retour à l'accueil
            </a>
        </div>

        <?php endif; ?>
    </div>

</div>
</body>
</html>