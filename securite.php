<?php
require_once 'config.php';
require_login();

$user = get_logged_user();
$message = '';
$error = '';

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Changer mot de passe
    if (isset($_POST['changer_mdp'])) {
        $ancien = $_POST['ancien_mdp'];
        $nouveau = $_POST['nouveau_mdp'];
        $confirmation = $_POST['confirmation_mdp'];

        if (!password_verify($ancien, $user['mot_de_passe'])) {
            $error = "L'ancien mot de passe est incorrect.";
        } elseif ($nouveau !== $confirmation) {
            $error = "La confirmation ne correspond pas.";
        } elseif (strlen($nouveau) < 6) {
            $error = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
        } else {
            $hash = password_hash($nouveau, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?");
            $stmt->execute([$hash, $user['id']]);
            log_action('Changement mot de passe', "Mot de passe changé pour utilisateur " . $user['id']);
            $message = "Mot de passe changé avec succès.";
        }
    }

    // Activer 2FA (simulation)
    if (isset($_POST['activer_2fa'])) {
        $message = "Authentification à deux facteurs activée (simulation).";
        log_action('Activation 2FA', "2FA activée pour utilisateur " . $user['id']);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sécurité du compte</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="dashboard-container">
    <?php
    if ($user['type_utilisateur'] === 'admin') include 'sidebar_admin.php';
    elseif ($user['type_utilisateur'] === 'representant') include 'sidebar_representant.php';
    else include 'sidebar_membres.php';
    ?>
    <div class="main-content">
        <?php include 'topbar.php'; ?>
        <div class="content-area">
            <div class="section active">
                <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

                <div class="card">
                    <div class="card-header"><h2 class="card-title">Changer votre mot de passe</h2></div>
                    <form method="POST">
                        <div class="form-group">
                            <label>Ancien mot de passe</label>
                            <input type="password" name="ancien_mdp" required>
                        </div>
                        <div class="form-group">
                            <label>Nouveau mot de passe</label>
                            <input type="password" name="nouveau_mdp" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label>Confirmer le nouveau mot de passe</label>
                            <input type="password" name="confirmation_mdp" required minlength="6">
                        </div>
                        <button type="submit" name="changer_mdp" class="btn btn-primary">Changer le mot de passe</button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header"><h2 class="card-title">Authentification à deux facteurs (2FA)</h2></div>
                    <form method="POST">
                        <p>Activez la double authentification pour renforcer la sécurité de votre compte.</p>
                        <button type="submit" name="activer_2fa" class="btn btn-success">Activer 2FA</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
