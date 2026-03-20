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

// Suspendre le membre
$pdo->prepare("UPDATE utilisateurs SET statut = 'suspendu' WHERE id = ?")->execute([$id]);

// Envoyer email de refus/suspension
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
    $mail->Subject = "❌ Compte Afriton – Accès suspendu";

    $nom = htmlspecialchars($membre['prenom'] . ' ' . $membre['nom']);

    $mail->Body = "
    <div style='font-family:Arial,sans-serif;max-width:580px;margin:0 auto;'>
        <div style='background:linear-gradient(135deg,#dc2626,#ef4444);padding:32px;
                    text-align:center;border-radius:12px 12px 0 0;'>
            <h1 style='color:white;margin:0;font-size:1.5rem;'>Accès suspendu</h1>
            <p style='color:rgba(255,255,255,.8);margin:8px 0 0;font-size:.9rem;'>Notification Afriton</p>
        </div>
        <div style='background:#f9fafb;padding:32px;border-radius:0 0 12px 12px;border:1px solid #e2e8f0;'>
            <p style='color:#1e293b;font-size:1rem;'>Bonjour <strong>{$nom}</strong>,</p>
            <p style='color:#475569;line-height:1.7;'>
                Nous vous informons que votre compte membre sur <strong>Afriton</strong> 
                a été <strong style='color:#dc2626;'>suspendu</strong> par l'administrateur.
            </p>

            <div style='background:#fee2e2;border-left:4px solid #dc2626;padding:14px 18px;
                        border-radius:8px;margin:20px 0;'>
                <p style='margin:0;color:#991b1b;font-size:.875rem;'>
                    Vous ne pouvez plus accéder à votre espace membre pour le moment.<br>
                    Si vous pensez qu'il s'agit d'une erreur, veuillez contacter l'administrateur.
                </p>
            </div>

            <p style='color:#64748b;font-size:.875rem;line-height:1.7;'>
                Pour toute réclamation ou demande de réactivation, contactez directement 
                l'administrateur de votre tontine.
            </p>

            <p style='color:#94a3b8;font-size:.78rem;margin-top:24px;text-align:center;'>
                © Afriton – Système de gestion de tontines
            </p>
        </div>
    </div>
    ";
    $mail->AltBody = "Bonjour {$nom}, votre compte Afriton a été suspendu. Contactez l'administrateur pour plus d'informations.";

    $mail->send();
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => "❌ Membre suspendu. Un email de notification lui a été envoyé."];

} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => "❌ Membre suspendu (email non envoyé : " . $mail->ErrorInfo . ")"];
}

header('Location: membres.php');
exit();