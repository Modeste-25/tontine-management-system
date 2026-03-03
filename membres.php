<?php
require_once 'config.php';
require_login();
check_user_type('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {

    $action = $_POST['action'];
    $id = (int) $_POST['id'];

    if ($id <= 0) {
        header('Location: membres.php');
        exit();
    }

    if ($action === 'suspendre') {
        $stmt = $pdo->prepare("UPDATE utilisateurs SET statut = 'suspendu' WHERE id = ? AND type_utilisateur = 'membre'");
        $stmt->execute([$id]);

        log_action('Suspension membre', "Membre ID $id suspendu");
        header('Location: membres.php?msg=suspendu');
        exit();
    }

    if ($action === 'activer') {
        $stmt = $pdo->prepare("UPDATE utilisateurs SET statut = 'actif' WHERE id = ? AND type_utilisateur = 'membre'");
        $stmt->execute([$id]);

        log_action('Activation membre', "Membre ID $id activé");
        header('Location: membres.php?msg=active');
        exit();
    }

    if ($action === 'supprimer') {

        $check = $pdo->prepare("SELECT COUNT(*) FROM cotisations WHERE membre_id = ?");
        $check->execute([$id]);
        $hasCotisations = $check->fetchColumn();

        if ($hasCotisations > 0) {
            header('Location: membres.php?msg=impossible');
            exit();
        }

        $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ? AND type_utilisateur = 'membre'");
        $stmt->execute([$id]);

        log_action('Suppression membre', "Membre ID $id supprimé");

        header('Location: membres.php?msg=supprime');
        exit();
    }
}

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$query = "SELECT * FROM utilisateurs WHERE type_utilisateur = 'membre'";
$params = [];

if (!empty($search)) {
    $query .= " AND (nom LIKE ? OR prenom LIKE ? OR email LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($status)) {
    $query .= " AND statut = ?";
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
    <title>Gestion des Membres</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <div class="dashboard-container">

        <?php include 'sidebar_admin.php'; ?>
        <div class="main-content">
            <?php include 'topbar.php'; ?>

            <div class="content-area">

                <div class="card">

                    <div class="card-header">
                        <h2>Gestion des Membres</h2>
                        <a href="membre_edit.php?action=add" class="btn btn-primary">Ajouter un membre</a>
                    </div>

                    <!-- Messages -->
                    <?php if (isset($_GET['msg'])): ?>
                        <div class="alert alert-success">
                            <?php
                            switch ($_GET['msg']) {
                                case 'suspendu':
                                    echo "Membre suspendu avec succès.";
                                    break;
                                case 'active':
                                    echo "Membre activé avec succès.";
                                    break;
                                case 'supprime':
                                    echo "Membre supprimé avec succès.";
                                    break;
                                case 'modifie':
                                    echo "Membre modifié avec succès.";
                                    break;
                                case 'impossible':
                                    echo "Impossible de supprimer : cotisations existantes.";
                                    break;
                            }
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Filtres -->
                    <form method="GET" style="margin-bottom:20px;">
                        <input type="text" name="search" placeholder="Rechercher..." value="<?= htmlspecialchars($search) ?>">
                        <select name="status">
                            <option value="">Tous</option>
                            <option value="actif" <?= $status === 'actif' ? 'selected' : '' ?>>Actif</option>
                            <option value="inactif" <?= $status === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                            <option value="suspendu" <?= $status === 'suspendu' ? 'selected' : '' ?>>Suspendu</option>
                        </select>
                        <button type="submit" class="btn btn-primary">Filtrer</button>
                        <a href="membres.php" class="btn">Réinitialiser</a>
                    </form>

                    <!-- Tableau -->
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom & Prénom</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Date inscription</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($membres as $membre): ?>
                                <tr>

                                    <td><?= $membre['id'] ?></td>

                                    <td><strong><?= htmlspecialchars($membre['nom'] . ' ' . $membre['prenom']) ?></strong></td>

                                    <td><?= htmlspecialchars($membre['email']) ?></td>

                                    <td><?= htmlspecialchars($membre['telephone']) ?></td>

                                    <td><?= date('d/m/Y', strtotime($membre['date_inscription'])) ?></td>

                                    <td>
                                        <?php if ($membre['statut'] === 'actif'): ?>
                                            <span class="badge badge-success">Actif</span>
                                        <?php elseif ($membre['statut'] === 'suspendu'): ?>
                                            <span class="badge badge-danger">Suspendu</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Inactif</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>

                                        <a href="membre_detail.php?id=<?= $membre['id'] ?>" class="btn btn-sm btn-primary">Voir</a>

                                        <a href="membre_edit.php?action=edit&id=<?= $membre['id'] ?>" class="btn btn-sm">Modifier</a>

                                        <?php if ($membre['statut'] === 'actif'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="id" value="<?= $membre['id'] ?>">
                                                <input type="hidden" name="action" value="suspendre">
                                                <button type="submit" class="btn btn-sm btn-warning"
                                                    onclick="return confirm('Suspendre ce membre ?')">Suspendre</button>
                                            </form>
                                        <?php elseif ($membre['statut'] === 'suspendu'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="id" value="<?= $membre['id'] ?>">
                                                <input type="hidden" name="action" value="activer">
                                                <button type="submit" class="btn btn-sm btn-success"
                                                    onclick="return confirm('Activer ce membre ?')">Activer</button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="id" value="<?= $membre['id'] ?>">
                                            <input type="hidden" name="action" value="supprimer">
                                            <button type="submit" class="btn btn-sm btn-danger"
                                                onclick="return confirm('Supprimer définitivement ?')">Supprimer</button>
                                        </form>

                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                </div>
            </div>
        </div>
    </div>

</body>

</html>