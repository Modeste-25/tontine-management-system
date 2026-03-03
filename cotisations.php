<?php
require_once 'config.php';
require_login();

$user = get_logged_user();
$is_admin = (isset($user['type_utilisateur']) && $user['type_utilisateur'] === 'admin');
$is_representant = (isset($user['type_utilisateur']) && $user['type_utilisateur'] === 'representant');
$is_membre = (isset($user['type_utilisateur']) && $user['type_utilisateur'] === 'membre');

// --- Traitement des actions ---
if (isset($_POST['enregistrer_paiement']) && ($is_admin || $is_representant)) {
    $stmt = $pdo->prepare("
        INSERT INTO cotisations (tontine_id, membre_id, montant, date_echeance, statut)
        VALUES (?, ?, ?, ?, 'en_attente')
    ");
    $stmt->execute([
        $_POST['tontine_id'],
        $_POST['membre_id'],
        $_POST['montant'],
        $_POST['date_echeance']
    ]);
    header("Location: cotisations.php?success=1");
    exit();
}

if (isset($_POST['valider_paiement']) && ($is_admin || $is_representant)) {
    $stmt = $pdo->prepare("
        UPDATE cotisations
        SET statut = 'payee', date_paiement = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$_POST['cotisation_id']]);
    header("Location: cotisations.php?success=2");
    exit();
}

if (isset($_POST['modifier_cotisation']) && ($is_admin || $is_representant)) {
    $stmt = $pdo->prepare("
        UPDATE cotisations
        SET montant = ?, date_echeance = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $_POST['montant'],
        $_POST['date_echeance'],
        $_POST['cotisation_id']
    ]);
    header("Location: cotisations.php?success=3");
    exit();
}

if (isset($_POST['supprimer_cotisation']) && ($is_admin || $is_representant)) {
    $stmt = $pdo->prepare("DELETE FROM cotisations WHERE id = ?");
    $stmt->execute([$_POST['cotisation_id']]);
    header("Location: cotisations.php?success=4");
    exit();
}

