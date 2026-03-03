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

if (isset($_GET['supprimer']) && $is_admin) {
    $id = (int) $_GET['supprimer'];

    $stmt = $pdo->prepare("DELETE FROM tontines WHERE id = ?");
    $stmt->execute([$id]);

    log_action('Suppression tontine', "Tontine $id supprimée");

    header('Location: tontine.php?success=3');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['creer_tontine']) && $is_admin) {

        $nom = secure($_POST['nom']);
        $description = secure($_POST['description']);
        $montant = (float) $_POST['montant_cotisation'];
        $frequence = secure($_POST['frequence']);
        $date_debut = $_POST['date_debut'];
        $representant_id = !empty($_POST['representant_id']) ? (int) $_POST['representant_id'] : null;

        $stmt = $pdo->prepare("
            INSERT INTO tontines 
            (nom, description, montant_cotisation, frequence, date_debut, representant_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nom, $description, $montant, $frequence, $date_debut, $representant_id]);

        log_action('Création tontine', "Nouvelle tontine: $nom");

        header('Location: tontine.php?success=1');
        exit();
    }

    if (isset($_POST['modifier_tontine'])) {

        $id = (int) $_POST['id'];

        if (!$is_admin) {
            $check = $pdo->prepare("SELECT representant_id FROM tontines WHERE id = ?");
            $check->execute([$id]);
            $t = $check->fetch();

            if (!$t || $t['representant_id'] != $user['id']) {
                header('Location: tontine.php?error=permission');
                exit();
            }
        }

        $nom = secure($_POST['nom']);
        $description = secure($_POST['description']);
        $montant = (float) $_POST['montant_cotisation'];
        $frequence = secure($_POST['frequence']);
        $date_debut = $_POST['date_debut'];
        $statut = secure($_POST['statut']);

        $stmt = $pdo->prepare("
            UPDATE tontines 
            SET nom=?, description=?, montant_cotisation=?, frequence=?, date_debut=?, statut=? 
            WHERE id=?
        ");
        $stmt->execute([$nom, $description, $montant, $frequence, $date_debut, $statut, $id]);

        log_action('Modification tontine', "Tontine $id modifiée");

        header('Location: tontine.php?success=2');
        exit();
    }
}

if ($is_admin) {

    $stmt = $pdo->query("
        SELECT t.*, u.prenom, u.nom AS representant_nom 
        FROM tontines t 
        LEFT JOIN utilisateurs u ON t.representant_id = u.id 
        ORDER BY t.id DESC
    ");
    $tontines = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT id, prenom, nom 
        FROM utilisateurs 
        WHERE type_utilisateur = 'representant' 
        AND statut = 'actif'
    ");
    $representants = $stmt->fetchAll();
} else {

    $stmt = $pdo->prepare("
        SELECT * FROM tontines 
        WHERE representant_id = ?
        ORDER BY id DESC
    ");
    $stmt->execute([$user['id']]);
    $tontines = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Tontines</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="dashboard-container">
        <?php
        if ($is_admin) {
            include 'sidebar_admin.php';
        } else {
            include 'sidebar_representant.php';
        }
        ?>

        <div class="main-content">
            <?php include 'topbar.php'; ?>

            <div class="content-area">
                <div class="section active">
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success">
                            <?php if ($_GET['success'] == 1) echo "Tontine créée avec succès."; ?>
                            <?php if ($_GET['success'] == 2) echo "Tontine modifiée avec succès."; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['error']) && $_GET['error'] == 'permission'): ?>
                        <div class="alert alert-error">Vous n'avez pas la permission d'effectuer cette action.</div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Gestion des Tontines</h2>
                            <?php if ($is_admin): ?>
                                <button onclick="document.getElementById('modalCreer').style.display='block'" class="btn btn-primary">
                                    Créer une tontine
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Liste des tontines -->
                        <div style="margin-top: 20px;">
                            <?php foreach ($tontines as $tontine): ?>
                                <div class="card" style="margin-bottom: 15px;">
                                    <div class="card-header">
                                        <h3><?php echo htmlspecialchars($tontine['nom']); ?></h3>
                                        <div>
                                            <a href="?edit=<?php echo $tontine['id']; ?>" class="btn btn-sm btn-primary">Modifier</a>
                                            <?php if ($is_admin): ?>
                                                <a href="?supprimer=<?php echo $tontine['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cette tontine ?')">Supprimer</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <a href="membres_tontines.php?id=<?php echo $tontine['id']; ?>" class="btn btn-sm btn-success">
                                        Gérer les membres
                                    </a>
                                </div>
                                <p><?php echo nl2br(htmlspecialchars($tontine['description'])); ?></p>
                                <div class="form-row">
                                    <div><strong>Montant cotisation:</strong> <?php echo number_format($tontine['montant_cotisation'], 0, ',', ' '); ?> FCFA</div>
                                    <div><strong>Fréquence:</strong> <?php echo ucfirst($tontine['frequence']); ?></div>
                                    <div><strong>Date début:</strong> <?php echo date('d/m/Y', strtotime($tontine['date_debut'])); ?></div>
                                    <div><strong>Statut:</strong>
                                        <span class="badge badge-<?php echo $tontine['statut'] == 'active' ? 'success' : ($tontine['statut'] == 'inactive' ? 'warning' : 'info'); ?>">
                                            <?php echo ucfirst($tontine['statut']); ?>
                                        </span>
                                    </div>
                                    <?php if ($is_admin): ?>
                                        <div><strong>Représentant:</strong> <?php echo $tontine['representant_nom'] ? htmlspecialchars($tontine['prenom'] . ' ' . $tontine['representant_nom']) : 'Non assigné'; ?></div>
                                    <?php endif; ?>
                                </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Modal Créer tontine (admin) -->
    <?php if ($is_admin): ?>
        <div id="modalCreer" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
            <div style="background: white; margin: 5% auto; padding: 30px; border-radius: 10px; width: 90%; max-width: 600px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Créer une nouvelle tontine</h2>
                    <button onclick="document.getElementById('modalCreer').style.display='none'" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label>Nom de la tontine</label>
                        <input type="text" name="nom" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Montant cotisation (FCFA)</label>
                            <input type="number" name="montant_cotisation" required>
                        </div>
                        <div class="form-group">
                            <label>Fréquence</label>
                            <select name="frequence">
                                <option value="quotidien">Quotidien</option>
                                <option value="hebdomadaire">Hebdomadaire</option>
                                <option value="mensuel">Mensuel</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date de début</label>
                            <input type="date" name="date_debut" required>
                        </div>
                        <div class="form-group">
                            <label>Représentant (optionnel)</label>
                            <select name="representant_id">
                                <option value="">-- Aucun --</option>
                                <?php foreach ($representants as $rep): ?>
                                    <option value="<?php echo $rep['id']; ?>"><?php echo htmlspecialchars($rep['prenom'] . ' ' . $rep['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" name="creer_tontine" class="btn btn-primary">Créer</button>
                        <button type="button" onclick="document.getElementById('modalCreer').style.display='none'" class="btn">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        window.onclick = function(event) {
            var modal = document.getElementById('modalCreer');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>

</html>