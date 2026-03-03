<?php
require_once 'config.php';

if (is_logged_in()) {
    $user = get_current_user();
    if ($user['type_utilisateur'] === 'membre') {
        header('Location: dashboard_membre.php');
        exit();
    } else {
        session_destroy();
    }
}


$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = secure($_POST['email']);
    $password = $_POST['password'];
    $code_membre = secure($_POST['code_membre']);

    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ? AND code_membre = ? AND statut = 'actif'");
    $stmt->execute([$email, $code_membre]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['mot_de_passe']) && $user['type_utilisateur'] === 'membre') {

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = $user['type_utilisateur'];
        $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
        $_SESSION['code_membre'] = $user['code_membre'];

        log_action('Connexion Membre', "Membre {$user['email']} connecté");

        header('Location: dashboard_membre.php');
        exit();
    } else {
        $error = "Email, mot de passe ou code membre incorrect, ou vous n'avez pas les droits de membre.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Membre</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .login-container {
            background: linear-gradient(135deg, #e1f5fe 0%,  #90caf9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .form-group { margin-bottom: 15px; }
        .form-control { padding: 12px; border-radius: 8px; }
        button { width: 100%; padding: 12px; margin-top: 20px; background: #90caf9; border: none; font-size: 16px; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="text-center mb-4">
                <div style="width: 80px; height: 80px; background:#90caf9; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
                    <i class="bi bi-people-fill" style="font-size: 2rem; color: white;"></i>
                </div>
                <h1 class="mb-2" style="font-size: 28px; color:#1e293b;">Connexion Membre</h1>
                <p style="color:#64748b; font-size:16px;">Connectez-vous pour accéder à votre espace membre</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger mb-3">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email" style="font-weight:600; color:#1e293b;">Email</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="code_membre" style="font-weight:600; color:#1e293b;">Code Membre</label>
                    <input type="text" id="code_membre" name="code_membre" class="form-control" placeholder="" required>
                </div>

                <div class="form-group">
                    <label for="password" style="font-weight:600; color:#1e293b;">Mot de passe</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Votre mot de passe" required>
                </div>

                <button type="submit">Se connecter</button>
            </form>

            <div class="text-center mt-3">
                <a href="index.php" style="color:#1e293b; text-decoration:none;">Retour à l'accueil</a>
            </div>
        </div>
    </div>
</body>
</html>