// --- Récupération des cotisations selon le rôle ---
if ($is_admin) {
    // Admin voit toutes les cotisations
    $stmt = $pdo->query("
        SELECT c.*, t.nom AS tontine_nom, u.prenom, u.nom
        FROM cotisations c
        JOIN tontines t ON c.tontine_id = t.id
        JOIN utilisateurs u ON c.membre_id = u.id
        ORDER BY c.date_echeance DESC
    ");
    $cotisations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($is_representant) {
    // Représentant voit les cotisations de sa tontine
    // Préparer la requête
$stmt = $pdo->prepare("SELECT id, nom FROM tontines WHERE representant_id = ?");

// Exécuter avec le paramètre
$stmt->execute([$user['id']]);

// Récupérer le résultat
$tontine = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tontine) {
        $stmt = $pdo->prepare("
            SELECT c.*, t.nom AS tontine_nom, u.prenom, u.nom
            FROM cotisations c
            JOIN tontines t ON c.tontine_id = t.id
            JOIN utilisateurs u ON c.membre_id = u.id
            WHERE c.tontine_id = ?
            ORDER BY c.date_echeance DESC
        ");
        $stmt->execute([$tontine['id']]);
        $cotisations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $cotisations = [];
    }
} else {
    // Membre voit ses propres cotisations
    $stmt = $pdo->prepare("
        SELECT c.*, t.nom AS tontine_nom
        FROM cotisations c
        JOIN tontines t ON c.tontine_id = t.id
        WHERE c.membre_id = ?
        ORDER BY c.date_echeance DESC
    ");
    
    $stmt->execute([$user['id']]);
    $cotisations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Pour les formulaires (admin/representant) ---
$tontines = [];
$membres_par_tontine = [];

if ($is_admin || $is_representant) {
    // Récupérer les tontines (toutes pour admin, une seule pour représentant)
    if ($is_admin) {
        $tontines = $pdo->query("SELECT id, nom FROM tontines WHERE statut = 'active'")->fetchAll(PDO::FETCH_ASSOC);
    } else {
       // Préparer la requête
$stmt = $pdo->prepare("SELECT id, nom FROM tontines WHERE representant_id = ?");

// Exécuter avec le paramètre
$stmt->execute([$user['id']]);

// Récupérer le résultat
$tontine = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tontine) {
            $tontines = [$tontine];
        }
    }

    // Récupérer les membres actifs pour chaque tontine
    foreach ($tontines as $t) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.prenom, u.nom
            FROM utilisateurs u
            JOIN membres_tontines mt ON u.id = mt.membre_id
            WHERE mt.tontine_id = ? AND mt.statut = 'actif'
            ORDER BY u.nom, u.prenom
        ");
        $stmt->execute([$t['id']]);
        $membres_par_tontine[$t['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Cotisations</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
        }
        .modal-content h3 { margin-bottom: 20px; }
        .modal-content form input,
        .modal-content form select {
            width: 100%;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php
        // Inclusion de la sidebar appropriée
        if ($is_admin) {
            include 'sidebar_admin.php';
        } elseif ($is_representant) {
            include 'sidebar_representant.php';
        } else {
            include 'sidebar_membres.php';
        }
        ?>

        <div class="main-content">
            <?php include 'topbar.php'; ?>

            <div class="content-area">
                <div class="section active">
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success">
                            <?php
                            $messages = [
                                1 => "Cotisation ajoutée avec succès.",
                                2 => "Paiement validé avec succès.",
                                3 => "Cotisation modifiée avec succès.",
                                4 => "Cotisation supprimée avec succès."
                            ];
                            echo $messages[$_GET['success']] ?? '';
                            ?>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Suivi des Cotisations</h2>
                            <?php if ($is_admin || $is_representant): ?>
                                <button onclick="document.getElementById('modalAjouter').style.display='block'" class="btn btn-primary">
                                    Ajouter une cotisation
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Tableau des cotisations -->
                        <div style="max-height: 600px; overflow-y: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Échéance</th>
                                        <th>Membre</th>
                                        <th>Tontine</th>
                                        <th>Montant</th>
                                        <th>Date paiement</th>
                                        <th>Statut</th>
                                        <?php if ($is_admin || $is_representant): ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cotisations as $c): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($c['date_echeance'])) ?></td>
                                            <td><?= htmlspecialchars($c['prenom'] ?? $user['prenom'] . ' ' . $c['nom'] ?? $user['nom']) ?></td>
                                            <td><?= htmlspecialchars($c['tontine_nom']) ?></td>
                                            <td><?= number_format($c['montant'], 0, ',', ' ') ?> FCFA</td>
                                            <td><?= $c['date_paiement'] ? date('d/m/Y', strtotime($c['date_paiement'])) : '--' ?></td>
                                            <td>
                                                <?php
                                                $badge = '';
                                                $label = '';
                                                switch ($c['statut']) {
                                                    case 'payee':
                                                        $badge = 'badge-success';
                                                        $label = 'Payée';
                                                        break;
                                                    case 'en_retard':
                                                        $badge = 'badge-danger';
                                                        $label = 'En retard';
                                                        break;
                                                    default:
                                                        $badge = 'badge-warning';
                                                        $label = 'En attente';
                                                }
                                                ?>
                                                <span class="badge <?= $badge ?>"><?= $label ?></span>
                                            </td>
                                            <?php if ($is_admin || $is_representant): ?>
                                                <td>
                                                    <?php if ($c['statut'] === 'en_attente'): ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="cotisation_id" value="<?= $c['id'] ?>">
                                                            <button type="submit" name="valider_paiement" class="btn btn-sm btn-success">Valider</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <button type="button"
                                                        class="btn btn-sm btn-primary"
                                                        onclick="ouvrirModalModifier(<?= $c['id'] ?>, '<?= $c['montant'] ?>', '<?= $c['date_echeance'] ?>')">
                                                        Modifier
                                                    </button>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cette cotisation ?');">
                                                        <input type="hidden" name="cotisation_id" value="<?= $c['id'] ?>">
                                                        <button type="submit" name="supprimer_cotisation" class="btn btn-sm btn-danger">Supprimer</button>
                                                    </form>
                                                </td>
                                            <?php endif; ?>
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

    <!-- Modal Ajouter une cotisation -->
    <?php if ($is_admin || $is_representant): ?>
    <div id="modalAjouter" class="modal">
        <div class="modal-content">
            <h3>Ajouter une cotisation</h3>
            <form method="POST">
                <select name="tontine_id" id="tontine_id" required onchange="chargerMembres(this.value)">
                    <option value="">Choisir une tontine</option>
                    <?php foreach ($tontines as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nom']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="membre_id" id="membre_id" required>
                    <option value="">Choisir un membre</option>
                </select>

                <input type="number" name="montant" placeholder="Montant" required>
                <input type="date" name="date_echeance" required>

                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" name="enregistrer_paiement" class="btn btn-primary">Enregistrer</button>
                    <button type="button" onclick="document.getElementById('modalAjouter').style.display='none'" class="btn">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Modifier une cotisation -->
    <div id="modalModifier" class="modal">
        <div class="modal-content">
            <h3>Modifier la cotisation</h3>
            <form method="POST">
                <input type="hidden" name="cotisation_id" id="edit_id">
                <input type="number" name="montant" id="edit_montant" required>
                <input type="date" name="date_echeance" id="edit_date" required>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" name="modifier_cotisation" class="btn btn-primary">Enregistrer</button>
                    <button type="button" onclick="document.getElementById('modalModifier').style.display='none'" class="btn">Annuler</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Données des membres par tontine (générées par PHP)
        var membresData = <?= json_encode($membres_par_tontine) ?>;

        function chargerMembres(tontineId) {
            var select = document.getElementById('membre_id');
            select.innerHTML = '<option value="">Choisir un membre</option>';
            if (tontineId && membresData[tontineId]) {
                membresData[tontineId].forEach(function(m) {
                    var option = document.createElement('option');
                    option.value = m.id;
                    option.textContent = m.prenom + ' ' + m.nom;
                    select.appendChild(option);
                });
            }
        }

        function ouvrirModalModifier(id, montant, date) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_montant').value = montant;
            document.getElementById('edit_date').value = date;
            document.getElementById('modalModifier').style.display = 'block';
        }

        // Fermer les modals en cliquant à l'extérieur
        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>