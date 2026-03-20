<?php
require_once 'config.php';
require_login();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/vendor/autoload.php';

$user            = get_logged_user();
$is_admin        = ($user['type_utilisateur'] === 'admin');
$is_representant = ($user['type_utilisateur'] === 'representant');

if (!$is_admin && !$is_representant) {
    header('Location: index.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: tontine.php');
    exit();
}

$tontine_id = (int) $_GET['id'];

// Vérifier droits
if (!$is_admin) {
    $stmt = $pdo->prepare("SELECT * FROM tontines WHERE id = ? AND representant_id = ?");
    $stmt->execute([$tontine_id, $user['id']]);
    $tontine = $stmt->fetch();
    if (!$tontine) { header('Location: tontine.php?error=permission'); exit(); }
} else {
    $stmt = $pdo->prepare("SELECT * FROM tontines WHERE id = ?");
    $stmt->execute([$tontine_id]);
    $tontine = $stmt->fetch();
    if (!$tontine) { header('Location: tontine.php?error=notfound'); exit(); }
}

// ── AJOUTER un membre + envoyer email PHPMailer ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_membre'])) {
    $membre_id = (int) $_POST['membre_id'];

    // Vérifier doublon
    $check = $pdo->prepare("SELECT id FROM membres_tontines WHERE tontine_id = ? AND membre_id = ?");
    $check->execute([$tontine_id, $membre_id]);

    if (!$check->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO membres_tontines (tontine_id, membre_id, solde, statut) VALUES (?, ?, 0.00, 'actif')");
        $stmt->execute([$tontine_id, $membre_id]);

        // Récupérer infos membre
        $stmt2 = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
        $stmt2->execute([$membre_id]);
        $m = $stmt2->fetch();

        $prenom      = htmlspecialchars($m['prenom']);
        $nom         = htmlspecialchars($m['nom']);
        $code        = htmlspecialchars($m['code_membre']);
        $tontine_nom = htmlspecialchars($tontine['nom']);

        // Envoi email PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'tchouheukmodeste@gmail.com';  // ← à remplacer
            $mail->Password   = 'gycz lahd vmau vcjw'; // ← à remplacer
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->SMTPDebug  = 0;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('tchouheukmodeste@gmail.com', 'Afriton');
            $mail->addAddress($m['email'], $m['prenom'] . ' ' . $m['nom']);

            $mail->isHTML(true);
            $mail->Subject = " Afriton – Vous avez été ajouté à la tontine « {$tontine['nom']} »";
            $mail->Body    = "
            <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;'>
                <div style='background:linear-gradient(135deg,#1d4ed8,#60a5fa);
                            padding:30px;text-align:center;border-radius:12px 12px 0 0;'>
                    <h1 style='color:white;margin:0;font-size:1.4rem;'> Bienvenue dans la tontine !</h1>
                    <p style='color:rgba(255,255,255,.8);margin:6px 0 0;font-size:.88rem;'>Afriton</p>
                </div>
                <div style='background:#f9fafb;padding:30px;border-radius:0 0 12px 12px;
                            border:1px solid #e2e8f0;'>
                    <p style='color:#1e293b;'>Bonjour <strong>{$prenom} {$nom}</strong>,</p>
                    <p style='color:#475569;line-height:1.7;font-size:.9rem;'>
                        Vous avez été ajouté à la tontine 
                        <strong style='color:#1d4ed8;'>« {$tontine_nom} »</strong> 
                        par le représentant. Vous pouvez maintenant vous connecter à votre espace membre.
                    </p>

                    <p style='color:#475569;font-size:.9rem;margin-bottom:8px;'>
                        Voici votre <strong>code membre</strong> :
                    </p>
                    <div style='background:#eff6ff;border:2px dashed #93c5fd;border-radius:10px;
                                padding:20px;text-align:center;margin:16px 0;'>
                        <span style='font-size:2rem;font-weight:800;color:#1d4ed8;letter-spacing:4px;'>
                            {$code}
                        </span>
                        <p style='color:#64748b;font-size:.78rem;margin:8px 0 0;'>
                            Conservez ce code — il vous sera demandé à chaque connexion.
                        </p>
                    </div>

                    <div style='background:#dbeafe;border-left:4px solid #3b82f6;padding:12px 16px;
                                border-radius:8px;margin:16px 0;font-size:.85rem;color:#1e3a8a;'>
                        <strong>Pour vous connecter :</strong> email + code membre + mot de passe
                    </div>

                    <a href='http://localhost/afriton/login_membre.php'
                       style='display:block;background:linear-gradient(135deg,#1d4ed8,#3b82f6);
                              color:white;text-align:center;padding:13px;border-radius:8px;
                              text-decoration:none;font-weight:700;font-size:.92rem;margin-top:18px;'>
                        Se connecter maintenant →
                    </a>

                    <p style='color:#94a3b8;font-size:.75rem;margin-top:22px;text-align:center;'>
                        © Afriton – Système de gestion de tontines
                    </p>
                </div>
            </div>";
            $mail->AltBody = "Bonjour {$prenom} {$nom}, vous avez été ajouté à la tontine « {$tontine['nom']} ». Votre code membre est : {$code}. Connectez-vous sur login_membre.php";
            $mail->send();
        } catch (Exception $e) {
            // Email non bloquant — l'ajout a quand même réussi
            error_log("Email non envoyé pour membre $membre_id : " . $mail->ErrorInfo);
        }

        log_action('Ajout membre', "Membre $membre_id ajouté à tontine $tontine_id");
    }

    header("Location: membres_tontines.php?id=$tontine_id&success=1");
    exit();
}

