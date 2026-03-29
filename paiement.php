<?php
require_once 'config.php';
require_login();
check_user_type('representant');

$user    = get_logged_user();
$user_id = $user['id'];

// Flash message
$flash = (isset($_SESSION['flash']) && is_array($_SESSION['flash'])) ? $_SESSION['flash'] : null;
unset($_SESSION['flash']);

// Tontines du représentant
$stmt = $pdo->prepare("SELECT * FROM tontines WHERE representant_id = ? AND statut = 'active' ORDER BY nom");
$stmt->execute([$user_id]);
$tontines = $stmt->fetchAll();

// Tontine sélectionnée
$tontine_id = (int) ($_GET['tontine_id'] ?? ($tontines[0]['id'] ?? 0));
$tontine = null;
foreach ($tontines as $t) {
    if ($t['id'] === $tontine_id) { $tontine = $t; break; }
}
if (!$tontine && !empty($tontines)) $tontine = $tontines[0];

// Membres de la tontine sélectionnée
$membres = [];
if ($tontine) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.prenom, u.nom, u.telephone
        FROM membres_tontines mt
        JOIN utilisateurs u ON mt.membre_id = u.id
        WHERE mt.tontine_id = ? AND mt.statut = 'actif'
        ORDER BY u.nom
    ");
    $stmt->execute([$tontine['id']]);
    $membres = $stmt->fetchAll();
}

// ── ENREGISTRER PAIEMENT APRÈS SUCCÈS CAMPAY ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['campay_success'])) {
    $membre_id  = (int) $_POST['membre_id'];
    $montant    = (float) $_POST['montant'];
    $reference  = trim($_POST['reference']);
    $mode       = $_POST['mode_paiement']; // 'mtn_momo' | 'orange_money'
    $tid        = (int) $_POST['tontine_id'];
    $date_paie  = date('Y-m-d');

    if ($membre_id > 0 && $montant > 0 && !empty($reference)) {
        $stmt = $pdo->prepare("
            INSERT INTO cotisations
                (tontine_id, membre_id, montant, date_paiement, statut, mode_paiement, reference_paiement)
            VALUES (?, ?, ?, ?, 'payee', ?, ?)
        ");
        $stmt->execute([$tid, $membre_id, $montant, $date_paie, $mode, $reference]);
        log_action('Paiement CamPay', "Membre $membre_id – $montant FCFA – Réf: $reference");
        $_SESSION['flash'] = ['type' => 'success', 'msg' => " Paiement de " . number_format($montant, 0, ',', ' ') . " FCFA enregistré. Référence : $reference"];
        header("Location: paiement.php?tontine_id=$tid");
        exit();
    }
}

// ── ENREGISTRER PAIEMENT ESPÈCES ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['paiement_especes'])) {
    $membre_id = (int) $_POST['membre_id'];
    $montant   = (float) $_POST['montant'];
    $tid       = (int) $_POST['tontine_id'];
    $date_paie = $_POST['date_paiement'] ?? date('Y-m-d');

    if ($membre_id > 0 && $montant > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO cotisations
                (tontine_id, membre_id, montant, date_paiement, statut, mode_paiement)
            VALUES (?, ?, ?, ?, 'payee', 'especes')
        ");
        $stmt->execute([$tid, $membre_id, $montant, $date_paie]);
        log_action('Paiement Espèces', "Membre $membre_id – $montant FCFA en espèces");
        $_SESSION['flash'] = ['type' => 'success', 'msg' => " Paiement en espèces de " . number_format($montant, 0, ',', ' ') . " FCFA enregistré."];
        header("Location: paiement.php?tontine_id=$tid");
        exit();
    }
}

