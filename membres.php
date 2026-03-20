<?php
require_once 'config.php';
require_login();
check_user_type('admin');

// Flash message
$flash = (isset($_SESSION['flash']) && is_array($_SESSION['flash'])) ? $_SESSION['flash'] : null;
unset($_SESSION['flash']);

// Action suppression (POST — pas d'email nécessaire)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $action = $_POST['action'];
    $id     = (int) $_POST['id'];

    if ($action === 'supprimer' && $id > 0) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM cotisations WHERE membre_id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => "Impossible de supprimer : cotisations existantes."];
        } else {
            $pdo->prepare("DELETE FROM utilisateurs WHERE id = ? AND type_utilisateur = 'membre'")->execute([$id]);
            log_action('Suppression membre', "Membre ID $id supprimé");
            $_SESSION['flash'] = ['type' => 'success', 'msg' => "Membre supprimé avec succès."];
        }
        header('Location: membres.php');
        exit();
    }
}

// Filtres
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$query  = "SELECT * FROM utilisateurs WHERE type_utilisateur = 'membre'";
$params = [];

if (!empty($search)) {
    $query .= " AND (nom LIKE ? OR prenom LIKE ? OR email LIKE ?)";
    $term   = "%$search%";
    $params = array_merge($params, [$term, $term, $term]);
}
if (!empty($status)) {
    $query  .= " AND statut = ?";
    $params[] = $status;
}
$query .= " ORDER BY date_inscription DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$membres = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Membres – Afriton</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="dashboard-container">
    <?php include 'sidebar_admin.php'; ?>
    <div class="main-content">
        <?php include 'topbar.php'; ?>
        <div class="content-area">

            <!-- Flash -->
            <?php if ($flash && isset($flash['type'], $flash['msg'])): ?>
            <?php $ok = $flash['type'] === 'success'; ?>
            <div style="background:<?= $ok?'#d1fae5':'#fee2e2' ?>;
                        border:1px solid <?= $ok?'#22c55e':'#f87171' ?>;
                        color:<?= $ok?'#065f46':'#991b1b' ?>;
                        border-radius:10px;padding:12px 18px;margin-bottom:18px;
                        display:flex;align-items:center;gap:10px;font-size:.9rem;font-weight:600;">
                <i class="bi <?= $ok?'bi-check-circle-fill':'bi-x-circle-fill' ?>"></i>
                <?= htmlspecialchars((string)$flash['msg']) ?>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2>Gestion des Membres</h2>
                    <a href="membre_edit.php?action=add" class="btn btn-primary">
                        <i class="bi bi-person-plus-fill me-1"></i>Ajouter un membre
                    </a>
                </div>

                <!-- Filtres -->
                <form method="GET" style="padding:16px 20px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;border-bottom:1px solid #f1f5f9;">
                    <div>
                        <label style="font-size:.78rem;font-weight:600;color:#64748b;display:block;margin-bottom:4px;">Recherche</label>
                        <input type="text" name="search" placeholder="Nom, prénom, email..."
                               value="<?= htmlspecialchars($search) ?>"
                               style="border:1px solid #e2e8f0;border-radius:7px;padding:7px 12px;font-size:.875rem;min-width:220px;">
                    </div>
                    <div>
                        <label style="font-size:.78rem;font-weight:600;color:#64748b;display:block;margin-bottom:4px;">Statut</label>
                        <select name="status" style="border:1px solid #e2e8f0;border-radius:7px;padding:7px 12px;font-size:.875rem;">
                            <option value="">Tous</option>
                            <option value="actif"    <?= $status==='actif'    ?'selected':'' ?>>Actif</option>
                            <option value="inactif"  <?= $status==='inactif'  ?'selected':'' ?>>Inactif</option>
                            <option value="suspendu" <?= $status==='suspendu' ?'selected':'' ?>>Suspendu</option>
                        </select>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-search me-1"></i>Filtrer
                        </button>
                        <a href="membres.php" class="btn btn-sm" style="border:1px solid #e2e8f0;">
                            <i class="bi bi-x me-1"></i>Réinitialiser
                        </a>
                    </div>
                </form>

                <!-- Tableau -->
                <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom & Prénom</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Code Membre</th>
                            <th>Inscription</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($membres as $m): ?>
                    <tr>
                        <td><?= $m['id'] ?></td>
                        <td><strong><?= htmlspecialchars($m['nom'] . ' ' . $m['prenom']) ?></strong></td>
                        <td><?= htmlspecialchars($m['email']) ?></td>
                        <td><?= htmlspecialchars($m['telephone'] ?? '—') ?></td>
                        <td>
                            <?php if ($m['code_membre']): ?>
                                <code style="background:#eff6ff;color:#1d4ed8;padding:2px 8px;border-radius:5px;font-size:.82rem;">
                                    <?= htmlspecialchars($m['code_membre']) ?>
                                </code>
                            <?php else: ?>
                                <span style="color:#94a3b8;font-size:.8rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y', strtotime($m['date_inscription'])) ?></td>
                        <td>
                            <?php if ($m['statut'] === 'actif'): ?>
                                <span class="badge badge-success">Actif</span>
                            <?php elseif ($m['statut'] === 'suspendu'): ?>
                                <span class="badge badge-danger">Suspendu</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Inactif</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;">

                            <!-- Voir -->
                            <a href="membre_detail.php?id=<?= $m['id'] ?>"
                               class="btn btn-sm btn-primary" title="Voir détail">
                                <i class="bi bi-eye"></i>
                            </a>

                            <!-- Modifier -->
                            <a href="membre_edit.php?action=edit&id=<?= $m['id'] ?>"
                               class="btn btn-sm" title="Modifier"
                               style="border:1px solid #e2e8f0;">
                                <i class="bi bi-pencil"></i>
                            </a>

                            <!-- Activer avec email (si inactif ou suspendu) -->
                            <?php if ($m['statut'] !== 'actif'): ?>
                            <a href="accepter_membre.php?id=<?= $m['id'] ?>"
                               class="btn btn-sm btn-success"
                               title="Activer et envoyer code membre par email"
                               onclick="return confirm('Activer <?= htmlspecialchars(addslashes($m['prenom'].' '.$m['nom'])) ?> et lui envoyer son code membre par email ?')">
                                <i class="bi bi-check-lg"></i> Activer
                            </a>
                            <?php endif; ?>

                            <!-- Suspendre avec email (si actif) -->
                            <?php if ($m['statut'] === 'actif'): ?>
                            <a href="refuser_membre.php?id=<?= $m['id'] ?>"
                               class="btn btn-sm btn-warning"
                               title="Suspendre et notifier par email"
                               onclick="return confirm('Suspendre <?= htmlspecialchars(addslashes($m['prenom'].' '.$m['nom'])) ?> et l\'en notifier par email ?')">
                                <i class="bi bi-slash-circle"></i> Suspendre
                            </a>
                            <?php endif; ?>

                            <!-- Supprimer -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <input type="hidden" name="action" value="supprimer">
                                <button type="submit" class="btn btn-sm btn-danger"
                                        title="Supprimer définitivement"
                                        onclick="return confirm('Supprimer définitivement ce membre ?')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>

                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($membres)): ?>
                    <tr><td colspan="8" style="text-align:center;padding:30px;color:#94a3b8;">Aucun membre trouvé.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>

            </div>
        </div>
    </div>
</div>
</body>
</html>