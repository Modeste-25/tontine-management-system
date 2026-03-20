<?php
require_once 'config.php';
require_login();
check_user_type('admin');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/vendor/autoload.php';

if (!isset($_GET['id'])) {
    header('Location: membres.php');
    exit();
}

$id = (int) $_GET['id'];

// Récupérer les infos du membre
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ? AND type_utilisateur = 'membre'");
$stmt->execute([$id]);
$membre = $stmt->fetch();

if (!$membre) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => "Membre introuvable."];
    header('Location: membres.php');
    exit();
}

// Activer le membre
$pdo->prepare("UPDATE utilisateurs SET statut = 'actif' WHERE id = ?")->execute([$id]);

// Envoyer email avec code membre
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'tchouheukmodeste@gmail.com';   // ← à remplacer
    $mail->Password   = 'gycz lahd vmau vcjw';  // ← à remplacer
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->SMTPDebug  = 0;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom('tchouheukmodeste@gmail.com', 'Afriton');
    $mail->addAddress($membre['email'], $membre['prenom'] . ' ' . $membre['nom']);

    $mail->isHTML(true);
    $mail->Subject = " Compte Afriton activé – Votre code membre";

    $code = htmlspecialchars($membre['code_membre']);
    $nom  = htmlspecialchars($membre['prenom'] . ' ' . $membre['nom']);

    $mail->Body = "
    <div style='font-family:Arial,sans-serif;max-width:580px;margin:0 auto;'>
        <div style='background:linear-gradient(135deg,#1d4ed8,#3b82f6);padding:32px;text-align:center;border-radius:12px 12px 0 0;'>
            <h1 style='color:white;margin:0;font-size:1.5rem;'> Compte activé !</h1>
            <p style='color:rgba(255,255,255,.8);margin:8px 0 0;font-size:.9rem;'>Bienvenue sur Afriton</p>
        </div>
        <div style='background:#f9fafb;padding:32px;border-radius:0 0 12px 12px;border:1px solid #e2e8f0;'>
            <p style='color:#1e293b;font-size:1rem;'>Bonjour <strong>{$nom}</strong>,</p>
            <p style='color:#475569;line-height:1.7;'>
                Votre compte membre sur <strong>Afriton</strong> a été 
                <strong style='color:#1d4ed8;'>activé</strong> par l'administrateur.
                Vous pouvez maintenant vous connecter à votre espace personnel.
            </p>

            <p style='color:#475569;font-size:.9rem;margin-bottom:8px;'>Voici votre <strong>code membre</strong> :</p>
            <div style='background:#eff6ff;border:2px dashed #93c5fd;border-radius:10px;
                        padding:20px;text-align:center;margin:16px 0;'>
                <span style='font-size:2rem;font-weight:800;color:#1d4ed8;letter-spacing:4px;'>{$code}</span>
                <p style='color:#64748b;font-size:.78rem;margin:8px 0 0;'>
                    Conservez ce code — il vous sera demandé à chaque connexion.
                </p>
            </div>

            <div style='background:#dbeafe;border-left:4px solid #3b82f6;padding:14px 18px;
                        border-radius:8px;margin:20px 0;font-size:.875rem;color:#1e3a8a;'>
                <strong>Pour vous connecter :</strong> email + code membre + mot de passe
            </div>

            <a href='http://localhost/afriton/login_membre.php'
               style='display:block;background:linear-gradient(135deg,#1d4ed8,#3b82f6);
                      color:white;text-align:center;padding:13px;border-radius:8px;
                      text-decoration:none;font-weight:700;font-size:.95rem;margin-top:20px;'>
                Se connecter maintenant →
            </a>

            <p style='color:#94a3b8;font-size:.78rem;margin-top:24px;text-align:center;'>
                © Afriton – Système de gestion de tontines
            </p>
        </div>
    </div>
    ";
    $mail->AltBody = "Bonjour {$nom}, votre compte Afriton est activé. Votre code membre est : {$code}. Connectez-vous sur login_membre.php";

    $mail->send();
    $_SESSION['flash'] = ['type' => 'success', 'msg' => " Membre activé. Son code membre ({$membre['code_membre']}) lui a été envoyé par email."];

} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'success', 'msg' => " Membre activé (email non envoyé : " . $mail->ErrorInfo . ")"];
}

header('Location: membres.php');
exit();
?>