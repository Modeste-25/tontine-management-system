<?php
require_once 'config.php';
require_login();
check_user_type('admin');

$user = get_logged_user();

// Flash message
$flash = (isset($_SESSION['flash']) && is_array($_SESSION['flash'])) ? $_SESSION['flash'] : null;
unset($_SESSION['flash']);

// Onglet actif
$active_tab = $_GET['tab'] ?? 'tontines';

// ── Statistiques ──
$stats = [
    'total_membres'      => $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE type_utilisateur = 'membre'")->fetchColumn(),
    'total_tontines'     => $pdo->query("SELECT COUNT(*) FROM tontines")->fetchColumn(),
    'tontines_actives'   => $pdo->query("SELECT COUNT(*) FROM tontines WHERE statut = 'active'")->fetchColumn(),
    'cotisations_payees' => $pdo->query("SELECT COUNT(*) FROM cotisations WHERE statut = 'payee'")->fetchColumn(),
    'cotisations_retard' => $pdo->query("SELECT COUNT(*) FROM cotisations WHERE statut = 'en_retard'")->fetchColumn(),
    'prets_actifs'       => $pdo->query("SELECT COUNT(*) FROM prets WHERE statut = 'approuve'")->fetchColumn(),
    'representants'      => $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE type_utilisateur = 'representant' AND statut = 'actif'")->fetchColumn(),
    'en_attente'         => $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE type_utilisateur = 'representant' AND statut = 'en_attente'")->fetchColumn(),
];

