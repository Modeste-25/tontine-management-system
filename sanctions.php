<?php
require_once 'config.php';
require_login();

$user = get_logged_user();
$is_admin = (isset($user['type_utilisateur']) && $user['type_utilisateur'] === 'admin');
$is_representant = (isset($user['type_utilisateur']) && $user['type_utilisateur'] === 'representant');

if (!$is_admin && !$is_representant) {
    header('Location: index.php');
    exit();
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['appliquer_sanction'])) {
        $membre_id = $_POST['membre_id'];
        $tontine_id = $_POST['tontine_id'];
        $motif = secure($_POST['motif']);
        $montant_penalite = $_POST['montant_penalite'];
        $date_fin = $_POST['date_fin'];

        if (!$is_admin) {
            $stmtCheck = $pdo->prepare("SELECT representant_id FROM tontines WHERE id = ?");
            $stmtCheck->execute([$tontine_id]);
            $t = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$t || $t['representant_id'] != $user['id']) {
                header('Location: sanctions.php?error=permission');
                exit();
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO sanctions (membre_id, tontine_id, motif, montant_penalite, date_fin, imposee_par) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$membre_id, $tontine_id, $motif, $montant_penalite, $date_fin, $user['id']]);

        add_notification($membre_id, 'Nouvelle sanction', "Une sanction de $montant_penalite FCFA vous a été appliquée. Motif: $motif");

        log_action('Application sanction', "Sanction de $montant_penalite FCFA pour membre $membre_id");
        header('Location: sanctions.php?success=1');
        exit();
    }

    if (isset($_POST['lever_sanction'])) {
        $sanction_id = $_POST['sanction_id'];

        $stmt = $pdo->prepare("UPDATE sanctions SET statut = 'levee' WHERE id = ?");
        $stmt->execute([$sanction_id]);

        $stmtSanction = $pdo->prepare("SELECT membre_id FROM sanctions WHERE id = ?");
        $stmtSanction->execute([$sanction_id]);
        $sanction = $stmtSanction->fetch(PDO::FETCH_ASSOC);

        add_notification($sanction['membre_id'], 'Sanction levée', "Votre sanction a été levée.");
        log_action('Levée sanction', "Sanction $sanction_id levée");

        header('Location: sanctions.php?success=2');
        exit();
    }
}

