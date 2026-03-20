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

// Mettre le statut à 'refuse'
$pdo->prepare("UPDATE utilisateurs SET statut = 'refuse' WHERE id = ?")->execute([$id]);

// Envoyer email de refus
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'tchouheukmodeste@gmail.com';       // ← à remplacer
    $mail->Password   = 'gycz lahd vmau vcjw';      // ← à remplacer
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->SMTPDebug  = 0;

    $mail->setFrom('tchouheukmodeste@gmail.com', 'Afriton');
    $mail->addAddress($rep['email'], $rep['prenom'] . ' ' . $rep['nom']);

    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = "❌ Demande de compte Afriton – Non retenue";
    $mail->Body = "
        <div style='font-family:Arial,sans-serif;max-width:580px;margin:0 auto;'>
            <div style='background:linear-gradient(135deg,#dc2626,#ef4444);padding:32px;text-align:center;border-radius:12px 12px 0 0;'>
                <h1 style='color:white;margin:0;font-size:1.5rem;'>Demande non retenue</h1>
            </div>
            <div style='background:#f9fafb;padding:32px;border-radius:0 0 12px 12px;border:1px solid #e2e8f0;'>
                <p style='color:#1e293b;font-size:1rem;'>Bonjour <strong>" . htmlspecialchars($rep['prenom'] . ' ' . $rep['nom']) . "</strong>,</p>
                <p style='color:#475569;line-height:1.7;'>
                    Nous avons examiné votre demande d'inscription en tant que représentant sur <strong>Afriton</strong>.
                    Après vérification, nous avons le regret de vous informer que votre demande a été 
                    <strong style='color:#dc2626;'>refusée</strong>.
                </p>
                <div style='background:#fee2e2;border-left:4px solid #dc2626;padding:14px 18px;border-radius:8px;margin:20px 0;'>
                    <p style='margin:0;color:#991b1b;'>
                        Si vous pensez qu'il s'agit d'une erreur ou souhaitez des informations complémentaires, 
                        veuillez contacter directement l'administrateur.
                    </p>
                </div>
                <p style='color:#94a3b8;font-size:.85rem;margin-top:24px;'>
                    Cordialement,<br><strong>L'équipe Afriton</strong>
                </p>
            </div>
        </div>
    ";
    $mail->AltBody = "Bonjour " . $rep['prenom'] . " " . $rep['nom'] . ", votre demande de compte Afriton a été refusée. Contactez l'administrateur pour plus d'informations.";
    $mail->send();

    $_SESSION['flash'] = ['type' => 'danger', 'msg' => "❌ Représentant refusé. Un email de notification lui a été envoyé."];
} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => "❌ Représentant refusé (email non envoyé : " . $mail->ErrorInfo . ")"];
}

header('Location: dashboard.php?tab=validation');
exit();