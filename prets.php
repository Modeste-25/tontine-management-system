<?php
require_once 'config.php';
require_login();

$user = get_logged_user(); 

if (!$user) {
    header('Location: index.php');
    exit();
}

$is_admin = (isset($user['type_utilisateur']) && $user['type_utilisateur'] === 'admin');
$is_representant = (isset($user['type_utilisateur']) && $user['type_utilisateur'] === 'representant');
$is_membre = (isset($user['type_utilisateur']) && $user['type_utilisateur'] === 'membre');

$prets = [];

if (!$is_admin && !$is_representant && !$is_membre) {
    header('Location: index.php');
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ===== DEMANDE DE PRÊT ===== */
    if (isset($_POST['demander_pret']) && $is_membre) {

        $tontine_id = $_POST['tontine_id'];
        $montant = $_POST['montant'];
        $taux_interet = $_POST['taux_interet'] ?? 5;

        $montant_interet = $montant * $taux_interet / 100;
        $montant_total = $montant + $montant_interet;
        $date_echeance = $_POST['date_echeance'];

        $stmt = $pdo->prepare("
            INSERT INTO prets 
            (tontine_id, membre_id, montant, taux_interet, montant_interet, montant_total, date_echeance, statut) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente')
        ");
        $stmt->execute([
            $tontine_id,
            $user['id'],
            $montant,
            $taux_interet,
            $montant_interet,
            $montant_total,
            $date_echeance
        ]);

        log_action('Demande prêt', "Demande de prêt de $montant FCFA");

        header('Location: prets.php?success=1');
        exit();
    }

    /* ===== APPROUVER ===== */
    if (isset($_POST['approuver_pret']) && ($is_admin || $is_representant)) {

        $pret_id = $_POST['pret_id'];

        $stmt = $pdo->prepare("
            UPDATE prets 
            SET statut = 'approuve', date_approbation = NOW(), approbateur_id = ? 
            WHERE id = ?
        ");
        $stmt->execute([$user['id'], $pret_id]);

        $stmt = $pdo->prepare("SELECT membre_id, montant FROM prets WHERE id = ?");
        $stmt->execute([$pret_id]);
        $pret = $stmt->fetch();

        if ($pret) {
            add_notification(
                $pret['membre_id'],
                'Prêt approuvé',
                "Votre prêt de {$pret['montant']} FCFA a été approuvé."
            );
        }

        log_action('Approbation prêt', "Prêt $pret_id approuvé");

        header('Location: prets.php?success=2');
        exit();
    }

    /* ===== REFUSER ===== */
    if (isset($_POST['refuser_pret']) && ($is_admin || $is_representant)) {

        $pret_id = $_POST['pret_id'];

        $stmt = $pdo->prepare("UPDATE prets SET statut = 'refuse' WHERE id = ?");
        $stmt->execute([$pret_id]);

        $stmt = $pdo->prepare("SELECT membre_id, montant FROM prets WHERE id = ?");
        $stmt->execute([$pret_id]);
        $pret = $stmt->fetch();

        if ($pret) {
            add_notification(
                $pret['membre_id'],
                'Prêt refusé',
                "Votre demande de prêt de {$pret['montant']} FCFA a été refusée."
            );
        }

        log_action('Refus prêt', "Prêt $pret_id refusé");

        header('Location: prets.php?success=3');
        exit();
    }

    /* ===== REMBOURSEMENT ===== */
    if (isset($_POST['rembourser']) && $is_membre) {

        $pret_id = $_POST['pret_id'];
        $montant = $_POST['montant'];

        $stmt = $pdo->prepare("
            INSERT INTO remboursements (pret_id, montant) 
            VALUES (?, ?)
        ");
        $stmt->execute([$pret_id, $montant]);

        // Vérifier remboursement total
        $stmt = $pdo->prepare("SELECT montant_total FROM prets WHERE id = ?");
        $stmt->execute([$pret_id]);
        $pret = $stmt->fetch();

        $stmt = $pdo->prepare("SELECT SUM(montant) FROM remboursements WHERE pret_id = ?");
        $stmt->execute([$pret_id]);
        $total_rembourse = $stmt->fetchColumn() ?? 0;

        if ($pret && $total_rembourse >= $pret['montant_total']) {
            $stmt = $pdo->prepare("UPDATE prets SET statut = 'rembourse' WHERE id = ?");
            $stmt->execute([$pret_id]);
        }

        log_action('Remboursement prêt', "Remboursement de $montant FCFA");

        header('Location: prets.php?success=4');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Prêts</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php
        if ($is_admin) include 'sidebar_admin.php';
        elseif ($is_representant) include 'sidebar_representant.php';
        else include 'sidebar_membres.php';
        ?>

        <div class="main-content">
            <?php include 'topbar.php'; ?>

            <div class="content-area">
                <div class="section active">
                    <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        <?php
                        if ($_GET['success'] == 1) echo "Demande de prêt envoyée avec succès.";
                        if ($_GET['success'] == 2) echo "Prêt approuvé.";
                        if ($_GET['success'] == 3) echo "Prêt refusé.";
                        if ($_GET['success'] == 4) echo "Remboursement enregistré.";
                        ?>
                    </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Gestion des Prêts</h2>
                            <?php if ($is_membre): ?>
                            <button onclick="document.getElementById('modalDemander').style.display='block'" class="btn btn-primary">
                                Demander un prêt
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- Tableau des prêts -->
                        <div style="max-height: 600px; overflow-y: auto; margin-top: 20px;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Membre</th>
                                        <th>Tontine</th>
                                        <th>Montant</th>
                                        <th>Intérêt</th>
                                        <th>Total</th>
                                        <th>Échéance</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    
                                    <?php
                                    foreach ($prets as $p): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($p['date_demande'])); ?></td>
                                        <td><?php echo htmlspecialchars($p['prenom'] . ' ' . $p['nom'] ?? $user['prenom'] . ' ' . $user['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($p['tontine_nom']); ?></td>
                                        <td><?php echo number_format($p['montant'], 0, ',', ' '); ?> FCFA</td>
                                        <td><?php echo $p['taux_interet']; ?>% (<?php echo number_format($p['montant_interet'], 0, ',', ' '); ?>)</td>
                                        <td><?php echo number_format($p['montant_total'], 0, ',', ' '); ?> FCFA</td>
                                        <td><?php echo date('d/m/Y', strtotime($p['date_echeance'])); ?></td>
                                        <td>
                                            <?php
                                            $badge = '';
                                            switch ($p['statut']) {
                                                case 'en_attente': $badge = 'warning'; break;
                                                case 'approuve': $badge = 'success'; break;
                                                case 'refuse': $badge = 'danger'; break;
                                                case 'rembourse': $badge = 'info'; break;
                                                case 'en_retard': $badge = 'danger'; break;
                                            }
                                            ?>
                                            <span class="badge badge-<?php echo $badge; ?>"><?php echo ucfirst($p['statut']); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($p['statut'] === 'en_attente' && ($is_admin || $is_representant)): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="pret_id" value="<?php echo $p['id']; ?>">
                                                    <button type="submit" name="approuver_pret" class="btn btn-sm btn-success">Approuver</button>
                                                    <button type="submit" name="refuser_pret" class="btn btn-sm btn-danger">Refuser</button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($p['statut'] === 'approuve' && $is_membre && $p['membre_id'] == $user['id']): ?>
                                                <a href="?rembourser=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary">Rembourser</a>
                                            <?php endif; ?>

                                            <?php if ($p['statut'] === 'en_retard' && ($is_admin || $is_representant)): ?>
                                                <a href="sanctions.php?action=appliquer&membre_id=<?php echo $p['membre_id']; ?>" class="btn btn-sm btn-warning">Sanctionner</a>
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

    <?php if ($is_membre): ?>
    <!-- Modal Demander un prêt -->
    <div id="modalDemander" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div style="background: white; margin: 5% auto; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Demander un prêt</h2>
                <button onclick="document.getElementById('modalDemander').style.display='none'" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label>Tontine</label>
                    <select name="tontine_id" required>
                        <option value="">Sélectionner une tontine</option>
                        <?php foreach ($tontines_membre as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Montant (FCFA)</label>
                    <input type="number" name="montant" required min="1000">
                </div>

                <div class="form-group">
                    <label>Taux d'intérêt (%)</label>
                    <input type="number" name="taux_interet" value="5" min="0" step="0.1">
                </div>

                <div class="form-group">
                    <label>Date d'échéance</label>
                    <input type="date" name="date_echeance" required>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="demander_pret" class="btn btn-primary">Envoyer la demande</button>
                    <button type="button" onclick="document.getElementById('modalDemander').style.display='none'" class="btn">Annuler</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        window.onclick = function(event) {
            var modal = document.getElementById('modalDemander');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>
