<?php
 $current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>Administrateur</h2>
        <small><?php echo htmlspecialchars($_SESSION['user_name']); ?></small>

        <div style="margin-top: 10px; background: rgba(255,255,255,0.1); padding: 5px; border-radius: 4px; font-size: 0.9em;">
             Super Administrateur
        </div>
    </div>
    
    <div class="sidebar-menu">
        <ul>
            <li>
                <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i> Tableau de bord
                </a>
            </li>
            <li>
                <a href="representants.php" class="<?php echo $current_page == 'representants.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person"></i> Représentants
                </a>
            </li>
            <li>
                <a href="rapports.php" class="<?php echo $current_page == 'rapports.php' ? 'active' : ''; ?>">
                    <i class="bi bi-bar-chart"></i> Rapports
                </a>
            </li>
            <li>
                <a href="exportation.php" class="<?php echo $current_page == 'exportation.php' ? 'active' : ''; ?>">
                    <i class="bi bi-download"></i> Exportation
                </a>
            </li>
            <li>
                <a href="securite.php" class="<?php echo $current_page == 'securite.php' ? 'active' : ''; ?>">
                    <i class="bi bi-shield"></i> Sécurité
                </a>
            </li>
            <li>
                <a href="parametres.php" class="<?php echo $current_page == 'parametres.php' ? 'active' : ''; ?>">
                    <i class="bi bi-gear"></i> Paramètres
                </a>
            </li>
            <li>
                <a href="notifications.php" class="<?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>">
                    <i class="bi bi-bell"></i> Notifications
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