// Historique des paiements
$historique = [];
if ($tontine) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.prenom, u.nom, u.telephone
        FROM cotisations c
        JOIN utilisateurs u ON c.membre_id = u.id
        WHERE c.tontine_id = ?
        ORDER BY c.date_paiement DESC
        LIMIT 20
    ");
    $stmt->execute([$tontine['id']]);
    $historique = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiements – Afriton</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">

    <!-- SDK CamPay -->
    <script src="https://demo.campay.net/sdk/js?app-id=CmOHuodsISlqOcPDWT7zmLPM37hTwIIgSz1k8zW4WGUXUf4R-jMpwHeKpIPbq6rZT6U0siWNb2R9og8EntTa2w"></script>

    <style>
        body { font-family: Arial, sans-serif; }

        /* ── ONGLETS MODE ── */
        .mode-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .mode-tab {
            flex: 1;
            min-width: 120px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 10px;
            text-align: center;
            cursor: pointer;
            background: white;
            transition: all 0.2s;
            font-size: .85rem;
            font-weight: 700;
            color: #475569;
        }
        .mode-tab:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,.08); }
        .mode-tab .tab-logo { font-size: 1.8rem; display: block; margin-bottom: 6px; }

        /* Actif selon mode */
        .mode-tab.tab-mtn.active    { border-color: #f59e0b; background: #fffbeb; color: #92400e; }
        .mode-tab.tab-orange.active { border-color: #f97316; background: #fff7ed; color: #9a3412; }
        .mode-tab.tab-cash.active   { border-color: #22c55e; background: #f0fdf4; color: #065f46; }

        /* ── FORMULAIRE ── */
        .form-card {
            background: white;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,.04);
        }
        .form-card h5 {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        .form-label { font-size: .83rem; font-weight: 600; color: #334155; }
        .form-control, .form-select {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: .9rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #18392B;
            box-shadow: 0 0 0 3px rgba(24,57,43,0.12);
            outline: none;
        }

        /* ── BOUTON CAMPAY ── */
        #payButton {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            border: none;
            border-radius: 10px;
            width: 100%;
            padding: 13px;
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
            transition: opacity .2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        #payButton:hover { opacity: 0.88; }

        /* ── BOUTON ESPÈCES ── */
        .btn-cash {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            border: none;
            border-radius: 10px;
            width: 100%;
            padding: 13px;
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
            transition: opacity .2s;
        }
        .btn-cash:hover { opacity: 0.88; }

        /* ── HISTORIQUE ── */
        .hist-card {
            background: white;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,.04);
        }
        .hist-card table { width: 100%; border-collapse: collapse; }
        .hist-card th {
            background: #f8fafc;
            font-size: .73rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            padding: 10px 16px;
            border-bottom: 1px solid #e2e8f0;
        }
        .hist-card td {
            padding: 11px 16px;
            font-size: .85rem;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .hist-card tr:last-child td { border-bottom: none; }

        /* Badges */
        .badge-mtn    { background: #fef3c7; color: #92400e; border-radius: 20px; padding: 3px 10px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
        .badge-orange { background: #ffedd5; color: #9a3412; border-radius: 20px; padding: 3px 10px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
        .badge-cash   { background: #d1fae5; color: #065f46; border-radius: 20px; padding: 3px 10px; font-size: .72rem; font-weight: 700; white-space: nowrap; }

        /* Panneau selon mode */
        .panel { display: none; }
        .panel.active { display: block; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include 'sidebar_representant.php'; ?>
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

            <!-- En-tête -->
            <div style="margin-bottom:24px;">
                <h1 style="font-size:1.4rem;font-weight:800;color:#1e293b;margin:0 0 4px;">
                    <i class="bi bi-phone me-2" style="color:#18392B;"></i>Paiements
                </h1>
                <p style="color:#64748b;font-size:.875rem;margin:0;">
                    MTN MoMo · Orange Money · Espèces — via CamPay
                </p>
            </div>

            <?php if (empty($tontines)): ?>
            <div style="text-align:center;padding:60px;color:#94a3b8;">
                <i class="bi bi-collection" style="font-size:3rem;display:block;margin-bottom:16px;"></i>
                <p>Aucune tontine active. <a href="tontine.php?action=create">Créer une tontine</a></p>
            </div>
            <?php else: ?>

            <!-- Sélecteur tontine -->
            <?php if (count($tontines) > 1): ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
                <?php foreach ($tontines as $t): ?>
                <a href="?tontine_id=<?= $t['id'] ?>"
                   style="padding:8px 16px;border-radius:50px;
                          border:1.5px solid <?= $tontine && $tontine['id']===$t['id'] ? '#18392B' : '#e2e8f0' ?>;
                          background:<?= $tontine && $tontine['id']===$t['id'] ? '#18392B' : 'white' ?>;
                          color:<?= $tontine && $tontine['id']===$t['id'] ? 'white' : '#475569' ?>;
                          font-size:.84rem;font-weight:600;text-decoration:none;">
                    <?= htmlspecialchars($t['nom']) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="row g-4">

                <!-- COLONNE GAUCHE : Formulaire -->
                <div class="col-lg-5">
                    <div class="form-card">
                        <h5><i class="bi bi-credit-card me-2"></i>Nouveau paiement</h5>

                        <!-- Onglets mode -->
                        <div class="mode-tabs">
                            <div class="mode-tab tab-mtn active" onclick="switchMode('mtn')">
                                <span class="tab-logo"></span>
                                MTN MoMo
                            </div>
                            <div class="mode-tab tab-orange" onclick="switchMode('orange')">
                                <span class="tab-logo"></span>
                                Orange Money
                            </div>
                            <div class="mode-tab tab-cash" onclick="switchMode('cash')">
                                <span class="tab-logo"></span>
                                Espèces
                            </div>
                        </div>

                        <!-- Champs communs -->
                        <div class="mb-3">
                            <label class="form-label">Membre</label>
                            <select id="selectMembre" class="form-select" onchange="updateMembre()">
                                <option value="">-- Choisir un membre --</option>
                                <?php foreach ($membres as $m): ?>
                                <option value="<?= $m['id'] ?>"
                                        data-tel="<?= htmlspecialchars($m['telephone'] ?? '') ?>">
                                    <?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?>
                                    <?= $m['telephone'] ? ' – ' . $m['telephone'] : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Montant (FCFA)</label>
                            <input type="number" id="inputMontant" class="form-control"
                                   min="100" step="100"
                                   value="<?= $tontine['montant_cotisation'] ?>"
                                   placeholder="Ex: 10000"
                                   onchange="updateCamPay()">
                        </div>

                        <!-- ── PANNEAU MTN / ORANGE (CamPay) ── -->
                        <div id="panel-mobile" class="panel active">

                            <!-- Info numéro -->
                            <div style="background:#f8fafc;border-radius:8px;padding:10px 14px;
                                        margin-bottom:16px;font-size:.82rem;color:#64748b;">
                                <i class="bi bi-info-circle me-1"></i>
                                Le membre recevra une demande de paiement sur son téléphone.
                                Assurez-vous que son numéro est correct.
                            </div>

                            <!-- Bouton CamPay -->
                            <button id="payButton" type="button">
                                <i class="bi bi-phone-fill"></i>
                                <span id="payButtonText">Payer avec MTN MoMo</span>
                            </button>

                            <!-- Formulaire caché pour enregistrer après succès -->
                            <form id="formCamPay" method="POST" style="display:none;">
                                <input type="hidden" name="campay_success" value="1">
                                <input type="hidden" name="tontine_id" value="<?= $tontine['id'] ?>">
                                <input type="hidden" name="membre_id"  id="hidden_membre_id">
                                <input type="hidden" name="montant"    id="hidden_montant">
                                <input type="hidden" name="mode_paiement" id="hidden_mode">
                                <input type="hidden" name="reference"  id="hidden_reference">
                            </form>
                        </div>

                        <!-- ── PANNEAU ESPÈCES ── -->
                        <div id="panel-cash" class="panel">
                            <form method="POST">
                                <input type="hidden" name="paiement_especes" value="1">
                                <input type="hidden" name="tontine_id" value="<?= $tontine['id'] ?>">
                                <input type="hidden" name="membre_id"  id="cash_membre_id">
                                <input type="hidden" name="montant"    id="cash_montant" value="<?= $tontine['montant_cotisation'] ?>">

                                <div class="mb-3">
                                    <label class="form-label">Date du paiement</label>
                                    <input type="date" name="date_paiement" class="form-control"
                                           value="<?= date('Y-m-d') ?>">
                                </div>

                                <button type="submit" class="btn-cash"
                                        onclick="syncCashForm()">
                                    <i class="bi bi-cash-coin me-2"></i>Enregistrer le paiement
                                </button>
                            </form>
                        </div>

                    </div>
                </div>

                <!-- COLONNE DROITE : Historique -->
                <div class="col-lg-7">
                    <div class="hist-card">
                        <div style="padding:14px 18px;border-bottom:1px solid #e2e8f0;
                                    display:flex;align-items:center;justify-content:space-between;">
                            <h5 style="font-size:.95rem;font-weight:700;color:#1e293b;margin:0;">
                                <i class="bi bi-clock-history me-2"></i>Historique des paiements
                            </h5>
                            <span style="font-size:.78rem;color:#64748b;">
                                <?= count($historique) ?> dernier(s)
                            </span>
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
                                <tr>
                                    <th>Date</th>
                                    <th>Membre</th>
                                    <th>Montant</th>
                                    <th>Mode</th>
                                    <th>Référence</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($historique as $h): ?>
                            <tr>
                                <td style="white-space:nowrap;">
                                    <?= date('d/m/Y', strtotime($h['date_paiement'])) ?>
                                </td>
                                <td><?= htmlspecialchars($h['prenom'] . ' ' . $h['nom']) ?></td>
                                <td style="font-weight:700;color:#18392B;">
                                    <?= number_format($h['montant'], 0, ',', ' ') ?> F
                                </td>
                                <td>
                                    <?php $mode = $h['mode_paiement'] ?? 'especes'; ?>
                                    <?php if ($mode === 'mtn_momo'): ?>
                                        <span class="badge-mtn"> MTN MoMo</span>
                                    <?php elseif ($mode === 'orange_money'): ?>
                                        <span class="badge-orange"> Orange Money</span>
                                    <?php else: ?>
                                        <span class="badge-cash"> Espèces</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color:#94a3b8;font-size:.8rem;">
                                    <?= $h['reference_paiement'] ? htmlspecialchars($h['reference_paiement']) : '—' ?>
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

// Mode actuel : 'mtn' | 'orange' | 'cash'
let currentMode = 'mtn';

// ── Changer le mode ──
function switchMode(mode) {
    currentMode = mode;

    // Mettre à jour les onglets
    document.querySelectorAll('.mode-tab').forEach(t => t.classList.remove('active'));
    document.querySelector('.tab-' + (mode === 'mtn' ? 'mtn' : mode === 'orange' ? 'orange' : 'cash')).classList.add('active');

    // Afficher le bon panneau
    document.getElementById('panel-mobile').classList.remove('active');
    document.getElementById('panel-cash').classList.remove('active');

    if (mode === 'cash') {
        document.getElementById('panel-cash').classList.add('active');
    } else {
        document.getElementById('panel-mobile').classList.add('active');
        // Changer le texte du bouton
        document.getElementById('payButtonText').textContent =
            mode === 'mtn' ? 'Payer avec MTN MoMo' : 'Payer avec Orange Money';
    }

    // Mettre à jour CamPay
    updateCamPay();
}

// ── Mettre à jour la config CamPay ──
function updateCamPay() {
    const montant  = document.getElementById('inputMontant').value || '0';
    const membreEl = document.getElementById('selectMembre');
    const tel      = membreEl.options[membreEl.selectedIndex]?.dataset.tel || '';
    const mode     = currentMode === 'mtn' ? 'mtn_momo' : 'orange_money';

    campay.options({
        payButtonId:       "payButton",
        description:       "Cotisation tontine Afriton",
        amount:            montant,
        currency:          "XAF",
        externalReference: "AFR-" + Date.now(),
        redirectUrl:       "",
    });
}

// ── Quand on change le membre ──
function updateMembre() {
    updateCamPay();
}

// ── Succès CamPay ──
campay.onSuccess = function(data) {
    // Remplir le formulaire caché et soumettre
    document.getElementById('hidden_membre_id').value = document.getElementById('selectMembre').value;
    document.getElementById('hidden_montant').value   = document.getElementById('inputMontant').value;
    document.getElementById('hidden_mode').value      = currentMode === 'mtn' ? 'mtn_momo' : 'orange_money';
    document.getElementById('hidden_reference').value = data.reference;
    document.getElementById('formCamPay').submit();
}

// ── Échec CamPay ──
campay.onFail = function(data) {
    alert(' Paiement échoué. Statut : ' + data.status + '\nVeuillez réessayer.');
}

// ── Fermeture du modal ──
campay.onModalClose = function(data) {
    if (data.status !== 'SUCCESSFUL') {
        console.log('Modal fermé sans paiement.');
    }
}

// ── Synchroniser le formulaire espèces ──
function syncCashForm() {
    document.getElementById('cash_membre_id').value = document.getElementById('selectMembre').value;
    document.getElementById('cash_montant').value   = document.getElementById('inputMontant').value;
}

// Initialiser CamPay au chargement
document.addEventListener('DOMContentLoaded', function() {
    updateCamPay();
});
</script>
</body>
</html>