// ── CHANGER STATUT (retirer / exclure / réactiver) ──
if (isset($_GET['action']) && isset($_GET['mid'])) {
    $mid    = (int) $_GET['mid'];
    $action = $_GET['action'];

    $statuts_valides = ['retire', 'exclu', 'actif'];
    if (in_array($action, $statuts_valides)) {
        $stmt = $pdo->prepare("UPDATE membres_tontines SET statut = ? WHERE tontine_id = ? AND membre_id = ?");
        $stmt->execute([$action, $tontine_id, $mid]);
        log_action("Statut membre $action", "Membre $mid dans tontine $tontine_id");
    }

    header("Location: membres_tontines.php?id=$tontine_id&success=$action");
    exit();
}

// ── LIRE les membres de la tontine ──
$stmt = $pdo->prepare("
    SELECT u.id, u.prenom, u.nom, u.email, u.code_membre,
           mt.solde, mt.statut as mt_statut, mt.date_adhesion
    FROM membres_tontines mt
    JOIN utilisateurs u ON mt.membre_id = u.id
    WHERE mt.tontine_id = ?
    ORDER BY mt.statut ASC, u.nom ASC
");
$stmt->execute([$tontine_id]);
$membres = $stmt->fetchAll();

// ── Membres disponibles à ajouter ──
$stmt = $pdo->prepare("
    SELECT id, prenom, nom, email FROM utilisateurs
    WHERE type_utilisateur = 'membre' AND statut = 'actif'
    AND id NOT IN (SELECT membre_id FROM membres_tontines WHERE tontine_id = ?)
    ORDER BY nom
");
$stmt->execute([$tontine_id]);
$disponibles = $stmt->fetchAll();

$nb_actifs  = count(array_filter($membres, fn($m) => $m['mt_statut'] === 'actif'));
$nb_retires = count(array_filter($membres, fn($m) => $m['mt_statut'] !== 'actif'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membres – <?= htmlspecialchars($tontine['nom']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { font-family:Arial,sans-serif; background:#f1f5f9; }
        .page-header { background:white; border-bottom:1px solid #e2e8f0; padding:14px 0; margin-bottom:24px; }
        .section-card { background:white; border-radius:10px; border:1px solid #e2e8f0; margin-bottom:20px; }
        .sc-header { padding:14px 20px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; }
        .sc-header h6 { font-weight:700; margin:0; color:#1e293b; font-size:.92rem; }
        table { width:100%; border-collapse:collapse; font-size:.86rem; }
        th { background:#f8fafc; color:#64748b; font-size:.74rem; font-weight:600; text-transform:uppercase; padding:9px 14px; text-align:left; }
        td { padding:10px 14px; border-bottom:1px solid #f1f5f9; color:#334155; vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tr:hover td { background:#fafafa; }
    </style>
</head>
<body>

<!-- EN-TÊTE -->
<div class="page-header">
    <div class="container-lg d-flex align-items-center justify-content-between">
        <div>
            <h5 class="fw-bold mb-0"><?= htmlspecialchars($tontine['nom']) ?></h5>
            <small class="text-muted">
                Gestion des membres ·
                <span class="text-success fw-semibold"><?= $nb_actifs ?> actif(s)</span>
                <?php if ($nb_retires > 0): ?>
                · <span class="text-danger"><?= $nb_retires ?> retiré(s)/exclu(s)</span>
                <?php endif; ?>
            </small>
        </div>
        <a href="<?= $is_admin ? 'tontine.php' : 'dashboard_representant.php' ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Retour
        </a>
    </div>
</div>

<div class="container-lg">

    <!-- ALERTES -->
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success py-2 mb-3" style="font-size:.88rem;">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php
            $s = $_GET['success'];
            if ($s == 1)              echo "Membre ajouté avec succès. Un email avec son code membre lui a été envoyé.";
            elseif ($s === 'actif')   echo "Membre réactivé avec succès.";
            elseif ($s === 'retire')  echo "Membre retiré avec succès.";
            elseif ($s === 'exclu')   echo "Membre exclu avec succès.";
        ?>
    </div>
    <?php endif; ?>

    <!-- TABLEAU DES MEMBRES -->
    <div class="section-card">
        <div class="sc-header">
            <h6><i class="bi bi-people-fill text-primary me-2"></i>Membres de la tontine</h6>
            <span class="badge bg-primary"><?= count($membres) ?> membre(s)</span>
        </div>
        <div>
            <table>
                <thead>
                    <tr>
                        <th>Nom & Prénom</th>
                        <th>Email</th>
                        <th>Code membre</th>
                        <th>Adhésion</th>
                        <th>Solde</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($membres as $m): ?>
                <tr>
                    <td>
                        <i class="bi bi-person-circle text-muted me-1"></i>
                        <strong><?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?></strong>
                    </td>
                    <td><?= htmlspecialchars($m['email']) ?></td>
                    <td>
                        <code style="background:#eff6ff;color:#1d4ed8;padding:2px 8px;
                                     border-radius:5px;font-size:.82rem;">
                            <?= htmlspecialchars($m['code_membre']) ?>
                        </code>
                    </td>
                    <td><?= date('d/m/Y', strtotime($m['date_adhesion'])) ?></td>
                    <td style="font-weight:600;color:#1d4ed8;">
                        <?= number_format($m['solde'], 0, ',', ' ') ?> FCFA
                    </td>
                    <td>
                        <?php if ($m['mt_statut'] === 'actif'): ?>
                            <span class="badge bg-success">Actif</span>
                        <?php elseif ($m['mt_statut'] === 'retire'): ?>
                            <span class="badge bg-secondary">Retiré</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Exclu</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <?php if ($m['mt_statut'] === 'actif'): ?>
                            <a href="?id=<?= $tontine_id ?>&action=retire&mid=<?= $m['id'] ?>"
                               class="btn btn-outline-secondary btn-sm me-1"
                               onclick="return confirm('Retirer ce membre de la tontine ?')">
                                <i class="bi bi-person-dash"></i> Retirer
                            </a>
                            <a href="?id=<?= $tontine_id ?>&action=exclu&mid=<?= $m['id'] ?>"
                               class="btn btn-outline-danger btn-sm"
                               onclick="return confirm('Exclure définitivement ce membre ?')">
                                <i class="bi bi-slash-circle"></i> Exclure
                            </a>
                        <?php else: ?>
                            <a href="?id=<?= $tontine_id ?>&action=actif&mid=<?= $m['id'] ?>"
                               class="btn btn-outline-success btn-sm"
                               onclick="return confirm('Réactiver ce membre dans la tontine ?')">
                                <i class="bi bi-arrow-counterclockwise"></i> Réactiver
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($membres)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <i class="bi bi-people fs-3 d-block mb-2"></i>
                        Aucun membre dans cette tontine
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- AJOUTER UN MEMBRE -->
    <?php if (!$is_admin): ?>
    <div class="section-card">
        <div class="sc-header">
            <h6><i class="bi bi-person-plus text-success me-2"></i>Ajouter un membre</h6>
        </div>
        <div class="p-3">
            <?php if (count($disponibles) > 0): ?>
            <form method="POST" class="row g-3 align-items-end">
                <div class="col-md-7">
                    <label class="form-label small fw-bold text-muted">
                        Sélectionner un membre inscrit
                    </label>
                    <select name="membre_id" class="form-select" required style="border-radius:8px;">
                        <option value="">-- Choisir --</option>
                        <?php foreach ($disponibles as $d): ?>
                        <option value="<?= $d['id'] ?>">
                            <?= htmlspecialchars($d['prenom'] . ' ' . $d['nom']) ?>
                            – <?= htmlspecialchars($d['email']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" name="ajouter_membre" class="btn btn-success w-100">
                        <i class="bi bi-plus-lg me-1"></i>Ajouter &amp; notifier
                    </button>
                </div>
            </form>
            <p class="text-muted small mt-2 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Un email avec le code membre sera automatiquement envoyé au membre ajouté.
            </p>
            <?php else: ?>
            <p class="text-muted small mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Aucun membre disponible à ajouter pour l'instant.
            </p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>