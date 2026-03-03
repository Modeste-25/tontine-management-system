<?php
require_once 'config.php';
require_login();
check_user_type('representant');

$user = get_logged_user();

// Récupère la tontine active du représentant
$stmt = $pdo->prepare("SELECT * FROM tontines WHERE representant_id = ? AND statut = 'active' LIMIT 1");
$stmt->execute([$user['id']]);
$tontine = $stmt->fetch();

 $current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>Représentant</h2>
        <small><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></small>
        <?php if ($tontine): ?>
        <div style="margin-top: 10px; background: rgba(255,255,255,0.1); padding: 5px; border-radius: 4px; font-size: 0.9em;">
            <?php echo htmlspecialchars($tontine['nom']); ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="sidebar-menu">
        <ul>
            <li>
                <a href="dashboard_representant.php" class="<?php echo $current_page == 'dashboard_representant.php' ? 'active' : ''; ?>">
                    <i></i> Tableau de bord
                </a>
            </li>
            <li>
                <a href="<?php echo $tontine ? 'membres_tontines.php?id='.$tontine['id'] : '#'; ?>" 
                   class="<?php echo $current_page == 'membres_tontines.php' ? 'active' : ''; ?>"
                   <?php echo !$tontine ? 'style="pointer-events:none; color: gray;"' : ''; ?>>
                    <i></i> Membres Tontines
                </a>
            </li>
            <li>
                <a href="cotisations.php" class="<?php echo $current_page == 'cotisations.php' ? 'active' : ''; ?>">
                    <i></i> Cotisations
                </a>
            </li>
            <li>
                <a href="prets.php" class="<?php echo $current_page == 'prets.php' ? 'active' : ''; ?>">
                    <i></i> Prêts
                </a>
            </li>
            <li>
                <a href="tours.php" class="<?php echo $current_page == 'tours.php' ? 'active' : ''; ?>">
                    <i></i> Tours
                </a>
            </li>
            <li>
                <a href="sanctions.php" class="<?php echo $current_page == 'sanctions.php' ? 'active' : ''; ?>">
                    <i></i> Sanctions
                </a>
            </li>
            <li>
                <a href="rapports.php" class="<?php echo $current_page == 'rapports.php' ? 'active' : ''; ?>">
                    <i></i> Rapports
                </a>
            </li>
            <li>
                <a href="notifications.php" class="<?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>">
                    <i></i> Notifications
                </a>
            </li>
            <li>
                <a href="securite.php" class="<?php echo $current_page == 'securite.php' ? 'active' : ''; ?>">
                    <i></i> Sécurité
                </a>
            </li>
            <li style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 10px;">
                <a href="logout.php" style="color: #f8d7da;">
                    <i></i> Déconnexion
                </a>
            </li>
        </ul>
    </div>
</div>