<?php
require_once 'config.php';
require_login();
check_user_type('membre');

$user    = get_logged_user();
$user_id = $user['id'];

$flash = (isset($_SESSION['flash']) && is_array($_SESSION['flash'])) ? $_SESSION['flash'] : null;
unset($_SESSION['flash']);

// Tontines actives du membre
$stmt = $pdo->prepare("
    SELECT t.*, mt.solde, mt.id as mt_id
    FROM tontines t
    JOIN membres_tontines mt ON t.id = mt.tontine_id
    WHERE mt.membre_id = ? AND mt.statut = 'actif'
    ORDER BY t.nom
");
$stmt->execute([$user_id]);
$tontines = $stmt->fetchAll();

$tontine_id = (int) ($_GET['tontine_id'] ?? ($tontines[0]['id'] ?? 0));
$tontine    = null;
foreach ($tontines as $t) {
    if ($t['id'] === $tontine_id) { $tontine = $t; break; }
}
if (!$tontine && !empty($tontines)) $tontine = $tontines[0];

// FACTEUR DÉMO
define('DEMO_FACTOR', 100);

// ── SOUMETTRE DEMANDE APRÈS SUCCÈS CAMPAY ──
// Statut = 'en_attente' → le représentant doit confirmer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['campay_success'])) {
    $tid          = (int) $_POST['tontine_id'];
    $montant_reel = (float) $_POST['montant_reel'];
    $montant_bd   = $montant_reel * DEMO_FACTOR;
    $reference    = trim($_POST['reference']);
    $mode         = $_POST['mode_paiement'];

    if ($montant_reel > 0 && !empty($reference)) {
        // Insérer avec statut 'en_attente' — le représentant confirme
        $stmt = $pdo->prepare("
            INSERT INTO cotisations
                (tontine_id, membre_id, montant, date_paiement, statut, mode_paiement, reference_paiement)
            VALUES (?, ?, ?, CURDATE(), 'en_attente', ?, ?)
        ");
        $stmt->execute([$tid, $user_id, $montant_bd, $mode, $reference]);

        log_action('Demande recharge', "Membre $user_id – {$montant_bd} FCFA – Réf: $reference – en attente");
        $_SESSION['flash'] = [
            'type' => 'success',
            'msg'  => " Paiement envoyé ! Le représentant va vérifier et confirmer votre dépôt de " . number_format($montant_bd, 0, ',', ' ') . " FCFA."
        ];
        header("Location: recharge_membre.php?tontine_id=$tid");
        exit();
    }
}

// Historique des cotisations du membre
$historique = [];
if ($tontine) {
    $stmt = $pdo->prepare("
        SELECT * FROM cotisations
        WHERE tontine_id = ? AND membre_id = ?
        ORDER BY date_paiement DESC LIMIT 10
    ");
    $stmt->execute([$tontine['id'], $user_id]);
    $historique = $stmt->fetchAll();
}

$montant_cotisation = $tontine ? $tontine['montant_cotisation'] : 0;
$montant_demo       = min(25, max(1, round($montant_cotisation / DEMO_FACTOR)));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recharger mon compte – Afriton</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://demo.campay.net/sdk/js?app-id=CmOHuodsISlqOcPDWT7zmLPM37hTwIIgSz1k8zW4WGUXUf4R-jMpwHeKpIPbq6rZT6U0siWNb2R9og8EntTa2w"></script>
    <style>
        body { font-family: Arial, sans-serif; }
        .solde-card {
            background: linear-gradient(135deg, #18392B, #2d6a4f);
            border-radius: 16px; padding: 24px 28px; color: white; margin-bottom: 24px;
        }
        .solde-label  { font-size:.78rem; opacity:.7; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px; }
        .solde-montant { font-size:2rem; font-weight:800; margin-bottom:4px; }
        .form-card {
            background:white; border-radius:14px; border:1px solid #e2e8f0;
            padding:24px; margin-bottom:20px; box-shadow:0 2px 8px rgba(0,0,0,.04);
        }
        .form-card h5 { font-size:1rem; font-weight:700; color:#1e293b; margin-bottom:20px; padding-bottom:12px; border-bottom:1px solid #f1f5f9; }
        .mode-tabs { display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; }
        .mode-tab {
            flex:1; min-width:110px; border:2px solid #e2e8f0; border-radius:12px;
            padding:12px 8px; text-align:center; cursor:pointer; background:white;
            transition:all .2s; font-size:.82rem; font-weight:700; color:#475569;
        }
        .mode-tab .tab-logo { font-size:1.6rem; display:block; margin-bottom:4px; }
        .mode-tab.tab-mtn.active    { border-color:#f59e0b; background:#fffbeb; color:#92400e; }
        .mode-tab.tab-orange.active { border-color:#f97316; background:#fff7ed; color:#9a3412; }
        .form-label { font-size:.83rem; font-weight:600; color:#334155; }
        .form-control { border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; font-size:.9rem; }
        .form-control:focus { border-color:#18392B; box-shadow:0 0 0 3px rgba(24,57,43,.12); outline:none; }
        .demo-info {
            background:#fef3c7; border:1px solid #f59e0b; border-radius:10px;
            padding:12px 16px; font-size:.82rem; color:#78350f;
            margin-bottom:16px; display:flex; gap:8px; align-items:flex-start;
        }
        /* Explication flux */
        .flux-box {
            background:#eff6ff; border:1px solid #93c5fd; border-radius:10px;
            padding:14px 16px; margin-bottom:16px; font-size:.82rem; color:#1e3a8a;
        }
        .flux-step {
            display:flex; align-items:center; gap:10px; margin-bottom:8px;
        }
        .flux-step:last-child { margin-bottom:0; }
        .flux-num {
            width:24px; height:24px; border-radius:50%; background:#1d4ed8;
            color:white; font-size:.72rem; font-weight:800;
            display:flex; align-items:center; justify-content:center; flex-shrink:0;
        }
        .recap {
            background:#f0fdf4; border:1px solid #86efac; border-radius:10px;
            padding:14px 16px; margin-bottom:16px; font-size:.875rem;
        }
        .recap-row { display:flex; justify-content:space-between; margin-bottom:6px; }
        .recap-row:last-child { margin-bottom:0; border-top:1px solid #bbf7d0; padding-top:8px; font-weight:700; }
        #payButton {
            background:linear-gradient(135deg,#18392B,#2d6a4f);
            color:white; border:none; border-radius:10px;
            width:100%; padding:13px; font-size:.95rem; font-weight:700;
            cursor:pointer; transition:opacity .2s;
            display:flex; align-items:center; justify-content:center; gap:8px;
        }
        #payButton:hover { opacity:.88; }
        .hist-card { background:white; border-radius:14px; border:1px solid #e2e8f0; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.04); }
        .hist-card table { width:100%; border-collapse:collapse; }
        .hist-card th { background:#f8fafc; font-size:.73rem; font-weight:700; color:#64748b; text-transform:uppercase; padding:10px 16px; border-bottom:1px solid #e2e8f0; }
        .hist-card td { padding:11px 16px; font-size:.85rem; color:#334155; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        .hist-card tr:last-child td { border-bottom:none; }
        .badge-attente { background:#fef3c7; color:#92400e; border-radius:20px; padding:3px 10px; font-size:.72rem; font-weight:700; }
        .badge-payee   { background:#d1fae5; color:#065f46; border-radius:20px; padding:3px 10px; font-size:.72rem; font-weight:700; }
        .badge-refuse  { background:#fee2e2; color:#991b1b; border-radius:20px; padding:3px 10px; font-size:.72rem; font-weight:700; }
        .badge-mtn     { background:#fef3c7; color:#92400e; border-radius:20px; padding:3px 10px; font-size:.72rem; font-weight:700; }
        .badge-orange  { background:#ffedd5; color:#9a3412; border-radius:20px; padding:3px 10px; font-size:.72rem; font-weight:700; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include 'sidebar_membres.php'; ?>
    <div class="main-content">
        <?php include 'topbar.php'; ?>
        <div class="content-area">

            <!-- Flash -->
            <?php if ($flash && isset($flash['type'], $flash['msg'])): ?>
            <?php $ok = $flash['type'] === 'success'; ?>
            <div style="background:<?= $ok?'#d1fae5':'#fee2e2' ?>;border:1px solid <?= $ok?'#22c55e':'#f87171' ?>;
                        color:<?= $ok?'#065f46':'#991b1b' ?>;border-radius:10px;padding:12px 18px;
                        margin-bottom:20px;display:flex;align-items:center;gap:10px;font-size:.9rem;font-weight:600;">
                <i class="bi <?= $ok?'bi-check-circle-fill':'bi-x-circle-fill' ?>"></i>
                <?= htmlspecialchars((string)$flash['msg']) ?>
            </div>
            <?php endif; ?>

            <div style="margin-bottom:20px;">
                <h1 style="font-size:1.4rem;font-weight:800;color:#1e293b;margin:0 0 4px;">
                    <i class="bi bi-wallet2 me-2" style="color:#18392B;"></i>Recharger mon compte
                </h1>
                <p style="color:#64748b;font-size:.875rem;margin:0;">Payez votre cotisation via Mobile Money</p>
            </div>

            <?php if (empty($tontines)): ?>
            <div style="text-align:center;padding:60px;color:#94a3b8;">
                <i class="bi bi-people" style="font-size:3rem;display:block;margin-bottom:16px;"></i>
                <p>Vous n'êtes membre d'aucune tontine active.</p>
            </div>
            <?php else: ?>

            <?php if (count($tontines) > 1): ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
                <?php foreach ($tontines as $t): ?>
                <a href="?tontine_id=<?= $t['id'] ?>"
                   style="padding:8px 16px;border-radius:50px;text-decoration:none;font-size:.84rem;font-weight:600;
                          border:1.5px solid <?= $tontine && $tontine['id']===$t['id'] ? '#18392B':'#e2e8f0' ?>;
                          background:<?= $tontine && $tontine['id']===$t['id'] ? '#18392B':'white' ?>;
                          color:<?= $tontine && $tontine['id']===$t['id'] ? 'white':'#475569' ?>;">
                    <?= htmlspecialchars($t['nom']) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Solde -->
            <div class="solde-card">
                <div class="solde-label">Mon solde actuel</div>
                <div class="solde-montant"><?= number_format($tontine['solde'] ?? 0, 0, ',', ' ') ?> FCFA</div>
                <div style="font-size:.82rem;opacity:.7;">
                    <i class="bi bi-collection me-1"></i><?= htmlspecialchars($tontine['nom']) ?> ·
                    Cotisation : <?= number_format($tontine['montant_cotisation'], 0, ',', ' ') ?> FCFA
                </div>
            </div>

            <div class="row g-4">

                <!-- FORMULAIRE -->
                <div class="col-lg-5">
                    <div class="form-card">
                        <h5><i class="bi bi-phone me-2"></i>Payer ma cotisation</h5>

                        <!-- Flux explication -->
                        <div class="flux-box">
                            <div style="font-weight:700;margin-bottom:10px;font-size:.84rem;">
                                <i class="bi bi-info-circle me-1"></i> Comment ça fonctionne :
                            </div>
                            <div class="flux-step">
                                <span class="flux-num">1</span>
                                <span>Vous payez via Mobile Money</span>
                            </div>
                            <div class="flux-step">
                                <span class="flux-num">2</span>
                                <span>Le représentant reçoit une notification</span>
                            </div>
                            <div class="flux-step">
                                <span class="flux-num">3</span>
                                <span>Il <strong>confirme</strong> → votre solde est crédité</span>
                            </div>
                        </div>

                        <!-- Modes -->
                        <div class="mode-tabs">
                            <div class="mode-tab tab-mtn active" onclick="switchMode('mtn')">
                                <span class="tab-logo"></span>MTN MoMo
                            </div>
                            <div class="mode-tab tab-orange" onclick="switchMode('orange')">
                                <span class="tab-logo"></span>Orange Money
                            </div>
                        </div>

                        <!-- Récap -->
                        <div class="recap">
                            <div class="recap-row">
                                <span>Cotisation tontine</span>
                                <span><?= number_format($tontine['montant_cotisation'], 0, ',', ' ') ?> FCFA</span>
                            </div>
                            <div class="recap-row">
                                <span>Montant prélevé (démo)</span>
                                <span><?= $montant_demo ?> XAF</span>
                            </div>
                            <div class="recap-row">
                                <span style="color:#065f46;">Sera crédité après confirmation</span>
                                <span style="color:#065f46;"><?= number_format($montant_demo * DEMO_FACTOR, 0, ',', ' ') ?> FCFA</span>
                            </div>
                        </div>

                        <!-- Bouton CamPay -->
                        <button id="payButton" type="button">
                            <i class="bi bi-phone-fill"></i>
                            <span id="payButtonText">Payer avec MTN MoMo</span>
                        </button>

                        <!-- Formulaire caché -->
                        <form id="formCamPay" method="POST" style="display:none;">
                            <input type="hidden" name="campay_success" value="1">
                            <input type="hidden" name="tontine_id"     value="<?= $tontine['id'] ?>">
                            <input type="hidden" name="montant_reel"   value="<?= $montant_demo ?>">
                            <input type="hidden" name="mode_paiement"  id="hidden_mode">
                            <input type="hidden" name="reference"      id="hidden_reference">
                        </form>

                        <p style="text-align:center;font-size:.75rem;color:#94a3b8;margin-top:14px;">
                            <i class="bi bi-shield-lock me-1"></i>Paiement sécurisé via CamPay
                        </p>
                    </div>
                </div>

                <!-- HISTORIQUE -->
                <div class="col-lg-7">
                    <div class="hist-card">
                        <div style="padding:14px 18px;border-bottom:1px solid #e2e8f0;
                                    display:flex;align-items:center;justify-content:space-between;">
                            <h5 style="font-size:.95rem;font-weight:700;color:#1e293b;margin:0;">
                                <i class="bi bi-clock-history me-2"></i>Mes paiements
                            </h5>
                            <span style="font-size:.78rem;color:#64748b;"><?= count($historique) ?> dernier(s)</span>
                        </div>
                        <?php if (empty($historique)): ?>
                        <div style="text-align:center;padding:40px;color:#94a3b8;font-size:.875rem;">
                            <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:12px;"></i>
                            Aucun paiement enregistré.
                        </div>
                        <?php else: ?>
                        <div style="overflow-x:auto;">
                        <table>
                            <thead>
                                <tr><th>Date</th><th>Montant</th><th>Mode</th><th>Réf.</th><th>Statut</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($historique as $h): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($h['date_paiement'])) ?></td>
                                <td style="font-weight:700;color:#18392B;">
                                    <?= number_format($h['montant'], 0, ',', ' ') ?> FCFA
                                </td>
                                <td>
                                    <?php $mode = $h['mode_paiement'] ?? ''; ?>
                                    <?php if ($mode === 'mtn_momo'): ?>
                                        <span class="badge-mtn">MTN</span>
                                    <?php elseif ($mode === 'orange_money'): ?>
                                        <span class="badge-orange">Orange</span>
                                    <?php else: ?>
                                        <span style="color:#64748b;font-size:.8rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color:#94a3b8;font-size:.78rem;">
                                    <?= $h['reference_paiement'] ? htmlspecialchars(substr($h['reference_paiement'],0,12)).'...' : '—' ?>
                                </td>
                                <td>
                                    <?php if ($h['statut'] === 'payee'): ?>
                                        <span class="badge-payee"> Confirmé</span>
                                    <?php elseif ($h['statut'] === 'en_attente'): ?>
                                        <span class="badge-attente"> En attente</span>
                                    <?php else: ?>
                                        <span class="badge-refuse"> Refusé</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentMode = 'mtn';
const montantDemo = <?= $montant_demo ?>;

function switchMode(mode) {
    currentMode = mode;
    document.querySelectorAll('.mode-tab').forEach(t => t.classList.remove('active'));
    document.querySelector('.tab-' + (mode === 'mtn' ? 'mtn' : 'orange')).classList.add('active');
    document.getElementById('payButtonText').textContent =
        mode === 'mtn' ? 'Payer avec MTN MoMo' : 'Payer avec Orange Money';
    updateCamPay();
}

function updateCamPay() {
    campay.options({
        payButtonId:       "payButton",
        description:       "Cotisation tontine Afriton",
        amount:            String(montantDemo),
        currency:          "XAF",
        externalReference: "AFR-" + Date.now(),
        redirectUrl:       "",
    });
}

campay.onSuccess = function(data) {
    document.getElementById('hidden_mode').value      = currentMode === 'mtn' ? 'mtn_momo' : 'orange_money';
    document.getElementById('hidden_reference').value = data.reference;
    document.getElementById('formCamPay').submit();
}

campay.onFail = function(data) {
    alert(' Paiement échoué. Veuillez réessayer.');
}

campay.onModalClose = function(data) {
    if (data.status !== 'SUCCESSFUL') console.log('Modal fermé.');
}

document.addEventListener('DOMContentLoaded', updateCamPay);
</script>
</body>
</html>