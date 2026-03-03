<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'admin') {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare(
        "SELECT *
         FROM utilisateurs
         WHERE email = ?
           AND statut = 'actif'
         LIMIT 1"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        $user &&
        password_verify($password, $user['mot_de_passe']) &&
        $user['type_utilisateur'] === 'admin'
    ) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_type'] = $user['type_utilisateur'];
        $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];

        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Email ou mot de passe incorrect ou accès refusé.";
    }
}
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Administrateur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .login-container {
            background: linear-gradient(135deg, #e6f2ff 0%, #90caf9 100%);
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
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div style="text-align: center; margin-bottom: 30px;">
                <div style="width: 80px; height: 80px; background: #90caf9; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                    <span style="color: white; font-size: 36px;"></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="80" height="50" fill="currentColor" class="bi bi-person-circle" viewBox="0 0 16 16">
                    <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0"/>
                    <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1"/>
                    </svg>
                </div>
                <h1 style="color: #1e293b; margin-bottom: 10px; font-size: 28px;">Connexion Administrateur</h1>
                <p style="color: #64748b; font-size: 16px;">Connectez-vous pour gérer toute la plateforme</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 20px;">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email" style="font-weight: 600; color: #1e293b;">Email</label>
                    <input type="email" id="email" name="email" required placeholder="" style="padding: 12px; border-radius: 8px;">
                </div>
                
                <div class="form-group">
                    <label for="password" style="font-weight: 600; color: #1e293b;">Mot de passe</label>
                    <input type="password" id="password" name="password" required placeholder="Votre mot de passe" style="padding: 12px; border-radius: 8px;">
                </div>
                
                <button type="submit" class="" style="width: 100%; padding: 12px; margin-top: 20px; background:  #90caf9; border: none; font-size: 16px;">
                    Se connecter
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="index.php" style="color: w; text-decoration: none;"> Retour à l'accueil</a>
            </div>
        </div>
    </div>
</body>
</html>