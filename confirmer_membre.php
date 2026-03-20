<?php
require_once 'config.php';
require_login();

$user = get_logged_user();
if ($user['type_utilisateur'] !== 'representant') {
    header('Location: index.php');
    exit();
}

// ─── CONFIGURATION TWILIO ──────────────────────────────────────────────────
// Mets tes vraies credentials Twilio ici (ou dans config.php)
define('TWILIO_SID',   'ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'); // Ton Account SID
define('TWILIO_TOKEN', 'your_auth_token');                   // Ton Auth Token
define('TWILIO_FROM',  '+1XXXXXXXXXX');                      // Ton numéro Twilio
// ───────────────────────────────────────────────────────────────────────────

/**
 * Envoie un SMS via l'API Twilio (sans librairie, juste cURL)
 */
function envoyer_sms(string $to, string $message): bool {
    $url  = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_SID . '/Messages.json';
    $data = http_build_query(['To' => $to, 'From' => TWILIO_FROM, 'Body' => $message]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => TWILIO_SID . ':' . TWILIO_TOKEN,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($http_code === 201); // Twilio renvoie 201 si succès
}

// ─── TRAITEMENT ────────────────────────────────────────────────────────────

if (isset($_GET['id']) && isset($_GET['action'])) {
    $membre_id = (int) $_GET['id'];
    $action    = $_GET['action']; // 'confirmer' ou 'refuser'

    // Récupérer le membre + tontine en vérifiant que c'est bien la tontine du représentant
    $stmt = $pdo->prepare("
        SELECT mt.*, 
               u.prenom, u.nom, u.telephone, u.code_membre,
               t.nom AS tontine_nom
        FROM membres_tontines mt
        JOIN utilisateurs u ON mt.membre_id = u.id
        JOIN tontines t ON mt.tontine_id = t.id
        WHERE mt.membre_id = ? AND t.representant_id = ?
        LIMIT 1
    ");
    $stmt->execute([$membre_id, $user['id']]);
    $membre = $stmt->fetch();

    if (!$membre) {
        header('Location: membres_tontines.php?error=permission');
        exit();
    }

    if ($action === 'confirmer') {

        // Mettre le membre actif dans membres_tontines
        $stmt = $pdo->prepare("
            UPDATE membres_tontines SET statut = 'actif'
            WHERE membre_id = ? AND tontine_id = ?
        ");
        $stmt->execute([$membre_id, $membre['tontine_id']]);

        // Envoyer le SMS avec le code membre
        $prenom  = $membre['prenom'];
        $code    = $membre['code_membre'];
        $tontine = $membre['tontine_nom'];
        $tel     = $membre['telephone']; // format international ex: +237612345678

        $sms = "Bonjour $prenom,\n\nVotre adhésion à la tontine « $tontine » est confirmée !\n\nVotre code membre : $code\n\nConservez-le, il est nécessaire pour vous connecter sur Afriton.";

        $sms_envoye = envoyer_sms($tel, $sms);

        log_action('Confirmation membre', "Membre $membre_id confirmé, SMS " . ($sms_envoye ? 'envoyé' : 'échoué'));
        header('Location: membres_tontines.php?id=' . $membre['tontine_id'] . '&success=confirme');

    } elseif ($action === 'refuser') {

        // On retire le membre (statut retire)
        $stmt = $pdo->prepare("
            UPDATE membres_tontines SET statut = 'retire'
            WHERE membre_id = ? AND tontine_id = ?
        ");
        $stmt->execute([$membre_id, $membre['tontine_id']]);

        log_action('Refus membre', "Membre $membre_id refusé pour tontine {$membre['tontine_id']}");
        header('Location: membres_tontines.php?id=' . $membre['tontine_id'] . '&success=refuse');
    }

    exit();
}

header('Location: membres_tontines.php');
exit();
?>