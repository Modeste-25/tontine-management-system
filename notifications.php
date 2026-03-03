<?php
require_once 'config.php';
require_login();

$user = get_logged_user();

// Marquer comme lue
if (isset($_GET['lire'])) {
    $id = $_GET['lire'];
    $stmt = $pdo->prepare("UPDATE notifications SET lue = 1 WHERE id = ? AND utilisateur_id = ?");
    $stmt->execute([$id, $user['id']]);
    header('Location: notifications.php');
    exit();
}

// Marquer tout lu
if (isset($_GET['tout_lire'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET lue = 1 WHERE utilisateur_id = ?");
    $stmt->execute([$user['id']]);
    header('Location: notifications.php');
    exit();
}

// Récupérer les notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE utilisateur_id = ? ORDER BY date_notification DESC");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$non_lues = array_filter($notifications, fn($n) => $n['lue'] == 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Notifications</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="dashboard-container">
    <?php
    if ($user['type_utilisateur'] === 'admin') include 'sidebar_admin.php';
    elseif ($user['type_utilisateur'] === 'representant') include 'sidebar_representant.php';
    else include 'sidebar_membre.php';
    ?>

    <div class="main-content">
        <?php include 'topbar.php'; ?>

        <div class="content-area">
            <div class="section active">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Mes Notifications</h2>
                        <?php if (!empty($non_lues)): ?>
                        <a href="?tout_lire=1" class="btn btn-sm btn-primary">Tout marquer comme lu</a>
                        <?php endif; ?>
                    </div>

                    <div style="max-height: 600px; overflow-y: auto;">
                        <?php if (empty($notifications)): ?>
                            <p style="text-align:center; padding:40px; color: var(--text-light);">Aucune notification pour le moment.</p>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                                <div style="padding:15px; border-bottom:1px solid var(--border); background: <?php echo $notif['lue'] ? 'transparent' : '#e6f7ff'; ?>;">
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <div>
                                            <div style="display:flex; align-items:center; gap:10px;">
                                                <span style="font-size:20px;">
                                                    <?php
                                                    switch ($notif['type']) {
                                                        case 'paiement': echo ''; break;
                                                        case 'pret': echo ''; break;
                                                        case 'sanction': echo ''; break;
                                                        case 'tour': echo ''; break;
                                                        case 'alerte': echo ''; break;
                                                        default: echo '';
                                                    }
                                                    ?>
                                                </span>
                                                <strong><?php echo htmlspecialchars($notif['titre']); ?></strong>
                                            </div>
                                            <p style="margin:5px 0 0 30px;"><?php echo htmlspecialchars($notif['message']); ?></p>
                                            <small style="color: var(--text-light); margin-left:30px;"><?php echo date('d/m/Y H:i', strtotime($notif['date_notification'])); ?></small>
                                        </div>
                                        <?php if (!$notif['lue']): ?>
                                            <a href="?lire=<?php echo $notif['id']; ?>" class="btn btn-sm">Marquer comme lu</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
