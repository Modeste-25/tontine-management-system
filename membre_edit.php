<?php
require_once 'config.php';
require_login();

$user = get_logged_user();
$is_admin = (isset($user['type_utilisateur']) && $user['type_utilisateur'] === 'admin');
$is_representant = (isset($user['type_utilisateur']) && $user['type_utilisateur'] === 'representant');
$is_membre = (isset($user['type_utilisateur']) && $user['type_utilisateur'] === 'membre');

// --- Gestion des actions (delete, edit, add) ---
$error = '';
$success = '';

// Traitement de la suppression
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    // Vérifier si l'utilisateur ciblé existe
    $stmt = $pdo->prepare("SELECT type_utilisateur FROM utilisateurs WHERE id = ?");
    $stmt->execute([$id]);
    $target_type = $stmt->fetchColumn();

    if (!$target_type) {
        $error = "Utilisateur introuvable.";
    } elseif ($target_type === 'admin' && !$is_admin) {
        $error = "Vous ne pouvez pas supprimer un administrateur.";
    } elseif ($target_type === 'admin') {
        // Empêcher la suppression du dernier admin
        $count = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE type_utilisateur = 'admin'")->fetchColumn();
        if ($count <= 1) {
            $error = "Impossible de supprimer le dernier administrateur.";
        }
    }

    if (empty($error)) {
        $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Utilisateur supprimé avec succès.";
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? 'add';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);
    $type_utilisateur = $_POST['type_utilisateur'] ?? 'membre';
    $statut = $_POST['statut'] ?? 'actif';

    // Vérification email unique
    $check = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ? AND id != ?");
    $check->execute([$email, $id]);

    if ($check->fetchColumn() > 0) {
        $error = "Cet email est déjà utilisé.";
    }

    if (empty($error)) {

        if ($action === 'add') {

            $count = $pdo->query("
                SELECT COUNT(*) 
                FROM utilisateurs 
                WHERE type_utilisateur = 'membre'
            ")->fetchColumn();

            $nextId = $count + 1;
            $code_membre = 'MBR' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            $mot_de_passe = password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO utilisateurs 
                (code_membre, nom, prenom, email, telephone, mot_de_passe, type_utilisateur, statut, date_inscription)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $code_membre,
                $nom,
                $prenom,
                $email,
                $telephone,
                $mot_de_passe,
                'membre',
                $statut
            ]);

            header("Location: membres.php?msg=ajoute");
            exit();

        } else { // EDIT

            if (!empty($_POST['mot_de_passe'])) {

                $mot_de_passe = password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    UPDATE utilisateurs 
                    SET nom=?, prenom=?, email=?, telephone=?, mot_de_passe=?, statut=?
                    WHERE id=?
                ");

                $stmt->execute([$nom, $prenom, $email, $telephone, $mot_de_passe, $statut, $id]);

            } else {

                $stmt = $pdo->prepare("
                    UPDATE utilisateurs 
                    SET nom=?, prenom=?, email=?, telephone=?, statut=?
                    WHERE id=?
                ");

                $stmt->execute([$nom, $prenom, $email, $telephone, $statut, $id]);
            }

            header("Location: membres.php?msg=modifie");
            exit();
        }
    }
}


// Traitement de l'ajout ou modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);
    $type_utilisateur = $_POST['type_utilisateur'] ?? 'membre';
    $statut = $_POST['statut'] ?? 'actif';

    // Validation email unique
    $check = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ? AND id != ?");
    $check->execute([$email, $id]);
    if ($check->fetchColumn() > 0) {
        $error = "Cet email est déjà utilisé par un autre utilisateur.";
    }

    // Sécurité : un non-admin ne peut pas créer/modifier un admin
    if (!$is_admin && $type_utilisateur === 'admin') {
        $error = "Vous n'avez pas les droits pour créer ou modifier un administrateur.";
    }

    if (empty($error)) {
        if ($action === 'add') {
            $mot_de_passe = password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO utilisateurs (nom, prenom, email, telephone, mot_de_passe, type_utilisateur, statut)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nom, $prenom, $email, $telephone, $mot_de_passe, $type_utilisateur, $statut]);
            $success = "Utilisateur ajouté avec succès.";
        } else { // edit
            // Récupérer l'ancien type pour vérifier si on change un admin
          $oldStmt = $pdo->prepare("SELECT type_utilisateur FROM utilisateurs WHERE id = ?");
            $oldStmt->execute([$id]);
            $old = $oldStmt->fetch(PDO::FETCH_ASSOC); // <-- récupère le résultat dans $old


            if ($old && $old['type_utilisateur'] === 'admin' && !$is_admin) {
                $error = "Vous ne pouvez pas modifier un administrateur.";
            } else {
                if (!empty($_POST['mot_de_passe'])) {
                    $mot_de_passe = password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE utilisateurs SET nom=?, prenom=?, email=?, telephone=?, mot_de_passe=?, type_utilisateur=?, statut=?
                        WHERE id=?
                    ");
                    $stmt->execute([$nom, $prenom, $email, $telephone, $mot_de_passe, $type_utilisateur, $statut, $id]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE utilisateurs SET nom=?, prenom=?, email=?, telephone=?, type_utilisateur=?, statut=?
                        WHERE id=?
                    ");
                    $stmt->execute([$nom, $prenom, $email, $telephone, $type_utilisateur, $statut, $id]);
                }
                $success = "Utilisateur modifié avec succès.";
            }
        }
    }
}

