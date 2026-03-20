<?php
$user = get_logged_user();
if (!$user) {
    return;
}

$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM notifications 
    WHERE utilisateur_id = ? AND lue = 0
");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchColumn();

$user_type_icon = '';
$user_type_label = '';

switch ($user['type_utilisateur']) {
    case 'admin':
        $user_type_label = 'Administrateur';
        break;

    case 'representant':
        $user_type_label = 'Représentant';
        break;

    case 'membre':
        $user_type_label = 'Membre';
        break;
}
?>
<div class="top-bar">
    <div class="search-bar">
        <input type="text" placeholder="" id="globalSearch">
    </div>

    <div class="user-info">
        <div style="position: relative; margin-right: 20px;">
            <a href="notifications.php" style="color: var(--text); text-decoration: none;">
                <?php if ($notifications > 0): ?>
                    <span style="
                        position: absolute;
                        top: -8px;
                        right: -8px;
                        background: var(--danger);
                        color: white;
                        border-radius: 50%;
                        width: 18px;
                        height: 18px;
                        font-size: 12px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    ">
                        <?php echo $notifications; ?>
                    </span>
                <?php endif; ?>
            </a>
        </div>

        <div style="display: flex; align-items: center;">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)); ?>
            </div>
            <div style="text-align: right;">
                <div style="font-weight: 600;">
                    <?php echo $user['prenom'] . ' ' . $user['nom']; ?>
                </div>
                <div style="font-size: 0.85em; color: var(--text-light);">
                    <?php echo $user_type_icon . ' ' . $user_type_label; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('globalSearch').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        const query = this.value.trim();
        if (query) {
            window.location.href = 'recherche.php?q=' + encodeURIComponent(query);
        }
    }
});
</script>
