<?php
require_once 'config.php';
require_login();
check_user_type('representant');

$user    = get_logged_user();
$user_id = $user['id'];

// Flash message
$flash = (isset($_SESSION['flash']) && is_array($_SESSION['flash'])) ? $_SESSION['flash'] : null;
unset($_SESSION['flash']);

// Toutes les tontines du représentant
$stmt = $pdo->prepare("SELECT * FROM tontines WHERE representant_id = ? ORDER BY statut='active' DESC, date_debut DESC");
$stmt->execute([$user_id]);
$tontines_rep = $stmt->fetchAll();

// Tontine sélectionnée (via ?tontine_id= ou première active)
$tontine_id_sel = (int) ($_GET['tontine_id'] ?? 0);
$tontine = null;

if ($tontine_id_sel > 0) {
    $stmt = $pdo->prepare("SELECT * FROM tontines WHERE id = ? AND representant_id = ?");
    $stmt->execute([$tontine_id_sel, $user_id]);
    $tontine = $stmt->fetch();
}
if (!$tontine && !empty($tontines_rep)) {
    // Prendre la première active, sinon la première
    foreach ($tontines_rep as $t) {
        if ($t['statut'] === 'active') { $tontine = $t; break; }
    }
    if (!$tontine) $tontine = $tontines_rep[0];
}

// Stats globales (toutes ses tontines)
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT mt.membre_id) FROM membres_tontines mt JOIN tontines t ON t.id = mt.tontine_id WHERE t.representant_id = ? AND mt.statut = 'actif'");
$stmt->execute([$user_id]);
$total_membres_global = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tontines WHERE representant_id = ? AND statut = 'active'");
$stmt->execute([$user_id]);
$tontines_actives_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(c.montant),0) FROM cotisations c JOIN tontines t ON t.id = c.tontine_id WHERE t.representant_id = ? AND c.statut = 'payee'");
$stmt->execute([$user_id]);
$total_collecte_global = $stmt->fetchColumn();

// Stats de la tontine sélectionnée
$stats = [];
$cotisations_recentes = [];
$prets_attente = [];
$membre_retard = null;
$prochain_tour = null;

