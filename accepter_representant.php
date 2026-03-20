<?php
require_once 'config.php';
require_login();
check_user_type('admin');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/vendor/autoload.php';

if (!isset($_GET['id'])) {
    header('Location: dashboard.php?tab=validation');
    exit();
}

$id = (int) $_GET['id'];

// Récupérer les infos du représentant
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ? AND type_utilisateur = 'representant' AND statut = 'en_attente'");
$stmt->execute([$id]);
$rep = $stmt->fetch();

if (!$rep) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => "Représentant introuvable ou déjà traité."];
    header('Location: dashboard.php?tab=validation');
    exit();
}

// Mettre le statut à 'actif'
$pdo->prepare("UPDATE utilisateurs SET statut = 'actif' WHERE id = ?")->execute([$id]);

// Envoyer email de confirmation
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'tchouheukmodeste@gmail.com';      
    $mail->Password   = 'gycz lahd vmau vcjw';      
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->SMTPDebug  = 0;

    $mail->setFrom('tchouheukmodeste@gmail.com', 'Afriton');
    $mail->addAddress($rep['email'], $rep['prenom'] . ' ' . $rep['nom']);

    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = " Compte Afriton activé – Bienvenue !";
    $mail->Body = "
        <div style='font-family:Arial,sans-serif;max-width:580px;margin:0 auto;'>
            <div style='background:linear-gradient(135deg,#16a34a,#22c55e);padding:32px;text-align:center;border-radius:12px 12px 0 0;'>
                <h1 style='color:white;margin:0;font-size:1.5rem;'> Compte activé !</h1>
            </div>
            <div style='background:#f9fafb;padding:32px;border-radius:0 0 12px 12px;border:1px solid #e2e8f0;'>
                <p style='color:#1e293b;font-size:1rem;'>Bonjour <strong>" . htmlspecialchars($rep['prenom'] . ' ' . $rep['nom']) . "</strong>,</p>
                <p style='color:#475569;line-height:1.7;'>
                    Nous avons le plaisir de vous informer que votre compte représentant sur <strong>Afriton</strong> 
                    a été <strong style='color:#16a34a;'>accepté et activé</strong> par l'administrateur.
                </p>
                <div style='background:#d1fae5;border-left:4px solid #16a34a;padding:14px 18px;border-radius:8px;margin:20px 0;'>
                    <p style='margin:0;color:#065f46;font-weight:600;'>Vous pouvez maintenant vous connecter et commencer à gérer vos tontines.</p>
                </div>
                <p style='color:#475569;'>
                    Connectez-vous ici : <a href='http://localhost/afriton/login_representant.php' 
                    style='color:#16a34a;font-weight:bold;'>Espace Représentant</a>
                </p>
                <p style='color:#94a3b8;font-size:.85rem;margin-top:24px;'>
                    Cordialement,<br><strong>L'équipe Afriton</strong>
                </p>
            </div>
        </div>
    ";
    $mail->AltBody = "Bonjour " . $rep['prenom'] . " " . $rep['nom'] . ", votre compte Afriton a été accepté. Vous pouvez maintenant vous connecter.";
    $mail->send();

    $_SESSION['flash'] = ['type' => 'success', 'msg' => " Représentant accepté. Un email de confirmation lui a été envoyé."];
} catch (Exception $e) {
    // L'activation a quand même réussi, on signale juste l'échec du mail
    $_SESSION['flash'] = ['type' => 'success', 'msg' => " Représentant accepté (email non envoyé : " . $mail->ErrorInfo . ")"];
}

header('Location: dashboard.php?tab=validation');
exit();
?>