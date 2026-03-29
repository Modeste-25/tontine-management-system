<?php
require_once 'config.php';
require_login();
check_user_type('membre');

$user = get_logged_user();

$stmt = $pdo->prepare("
    SELECT t.*, mt.solde, mt.statut as statut_adhesion
    FROM tontines t
    JOIN membres_tontines mt ON t.id = mt.tontine_id
    WHERE mt.membre_id = ? AND mt.statut = 'actif'
");
$stmt->execute([$user['id']]);
$tontines = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT * FROM notifications
    WHERE utilisateur_id = ? AND lue = 0
    ORDER BY date_notification DESC LIMIT 10
");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT SUM(solde) FROM membres_tontines WHERE membre_id = ?");
$stmt->execute([$user['id']]);
$solde_total = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM cotisations WHERE membre_id = ? AND statut = 'en_attente'");
$stmt->execute([$user['id']]);
$cotisations_en_attente = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM prets WHERE membre_id = ? AND statut = 'approuve'");
$stmt->execute([$user['id']]);
$prets_actifs = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM sanctions WHERE membre_id = ? AND statut = 'active'");
$stmt->execute([$user['id']]);
$sanctions_actives = $stmt->fetchColumn();

$stats = [
    'solde_total'            => $solde_total,
    'cotisations_en_attente' => $cotisations_en_attente,
    'prets_actifs'           => $prets_actifs,
    'sanctions_actives'      => $sanctions_actives
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Membre</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Bouton recharge */
        .btn-recharge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 700;
            font-size: .875rem;
            text-decoration: none;
            transition: opacity .2s, transform .2s;
            cursor: pointer;
        }
        .btn-recharge:hover { opacity: .88; transform: translateY(-1px); color: white; }

        /* Carte solde hero */
        .solde-hero {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            border-radius: 16px;
            padding: 24px 28px;
            color: white;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }
        .solde-hero .montant {
            font-size: 2rem;
            font-weight: 800;
        }
        .solde-hero .label {
            font-size: .78rem;
            opacity: .7;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include 'sidebar_membres.php'; ?>

    <div class="main-content">
        <?php include 'topbar.php'; ?>

        <div class="content-area">
            <h1 style="margin-bottom:20px;">
                Bienvenue, <?php echo htmlspecialchars($user['prenom']); ?> !
            </h1>

            <!-- SOLDE HERO + BOUTON RECHARGE -->
            <div class="solde-hero">
                <div>
                    <div class="label">Mon solde total</div>
                    <div class="montant">
                        <?php echo number_format($stats['solde_total'], 0, ',', ' '); ?> FCFA
                    </div>
                    <div style="font-size:.82rem;opacity:.7;margin-top:4px;">
                        Toutes tontines confondues
                    </div>
                </div>
                <a href="recharge_membre.php" class="btn-recharge">
                    <i class="bi bi-wallet2"></i>
                    Recharger mon compte
                </a>
            </div>

            <!-- Notifications -->
            <?php if ($notifications): ?>
            <div class="card" style="margin-bottom:25px;">
                <div class="card-header">
                    <h2 class="card-title">Notifications Récentes</h2>
                </div>
                <div style="max-height:300px;overflow-y:auto;">
                    <?php foreach ($notifications as $notif): ?>
                    <div style="padding:15px;border-bottom:1px solid var(--border);
                                <?php echo $notif['type']==='alerte' ? 'background:#fff3cd;' : ''; ?>">
                        <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                            <strong><?php echo htmlspecialchars($notif['titre']); ?></strong>
                            <small><?php echo date('d/m/Y H:i', strtotime($notif['date_notification'])); ?></small>
                        </div>
                        <p style="margin:0;color:var(--text-light);">
                            <?php echo htmlspecialchars($notif['message']); ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Statistiques -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background:var(--success);"></div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['solde_total'], 0, ',', ' '); ?> FCFA</h3>
                        <p>Solde Total</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:var(--warning);"></div>
                    <div class="stat-info">
                        <h3><?php echo $stats['cotisations_en_attente']; ?></h3>
                        <p>Cotisations en attente</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:var(--info);"></div>
                    <div class="stat-info">
                        <h3><?php echo $stats['prets_actifs']; ?></h3>
                        <p>Prêts actifs</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:var(--danger);"></div>
                    <div class="stat-info">
                        <h3><?php echo $stats['sanctions_actives']; ?></h3>
                        <p>Sanctions actives</p>
                    </div>
                </div>
            </div>

            <!-- Mes tontines -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Mes Tontines</h2>
                </div>
                <?php if ($tontines): ?>
                <div class="module-grid">
                    <?php foreach ($tontines as $tontine): ?>
                    <div class="module-card">
                        <div class="module-icon"></div>
                        <div class="module-title"><?php echo htmlspecialchars($tontine['nom']); ?></div>
                        <div class="module-desc">
                            <p><strong>Cotisation:</strong> <?php echo number_format($tontine['montant_cotisation'], 0, ',', ' '); ?> FCFA</p>
                            <p><strong>Mon solde:</strong> <?php echo number_format($tontine['solde'], 0, ',', ' '); ?> FCFA</p>
                            <p><strong>Fréquence:</strong> <?php echo ucfirst($tontine['frequence']); ?></p>
                            <div style="margin-top:12px;display:flex;gap:6px;flex-wrap:wrap;">
                                <a href="recharge_membre.php?tontine_id=<?php echo $tontine['id']; ?>"
                                   style="display:inline-flex;align-items:center;gap:5px;
                                          background:linear-gradient(135deg,#18392B,#2d6a4f);
                                          color:white;border-radius:8px;padding:6px 12px;
                                          font-size:.78rem;font-weight:700;text-decoration:none;">
                                    <i class="bi bi-wallet2"></i> Recharger
                                </a>
                                <a href="cotisations.php?tontine=<?php echo $tontine['id']; ?>"
                                   class="btn btn-sm btn-primary">Cotisations</a>
                                <a href="prets.php?tontine=<?php echo $tontine['id']; ?>"
                                   class="btn btn-sm">Prêts</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div style="text-align:center;padding:40px;">
                    <h3 style="margin-bottom:10px;">Aucune tontine active</h3>
                    <p style="color:var(--text-light);margin-bottom:20px;">
                        Vous n'êtes actuellement membre d'aucune tontine.
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Actions Rapides -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Actions Rapides</h2>
                </div>
                <div class="module-grid">
                    <a href="recharge_membre.php" class="module-card">
                        <div class="module-icon"></div>
                        <div class="module-title">Recharger mon compte</div>
                        <div class="module-desc">Payer ma cotisation via Mobile Money</div>
                    </a>
                    <a href="prets.php?action=demander" class="module-card">
                        <div class="module-icon"></div>
                        <div class="module-title">Demander un prêt</div>
                        <div class="module-desc">Soumettre une demande de prêt</div>
                    </a>
                    <a href="prets.php?action=rembourser" class="module-card">
                        <div class="module-icon"></div>
                        <div class="module-title">Rembourser un prêt</div>
                        <div class="module-desc">Effectuer un remboursement</div>
                    </a>
                    <a href="notifications.php" class="module-card">
                        <div class="module-icon"></div>
                        <div class="module-title">Mes notifications</div>
                        <div class="module-desc">Consulter toutes les notifications</div>
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>
</body>
</html>