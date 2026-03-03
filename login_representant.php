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


$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = secure_input($_POST['email'] ?? '');
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ? AND statut = 'actif'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['mot_de_passe']) && $user['type_utilisateur'] === 'representant') {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = $user['type_utilisateur'];
        $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
        
        log_action('Connexion Représentant', "Représentant {$user['email']} connecté");
        
        header('Location: dashboard_representant.php');
        exit();
    } else {
        $error = "Email ou mot de passe incorrect, ou vous n'avez pas les droits de représentant.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Représentant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
    <style>
        .login-container {
            background: linear-gradient(135deg, #e6f2ff 0%,  #90caf9 100%);
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
                <div style="width: 80px; height: 80px; background:  #90caf9; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                    <span style="color: white; font-size: 36px;"></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="80" height="50" fill="currentColor" class="bi bi-coin" viewBox="0 0 16 16">
                    <path d="M5.5 9.511c.076.954.83 1.697 2.182 1.785V12h.6v-.709c1.4-.098 2.218-.846 2.218-1.932 0-.987-.626-1.496-1.745-1.76l-.473-.112V5.57c.6.068.982.396 1.074.85h1.052c-.076-.919-.864-1.638-2.126-1.716V4h-.6v.719c-1.195.117-2.01.836-2.01 1.853 0 .9.606 1.472 1.613 1.707l.397.098v2.034c-.615-.093-1.022-.43-1.114-.9zm2.177-2.166c-.59-.137-.91-.416-.91-.836 0-.47.345-.822.915-.925v1.76h-.005zm.692 1.193c.717.166 1.048.435 1.048.91 0 .542-.412.914-1.135.982V8.518z"/>
                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                    <path d="M8 13.5a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11m0 .5A6 6 0 1 0 8 2a6 6 0 0 0 0 12"/>
                     </svg>
                </div>
                <h1 style="color: #1e293b; margin-bottom: 10px; font-size: 28px;">Connexion Représentant</h1>
                <p style="color: #64748b; font-size: 16px;">Connectez-vous pour gérer votre tontine</p>
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
                
                <button type="submit" class="" style="width: 100%; padding: 12px; margin-top: 20px; background: #90caf9; border: none; font-size: 16px;">
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