$users = $pdo->query("
    SELECT * FROM utilisateurs 
    WHERE type_utilisateur = 'membre'
    ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);


// Pour pré-remplir le formulaire en cas d'édition
$edit_user = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $stmt->execute([$id]);
    $edit_user = $stmt->fetch();
    if (!$edit_user) {
        $error = "Utilisateur introuvable.";
    }
}
$is_editing = ($edit_user !== null);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des membres</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .table th { background-color: #f8f9fa; }
        .action-links a { margin-right: 10px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <?php
            if ($is_admin) {
                include 'sidebar_admin.php';
            } elseif ($is_representant) {
                include 'sidebar_representant.php';
            } else {
                include 'sidebar_membres.php';
            }
            ?>
        </div>

        <div class="main-content">
            <?php include 'topbar.php'; ?>

            <div class="content-area">
                <div class="section active">
                    <!-- Messages flash -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Formulaire d'ajout/édition (en haut) -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2 class="card-title"><?= $is_editing ? 'Modifier' : 'Ajouter' ?> un membre</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="<?= $is_editing ? 'edit' : 'add' ?>">
                                <?php if ($is_editing): ?>
                                    <input type="hidden" name="id" value="<?= $edit_user['id'] ?>">
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nom</label>
                                        <input type="text" class="form-control" name="nom" required
                                               value="<?= htmlspecialchars($edit_user['nom'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Prénom</label>
                                        <input type="text" class="form-control" name="prenom" required
                                               value="<?= htmlspecialchars($edit_user['prenom'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" required
                                               value="<?= htmlspecialchars($edit_user['email'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Téléphone</label>
                                        <input type="text" class="form-control" name="telephone"
                                               value="<?= htmlspecialchars($edit_user['telephone'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Mot de passe <?= $is_editing ? '(laisser vide pour conserver)' : '' ?></label>
                                    <input type="password" class="form-control" name="mot_de_passe"
                                           <?= $is_editing ? '' : 'required' ?>>
                                </div>

                                <?php if ($is_admin): ?>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Type d'utilisateur</label>
                                            <select class="form-select" name="type_utilisateur">
                                                <option value="membre" <?= ($edit_user['type_utilisateur'] ?? '') == 'membre' ? 'selected' : '' ?>>Membre</option>
                                                <option value="representant" <?= ($edit_user['type_utilisateur'] ?? '') == 'representant' ? 'selected' : '' ?>>Représentant</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Statut</label>
                                            <select class="form-select" name="statut">
                                                <option value="actif" <?= ($edit_user['statut'] ?? '') == 'actif' ? 'selected' : '' ?>>Actif</option>
                                                <option value="inactif" <?= ($edit_user['statut'] ?? '') == 'inactif' ? 'selected' : '' ?>>Inactif</option>
                                                <option value="suspendu" <?= ($edit_user['statut'] ?? '') == 'suspendu' ? 'selected' : '' ?>>Suspendu</option>
                                            </select>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Les non-admins ne peuvent pas changer le type/statut, on met en hidden -->
                                    <input type="hidden" name="type_utilisateur" value="membre">
                                    <input type="hidden" name="statut" value="actif">
                                <?php endif; ?>

                                <button type="submit" class="btn btn-primary">Enregistrer</button>
                                <?php if ($is_editing): ?>
                                    <a href="membres.php" class="btn btn-secondary">Annuler</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Tableau des utilisateurs (en bas) -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Liste des membres</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Code</th>
                                            <th>Nom</th>
                                            <th>Prénom</th>
                                            <th>Email</th>
                                            <th>Téléphone</th>
                                            <th>Type</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td><?= $u['id'] ?></td>
                                            <td><?= htmlspecialchars($u['code_membre'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($u['nom']) ?></td>
                                            <td><?= htmlspecialchars($u['prenom']) ?></td>
                                            <td><?= htmlspecialchars($u['email']) ?></td>
                                            <td><?= htmlspecialchars($u['telephone']) ?></td>
                                            <td><?= htmlspecialchars($u['type_utilisateur']) ?></td>
                                            <td class="action-links">
                                                <a href="membres.php?action=edit&id=<?= $u['id'] ?>" class="btn btn-sm btn-primary">Modifier</a>
                                                <a href="membres.php?action=delete&id=<?= $u['id'] ?>"
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Voulez-vous vraiment supprimer cet utilisateur ?');">
                                                   Supprimer
                                                </a>
                                            </td>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>