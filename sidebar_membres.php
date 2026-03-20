<?php
require_once 'config.php';

if (!is_logged_in()) {
    header('Location: index.php');
    exit();
}

$user = get_logged_user();

if (!$user || $user['type_utilisateur'] !== 'membre') {
    header('Location: index.php');
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h2>Membre</h2>
        <small><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></small>
    </div>

    <div class="sidebar-menu">
        <ul>
            <li>
                <a href="dashboard_membre.php" class="<?php echo $current_page == 'dashboard_membre.php' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i> Tableau de bord
                </a>
            </li>
            <li>
                <a href="membre_detail.php?id=<?php echo $user['id']; ?>" class="<?php echo $current_page == 'membre_detail.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person"></i> Mon Profil
                </a>
            </li>
            <li>
                <a href="cotisations.php?action=mes_cotisations" class="<?php echo $current_page == 'cotisations.php' && ($_GET['action'] ?? '') === 'mes_cotisations' ? 'active' : ''; ?>">
                    <i class="bi bi-wallet"></i> Mes Cotisations
                </a>
            </li>
            <li>
                <a href="prets.php?action=mes_prets" class="<?php echo $current_page == 'prets.php' && ($_GET['action'] ?? '') === 'mes_prets' ? 'active' : ''; ?>">
                    <i class="bi bi-bank"></i> Mes Prêts
                </a>
            </li>
            <li>
                <a href="tontine.php?action=mes_tontines" class="<?php echo $current_page == 'tontine.php' && ($_GET['action'] ?? '') === 'mes_tontines' ? 'active' : ''; ?>">
                    <i class="bi bi-people"></i> Mes Tontines
                </a>
            </li>
            <li>
                <a href="notifications.php" class="<?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>">
                    <i class="bi bi-bell"></i> Notifications
                </a>
            </li>
            <li>
                <a href="securite.php" class="<?php echo $current_page == 'securite.php' ? 'active' : ''; ?>">
                    <i class="bi bi-shield"></i> Sécurité
                </a>
            </li>
            <li style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 10px;">
                <a href="logout.php" style="color: #f8d7da;">
                    <i class="bi bi-box-arrow-right"></i> Déconnexion
                </a>
            </li>
        </ul>
    </div>
</div>
