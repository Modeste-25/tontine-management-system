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
                    <i></i> Tableau de bord
                </a>
            </li>
            <li>
                <a href="membres_tontines.php" class="<?php echo $current_page == 'membres_tontines.php' ? 'active' : ''; ?>">
                    <i></i> Membres Tontines
                </a>
            </li>
            <li>
                <a href="tontine.php" class="<?php echo $current_page == 'tontine.php' ? 'active' : ''; ?>">
                    <i></i> Tontines
                </a>
            </li>
            <li>
                <a href="representants.php" class="<?php echo $current_page == 'representants.php' ? 'active' : ''; ?>">
                    <i></i> Représentants
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
                <a href="exportation.php" class="<?php echo $current_page == 'exportation.php' ? 'active' : ''; ?>">
                    <i></i> Exportation
                </a>
            </li>
            <li>
                <a href="securite.php" class="<?php echo $current_page == 'securite.php' ? 'active' : ''; ?>">
                    <i></i> Sécurité
                </a>
            </li>
            <li>
                <a href="parametres.php" class="<?php echo $current_page == 'parametres.php' ? 'active' : ''; ?>">
                    <i></i> Paramètres
                </a>
            </li>
            <li>
                <a href="notifications.php" class="<?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>">
                    <i></i> Notifications
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