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
    if (isset($_POST['creer_tour'])) {
        $tontine_id = $_POST['tontine_id'];
        $numero_tour = $_POST['numero_tour'];
        $montant_total = $_POST['montant_total'];
        $date_tour = $_POST['date_tour'];

        if (!$is_admin) {
            $stmtCheck = $pdo->prepare("SELECT representant_id FROM tontines WHERE id = ?");
            $stmtCheck->execute([$tontine_id]);
            $t = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$t || $t['representant_id'] != $user['id']) {
                header('Location: tours.php?error=permission');
                exit();
            }
        }

        $stmt = $pdo->prepare("INSERT INTO tours (tontine_id, numero_tour, montant_total, date_tour, statut) VALUES (?, ?, ?, ?, 'en_cours')");
        $stmt->execute([$tontine_id, $numero_tour, $montant_total, $date_tour]);

        log_action('Création tour', "Tour #$numero_tour créé pour tontine $tontine_id");

        $stmtMembres = $pdo->prepare("SELECT membre_id FROM membres_tontines WHERE tontine_id = ?");
        $stmtMembres->execute([$tontine_id]);
        $membres = $stmtMembres->fetchAll(PDO::FETCH_ASSOC);
        foreach ($membres as $m) {
            add_notification($m['membre_id'], 'Nouveau tour de tontine', "Le tour #$numero_tour a été créé pour le " . date('d/m/Y', strtotime($date_tour)));
        }

        header('Location: tours.php?success=1');
        exit();
    }

    if (isset($_POST['designer_beneficiaire'])) {
        $tour_id = $_POST['tour_id'];
        $beneficiaire_id = $_POST['beneficiaire_id'];

        $stmtTour = $pdo->prepare("SELECT tontine_id FROM tours WHERE id = ?");
        $stmtTour->execute([$tour_id]);
        $tour = $stmtTour->fetch(PDO::FETCH_ASSOC);

        if (!$is_admin) {
            $stmtCheck = $pdo->prepare("SELECT representant_id FROM tontines WHERE id = ?");
            $stmtCheck->execute([$tour['tontine_id']]);
            $t = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$t || $t['representant_id'] != $user['id']) {
                header('Location: tours.php?error=permission');
                exit();
            }
        }

        $stmt = $pdo->prepare("UPDATE tours SET beneficiaire_id = ? WHERE id = ?");
        $stmt->execute([$beneficiaire_id, $tour_id]);

        log_action('Désignation bénéficiaire', "Membre $beneficiaire_id désigné bénéficiaire du tour $tour_id");
        add_notification($beneficiaire_id, 'Bénéficiaire de tour', 'Vous avez été désigné bénéficiaire du tour #' . $tour_id);

        header('Location: tours.php?success=2');
        exit();
    }

    if (isset($_POST['cloturer_tour'])) {
        $tour_id = $_POST['tour_id'];

        $stmtTour = $pdo->prepare("SELECT tontine_id FROM tours WHERE id = ?");
        $stmtTour->execute([$tour_id]);
        $tour = $stmtTour->fetch(PDO::FETCH_ASSOC);

        if (!$is_admin) {
            $stmtCheck = $pdo->prepare("SELECT representant_id FROM tontines WHERE id = ?");
            $stmtCheck->execute([$tour['tontine_id']]);
            $t = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$t || $t['representant_id'] != $user['id']) {
                header('Location: tours.php?error=permission');
                exit();
            }
        }

        $stmt = $pdo->prepare("UPDATE tours SET statut = 'termine' WHERE id = ?");
        $stmt->execute([$tour_id]);

        log_action('Clôture tour', "Tour $tour_id clôturé");

        header('Location: tours.php?success=3');
        exit();
    }
}

