<?php
require_once 'config.php';
require_login();

$user = get_logged_user();
$is_admin = ($user && $user['type_utilisateur'] === 'admin');
$is_representant = ($user && $user['type_utilisateur'] === 'representant');

if (!$is_admin && !$is_representant) {
    header('Location: index.php');
    exit();
}

// --- Gestion POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ajouter membre
    if (isset($_POST['ajouter_membre'])) {
        $tontine_id = $_POST['tontine_id'];
        $membre_id = $_POST['membre_id'];

        if (!$is_admin) {
            $check = $pdo->prepare("SELECT representant_id FROM tontines WHERE id=?");
            $check->execute([$tontine_id]);
            $t = $check->fetch(PDO::FETCH_ASSOC);
            if (!$t || $t['representant_id'] != $user['id']) {
                header('Location: tours.php?error=permission');
                exit();
            }
        }

        $exists = $pdo->prepare("SELECT COUNT(*) FROM ordre_passage WHERE tontine_id=? AND membre_id=?");
        $exists->execute([$tontine_id, $membre_id]);
        if ($exists->fetchColumn() == 0) {
            $maxPos = $pdo->prepare("SELECT MAX(position) FROM ordre_passage WHERE tontine_id=?");
            $maxPos->execute([$tontine_id]);
            $position = ($maxPos->fetchColumn() ?: 0) + 1;
            $stmt = $pdo->prepare("INSERT INTO ordre_passage (tontine_id, membre_id, position) VALUES (?, ?, ?)");
            $stmt->execute([$tontine_id, $membre_id, $position]);
        }

        header('Location: tours.php?tontine=' . $tontine_id);
        exit();
    }

    // Déplacer membre
    if (isset($_POST['deplacer'])) {
        $id = $_POST['id'];
        $direction = $_POST['deplacer']; // up ou down
        $tontine_id = $_POST['tontine_id'];

        $stmt = $pdo->prepare("SELECT position FROM ordre_passage WHERE id=? AND tontine_id=?");
        $stmt->execute([$id, $tontine_id]);
        $pos = $stmt->fetchColumn();
        if (!$pos) exit;

        $max = $pdo->prepare("SELECT MAX(position) FROM ordre_passage WHERE tontine_id=?");
        $max->execute([$tontine_id]);
        $maxPos = $max->fetchColumn();

        if ($direction === 'up' && $pos == 1) exit;
        if ($direction === 'down' && $pos == $maxPos) exit;

        $newPos = ($direction === 'up') ? $pos - 1 : $pos + 1;

        $pdo->beginTransaction();
        $stmt2 = $pdo->prepare("SELECT id FROM ordre_passage WHERE tontine_id=? AND position=?");
        $stmt2->execute([$tontine_id, $newPos]);
        $otherId = $stmt2->fetchColumn();
        if ($otherId) {
            $pdo->prepare("UPDATE ordre_passage SET position=? WHERE id=?")->execute([$pos, $otherId]);
        }
        $pdo->prepare("UPDATE ordre_passage SET position=? WHERE id=?")->execute([$newPos, $id]);
        $pdo->commit();

        header('Location: tours.php?tontine=' . $tontine_id);
        exit();
    }

    // Supprimer membre
    if (isset($_POST['supprimer'])) {
        $id = $_POST['id'];
        $tontine_id = $_POST['tontine_id'];
        $position = $_POST['position'];

        $pdo->prepare("DELETE FROM ordre_passage WHERE id=?")->execute([$id]);
        $pdo->prepare("UPDATE ordre_passage SET position=position-1 WHERE tontine_id=? AND position>?")
            ->execute([$tontine_id, $position]);

        header('Location: tours.php?tontine=' . $tontine_id);
        exit();
    }

    // Tour suivant
    if (isset($_POST['tour_suivant'])) {
        $tontine_id = $_POST['tontine_id'];

        // Vérifier s'il y a déjà un tour en cours
        $check = $pdo->prepare("SELECT COUNT(*) FROM tours WHERE tontine_id=? AND statut='en_cours'");
        $check->execute([$tontine_id]);
        if ($check->fetchColumn() > 0) {
            header('Location: tours.php?tontine=' . $tontine_id . '&error=tour_actif');
            exit();
        }

        // Premier membre de l'ordre
        $stmt = $pdo->prepare("SELECT membre_id FROM ordre_passage WHERE tontine_id=? ORDER BY position ASC LIMIT 1");
        $stmt->execute([$tontine_id]);
        $prochain = $stmt->fetchColumn();
        if ($prochain) {
            $maxNum = $pdo->prepare("SELECT MAX(numero_tour) FROM tours WHERE tontine_id=?");
            $maxNum->execute([$tontine_id]);
            $nextNum = ($maxNum->fetchColumn() ?: 0) + 1;

            $montant_cotisation = $pdo->prepare("SELECT montant_cotisation FROM tontines WHERE id=?");
            $montant_cotisation->execute([$tontine_id]);
            $montant_total = $montant_cotisation->fetchColumn() * 10;

            $stmt = $pdo->prepare("INSERT INTO tours (tontine_id, numero_tour, montant_total, date_tour, beneficiaire_id, statut) VALUES (?, ?, ?, ?, ?, 'en_cours')");
            $stmt->execute([$tontine_id, $nextNum, $montant_total, date('Y-m-d'), $prochain]);

            $pdo->prepare("UPDATE ordre_passage SET position=position-1 WHERE tontine_id=?")->execute([$tontine_id]);
            // Mettre le bénéficiaire en dernière position
            $stmtMax = $pdo->prepare("
    SELECT COALESCE(MAX(position),0)
    FROM ordre_passage
    WHERE tontine_id = ?
");
            $stmtMax->execute([$tontine_id]);
            $maxPosition = $stmtMax->fetchColumn();

            $stmtUpdate = $pdo->prepare("
    UPDATE ordre_passage
    SET position = ?
    WHERE tontine_id = ?
    AND membre_id = ?
");
            $stmtUpdate->execute([
                $maxPosition + 1,
                $tontine_id,
                $prochain
            ]);
        }

        header('Location: tours.php?tontine=' . $tontine_id);
        exit();
    }

    // Pause/Reprendre
    if (isset($_POST['toggle_pause'])) {
        $tontine_id = $_POST['tontine_id'];
        $statut = $_POST['statut'];
        $pdo->prepare("UPDATE tontines SET statut=? WHERE id=?")->execute([$statut, $tontine_id]);
        header('Location: tours.php?tontine=' . $tontine_id);
        exit();
    }

    // Réinitialiser
    if (isset($_POST['reinitialiser'])) {
        $tontine_id = $_POST['tontine_id'];
        $pdo->prepare("DELETE FROM ordre_passage WHERE tontine_id=?")->execute([$tontine_id]);

        $membres = $pdo->prepare("SELECT membre_id FROM membres_tontines WHERE tontine_id=? AND statut='actif' ORDER BY date_adhesion");
        $membres->execute([$tontine_id]);
        $pos = 1;
        foreach ($membres as $m) {
            $pdo->prepare("INSERT INTO ordre_passage (tontine_id, membre_id, position) VALUES (?, ?, ?)")->execute([$tontine_id, $m['membre_id'], $pos++]);
        }

        header('Location: tours.php?tontine=' . $tontine_id);
        exit();
    }
}

// --- Détermination tontine ---
$tontine_id = $_GET['tontine'] ?? null;
if (!$tontine_id && $is_representant) {
    $stmt = $pdo->prepare("SELECT id FROM tontines WHERE representant_id=?");
    $stmt->execute([$user['id']]);
    $tontine_id = $stmt->fetchColumn();
}
if (!$tontine_id) die("Veuillez sélectionner une tontine.");

// Infos tontine
$stmt = $pdo->prepare("SELECT * FROM tontines WHERE id=?");
$stmt->execute([$tontine_id]);
$tontine = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$tontine) die("Tontine introuvable.");

