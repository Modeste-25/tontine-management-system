<?php
require_once 'config.php';
require_login();
check_user_type('representant');

$user = get_current_user();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM tontines WHERE representant_id = ? AND statut = 'active'");
$stmt->execute([$user_id]);
$tontine = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tontine) {
    header('Location: tontine.php?action=create');
    exit();
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM membres_tontines WHERE tontine_id = ? AND statut = 'actif'");
$stmt->execute([$tontine['id']]);
$total_membres = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM cotisations WHERE tontine_id = ? AND DATE(date_paiement) = CURDATE() AND statut = 'payee'");
$stmt->execute([$tontine['id']]);
$cotisations_jour = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM cotisations WHERE tontine_id = ? AND statut = 'en_retard'");
$stmt->execute([$tontine['id']]);
$cotisations_retard = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM prets WHERE tontine_id = ? AND statut = 'en_attente'");
$stmt->execute([$tontine['id']]);
$prets_en_attente = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT SUM(montant)
    FROM cotisations
    WHERE tontine_id = ?
    AND MONTH(date_paiement) = MONTH(CURDATE())
    AND YEAR(date_paiement) = YEAR(CURDATE())
    AND statut = 'payee'
");
$stmt->execute([$tontine['id']]);
$cotisations_mois = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT SUM(montant) FROM cotisations WHERE tontine_id = ? AND statut = 'payee'");
$stmt->execute([$tontine['id']]);
$total_collecte = $stmt->fetchColumn() ?? 0;

$stats = [
    'total_membres' => $total_membres,
    'cotisations_jour' => $cotisations_jour,
    'cotisations_retard' => $cotisations_retard,
    'prets_en_attente' => $prets_en_attente,
    'cotisations_mois' => $cotisations_mois,
    'total_collecte' => $total_collecte
];

$stmt = $pdo->prepare("
    SELECT c.*, u.nom, u.prenom
    FROM cotisations c
    JOIN utilisateurs u ON c.membre_id = u.id
    WHERE c.tontine_id = ?
    ORDER BY c.date_paiement DESC
    LIMIT 10
");
$stmt->execute([$tontine['id']]);
$cotisations_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT p.*, u.nom, u.prenom
    FROM prets p
    JOIN utilisateurs u ON p.membre_id = u.id
    WHERE p.tontine_id = ?
    AND p.statut = 'en_attente'
    ORDER BY p.date_demande DESC
    LIMIT 5
");
$stmt->execute([$tontine['id']]);
$prets_attente = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT u.nom, u.prenom, COUNT(c.id) as retards
    FROM cotisations c
    JOIN utilisateurs u ON c.membre_id = u.id
    WHERE c.tontine_id = ?
    AND c.statut = 'en_retard'
    GROUP BY c.membre_id
    ORDER BY retards DESC
    LIMIT 1
");
$stmt->execute([$tontine['id']]);
$membre_retard = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT *
    FROM tours
    WHERE tontine_id = ?
    AND statut = 'en_cours'
    ORDER BY date_tour ASC
    LIMIT 1
");
$stmt->execute([$tontine['id']]);
$prochain_tour = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Représentant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar_representant.php'; ?>

        <div class="main-content">
            <?php include 'topbar.php'; ?>

            <div class="content-area">
                <div class="section active">
                    <!-- En-tête avec infos tontine -->
                    <div class="card" style="margin-bottom: 25px;">
                        <div class="card-header">
                            <div>
                                <h1 style="margin: 0;"><?php echo htmlspecialchars($tontine['nom']); ?></h1>
                                <p style="color: var(--text-light); margin: 5px 0 0 0;">
                                    Représentant: <?php echo $user['prenom'] . ' ' . $user['nom']; ?> • 
                                    Cotisation: <?php echo number_format($tontine['montant_cotisation'], 0, ',', ' '); ?> FCFA • 
                                    Fréquence: <?php echo ucfirst($tontine['frequence']); ?>
                                </p>
                            </div>
                            <div>
                                <a href="tontine.php?action=edit&id=<?php echo $tontine['id']; ?>" class="btn btn-sm">Éditer</a>
                            </div>
                        </div>
                    </div>

                    <!-- Statistiques -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: var(--primary);"></div>
                            <div class="stat-info">
                                <h3><?php echo $stats['total_membres']; ?></h3>
                                <p>Membres Actifs</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon" style="background: var(--success);"></div>
                            <div class="stat-info">
                                <h3><?php echo number_format($stats['cotisations_mois'], 0, ',', ' '); ?> FCFA</h3>
                                <p>Cotisations ce mois</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon" style="background: var(--danger);"></div>
                            <div class="stat-info">
                                <h3><?php echo $stats['cotisations_retard']; ?></h3>
                                <p>Retards de paiement</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon" style="background: var(--warning);"></div>
                            <div class="stat-info">
                                <h3><?php echo $stats['prets_en_attente']; ?></h3>
                                <p>Prêts en attente</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon" style="background: var(--info);"></div>
                            <div class="stat-info">
                                <h3><?php echo number_format($stats['total_collecte'], 0, ',', ' '); ?> FCFA</h3>
                                <p>Total collecté</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon" style="background: var(--secondary);"></div>
                            <div class="stat-info">
                                <h3><?php echo $stats['cotisations_jour']; ?></h3>
                                <p>Paiements aujourd'hui</p>
                            </div>
                        </div>
                    </div>

                    <!-- Deux colonnes : Cotisations récentes et Actions rapides -->
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px; margin-bottom: 25px;">
                        <!-- Cotisations récentes -->
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Cotisations Récentes</h2>
                                <a href="cotisations.php" class="btn btn-sm btn-primary">Voir toutes</a>
                            </div>

                            <div style="max-height: 400px; overflow-y: auto;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Membre</th>
                                            <th>Montant</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cotisations_recentes as $cotisation): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($cotisation['date_paiement'])); ?></td>
                                            <td><?php echo htmlspecialchars($cotisation['prenom'] . ' ' . $cotisation['nom']); ?></td>
                                            <td><?php echo number_format($cotisation['montant'], 0, ',', ' '); ?> FCFA</td>
                                            <td>
                                                <?php if ($cotisation['statut'] === 'payee'): ?>
                                                    <span class="badge badge-success">Payée</span>
                                                <?php elseif ($cotisation['statut'] === 'en_retard'): ?>
                                                    <span class="badge badge-danger">En retard</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning">En attente</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Actions rapides et informations -->
                        <div>
                            <!-- Actions rapides -->
                            <div class="card" style="margin-bottom: 25px;">
                                <div class="card-header">
                                    <h2 class="card-title">Actions Rapides</h2>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <a href="cotisations.php?action=collecter" class="btn btn-primary">Collecter cotisation</a>
                                    <a href="prets.php?action=approuver" class="btn">Approuver prêts</a>
                                    <a href="tours.php?action=creer" class="btn">Créer un tour</a>
                                    <a href="sanctions.php?action=appliquer" class="btn btn-warning">Appliquer sanction</a>
                                    <a href="membres.php?action=ajouter" class="btn btn-success">Ajouter membre</a>
                                </div>
                            </div>

                            <!-- Informations importantes -->
                            <div class="card">
                                <div class="card-header">
                                    <h2 class="card-title">Informations</h2>
                                </div>
                                <div>
                                    <?php if ($membre_retard): ?>
                                    <div style="margin-bottom: 15px; padding: 10px; background: #fff3cd; border-radius: 6px;">
                                        <strong> Membre en retard:</strong><br>
                                        <?php echo htmlspecialchars($membre_retard['prenom'] . ' ' . $membre_retard['nom']); ?><br>
                                        <small><?php echo $membre_retard['retards']; ?> retards de paiement</small>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($prochain_tour): ?>
                                    <div style="margin-bottom: 15px; padding: 10px; background: #d4edda; border-radius: 6px;">
                                        <strong>Prochain tour:</strong><br>
                                        Tour <?php echo $prochain_tour['numero_tour']; ?><br>
                                        <small>Date: <?php echo date('d/m/Y', strtotime($prochain_tour['date_tour'])); ?></small>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($prets_attente): ?>
                                    <div style="padding: 10px; background: #cfe2ff; border-radius: 6px;">
                                        <strong> Prêts en attente:</strong><br>
                                        <small><?php echo count($prets_attente); ?> demande(s) d'approbation</small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modules de gestion -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Gestion de la Tontine</h2>
                        </div>

                        <div class="module-grid">
                            <a href="membres.php" class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Gestion des Membres</div>
                                <div class="module-desc">Gérer les membres de votre tontine</div>
                            </a>

                            <a href="cotisations.php" class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Cotisations</div>
                                <div class="module-desc">Suivi et collecte des cotisations</div>
                            </a>

                            <a href="prets.php" class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Gestion des Prêts</div>
                                <div class="module-desc">Approuver et suivre les prêts</div>
                            </a>

                            <a href="tours.php" class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Tours de Distribution</div>
                                <div class="module-desc">Organiser les tours de tontine</div>
                            </a>

                            <a href="sanctions.php" class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Sanctions</div>
                                <div class="module-desc">Appliquer et gérer les sanctions</div>
                            </a>

                            <a href="rapports.php" class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Rapports</div>
                                <div class="module-desc">Générer des rapports de tontine</div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Script pour la navigation
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('href').startsWith('#')) {
                    e.preventDefault();
                    const target = this.getAttribute('href');

                    // Masquer toutes les sections
                    document.querySelectorAll('.section').forEach(section => {
                        section.classList.remove('active');
                    });

                    // Activer la section cible
                    if (target === '#dashboard') {
                        document.querySelector('.section').classList.add('active');
                    }

                    // Mettre à jour le menu actif
                    document.querySelectorAll('.sidebar-menu a').forEach(a => {
                        a.classList.remove('active');
                    });
                    this.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>