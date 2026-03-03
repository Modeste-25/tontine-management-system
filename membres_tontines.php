<?php
require_once 'config.php';
require_login();

$user = get_logged_user();
$is_admin = ($user['type_utilisateur'] === 'admin');
$is_representant = ($user['type_utilisateur'] === 'representant');

if (!$is_admin && !$is_representant) {
    header('Location: index.php');
    exit();
}

// Récupérer l'ID de la tontine
if (!isset($_GET['id'])) {
    header('Location: tontine.php');
    exit();
}

$tontine_id = (int) $_GET['id'];

// Vérifier le droit du représentant
if (!$is_admin) {
    $stmt = $pdo->prepare("SELECT * FROM tontines WHERE id = ? AND representant_id = ?");
    $stmt->execute([$tontine_id, $user['id']]);
    $tontine = $stmt->fetch();
    if (!$tontine) {
        header('Location: tontine.php?error=permission');
        exit();
    }
} else {
    $stmt = $pdo->prepare("SELECT * FROM tontines WHERE id = ?");
    $stmt->execute([$tontine_id]);
    $tontine = $stmt->fetch();
    if (!$tontine) {
        header('Location: tontine.php?error=notfound');
        exit();
    }
}

// Ajouter un membre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_membre'])) {
    $membre_id = (int) $_POST['membre_id'];

    // Vérifier si le membre n'est pas déjà dans la tontine
    $check = $pdo->prepare("SELECT * FROM membres_tontines WHERE tontine_id = ? AND membre_id = ?");
    $check->execute([$tontine_id, $membre_id]);
    if (!$check->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO membres_tontines (tontine_id, membre_id, solde, statut) VALUES (?, ?, 0.00, 'actif')");
        $stmt->execute([$tontine_id, $membre_id]);
        log_action('Ajout membre', "Membre $membre_id ajouté à tontine $tontine_id");
    }

    header("Location: membres_tontines.php?id=$tontine_id&success=1");
    exit();
}

// Retirer un membre (mettre statut = 'retire')
if (isset($_GET['retirer']) && ($is_admin || $is_representant)) {
    $membre_id = (int) $_GET['retirer'];
    $stmt = $pdo->prepare("UPDATE membres_tontines SET statut = 'retire' WHERE tontine_id = ? AND membre_id = ?");
    $stmt->execute([$tontine_id, $membre_id]);
    log_action('Retrait membre', "Membre $membre_id retiré de tontine $tontine_id");

    header("Location: membres_tontines.php?id=$tontine_id&success=2");
    exit();
}

// Récupérer tous les membres de la tontine
$stmt = $pdo->prepare("
    SELECT u.*, mt.solde, mt.statut, mt.date_adhesion
    FROM utilisateurs u 
    INNER JOIN membres_tontines mt ON u.id = mt.membre_id
    WHERE mt.tontine_id = ? 
    ORDER BY u.nom
");
$stmt->execute([$tontine_id]);
$membres = $stmt->fetchAll();

// Récupérer tous les membres disponibles (actifs, type 'membre') pour ajouter
$stmt = $pdo->prepare("
    SELECT * FROM utilisateurs 
    WHERE type_utilisateur='membre' 
    AND statut='actif'
    AND id NOT IN (SELECT membre_id FROM membres_tontines WHERE tontine_id = ?)
    ORDER BY nom
");
$stmt->execute([$tontine_id]);
$disponibles = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Membres de la tontine</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container mt-5">
    <h2>Membres de la tontine : <?php echo htmlspecialchars($tontine['nom']); ?></h2>
    <a href="tontine.php" class="btn btn-secondary mb-3">Retour</a>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php 
            if ($_GET['success']==1) echo "Membre ajouté avec succès.";
            if ($_GET['success']==2) echo "Membre retiré avec succès.";
            ?>
        </div>
    <?php endif; ?>

    <h4>Membres actuels</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Nom complet</th>
                <th>Email</th>
                <th>Date adhésion</th>
                <th>Solde</th>
                <th>Statut</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($membres as $m): ?>
            <tr>
                <td><?php echo htmlspecialchars($m['prenom'] . ' ' . $m['nom']); ?></td>
                <td><?php echo htmlspecialchars($m['email']); ?></td>
                <td><?php echo date('d/m/Y', strtotime($m['date_adhesion'])); ?></td>
                <td><?php echo number_format($m['solde'], 2, ',', ' '); ?> FCFA</td>
                <td><?php echo ucfirst($m['statut']); ?></td>
                <td>
                    <?php if($m['statut'] === 'actif'): ?>
                    <a href="?id=<?php echo $tontine_id; ?>&retirer=<?php echo $m['id']; ?>" 
                       class="btn btn-sm btn-danger" 
                       onclick="return confirm('Retirer ce membre ?');">Retirer</a>
                    <?php else: ?>
                        <span class="text-muted">Retiré</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h4>Ajouter un membre</h4>
    <form method="POST" class="row g-3">
        <div class="col-md-6">
            <select name="membre_id" class="form-select" required>
                <option value="">-- Sélectionner un membre --</option>
                <?php foreach ($disponibles as $d): ?>
                    <option value="<?php echo $d['id']; ?>">
                        <?php echo htmlspecialchars($d['prenom'] . ' ' . $d['nom']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <button type="submit" name="ajouter_membre" class="btn btn-success">Ajouter</button>
        </div>
    </form>
</div>
</body>
</html>