// Droits
if (!$is_admin && $tontine['representant_id'] != $user['id']) {
    header('Location: index.php');
    exit();
}

// Tour actuel
$stmt = $pdo->prepare("SELECT t.*, u.prenom, u.nom FROM tours t LEFT JOIN utilisateurs u ON t.beneficiaire_id=u.id WHERE t.tontine_id=? AND t.statut='en_cours' ORDER BY t.date_tour DESC LIMIT 1");
$stmt->execute([$tontine_id]);
$tour_actuel = $stmt->fetch(PDO::FETCH_ASSOC);

// Ordre de passage
$stmt = $pdo->prepare("SELECT op.*, u.prenom, u.nom, u.code_membre, u.telephone FROM ordre_passage op JOIN utilisateurs u ON op.membre_id=u.id WHERE op.tontine_id=? ORDER BY op.position");
$stmt->execute([$tontine_id]);
$ordre = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Membres pour ajout
$stmt = $pdo->prepare("SELECT u.id, u.prenom, u.nom FROM utilisateurs u JOIN membres_tontines mt ON u.id=mt.membre_id WHERE mt.tontine_id=? AND mt.statut='actif' ORDER BY u.nom, u.prenom");
$stmt->execute([$tontine_id]);
$membres_tontine = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tours passés
$stmt = $pdo->prepare("SELECT * FROM tours WHERE tontine_id=? ORDER BY date_tour DESC LIMIT 5");
$stmt->execute([$tontine_id]);
$tours_passes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Tours - <?= htmlspecialchars($tontine['nom']) ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        .control-panel {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .current-tour {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .tour-info {
            font-size: 1.2em;
        }
        .tour-info strong {
            color: #16a34a;
        }
        .member-list {
            list-style: none;
            padding: 0;
        }
        .member-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .member-item:last-child {
            border-bottom: none;
        }
        .member-actions button {
            margin-left: 5px;
        }
        .badge-actuel {
            background: #16a34a;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-left: 10px;
        }
        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.2em;
            padding: 0 5px;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include $is_admin ? 'sidebar_admin.php' : 'sidebar_representant.php'; ?>
    <div class="main-content">
        <?php include 'topbar.php'; ?>
        <div class="content-area">
            <div class="section active">
                <h1>Gestion des Tours - <?= isset($tontine['nom']) ? htmlspecialchars($tontine['nom']) : 'Tontine inconnue' ?></h1>
                <!-- Contrôle du Tour -->
                <div class="control-panel">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h2>Contrôle du Tour</h2>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="tontine_id" value="<?= $tontine_id ?>">
                            <input type="hidden" name="statut" value="<?= isset($tontine['statut']) && $tontine['statut'] === 'active' ? 'pause' : 'active' ?>">
                            <button type="submit" name="toggle_pause" class="btn btn-warning">
                                <?= (isset($tontine['statut']) && $tontine['statut'] === 'active') ? 'Mettre en pause' : 'Reprendre' ?>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Tour Actuel -->
                <div class="current-tour">
                    <div class="tour-info">
                        <?php if ($tour_actuel): ?>
                            <strong><?= htmlspecialchars($tour_actuel['prenom'] . ' ' . $tour_actuel['nom']) ?></strong><br>
                            Tour <?= $tour_actuel['numero_tour'] ?> — Position
                            <?php
                            // Trouver la position de ce membre dans l'ordre
                            $pos_actuelle = 1;
                            foreach ($ordre as $idx => $m) {
                                if ($m['membre_id'] == $tour_actuel['beneficiaire_id']) {
                                    $pos_actuelle = $idx + 1;
                                    break;
                                }
                            }
                            $total_membres = count($ordre);
                            ?>
                            <?= $pos_actuelle ?> / <?= $total_membres ?>
                        <?php else: ?>
                            Aucun tour en cours
                        <?php endif; ?>
                    </div>
                    <div>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="tontine_id" value="<?= $tontine_id ?>">
                            <button type="submit" name="tour_suivant" class="btn btn-primary"> Tour suivant</button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="tontine_id" value="<?= $tontine_id ?>">
                            <button type="submit" name="reinitialiser" class="btn btn-secondary" onclick="return confirm('Réinitialiser l\'ordre ?')">Créé initialiser</button>
                        </form>
                    </div>
                </div>

                <!-- Ajouter un Membre au Tour -->
                <div class="card">
                    <div class="card-header">
                        <h2>Ajouter un Membre au Tour</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" style="display: flex; gap: 10px;">
                            <input type="hidden" name="tontine_id" value="<?= $tontine_id ?>">
                            <select name="membre_id" required class="form-control" style="width: 300px;">
                                <option value="">Sélectionner un membre</option>
                                <?php foreach ($membres_tontine as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="ajouter_membre" class="btn btn-primary">+ Ajouter</button>
                        </form>
                    </div>
                </div>

                <!-- Ordre des Tours -->
                <div class="card">
                    <div class="card-header">
                        <h2>Ordre des Tours (<?= count($ordre) ?> membres)</h2>
                        <a href="?tontine=<?= $tontine_id ?>" class="btn btn-sm">Actualiser</a>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Position</th>
                                    <th>Membre</th>
                                    <th>Code</th>
                                    <th>Téléphone</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ordre as $idx => $m): ?>
                                <tr>
                                    <td><?= $m['position'] ?></td>
                                    <td>
                                        <?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?>
                                        <?php if ($tour_actuel && $tour_actuel['beneficiaire_id'] == $m['membre_id']): ?>
                                            <span class="badge-actuel">Actuel</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($m['code_membre'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($m['telephone'] ?? '-') ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                            <input type="hidden" name="tontine_id" value="<?= $tontine_id ?>">
                                            <input type="hidden" name="position" value="<?= $m['position'] ?>">
                                            <button type="submit" name="deplacer" value="up" class="btn-icon" title="Monter">↑</button>
                                            <button type="submit" name="deplacer" value="down" class="btn-icon" title="Descendre">↓</button>
                                            <button type="submit" name="supprimer" class="btn-icon" title="Supprimer" onclick="return confirm('Supprimer ce membre de l\'ordre ?')">🗑️</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Historique des tours (optionnel) -->
                <?php if ($tours_passes): ?>
                <div class="card">
                    <div class="card-header">
                        <h2>Tours précédents</h2>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Num</th>
                                    <th>Date</th>
                                    <th>Montant</th>
                                    <th>Bénéficiaire</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tours_passes as $t): ?>
                                <tr>
                                    <td>Tour #<?= $t['numero_tour'] ?></td>
                                    <td><?= date('d/m/Y', strtotime($t['date_tour'])) ?></td>
                                    <td><?= number_format($t['montant_total'], 0, ',', ' ') ?> FCFA</td>
                                    <td>
                                        <?php
                                    if ($t['beneficiaire_id']) {
                                        $stmt = $pdo->prepare("SELECT prenom, nom FROM utilisateurs WHERE id = ?");
                                        $stmt->execute([$t['beneficiaire_id']]);
                                        $benef = $stmt->fetch(PDO::FETCH_ASSOC);

                                        if ($benef) {
                                            echo htmlspecialchars($benef['prenom'] . ' ' . $benef['nom']);
                                        } else {
                                            echo 'Non attribué';
                                        }
                                    }
                                ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $t['statut'] === 'termine' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($t['statut']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Script pour la confirmation de suppression -->
<script>
    // Les confirmations sont déjà dans les formulaires
</script>
</body>
</html>