// Récupération des tontines et tours
if ($is_admin) {
    $tontines = $pdo->query("SELECT id, nom FROM tontines WHERE statut = 'active'")->fetchAll(PDO::FETCH_ASSOC);

    $tours = $pdo->query("
        SELECT t.*, u.prenom, u.nom as beneficiaire_nom, tont.nom as tontine_nom 
        FROM tours t 
        LEFT JOIN utilisateurs u ON t.beneficiaire_id = u.id 
        JOIN tontines tont ON t.tontine_id = tont.id 
        ORDER BY t.date_tour DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

} else {
    $stmtTontine = $pdo->prepare("SELECT id, nom FROM tontines WHERE representant_id = ?");
    $stmtTontine->execute([$user['id']]);
    $tontine = $stmtTontine->fetch(PDO::FETCH_ASSOC);
    $tontines = $tontine ? [$tontine] : [];

    if ($tontine) {
        $stmtTours = $pdo->prepare("
            SELECT t.*, u.prenom, u.nom as beneficiaire_nom 
            FROM tours t 
            LEFT JOIN utilisateurs u ON t.beneficiaire_id = u.id 
            WHERE t.tontine_id = ? 
            ORDER BY t.date_tour DESC
        ");
        $stmtTours->execute([$tontine['id']]);
        $tours = $stmtTours->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $tours = [];
    }
}

// Membres par tontine
$membres_par_tontine = [];
foreach ($tontines as $t) {
    $stmtMembres = $pdo->prepare("
        SELECT u.id, u.nom, u.prenom 
        FROM utilisateurs u 
        JOIN membres_tontines mt ON u.id = mt.membre_id 
        WHERE mt.tontine_id = ? AND mt.statut = 'actif'
    ");
    $stmtMembres->execute([$t['id']]);
    $membres_par_tontine[$t['id']] = $stmtMembres->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Tours</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="dashboard-container">
    <?php include $is_admin ? 'sidebar_admin.php' : 'sidebar_representant.php'; ?>
    <div class="main-content">
        <?php include 'topbar.php'; ?>
        <div class="content-area">
            <div class="section active">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        <?php
                        if ($_GET['success'] == 1) echo "Tour créé avec succès.";
                        if ($_GET['success'] == 2) echo "Bénéficiaire désigné avec succès.";
                        if ($_GET['success'] == 3) echo "Tour clôturé avec succès.";
                        ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['error']) && $_GET['error'] == 'permission'): ?>
                    <div class="alert alert-error">Vous n'avez pas la permission d'effectuer cette action.</div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Gestion des Tours de Distribution</h2>
                        <?php if (!empty($tontines)): ?>
                            <button onclick="document.getElementById('modalCreer').style.display='block'" class="btn btn-primary">
                                Créer un nouveau tour
                            </button>
                        <?php endif; ?>
                    </div>

                    <div style="max-height: 600px; overflow-y: auto; margin-top: 20px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Tontine</th>
                                    <th>Montant total</th>
                                    <th>Date du tour</th>
                                    <th>Bénéficiaire</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tours as $tour): ?>
                                <tr>
                                    <td><strong>Tour #<?= $tour['numero_tour'] ?></strong></td>
                                    <td><?= htmlspecialchars($tour['tontine_nom'] ?? $tontine['nom']) ?></td>
                                    <td><?= number_format($tour['montant_total'], 0, ',', ' ') ?> FCFA</td>
                                    <td><?= date('d/m/Y', strtotime($tour['date_tour'])) ?></td>
                                    <td>
                                        <?php if ($tour['beneficiaire_nom']): ?>
                                            <?= htmlspecialchars($tour['prenom'] . ' ' . $tour['beneficiaire_nom']) ?>
                                        <?php else: ?>
                                            <span style="color: var(--text-light);">À désigner</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($tour['statut'] === 'en_cours'): ?>
                                            <span class="badge badge-warning">En cours</span>
                                        <?php elseif ($tour['statut'] === 'termine'): ?>
                                            <span class="badge badge-success">Terminé</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Annulé</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($tour['statut'] === 'en_cours'): ?>
                                            <?php if (!$tour['beneficiaire_id']): ?>
                                                <button onclick="designerBeneficiaire(<?= $tour['id'] ?>, <?= $tour['tontine_id'] ?>)" class="btn btn-sm btn-primary">Désigner</button>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="tour_id" value="<?= $tour['id'] ?>">
                                                    <button type="submit" name="cloturer_tour" class="btn btn-sm btn-success">Clôturer</button>
                                                </form>
                                            <?php endif; ?>
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

<!-- Modal Créer un tour -->
<div id="modalCreer" style="display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background-color:rgba(0,0,0,0.5);">
    <div style="background:white;margin:5% auto;padding:30px;border-radius:10px;width:90%;max-width:500px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2>Créer un nouveau tour</h2>
            <button onclick="document.getElementById('modalCreer').style.display='none'" style="background:none;border:none;font-size:24px;cursor:pointer;">&times;</button>
        </div>
        <form method="POST">
            <div class="form-group">
                <label>Tontine</label>
                <select name="tontine_id" required>
                    <option value="">Sélectionner une tontine</option>
                    <?php foreach ($tontines as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Numéro du tour</label>
                <input type="number" name="numero_tour" required min="1">
            </div>
            <div class="form-group">
                <label>Montant total (FCFA)</label>
                <input type="number" name="montant_total" required>
            </div>
            <div class="form-group">
                <label>Date du tour</label>
                <input type="date" name="date_tour" required>
            </div>
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" name="creer_tour" class="btn btn-primary">Créer</button>
                <button type="button" onclick="document.getElementById('modalCreer').style.display='none'" class="btn">Annuler</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Désigner bénéficiaire -->
<div id="modalDesigner" style="display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background-color:rgba(0,0,0,0.5);">
    <div style="background:white;margin:5% auto;padding:30px;border-radius:10px;width:90%;max-width:500px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2>Désigner un bénéficiaire</h2>
            <button onclick="document.getElementById('modalDesigner').style.display='none'" style="background:none;border:none;font-size:24px;cursor:pointer;">&times;</button>
        </div>
        <form method="POST" id="formDesigner">
            <input type="hidden" name="tour_id" id="tour_id">
            <div class="form-group">
                <label>Sélectionner le bénéficiaire</label>
                <select name="beneficiaire_id" id="beneficiaire_id" required>
                    <option value="">Choisir un membre</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" name="designer_beneficiaire" class="btn btn-primary">Désigner</button>
                <button type="button" onclick="document.getElementById('modalDesigner').style.display='none'" class="btn">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
var membresData = <?= json_encode($membres_par_tontine) ?>;

function designerBeneficiaire(tourId, tontineId) {
    document.getElementById('tour_id').value = tourId;
    var select = document.getElementById('beneficiaire_id');
    select.innerHTML = '<option value="">Choisir un membre</option>';
    if (membresData[tontineId]) {
        membresData[tontineId].forEach(function(m) {
            var option = document.createElement('option');
            option.value = m.id;
            option.textContent = m.prenom + ' ' + m.nom;
            select.appendChild(option);
        });
    }
    document.getElementById('modalDesigner').style.display = 'block';
}

window.onclick = function(event) {
    if (event.target.id === 'modalCreer') document.getElementById('modalCreer').style.display = "none";
    if (event.target.id === 'modalDesigner') document.getElementById('modalDesigner').style.display = "none";
}
</script>
</body>
</html>
