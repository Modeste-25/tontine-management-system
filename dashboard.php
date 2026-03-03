<?php
require_once 'config.php';
require_login();
check_user_type('admin');

$user = get_logged_user();

// Statistiques globales
$stats = [
    'total_membres' => $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE type_utilisateur = 'membre'")->fetchColumn(),
    'total_tontines' => $pdo->query("SELECT COUNT(*) FROM tontines")->fetchColumn(),
    'cotisations_payees' => $pdo->query("SELECT COUNT(*) FROM cotisations WHERE statut = 'payee'")->fetchColumn(),
    'cotisations_retard' => $pdo->query("SELECT COUNT(*) FROM cotisations WHERE statut = 'en_retard'")->fetchColumn(),
    'prets_actifs' => $pdo->query("SELECT COUNT(*) FROM prets WHERE statut = 'approuve'")->fetchColumn(),
    'prets_en_retard' => $pdo->query("SELECT COUNT(*) FROM prets WHERE statut = 'en_retard'")->fetchColumn(),
    'sanctions_actives' => $pdo->query("SELECT COUNT(*) FROM sanctions WHERE statut = 'active'")->fetchColumn(),
    'representants' => $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE type_utilisateur = 'representant'")->fetchColumn(),
];

// Dernières activités
$activites = $pdo->query("
    SELECT h.*, u.prenom, u.nom, u.type_utilisateur 
    FROM historiques h 
    LEFT JOIN utilisateurs u ON h.utilisateur_id = u.id 
    ORDER BY h.date_action DESC 
    LIMIT 10
")->fetchAll();

// Tontines récentes
$tontines_recentes = $pdo->query("
    SELECT t.*, u.prenom, u.nom as representant_nom 
    FROM tontines t 
    LEFT JOIN utilisateurs u ON t.representant_id = u.id 
    ORDER BY t.date_debut DESC 
    LIMIT 5
")->fetchAll();

// Membres récents
$membres_recents = $pdo->query("
    SELECT * FROM utilisateurs 
    WHERE type_utilisateur = 'membre' 
    ORDER BY date_inscription DESC 
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Administrateur</title>
         <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
         <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar_admin.php'; ?>
        
        <div class="main-content">
            <?php include 'topbar.php'; ?>
            
            <div class="content-area">
                <div class="section active">
                    <h1 style="margin-bottom: 30px;">Tableau de Bord Administrateur</h1>
                    
                    <!-- Statistiques -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: var(--primary);"></div>
                            <div class="stat-info">
                                <h3><?php echo $stats['total_membres']; ?></h3>
                                <p>Membres Totaux</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon" style="background: var(--success);"></div>
                            <div class="stat-info">
                                <h3><?php echo $stats['total_tontines']; ?></h3>
                                <p>Tontines Actives</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon" style="background: var(--info);"></div>
                            <div class="stat-info">
                                <h3><?php echo $stats['representants']; ?></h3>
                                <p>Représentants</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #3b82f6;"></div>
                            <div class="stat-info">
                                <h3><?php echo $stats['cotisations_payees']; ?></h3>
                                <p>Cotisations Payées</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon" style="background: var(--warning);"></div>
                            <div class="stat-info">
                                <h3><?php echo $stats['cotisations_retard']; ?></h3>
                                <p>Cotisations en Retard</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon" style="background: var(--danger);"></div>
                            <div class="stat-info">
                                <h3><?php echo $stats['prets_actifs']; ?></h3>
                                <p>Prêts Actifs</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Deux colonnes : Tontines récentes et Membres récents -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px;">
                        <!-- Tontines récentes -->
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Tontines Récentes</h2>
                                <a href="tontine.php" class="btn btn-sm btn-primary">Voir toutes</a>
                            </div>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Représentant</th>
                                        <th>Date début</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tontines_recentes as $tontine): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tontine['nom']); ?></td>
                                        <td><?php echo $tontine['representant_nom'] ? htmlspecialchars($tontine['prenom'] . ' ' . $tontine['representant_nom']) : 'Non attribué'; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($tontine['date_debut'])); ?></td>
                                        <td>
                                            <?php if ($tontine['statut'] === 'active'): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php elseif ($tontine['statut'] === 'inactive'): ?>
                                                <span class="badge badge-warning">Inactive</span>
                                            <?php else: ?>
                                                <span class="badge badge-info">Terminée</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Membres récents -->
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Nouveaux Membres</h2>
                                <a href="membres.php" class="btn btn-sm btn-primary">Voir tous</a>
                            </div>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nom & Prénom</th>
                                        <th>Email</th>
                                        <th>Date inscription</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($membres_recents as $membre): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($membre['nom'] . ' ' . $membre['prenom']); ?></td>
                                        <td><?php echo htmlspecialchars($membre['email']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($membre['date_inscription'])); ?></td>
                                        <td>
                                            <?php if ($membre['statut'] === 'actif'): ?>
                                                <span class="badge badge-success">Actif</span>
                                            <?php elseif ($membre['statut'] === 'inactif'): ?>
                                                <span class="badge badge-warning">Inactif</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Suspendu</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Modules d'administration -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Modules d'Administration</h2>
                        </div>
                        
                        <div class="module-grid">
                            <a href="membres.php" class="module-card">
                                <div class="module-icon">👥</div>
                                <div class="module-title">Gestion des Membres</div>
                                <div class="module-desc">Gérer tous les membres du système</div>
                            </a>
                            
                            <a href="tontine.php" class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Gestion des Tontines</div>
                                <div class="module-desc">Voir et gérer toutes les tontines</div>
                            </a>
                            
                            <a href="representants.php" class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Représentants</div>
                                <div class="module-desc">Gérer les représentants de tontine</div>
                            </a>
                            
                            <a href="cotisations.php" class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Suivi des Cotisations</div>
                                <div class="module-desc">Suivre les paiements et retards</div>
                            </a>
                            
                            <a href="prets.php" class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Gestion des Prêts</div>
                                <div class="module-desc">Approuver et suivre les prêts</div>
                            </a>
                            
                            <a href="sanctions.php" class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Gestion des Sanctions</div>
                                <div class="module-desc">Appliquer et gérer les sanctions</div>
                            </a>
                            
                            <a href="rapports.php" class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Rapports & Statistiques</div>
                                <div class="module-desc">Générer des rapports détaillés</div>
                            </a>
                            
                            <a href="parametres.php" class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Paramètres Système</div>
                                <div class="module-desc">Configurer le système</div>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Activités récentes -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Activités Récentes</h2>
                        </div>
                        
                        <div style="max-height: 400px; overflow-y: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Utilisateur</th>
                                        <th>Type</th>
                                        <th>Action</th>
                                        <th>Détails</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activites as $activite): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($activite['date_action'])); ?></td>
                                        <td><?php echo $activite['prenom'] . ' ' . $activite['nom']; ?></td>
                                        <td>
                                            <?php if ($activite['type_utilisateur'] === 'admin'): ?>
                                                <span class="badge badge-danger">Admin</span>
                                            <?php elseif ($activite['type_utilisateur'] === 'representant'): ?>
                                                <span class="badge badge-warning">Représentant</span>
                                            <?php else: ?>
                                                <span class="badge badge-info">Membre</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $activite['action']; ?></td>
                                        <td><?php echo substr($activite['details'] ?? '', 0, 50) . '...'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Navigation entre sections
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