// Récupération des sanctions et membres
if ($is_admin) {
    $sanctions = $pdo->query("
        SELECT s.*, u.nom, u.prenom, u.email, t.nom as tontine_nom, a.prenom as admin_prenom, a.nom as admin_nom
        FROM sanctions s
        JOIN utilisateurs u ON s.membre_id = u.id
        JOIN tontines t ON s.tontine_id = t.id
        LEFT JOIN utilisateurs a ON s.imposee_par = a.id
        ORDER BY s.date_sanction DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $tontines = $pdo->query("SELECT id, nom FROM tontines WHERE statut = 'active'")->fetchAll(PDO::FETCH_ASSOC);

    $membres_par_tontine = [];
    foreach ($tontines as $t) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.nom, u.prenom 
            FROM utilisateurs u 
            JOIN membres_tontines mt ON u.id = mt.membre_id 
            WHERE mt.tontine_id = ? AND mt.statut = 'actif'
        ");
        $stmt->execute([$t['id']]);
        $membres_par_tontine[$t['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    $stmtTontine = $pdo->prepare("SELECT id, nom FROM tontines WHERE representant_id = ?");
    $stmtTontine->execute([$user['id']]);
    $tontine = $stmtTontine->fetch(PDO::FETCH_ASSOC);

    if ($tontine) {
        $stmtSanctions = $pdo->prepare("
            SELECT s.*, u.nom, u.prenom, u.email, a.prenom as admin_prenom, a.nom as admin_nom
            FROM sanctions s
            JOIN utilisateurs u ON s.membre_id = u.id
            LEFT JOIN utilisateurs a ON s.imposee_par = a.id
            WHERE s.tontine_id = ?
            ORDER BY s.date_sanction DESC
        ");
        $stmtSanctions->execute([$tontine['id']]);
        $sanctions = $stmtSanctions->fetchAll(PDO::FETCH_ASSOC);

        $tontines = [$tontine];

        $stmt = $pdo->prepare("
            SELECT u.id, u.nom, u.prenom 
            FROM utilisateurs u 
            JOIN membres_tontines mt ON u.id = mt.membre_id 
            WHERE mt.tontine_id = ? AND mt.statut = 'actif'
        ");
        $stmt->execute([$tontine['id']]);
        $membres_par_tontine[$tontine['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sanctions = [];
        $tontines = [];
        $membres_par_tontine = [];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Sanctions</title>
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
         <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
         <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
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
                        <?php if ($_GET['success'] == 1) echo "Sanction appliquée avec succès."; ?>
                        <?php if ($_GET['success'] == 2) echo "Sanction levée avec succès."; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['error']) && $_GET['error'] == 'permission'): ?>
                    <div class="alert alert-error">Vous n'avez pas la permission d'effectuer cette action.</div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Gestion des Sanctions</h2>
                            <?php if (!empty($tontines)): ?>
                            <button onclick="document.getElementById('modalAppliquer').style.display='block'" class="btn btn-primary">
                                Appliquer une sanction
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- Tableau des sanctions -->
                        <div style="max-height: 600px; overflow-y: auto; margin-top: 20px;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Membre</th>
                                        <th>Tontine</th>
                                        <th>Motif</th>
                                        <th>Montant</th>
                                        <th>Date fin</th>
                                        <th>Statut</th>
                                        <th>Appliquée par</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sanctions as $s): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($s['date_sanction'])); ?></td>
                                        <td><?php echo htmlspecialchars($s['prenom'] . ' ' . $s['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($s['tontine_nom'] ?? $tontine['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($s['motif']); ?></td>
                                        <td><?php echo number_format($s['montant_penalite'], 0, ',', ' '); ?> FCFA</td>
                                        <td><?php echo $s['date_fin'] ? date('d/m/Y', strtotime($s['date_fin'])) : '--'; ?></td>
                                        <td>
                                            <?php if ($s['statut'] === 'active'): ?>
                                                <span class="badge badge-danger">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Levée</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $s['admin_prenom'] ? htmlspecialchars($s['admin_prenom'] . ' ' . $s['admin_nom']) : 'Système'; ?></td>
                                        <td>
                                            <?php if ($s['statut'] === 'active'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="sanction_id" value="<?php echo $s['id']; ?>">
                                                    <button type="submit" name="lever_sanction" class="btn btn-sm btn-success">Lever</button>
                                                </form>
                                            <?php endif; ?>
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

    <!-- Modal Appliquer sanction -->
    <div id="modalAppliquer" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div style="background: white; margin: 5% auto; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Appliquer une sanction</h2>
                <button onclick="document.getElementById('modalAppliquer').style.display='none'" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>

            <form method="POST" id="formSanction">
                <div class="form-group">
                    <label>Tontine</label>
                    <select name="tontine_id" id="tontine_id" required onchange="chargerMembresSanction(this.value)">
                        <option value="">Sélectionner une tontine</option>
                        <?php foreach ($tontines as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Membre</label>
                    <select name="membre_id" id="membre_id" required>
                        <option value="">Sélectionner d'abord une tontine</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Motif</label>
                    <textarea name="motif" required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Montant pénalité (FCFA)</label>
                        <input type="number" name="montant_penalite" required>
                    </div>
                    <div class="form-group">
                        <label>Date de fin (optionnelle)</label>
                        <input type="date" name="date_fin">
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="appliquer_sanction" class="btn btn-primary">Appliquer</button>
                    <button type="button" onclick="document.getElementById('modalAppliquer').style.display='none'" class="btn">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        var membresData = <?php echo json_encode($membres_par_tontine); ?>;

        function chargerMembresSanction(tontineId) {
            var select = document.getElementById('membre_id');
            select.innerHTML = '<option value="">Sélectionner un membre</option>';
            if (membresData[tontineId]) {
                membresData[tontineId].forEach(function(m) {
                    var option = document.createElement('option');
                    option.value = m.id;
                    option.textContent = m.prenom + ' ' + m.nom;
                    select.appendChild(option);
                });
            }
        }

        window.onclick = function(event) {
            var modal = document.getElementById('modalAppliquer');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>