// ── Toutes les tontines ──
$tontines = $pdo->query("
    SELECT t.*, u.prenom, u.nom AS representant_nom,
           COUNT(mt.id) AS nb_membres
    FROM tontines t
    LEFT JOIN utilisateurs u ON t.representant_id = u.id
    LEFT JOIN membres_tontines mt ON mt.tontine_id = t.id AND mt.statut = 'actif'
    GROUP BY t.id
    ORDER BY t.date_debut DESC
")->fetchAll();

// ── Membres récents ──
$membres_recents = $pdo->query("
    SELECT * FROM utilisateurs
    WHERE type_utilisateur = 'membre'
    ORDER BY date_inscription DESC LIMIT 5
")->fetchAll();

// ── Activités récentes ──
$activites = $pdo->query("
    SELECT h.*, u.prenom, u.nom, u.type_utilisateur
    FROM historiques h
    LEFT JOIN utilisateurs u ON h.utilisateur_id = u.id
    ORDER BY h.date_action DESC LIMIT 8
")->fetchAll();

// ── Représentants actifs ──
$representants = $pdo->query("
    SELECT u.*, COUNT(t.id) AS nb_tontines,
           SUM(CASE WHEN t.statut = 'active' THEN 1 ELSE 0 END) AS tontines_actives
    FROM utilisateurs u
    LEFT JOIN tontines t ON t.representant_id = u.id
    WHERE u.type_utilisateur = 'representant' AND u.statut = 'actif'
    GROUP BY u.id ORDER BY u.date_inscription DESC
")->fetchAll();

// ── Représentants en attente ──
$rep_attente = $pdo->query("
    SELECT * FROM utilisateurs
    WHERE type_utilisateur = 'representant' AND statut = 'en_attente'
    ORDER BY date_inscription ASC
")->fetchAll();

// ── Représentants refusés ──
$rep_refuses = $pdo->query("
    SELECT * FROM utilisateurs
    WHERE type_utilisateur = 'representant' AND statut = 'refuse'
    ORDER BY date_inscription DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Administrateur – Afriton</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Badges statut */
        .b-active   { background:#d1fae5; color:#065f46; border-radius:20px; padding:3px 11px; font-size:.73rem; font-weight:600; white-space:nowrap; }
        .b-attente  { background:#fef3c7; color:#92400e; border-radius:20px; padding:3px 11px; font-size:.73rem; font-weight:600; white-space:nowrap; }
        .b-refuse   { background:#fee2e2; color:#991b1b; border-radius:20px; padding:3px 11px; font-size:.73rem; font-weight:600; white-space:nowrap; }
        .b-inactive { background:#f1f5f9; color:#475569; border-radius:20px; padding:3px 11px; font-size:.73rem; font-weight:600; white-space:nowrap; }
        .b-admin    { background:#fee2e2; color:#991b1b; border-radius:20px; padding:3px 11px; font-size:.73rem; font-weight:600; }
        .b-repres   { background:#fef3c7; color:#92400e; border-radius:20px; padding:3px 11px; font-size:.73rem; font-weight:600; }
        .b-membre   { background:#dbeafe; color:#1e40af; border-radius:20px; padding:3px 11px; font-size:.73rem; font-weight:600; }

        /* KPI */
        .kpi-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(165px,1fr)); gap:14px; margin-bottom:26px; }
        .kpi { background:#fff; border-radius:12px; padding:18px 16px; border:1px solid #e2e8f0; display:flex; align-items:center; gap:12px; box-shadow:0 2px 8px rgba(0,0,0,.04); }
        .kpi-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
        .kpi-val  { font-size:1.4rem; font-weight:800; color:#1e293b; line-height:1; }
        .kpi-lbl  { font-size:.72rem; color:#64748b; margin-top:3px; }
        .kpi-pulse { border:2px solid #f59e0b; animation: pulseKpi 2s infinite; }
        @keyframes pulseKpi { 0%,100%{box-shadow:0 0 0 0 rgba(245,158,11,.3)} 50%{box-shadow:0 0 0 6px rgba(245,158,11,.0)} }

        /* Onglets */
        .tab-btns { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
        .tab-btn {
            display:inline-flex; align-items:center; gap:6px;
            padding:9px 18px; border-radius:8px; border:1.5px solid #e2e8f0;
            background:#fff; cursor:pointer; font-size:.875rem; font-weight:600;
            color:#475569; transition:all .2s; position:relative;
        }
        .tab-btn.active, .tab-btn:hover { background:#1e40af; color:#fff; border-color:#1e40af; }
        .tab-badge {
            background:#ef4444; color:#fff; border-radius:50px;
            font-size:.65rem; font-weight:800; padding:1px 6px;
            min-width:18px; text-align:center;
        }
        .tab-panel { display:none; }
        .tab-panel.active { display:block; }

        /* Cards tableau */
        .s-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; margin-bottom:22px; box-shadow:0 2px 8px rgba(0,0,0,.04); }
        .s-card-header { padding:15px 20px; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; }
        .s-card-header h3 { font-size:.95rem; font-weight:700; color:#1e293b; margin:0; }
        .s-card table { width:100%; border-collapse:collapse; }
        .s-card table th { background:#f8fafc; font-size:.76rem; font-weight:700; color:#64748b; text-transform:uppercase; padding:9px 16px; border-bottom:1px solid #e2e8f0; }
        .s-card table td { padding:11px 16px; font-size:.875rem; color:#334155; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        .s-card table tr:last-child td { border-bottom:none; }
        .s-card table tr:hover td { background:#fafafa; }

        /* Boutons action validation */
        .btn-accept {
            display:inline-flex; align-items:center; gap:5px;
            padding:6px 14px; border-radius:7px;
            background:linear-gradient(135deg,#22c55e,#16a34a);
            color:#fff; font-size:.8rem; font-weight:700;
            border:none; cursor:pointer; transition:opacity .2s;
        }
        .btn-accept:hover { opacity:.88; }
        .btn-refuse {
            display:inline-flex; align-items:center; gap:5px;
            padding:6px 14px; border-radius:7px;
            background:linear-gradient(135deg,#f87171,#ef4444);
            color:#fff; font-size:.8rem; font-weight:700;
            border:none; cursor:pointer; transition:opacity .2s;
        }
        .btn-refuse:hover { opacity:.88; }
        .btn-reactivate {
            display:inline-flex; align-items:center; gap:5px;
            padding:6px 14px; border-radius:7px;
            background:linear-gradient(135deg,#60a5fa,#2563eb);
            color:#fff; font-size:.8rem; font-weight:700;
            border:none; cursor:pointer; transition:opacity .2s;
        }
        .btn-reactivate:hover { opacity:.88; }

        /* Carte profil représentant (onglet validation) */
        .rep-card {
            border:1px solid #e2e8f0; border-radius:12px;
            padding:18px 20px;
            display:flex; align-items:center; justify-content:space-between;
            flex-wrap:wrap; gap:12px;
            background:#fff;
        }
        .rep-card.attente { border-left:4px solid #f59e0b; }
        .rep-card.refuse  { border-left:4px solid #ef4444; }
        .rep-avatar {
            width:44px; height:44px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-weight:800; font-size:1rem; flex-shrink:0;
        }
        .empty-state { text-align:center; padding:40px 20px; color:#94a3b8; font-size:.875rem; }
        .view-lnk { color:#2563eb; font-size:.8rem; font-weight:600; text-decoration:none; }
        .view-lnk:hover { text-decoration:underline; }

        /* Expand tontines */
        .tontine-row { cursor:pointer; }
        .tontine-detail-row { background:#f8fafc; }
        .tontine-detail-row td { padding:0 !important; }
        .tontine-detail-inner { padding:14px 20px; border-top:2px solid #e2e8f0; display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:12px; }
        .detail-item-lbl { font-size:.7rem; font-weight:700; text-transform:uppercase; color:#94a3b8; margin-bottom:3px; }
        .detail-item-val { font-size:.875rem; color:#334155; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include 'sidebar_admin.php'; ?>
    <div class="main-content">
        <?php include 'topbar.php'; ?>
        <div class="content-area">

            <!-- Flash -->
            <?php if ($flash && isset($flash['type'], $flash['msg'])): ?>
            <?php $is_success = ($flash['type'] === 'success'); ?>
            <div style="background:<?= $is_success ? '#d1fae5' : '#fee2e2' ?>;
                        border:1px solid <?= $is_success ? '#22c55e' : '#f87171' ?>;
                        color:<?= $is_success ? '#065f46' : '#991b1b' ?>;
                        border-radius:10px;padding:12px 18px;margin-bottom:20px;
                        display:flex;align-items:center;gap:10px;font-size:.9rem;font-weight:600;">
                <i class="bi <?= $is_success ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                <?= htmlspecialchars((string)$flash['msg']) ?>
            </div>
            <?php endif; ?>

            <!-- En-tête -->
            <div style="margin-bottom:24px;">
                <h1 style="font-size:1.4rem;font-weight:800;color:#1e293b;margin:0 0 4px;">Tableau de Bord Administrateur</h1>
                <p style="color:#64748b;font-size:.875rem;margin:0;">Vue globale du système Afriton</p>
            </div>

            <!-- KPI -->
            <div class="kpi-grid">
                <div class="kpi">
                    <div class="kpi-icon" style="background:#dbeafe;color:#1d4ed8;"><i class="bi bi-people-fill"></i></div>
                    <div><div class="kpi-val"><?= $stats['total_membres'] ?></div><div class="kpi-lbl">Membres</div></div>
                </div>
                <div class="kpi">
                    <div class="kpi-icon" style="background:#d1fae5;color:#065f46;"><i class="bi bi-collection-fill"></i></div>
                    <div><div class="kpi-val"><?= $stats['tontines_actives'] ?></div><div class="kpi-lbl">Tontines actives</div></div>
                </div>
                <div class="kpi">
                    <div class="kpi-icon" style="background:#fef9c3;color:#854d0e;"><i class="bi bi-person-badge-fill"></i></div>
                    <div><div class="kpi-val"><?= $stats['representants'] ?></div><div class="kpi-lbl">Représentants actifs</div></div>
                </div>
                <?php if ($stats['en_attente'] > 0): ?>
                <div class="kpi kpi-pulse">
                    <div class="kpi-icon" style="background:#fef3c7;color:#d97706;"><i class="bi bi-hourglass-split"></i></div>
                    <div><div class="kpi-val" style="color:#d97706;"><?= $stats['en_attente'] ?></div><div class="kpi-lbl">En attente validation</div></div>
                </div>
                <?php endif; ?>
                <div class="kpi">
                    <div class="kpi-icon" style="background:#ede9fe;color:#5b21b6;"><i class="bi bi-cash-stack"></i></div>
                    <div><div class="kpi-val"><?= $stats['cotisations_payees'] ?></div><div class="kpi-lbl">Cotisations payées</div></div>
                </div>
                <div class="kpi">
                    <div class="kpi-icon" style="background:#fee2e2;color:#991b1b;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    <div><div class="kpi-val"><?= $stats['cotisations_retard'] ?></div><div class="kpi-lbl">Retards paiement</div></div>
                </div>
                <div class="kpi">
                    <div class="kpi-icon" style="background:#fce7f3;color:#9d174d;"><i class="bi bi-bank"></i></div>
                    <div><div class="kpi-val"><?= $stats['prets_actifs'] ?></div><div class="kpi-lbl">Prêts actifs</div></div>
                </div>
            </div>

            <!-- Onglets -->
            <div class="tab-btns">
                <button class="tab-btn <?= $active_tab==='tontines'?'active':'' ?>" onclick="switchTab('tontines',this)">
                    <i class="bi bi-collection"></i> Tontines
                </button>
                <button class="tab-btn <?= $active_tab==='validation'?'active':'' ?>" onclick="switchTab('validation',this)">
                    <i class="bi bi-person-check"></i> Validation comptes
                    <?php if ($stats['en_attente'] > 0): ?>
                        <span class="tab-badge"><?= $stats['en_attente'] ?></span>
                    <?php endif; ?>
                </button>
                <button class="tab-btn <?= $active_tab==='representants'?'active':'' ?>" onclick="switchTab('representants',this)">
                    <i class="bi bi-person-badge"></i> Représentants actifs
                </button>
                <button class="tab-btn <?= $active_tab==='membres'?'active':'' ?>" onclick="switchTab('membres',this)">
                    <i class="bi bi-people"></i> Membres récents
                </button>
                <button class="tab-btn <?= $active_tab==='activites'?'active':'' ?>" onclick="switchTab('activites',this)">
                    <i class="bi bi-clock-history"></i> Activités
                </button>
            </div>

            <!-- ══ ONGLET : TONTINES ══ -->
            <div id="tab-tontines" class="tab-panel <?= $active_tab==='tontines'?'active':'' ?>">
                <div class="s-card">
                    <div class="s-card-header">
                        <h3><i class="bi bi-collection-fill me-2 text-primary"></i>Toutes les tontines créées</h3>
                        <span style="font-size:.8rem;color:#64748b;"><?= count($tontines) ?> tontine(s)</span>
                    </div>
                    <?php if (empty($tontines)): ?>
                        <div class="empty-state"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Aucune tontine créée.</div>
                    <?php else: ?>
                    <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th></th><th>Nom</th><th>Représentant</th>
                                <th>Membres</th><th>Cotisation</th><th>Fréquence</th>
                                <th>Date début</th><th>Statut</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tontines as $t): ?>
                        <tr class="tontine-row" onclick="toggleRow('tr-<?= $t['id'] ?>',this)">
                            <td style="width:30px;text-align:center;">
                                <i class="bi bi-chevron-right toggle-ic" style="font-size:.75rem;color:#94a3b8;transition:transform .2s;"></i>
                            </td>
                            <td><strong><?= htmlspecialchars($t['nom']) ?></strong></td>
                            <td><?= $t['representant_nom'] ? htmlspecialchars($t['prenom'].' '.$t['representant_nom']) : '<span style="color:#94a3b8">—</span>' ?></td>
                            <td><?= $t['nb_membres'] ?></td>
                            <td style="font-weight:700;color:#1e40af;"><?= number_format($t['montant_cotisation'],0,',',' ') ?> FCFA</td>
                            <td><?= ucfirst($t['frequence']) ?></td>
                            <td><?= date('d/m/Y',strtotime($t['date_debut'])) ?></td>
                            <td>
                                <?php if ($t['statut']==='active'): ?><span class="b-active">Active</span>
                                <?php elseif ($t['statut']==='inactive'): ?><span class="b-inactive">Inactive</span>
                                <?php else: ?><span class="b-inactive">Terminée</span><?php endif; ?>
                            </td>
                            <td><a href="tontine.php?action=view&id=<?= $t['id'] ?>" class="view-lnk" onclick="event.stopPropagation()"><i class="bi bi-eye me-1"></i>Voir</a></td>
                        </tr>
                        <tr class="tontine-detail-row" id="tr-<?= $t['id'] ?>" style="display:none;">
                            <td colspan="9">
                                <div class="tontine-detail-inner">
                                    <div><div class="detail-item-lbl">Description</div><div class="detail-item-val"><?= $t['description'] ? htmlspecialchars($t['description']) : '—' ?></div></div>
                                    <div><div class="detail-item-lbl">Date de fin</div><div class="detail-item-val"><?= $t['date_fin'] ? date('d/m/Y',strtotime($t['date_fin'])) : '—' ?></div></div>
                                    <div>
                                        <div class="detail-item-lbl">Supervision (lecture seule)</div>
                                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;">
                                            <a href="tontine.php?action=view&id=<?= $t['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye me-1"></i>Consulter
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ══ ONGLET : VALIDATION ══ -->
            <div id="tab-validation" class="tab-panel <?= $active_tab==='validation'?'active':'' ?>">

                <!-- En attente -->
                <div class="s-card">
                    <div class="s-card-header">
                        <h3><i class="bi bi-hourglass-split me-2" style="color:#f59e0b;"></i>
                            Comptes en attente de validation
                            <?php if (!empty($rep_attente)): ?>
                                <span style="background:#fef3c7;color:#92400e;border-radius:20px;padding:2px 10px;font-size:.73rem;margin-left:8px;">
                                    <?= count($rep_attente) ?> en attente
                                </span>
                            <?php endif; ?>
                        </h3>
                    </div>

                    <?php if (empty($rep_attente)): ?>
                        <div class="empty-state">
                            <i class="bi bi-check-circle-fill text-success fs-2 d-block mb-2"></i>
                            Aucun compte en attente — tout est à jour !
                        </div>
                    <?php else: ?>
                    <div style="padding:16px;display:flex;flex-direction:column;gap:12px;">
                        <?php foreach ($rep_attente as $r): ?>
                        <div class="rep-card attente">
                            <!-- Avatar + infos -->
                            <div style="display:flex;align-items:center;gap:14px;flex:1;min-width:200px;">
                                <div class="rep-avatar" style="background:#fef3c7;color:#92400e;">
                                    <?= strtoupper(substr($r['prenom'],0,1).substr($r['nom'],0,1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight:700;font-size:.95rem;color:#1e293b;">
                                        <?= htmlspecialchars($r['prenom'].' '.$r['nom']) ?>
                                    </div>
                                    <div style="font-size:.8rem;color:#64748b;">
                                        <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($r['email']) ?>
                                    </div>
                                    <?php if ($r['telephone']): ?>
                                    <div style="font-size:.8rem;color:#64748b;">
                                        <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($r['telephone']) ?>
                                    </div>
                                    <?php endif; ?>
                                    <div style="font-size:.75rem;color:#94a3b8;margin-top:3px;">
                                        Inscrit le <?= date('d/m/Y à H:i', strtotime($r['date_inscription'])) ?>
                                    </div>
                                </div>
                            </div>
                            <!-- Badge + boutons -->
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                <span class="b-attente"><i class="bi bi-hourglass-split me-1"></i>En attente</span>
                                <!-- Accepter -->
                                <a href="accepter_representant.php?id=<?= $r['id'] ?>"
                                   class="btn-accept"
                                   onclick="return confirm('Accepter <?= htmlspecialchars(addslashes($r['prenom'].' '.$r['nom'])) ?> comme représentant ?')">
                                    <i class="bi bi-check-lg"></i> Accepter
                                </a>
                                <!-- Refuser -->
                                <a href="refuser_representant.php?id=<?= $r['id'] ?>"
                                   class="btn-refuse"
                                   onclick="return confirm('Refuser <?= htmlspecialchars(addslashes($r['prenom'].' '.$r['nom'])) ?> ?')">
                                    <i class="bi bi-x-lg"></i> Refuser
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Refusés (avec option réactivation) -->
                <?php if (!empty($rep_refuses)): ?>
                <div class="s-card">
                    <div class="s-card-header">
                        <h3><i class="bi bi-x-circle-fill me-2" style="color:#ef4444;"></i>Comptes refusés</h3>
                        <span style="font-size:.8rem;color:#64748b;"><?= count($rep_refuses) ?> refusé(s)</span>
                    </div>
                    <div style="padding:16px;display:flex;flex-direction:column;gap:12px;">
                        <?php foreach ($rep_refuses as $r): ?>
                        <div class="rep-card refuse">
                            <div style="display:flex;align-items:center;gap:14px;flex:1;min-width:200px;">
                                <div class="rep-avatar" style="background:#fee2e2;color:#991b1b;">
                                    <?= strtoupper(substr($r['prenom'],0,1).substr($r['nom'],0,1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight:700;font-size:.95rem;color:#1e293b;"><?= htmlspecialchars($r['prenom'].' '.$r['nom']) ?></div>
                                    <div style="font-size:.8rem;color:#64748b;"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($r['email']) ?></div>
                                    <div style="font-size:.75rem;color:#94a3b8;margin-top:3px;">Inscrit le <?= date('d/m/Y', strtotime($r['date_inscription'])) ?></div>
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                <span class="b-refuse"><i class="bi bi-x-circle me-1"></i>Refusé</span>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="representant_id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="action_validation" value="reactiver">
                                    <button type="submit" class="btn-reactivate"
                                            onclick="return confirm('Réactiver ce compte ?')">
                                        <i class="bi bi-arrow-clockwise"></i> Réactiver
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ══ ONGLET : REPRÉSENTANTS ACTIFS ══ -->
            <div id="tab-representants" class="tab-panel <?= $active_tab==='representants'?'active':'' ?>">
                <div class="s-card">
                    <div class="s-card-header">
                        <h3><i class="bi bi-person-badge-fill me-2" style="color:#d97706;"></i>Représentants actifs</h3>
                        <span style="font-size:.8rem;color:#64748b;"><?= count($representants) ?> représentant(s)</span>
                    </div>
                    <?php if (empty($representants)): ?>
                        <div class="empty-state"><i class="bi bi-person-x fs-2 d-block mb-2"></i>Aucun représentant actif.</div>
                    <?php else: ?>
                    <div style="overflow-x:auto;">
                    <table>
                        <thead><tr><th>Représentant</th><th>Email</th><th>Téléphone</th><th>Tontines</th><th>Actives</th><th>Inscription</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($representants as $r): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['prenom'].' '.$r['nom']) ?></strong></td>
                            <td><?= htmlspecialchars($r['email']) ?></td>
                            <td><?= htmlspecialchars($r['telephone'] ?? '—') ?></td>
                            <td style="text-align:center;font-weight:700;"><?= $r['nb_tontines'] ?></td>
                            <td style="text-align:center;"><span class="b-active"><?= $r['tontines_actives'] ?></span></td>
                            <td><?= date('d/m/Y',strtotime($r['date_inscription'])) ?></td>
                            <td>
                                <a href="representants.php?action=view&id=<?= $r['id'] ?>" class="view-lnk">
                                    <i class="bi bi-eye me-1"></i>Voir
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ══ ONGLET : MEMBRES RÉCENTS ══ -->
            <div id="tab-membres" class="tab-panel <?= $active_tab==='membres'?'active':'' ?>">
                <div class="s-card">
                    <div class="s-card-header">
                        <h3><i class="bi bi-people-fill me-2 text-primary"></i>Nouveaux membres</h3>
                        <a href="membres.php" class="view-lnk">Voir tous <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <table>
                        <thead><tr><th>Nom & Prénom</th><th>Email</th><th>Téléphone</th><th>Inscription</th><th>Statut</th></tr></thead>
                        <tbody>
                        <?php foreach ($membres_recents as $m): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($m['nom'].' '.$m['prenom']) ?></strong></td>
                            <td><?= htmlspecialchars($m['email']) ?></td>
                            <td><?= htmlspecialchars($m['telephone'] ?? '—') ?></td>
                            <td><?= date('d/m/Y',strtotime($m['date_inscription'])) ?></td>
                            <td>
                                <?php if ($m['statut']==='actif'): ?><span class="b-active">Actif</span>
                                <?php elseif ($m['statut']==='inactif'): ?><span class="b-inactive">Inactif</span>
                                <?php else: ?><span class="b-refuse">Suspendu</span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ══ ONGLET : ACTIVITÉS ══ -->
            <div id="tab-activites" class="tab-panel <?= $active_tab==='activites'?'active':'' ?>">
                <div class="s-card">
                    <div class="s-card-header">
                        <h3><i class="bi bi-clock-history me-2" style="color:#7c3aed;"></i>Activités récentes</h3>
                    </div>
                    <table>
                        <thead><tr><th>Date & Heure</th><th>Utilisateur</th><th>Rôle</th><th>Action</th><th>Détails</th></tr></thead>
                        <tbody>
                        <?php foreach ($activites as $a): ?>
                        <tr>
                            <td style="white-space:nowrap;"><?= date('d/m/Y H:i',strtotime($a['date_action'])) ?></td>
                            <td><?= htmlspecialchars($a['prenom'].' '.$a['nom']) ?></td>
                            <td>
                                <?php if ($a['type_utilisateur']==='admin'): ?><span class="b-admin">Admin</span>
                                <?php elseif ($a['type_utilisateur']==='representant'): ?><span class="b-repres">Représentant</span>
                                <?php else: ?><span class="b-membre">Membre</span><?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($a['action']) ?></td>
                            <td style="color:#64748b;font-size:.82rem;"><?= htmlspecialchars(substr($a['details'] ?? '',0,60)) ?>…</td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    if (btn) btn.classList.add('active');
    // Mettre à jour l'URL sans reload
    history.replaceState(null, '', '?tab=' + name);
}

function toggleRow(id, row) {
    const tr   = document.getElementById(id);
    const icon = row.querySelector('.toggle-ic');
    const open = tr.style.display !== 'none';
    tr.style.display = open ? 'none' : 'table-row';
    if (icon) icon.style.transform = open ? '' : 'rotate(90deg)';
}
</script>
</body>
</html>