if ($tontine) {
    $tid = $tontine['id'];

    $stats['nb_membres']    = $pdo->prepare("SELECT COUNT(*) FROM membres_tontines WHERE tontine_id = ? AND statut = 'actif'")->execute([$tid]) ? $pdo->query("SELECT COUNT(*) FROM membres_tontines WHERE tontine_id = $tid AND statut = 'actif'")->fetchColumn() : 0;

    $s = $pdo->prepare("SELECT COUNT(*) FROM membres_tontines WHERE tontine_id = ? AND statut = 'actif'"); $s->execute([$tid]); $stats['nb_membres'] = $s->fetchColumn();
    $s = $pdo->prepare("SELECT COUNT(*) FROM cotisations WHERE tontine_id = ? AND DATE(date_paiement)=CURDATE() AND statut='payee'"); $s->execute([$tid]); $stats['cotisations_jour'] = $s->fetchColumn();
    $s = $pdo->prepare("SELECT COUNT(*) FROM cotisations WHERE tontine_id = ? AND statut='en_retard'"); $s->execute([$tid]); $stats['cotisations_retard'] = $s->fetchColumn();
    $s = $pdo->prepare("SELECT COUNT(*) FROM prets WHERE tontine_id = ? AND statut='en_attente'"); $s->execute([$tid]); $stats['prets_en_attente'] = $s->fetchColumn();
    $s = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM cotisations WHERE tontine_id = ? AND MONTH(date_paiement)=MONTH(CURDATE()) AND YEAR(date_paiement)=YEAR(CURDATE()) AND statut='payee'"); $s->execute([$tid]); $stats['cotisations_mois'] = $s->fetchColumn();
    $s = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM cotisations WHERE tontine_id = ? AND statut='payee'"); $s->execute([$tid]); $stats['total_collecte'] = $s->fetchColumn();

    $s = $pdo->prepare("SELECT c.*, u.nom, u.prenom FROM cotisations c JOIN utilisateurs u ON c.membre_id = u.id WHERE c.tontine_id = ? ORDER BY c.date_paiement DESC LIMIT 8");
    $s->execute([$tid]); $cotisations_recentes = $s->fetchAll();

    $s = $pdo->prepare("SELECT p.*, u.nom, u.prenom FROM prets p JOIN utilisateurs u ON p.membre_id = u.id WHERE p.tontine_id = ? AND p.statut = 'en_attente' ORDER BY p.date_demande DESC LIMIT 5");
    $s->execute([$tid]); $prets_attente = $s->fetchAll();

    $s = $pdo->prepare("SELECT u.nom, u.prenom, COUNT(c.id) as retards FROM cotisations c JOIN utilisateurs u ON c.membre_id = u.id WHERE c.tontine_id = ? AND c.statut='en_retard' GROUP BY c.membre_id ORDER BY retards DESC LIMIT 1");
    $s->execute([$tid]); $membre_retard = $s->fetch();

    $s = $pdo->prepare("SELECT * FROM tours WHERE tontine_id = ? AND statut = 'en_cours' ORDER BY date_tour ASC LIMIT 1");
    $s->execute([$tid]); $prochain_tour = $s->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Représentant – Afriton</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        /* KPI */
        .kpi-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:12px; margin-bottom:22px; }
        .kpi { background:#fff; border-radius:12px; padding:16px; border:1px solid #e2e8f0; display:flex; align-items:center; gap:10px; box-shadow:0 2px 8px rgba(0,0,0,.04); }
        .kpi-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
        .kpi-val  { font-size:1.3rem; font-weight:800; color:#1e293b; line-height:1; }
        .kpi-lbl  { font-size:.70rem; color:#64748b; margin-top:2px; }
        /* Sélecteur tontines */
        .tontine-pills { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
        .t-pill {
            display:inline-flex; align-items:center; gap:6px;
            padding:8px 16px; border-radius:50px;
            border:1.5px solid #e2e8f0; background:#fff;
            font-size:.84rem; font-weight:600; color:#475569;
            text-decoration:none; transition:all .2s;
        }
        .t-pill:hover { border-color:#18392b; color:#18392b; }
        .t-pill.active { background:#18392b; color:white; border-color:#18392b; }
        .t-pill .dot { width:7px; height:7px; border-radius:50%; background:#22c55e; }
        .t-pill .dot.inactive { background:#94a3b8; }
        /* Cards */
        .s-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; margin-bottom:20px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.04); }
        .s-card-hdr { padding:14px 18px; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; }
        .s-card-hdr h3 { font-size:.92rem; font-weight:700; color:#1e293b; margin:0; }
        .s-card table { width:100%; border-collapse:collapse; }
        .s-card th { background:#f8fafc; font-size:.73rem; font-weight:700; color:#64748b; text-transform:uppercase; padding:8px 14px; border-bottom:1px solid #e2e8f0; }
        .s-card td { padding:10px 14px; font-size:.85rem; color:#334155; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        .s-card tr:last-child td { border-bottom:none; }
        /* Modules */
        .modules-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:12px; padding:16px; }
        .mod-card {
            background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;
            padding:18px 14px; text-align:center; text-decoration:none;
            color:#334155; transition:all .2s; display:block;
        }
        .mod-card:hover { background:#18392b; color:white; border-color:#18392b; transform:translateY(-3px); box-shadow:0 8px 24px rgba(24,57,43,.2); }
        .mod-card i { font-size:1.6rem; display:block; margin-bottom:8px; color:#18392b; }
        .mod-card:hover i { color:white; }
        .mod-card .mod-title { font-size:.82rem; font-weight:700; }
        /* Alertes info */
        .alert-info-box { background:#eff6ff; border-left:4px solid #3b82f6; border-radius:10px; padding:14px 16px; margin-bottom:12px; font-size:.85rem; color:#1e3a8a; }
        .alert-warn-box  { background:#fffbeb; border-left:4px solid #f59e0b; border-radius:10px; padding:14px 16px; margin-bottom:12px; font-size:.85rem; color:#78350f; }
        /* Empty state */
        .empty-box { text-align:center; padding:60px 20px; color:#94a3b8; }
        .empty-box i { font-size:3rem; display:block; margin-bottom:16px; }
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
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:10px;">
                <div>
                    <h1 style="font-family:'Cormorant Garamond',serif;font-size:1.5rem;font-weight:700;color:#1e293b;margin:0 0 4px;">
                        Bonjour, <?= htmlspecialchars($user['prenom']) ?>
                    </h1>
                    <p style="color:#64748b;font-size:.875rem;margin:0;">
                        <?= $tontines_actives_count ?> tontine(s) active(s) · <?= $total_membres_global ?> membres au total
                    </p>
                </div>
                <a href="tontine.php?action=create"
                   style="display:inline-flex;align-items:center;gap:7px;
                          background:linear-gradient(135deg,#18392b,#2d6a4f);
                          color:white;border-radius:10px;padding:10px 20px;
                          font-weight:700;font-size:.875rem;text-decoration:none;
                          box-shadow:0 4px 16px rgba(24,57,43,.25);">
                    <i class="bi bi-plus-circle-fill"></i> Créer une tontine
                </a>
            </div>

            <!-- KPI globaux -->
            <div class="kpi-grid">
                <div class="kpi">
                    <div class="kpi-icon" style="background:#d1fae5;color:#065f46;"><i class="bi bi-collection-fill"></i></div>
                    <div><div class="kpi-val"><?= count($tontines_rep) ?></div><div class="kpi-lbl">Mes tontines</div></div>
                </div>
                <div class="kpi">
                    <div class="kpi-icon" style="background:#dbeafe;color:#1d4ed8;"><i class="bi bi-people-fill"></i></div>
                    <div><div class="kpi-val"><?= $total_membres_global ?></div><div class="kpi-lbl">Membres total</div></div>
                </div>
                <div class="kpi">
                    <div class="kpi-icon" style="background:#fef9c3;color:#854d0e;"><i class="bi bi-cash-coin"></i></div>
                    <div><div class="kpi-val"><?= number_format($total_collecte_global/1000, 0) ?>K</div><div class="kpi-lbl">FCFA collectés</div></div>
                </div>
            </div>

            <?php if (empty($tontines_rep)): ?>
            <!-- CAS : AUCUNE TONTINE -->
            <div class="s-card">
                <div class="empty-box">
                    <i class="bi bi-collection"></i>
                    <h4 style="font-family:'Cormorant Garamond',serif;font-weight:700;color:#1e293b;margin-bottom:10px;">
                        Aucune tontine créée
                    </h4>
                    <p style="font-size:.9rem;margin-bottom:24px;">
                        Créez votre première tontine pour commencer à gérer vos membres et cotisations.
                    </p>
                    <a href="tontine.php?action=create"
                       style="display:inline-flex;align-items:center;gap:8px;
                              background:linear-gradient(135deg,#18392b,#2d6a4f);
                              color:white;border-radius:11px;padding:12px 28px;
                              font-weight:700;text-decoration:none;font-size:.95rem;">
                        <i class="bi bi-plus-circle-fill"></i> Créer ma première tontine
                    </a>
                </div>
            </div>

            <?php else: ?>

            <!-- Sélecteur tontines -->
            <div class="tontine-pills">
                <?php foreach ($tontines_rep as $t): ?>
                <a href="?tontine_id=<?= $t['id'] ?>"
                   class="t-pill <?= ($tontine && $tontine['id'] === $t['id']) ? 'active' : '' ?>">
                    <span class="dot <?= $t['statut'] !== 'active' ? 'inactive' : '' ?>"></span>
                    <?= htmlspecialchars($t['nom']) ?>
                </a>
                <?php endforeach; ?>
                <a href="tontine.php" class="t-pill" style="border-style:dashed;">
                    <i class="bi bi-list-ul"></i> Gérer mes tontines
                </a>
            </div>

            <?php if ($tontine): ?>
            <!-- Infos tontine sélectionnée -->
            <div style="background:white;border-radius:14px;border:1px solid #e2e8f0;
                        padding:18px 22px;margin-bottom:20px;
                        display:flex;align-items:center;justify-content:space-between;
                        flex-wrap:wrap;gap:12px;">
                <div>
                    <div style="font-family:'Cormorant Garamond',serif;font-size:1.25rem;font-weight:700;color:#1e293b;">
                        <?= htmlspecialchars($tontine['nom']) ?>
                    </div>
                    <div style="font-size:.8rem;color:#64748b;margin-top:3px;">
                        <i class="bi bi-arrow-repeat me-1"></i><?= ucfirst($tontine['frequence']) ?> ·
                        <i class="bi bi-cash-coin me-1"></i><?= number_format($tontine['montant_cotisation'],0,',',' ') ?> FCFA ·
                        <i class="bi bi-calendar3 me-1"></i>Depuis <?= date('d/m/Y', strtotime($tontine['date_debut'])) ?>
                    </div>
                </div>
                <div style="display:flex;gap:8px;">
                    <a href="membres_tontines.php?id=<?= $tontine['id'] ?>"
                       class="btn btn-sm btn-primary">
                        <i class="bi bi-people me-1"></i>Membres
                    </a>
                    <a href="tontine.php?action=edit&id=<?= $tontine['id'] ?>"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil"></i>
                    </a>
                </div>
            </div>

            <!-- KPI tontine -->
            <div class="kpi-grid" style="grid-template-columns:repeat(auto-fill,minmax(140px,1fr));">
                <div class="kpi">
                    <div class="kpi-icon" style="background:#dbeafe;color:#1d4ed8;"><i class="bi bi-people-fill"></i></div>
                    <div><div class="kpi-val"><?= $stats['nb_membres'] ?></div><div class="kpi-lbl">Membres actifs</div></div>
                </div>
                <div class="kpi">
                    <div class="kpi-icon" style="background:#d1fae5;color:#065f46;"><i class="bi bi-cash-stack"></i></div>
                    <div><div class="kpi-val"><?= number_format($stats['cotisations_mois']/1000,0) ?>K</div><div class="kpi-lbl">Ce mois (FCFA)</div></div>
                </div>
                <div class="kpi">
                    <div class="kpi-icon" style="background:#fee2e2;color:#991b1b;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    <div><div class="kpi-val"><?= $stats['cotisations_retard'] ?></div><div class="kpi-lbl">En retard</div></div>
                </div>
                <div class="kpi">
                    <div class="kpi-icon" style="background:#fef3c7;color:#92400e;"><i class="bi bi-bank"></i></div>
                    <div><div class="kpi-val"><?= $stats['prets_en_attente'] ?></div><div class="kpi-lbl">Prêts à traiter</div></div>
                </div>
                <div class="kpi">
                    <div class="kpi-icon" style="background:#ede9fe;color:#5b21b6;"><i class="bi bi-check2-all"></i></div>
                    <div><div class="kpi-val"><?= $stats['cotisations_jour'] ?></div><div class="kpi-lbl">Payés aujourd'hui</div></div>
                </div>
            </div>

            <!-- Alertes -->
            <?php if ($stats['cotisations_retard'] > 0): ?>
            <div class="alert-warn-box">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong><?= $stats['cotisations_retard'] ?> cotisation(s) en retard</strong> dans cette tontine.
                <a href="cotisations.php?tontine_id=<?= $tontine['id'] ?>&filter=retard" class="ms-2" style="color:#92400e;font-weight:700;">Voir →</a>
            </div>
            <?php endif; ?>
            <?php if ($stats['prets_en_attente'] > 0): ?>
            <div class="alert-info-box">
                <i class="bi bi-clock-history me-2"></i>
                <strong><?= $stats['prets_en_attente'] ?> demande(s) de prêt</strong> en attente d'approbation.
                <a href="prets.php?tontine_id=<?= $tontine['id'] ?>&action=approuver" class="ms-2" style="color:#1d4ed8;font-weight:700;">Traiter →</a>
            </div>
            <?php endif; ?>
            <?php if ($prochain_tour): ?>
            <div class="alert-info-box" style="background:#f0fdf4;border-color:#22c55e;color:#065f46;">
                <i class="bi bi-calendar-check me-2"></i>
                Prochain tour : <strong>Tour n°<?= $prochain_tour['numero_tour'] ?></strong> le
                <strong><?= date('d/m/Y', strtotime($prochain_tour['date_tour'])) ?></strong>
            </div>
            <?php endif; ?>

            <!-- 2 colonnes -->
            <div style="display:grid;grid-template-columns:2fr 1fr;gap:18px;margin-bottom:20px;">

                <!-- Cotisations récentes -->
                <div class="s-card">
                    <div class="s-card-hdr">
                        <h3><i class="bi bi-cash-stack me-2 text-success"></i>Cotisations récentes</h3>
                        <a href="cotisations.php?tontine_id=<?= $tontine['id'] ?>" style="font-size:.78rem;font-weight:700;color:#2563eb;text-decoration:none;">Voir toutes →</a>
                    </div>
                    <?php if (empty($cotisations_recentes)): ?>
                    <div style="text-align:center;padding:30px;color:#94a3b8;font-size:.85rem;">Aucune cotisation enregistrée.</div>
                    <?php else: ?>
                    <div style="max-height:280px;overflow-y:auto;">
                    <table>
                        <thead><tr><th>Date</th><th>Membre</th><th>Montant</th><th>Statut</th></tr></thead>
                        <tbody>
                        <?php foreach ($cotisations_recentes as $c): ?>
                        <tr>
                            <td><?= date('d/m', strtotime($c['date_paiement'])) ?></td>
                            <td><?= htmlspecialchars($c['prenom'].' '.$c['nom']) ?></td>
                            <td style="font-weight:700;"><?= number_format($c['montant'],0,',',' ') ?> F</td>
                            <td>
                                <?php if ($c['statut']==='payee'): ?><span style="background:#d1fae5;color:#065f46;border-radius:10px;padding:2px 8px;font-size:.72rem;font-weight:700;">Payée</span>
                                <?php elseif ($c['statut']==='en_retard'): ?><span style="background:#fee2e2;color:#991b1b;border-radius:10px;padding:2px 8px;font-size:.72rem;font-weight:700;">Retard</span>
                                <?php else: ?><span style="background:#fef3c7;color:#92400e;border-radius:10px;padding:2px 8px;font-size:.72rem;font-weight:700;">Attente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Actions rapides + Infos -->
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <div class="s-card">
                        <div class="s-card-hdr"><h3><i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Actions rapides</h3></div>
                        <div style="padding:14px;display:flex;flex-direction:column;gap:8px;">
                            <a href="cotisations.php?tontine_id=<?= $tontine['id'] ?>&action=collecter" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Collecter cotisation</a>
                            <a href="prets.php?tontine_id=<?= $tontine['id'] ?>&action=approuver" class="btn btn-sm btn-outline-secondary"><i class="bi bi-bank me-1"></i>Approuver prêts</a>
                            <a href="tours.php?tontine_id=<?= $tontine['id'] ?>&action=creer" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i>Créer un tour</a>
                            <a href="sanctions.php?tontine_id=<?= $tontine['id'] ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-exclamation-triangle me-1"></i>Sanctions</a>
                            <a href="membres_tontines.php?id=<?= $tontine['id'] ?>" class="btn btn-sm btn-success"><i class="bi bi-person-plus me-1"></i>Ajouter membre</a>
                        </div>
                    </div>
                    <?php if ($membre_retard): ?>
                    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:14px 16px;font-size:.82rem;">
                        <div style="font-weight:700;color:#78350f;margin-bottom:4px;"><i class="bi bi-person-x me-1"></i>Membre le + en retard</div>
                        <div style="color:#92400e;"><?= htmlspecialchars($membre_retard['prenom'].' '.$membre_retard['nom']) ?></div>
                        <div style="color:#a16207;"><?= $membre_retard['retards'] ?> retard(s)</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modules de gestion -->
            <div class="s-card">
                <div class="s-card-hdr">
                    <h3><i class="bi bi-grid-3x3-gap-fill me-2"></i>Modules de gestion</h3>
                    <span style="font-size:.78rem;color:#64748b;"><?= htmlspecialchars($tontine['nom']) ?></span>
                </div>
                <div class="modules-grid">
                    <?php
                    $modules = [
                        ['membres_tontines.php?id='.$tontine['id'],          'bi-people-fill',       'Membres'],
                        ['cotisations.php?tontine_id='.$tontine['id'],       'bi-cash-stack',        'Cotisations'],
                        ['prets.php?tontine_id='.$tontine['id'],             'bi-bank',              'Prêts'],
                        ['tours.php?tontine_id='.$tontine['id'],             'bi-arrow-clockwise',   'Tours'],
                        ['sanctions.php?tontine_id='.$tontine['id'],         'bi-shield-exclamation','Sanctions'],
                        ['rapports.php?tontine_id='.$tontine['id'],          'bi-bar-chart-fill',    'Rapports'],
                    ];
                    foreach ($modules as [$url, $ico, $titre]):
                    ?>
                    <a href="<?= $url ?>" class="mod-card">
                        <i class="bi <?= $ico ?>"></i>
                        <div class="mod-title"><?= $titre ?></div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php endif; // $tontine ?>
            <?php endif; // has tontines ?>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>