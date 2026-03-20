<?php
require_once 'config.php';
require_login();

$user = get_logged_user();

$is_admin = ($user && $user['type_utilisateur'] === 'admin');
$is_representant = ($user && $user['type_utilisateur'] === 'representant');
$is_membre = ($user && $user['type_utilisateur'] === 'membre');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ===== DEMANDE DE PRÊT (MEMBRE) ===== */
    if (isset($_POST['demander_pret']) && $is_membre) {

        $tontine_id = $_POST['tontine_id'];
        $montant = $_POST['montant'];
        $taux_interet = $_POST['taux_interet'] ?? 5;
        $duree_mois = $_POST['duree_mois'];
        $date_debut = $_POST['date_debut'];
        $motif = trim($_POST['motif']);

        $date_echeance = date('Y-m-d', strtotime("+$duree_mois months", strtotime($date_debut)));

        $montant_interet = $montant * $taux_interet / 100;
        $montant_total = $montant + $montant_interet;

        $stmt = $pdo->prepare("
            INSERT INTO prets
            (tontine_id, membre_id, montant, taux_interet,
             montant_interet, montant_total, date_echeance, motif, statut)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'en_attente')
        ");

        $stmt->execute([
            $tontine_id,
            $user['id'],
            $montant,
            $taux_interet,
            $montant_interet,
            $montant_total,
            $date_echeance,
            $motif
        ]);

        log_action('Demande prêt', "Demande de prêt de $montant FCFA");

        header('Location: prets.php?success=1');
        exit();
    }


    /* ===== CRÉATION DIRECTE (ADMIN / REPRESENTANT) ===== */
    if (isset($_POST['creer_pret']) && ($is_admin || $is_representant)) {

        $tontine_id = $_POST['tontine_id'];
        $membre_id = $_POST['membre_id'];
        $montant = $_POST['montant'];
        $taux_interet = $_POST['taux_interet'] ?? 5;
        $duree_mois = $_POST['duree_mois'];
        $date_debut = $_POST['date_debut'];
        $motif = trim($_POST['motif']);
        $statut = $_POST['statut'] ?? 'approuve';

        $date_echeance = date('Y-m-d', strtotime("+$duree_mois months", strtotime($date_debut)));

        $montant_interet = $montant * $taux_interet / 100;
        $montant_total = $montant + $montant_interet;

        $stmt = $pdo->prepare("
            INSERT INTO prets
            (tontine_id, membre_id, montant, taux_interet,
             montant_interet, montant_total, date_echeance,
             motif, statut, date_approbation, approbateur_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");

        $stmt->execute([
            $tontine_id,
            $membre_id,
            $montant,
            $taux_interet,
            $montant_interet,
            $montant_total,
            $date_echeance,
            $motif,
            $statut,
            $user['id']
        ]);

        add_notification(
            $membre_id,
            'Nouveau prêt',
            "Un prêt de $montant FCFA vous a été accordé."
        );

        log_action('Création prêt', "Prêt créé pour membre $membre_id");

        header('Location: prets.php?success=5');
        exit();
    }


    /* ===== APPROUVER ===== */
    if (isset($_POST['approuver_pret']) && ($is_admin || $is_representant)) {

        $pret_id = $_POST['pret_id'];

        $stmt = $pdo->prepare("
            UPDATE prets
            SET statut='approuve',
                date_approbation=NOW(),
                approbateur_id=?
            WHERE id=?
        ");
        $stmt->execute([$user['id'], $pret_id]);

        $stmtPret = $pdo->prepare("SELECT membre_id, montant FROM prets WHERE id=?");
        $stmtPret->execute([$pret_id]);
        $pret = $stmtPret->fetch(PDO::FETCH_ASSOC);

        if ($pret) {
            add_notification(
                $pret['membre_id'],
                'Prêt approuvé',
                "Votre prêt de {$pret['montant']} FCFA a été approuvé."
            );
        }

        header('Location: prets.php?success=2');
        exit();
    }


    /* ===== REFUSER ===== */
    if (isset($_POST['refuser_pret']) && ($is_admin || $is_representant)) {

        $pret_id = $_POST['pret_id'];

        $stmt = $pdo->prepare("UPDATE prets SET statut='refuse' WHERE id=?");
        $stmt->execute([$pret_id]);

        $stmtPret = $pdo->prepare("SELECT membre_id, montant FROM prets WHERE id=?");
        $stmtPret->execute([$pret_id]);
        $pret = $stmtPret->fetch(PDO::FETCH_ASSOC);

        if ($pret) {
            add_notification(
                $pret['membre_id'],
                'Prêt refusé',
                "Votre demande de prêt de {$pret['montant']} FCFA a été refusée."
            );
        }

        header('Location: prets.php?success=3');
        exit();
    }


    /* ===== REMBOURSEMENT ===== */
    if (isset($_POST['rembourser']) && $is_membre) {

        $pret_id = $_POST['pret_id'];
        $montant = $_POST['montant'];

        $stmt = $pdo->prepare(
            "INSERT INTO remboursements (pret_id, montant) VALUES (?, ?)"
        );
        $stmt->execute([$pret_id, $montant]);

        $stmtPret = $pdo->prepare("SELECT montant_total FROM prets WHERE id=?");
        $stmtPret->execute([$pret_id]);
        $pret = $stmtPret->fetch(PDO::FETCH_ASSOC);

        $stmtTotal = $pdo->prepare(
            "SELECT SUM(montant) FROM remboursements WHERE pret_id=?"
        );
        $stmtTotal->execute([$pret_id]);
        $total_rembourse = $stmtTotal->fetchColumn() ?? 0;

        if ($pret && $total_rembourse >= $pret['montant_total']) {
            $pdo->prepare("UPDATE prets SET statut='rembourse' WHERE id=?")
                ->execute([$pret_id]);
        }

        header('Location: prets.php?success=4');
        exit();
    }
}

if ($is_admin) {

    $prets = $pdo->query("
        SELECT p.*, u.prenom, u.nom, t.nom AS tontine_nom
        FROM prets p
        JOIN utilisateurs u ON p.membre_id = u.id
        JOIN tontines t ON p.tontine_id = t.id
        ORDER BY p.date_demande DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $tontines = $pdo->query("
        SELECT id, nom FROM tontines WHERE statut='active'
    ")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($is_representant) {

    $stmtT = $pdo->prepare(
        "SELECT id, nom FROM tontines WHERE representant_id=?"
    );
    $stmtT->execute([$user['id']]);
    $tontine = $stmtT->fetch(PDO::FETCH_ASSOC);

    if ($tontine) {

        $stmtPrets = $pdo->prepare("
            SELECT p.*, u.prenom, u.nom, t.nom AS tontine_nom
            FROM prets p
            JOIN utilisateurs u ON p.membre_id=u.id
            JOIN tontines t ON p.tontine_id=t.id
            WHERE p.tontine_id=?
            ORDER BY p.date_demande DESC
        ");
        $stmtPrets->execute([$tontine['id']]);
        $prets = $stmtPrets->fetchAll(PDO::FETCH_ASSOC);

        $tontines = [$tontine];
    } else {
        $prets = [];
        $tontines = [];
    }
} else {

    $stmtPrets = $pdo->prepare("
        SELECT p.*, t.nom AS tontine_nom
        FROM prets p
        JOIN tontines t ON p.tontine_id=t.id
        WHERE p.membre_id=?
        ORDER BY p.date_demande DESC
    ");
    $stmtPrets->execute([$user['id']]);
    $prets = $stmtPrets->fetchAll(PDO::FETCH_ASSOC);

    $stmtT = $pdo->prepare("
        SELECT t.id, t.nom
        FROM tontines t
        JOIN membres_tontines mt ON t.id=mt.tontine_id
        WHERE mt.membre_id=?
        AND mt.statut='actif'
        AND t.statut='active'
    ");
    $stmtT->execute([$user['id']]);
    $tontines_membre = $stmtT->fetchAll(PDO::FETCH_ASSOC);
}


/* ===== MEMBRES PAR TONTINE ===== */
$membres_par_tontine = [];

if ($is_admin || $is_representant) {
    foreach ($tontines as $t) {

        $stmtM = $pdo->prepare("
            SELECT u.id, u.prenom, u.nom
            FROM utilisateurs u
            JOIN membres_tontines mt ON u.id=mt.membre_id
            WHERE mt.tontine_id=? AND mt.statut='actif'
            ORDER BY u.nom, u.prenom
        ");

        $stmtM->execute([$t['id']]);

        $membres_par_tontine[$t['id']] =
            $stmtM->fetchAll(PDO::FETCH_ASSOC);
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
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .preview {
            background: #f0f9ff;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
            font-size: 1.1em;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <?php
            if ($is_admin) include 'sidebar_admin.php';
            elseif ($is_representant) include 'sidebar_representant.php';
            else include 'sidebar_membre.php';
            ?>
        </div>

        <div class="main-content">
            <?php include 'topbar.php'; ?>

            <div class="content-area">
                <div class="section active">
                    <!-- Messages flash -->
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success">
                            <?php
                            $messages = [
                                1 => "Demande de prêt envoyée avec succès.",
                                2 => "Prêt approuvé.",
                                3 => "Prêt refusé.",
                                4 => "Remboursement enregistré.",
                                5 => "Prêt créé avec succès."
                            ];
                            echo $messages[$_GET['success']] ?? '';
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Formulaire de création de prêt (admin/representant) -->
                    <?php if ($is_admin || $is_representant): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h2 class="card-title">Nouveau Prêt</h2>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="formCreerPret">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Tontine</label>
                                                <select name="tontine_id" id="tontine_id" required onchange="chargerMembresPret(this.value)">
                                                    <option value="">Sélectionner une tontine</option>
                                                    <?php foreach ($tontines as $t): ?>
                                                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nom']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Membre</label>
                                                <select name="membre_id" id="membre_id" required>
                                                    <option value="">Sélectionner un membre</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Montant (FCFA)</label>
                                                <input type="number" name="montant" id="montant" required min="1000" step="1000" oninput="calculerPret()">
                                            </div>
                                            <div class="form-group">
                                                <label>Taux d'intérêt (%)</label>
                                                <input type="number" name="taux_interet" id="taux" value="5" min="0" step="0.1" oninput="calculerPret()">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Durée (mois)</label>
                                                <input type="number" name="duree_mois" id="duree" required min="1" oninput="calculerPret()">
                                            </div>
                                            <div class="form-group">
                                                <label>Date de début</label>
                                                <input type="date" name="date_debut" id="date_debut" required onchange="calculerPret()">
                                            </div>
                                            <div class="form-group">
                                                <label>Objet du prêt</label>
                                                <textarea name="motif" rows="3" placeholder="Raison du prêt"></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label>Statut initial</label>
                                                <select name="statut">
                                                    <option value="en_cours">En cours</option>
                                                    <option value="approuve">Approuvé</option>
                                                    <option value="refuse">Refusé</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="preview" id="preview">
                                        Montant total à rembourser : <span id="total">0</span> FCFA<br>
                                        Date d'échéance : <span id="echeance">--</span>
                                    </div>
                                    <button type="submit" name="creer_pret" class="btn btn-primary">Créer le prêt</button>
                                    <button type="button" class="btn btn-secondary" onclick="calculerPret()">Calculer</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Liste des prêts -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Gestion des Prêts</h2>
                            <?php if ($is_membre && !empty($tontines_membre)): ?>
                                <button onclick="document.getElementById('modalDemander').style.display='block'" class="btn btn-primary">
                                    Demander un prêt
                                </button>
                            <?php endif; ?>
                        </div>

                        <div style="max-height: 600px; overflow-y: auto;">
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
                                    <?php foreach ($prets as $p): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($p['date_demande'])) ?></td>
                                            <td><?= htmlspecialchars($p['prenom'] ?? $user['prenom'] . ' ' . ($p['nom'] ?? $user['nom'])) ?></td>
                                            <td><?= htmlspecialchars($p['tontine_nom']) ?></td>
                                            <td><?= number_format($p['montant'], 0, ',', ' ') ?> FCFA</td>
                                            <td><?= $p['taux_interet'] ?>% (<?= number_format($p['montant_interet'], 0, ',', ' ') ?>)</td>
                                            <td><?= number_format($p['montant_total'], 0, ',', ' ') ?> FCFA</td>
                                            <td><?= date('d/m/Y', strtotime($p['date_echeance'])) ?></td>
                                            <td>
                                                <?php
                                                $badge = '';
                                                switch ($p['statut']) {
                                                    case 'en_attente':
                                                        $badge = 'badge-warning';
                                                        break;
                                                    case 'approuve':
                                                        $badge = 'badge-success';
                                                        break;
                                                    case 'refuse':
                                                        $badge = 'badge-danger';
                                                        break;
                                                    case 'rembourse':
                                                        $badge = 'badge-info';
                                                        break;
                                                    case 'en_retard':
                                                        $badge = 'badge-danger';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?= $badge ?>"><?= ucfirst($p['statut']) ?></span>
                                            </td>
                                            <td>
                                                <?php if ($p['statut'] === 'en_attente' && ($is_admin || $is_representant)): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="pret_id" value="<?= $p['id'] ?>">
                                                        <button type="submit" name="approuver_pret" class="btn btn-sm btn-success">Approuver</button>
                                                        <button type="submit" name="refuser_pret" class="btn btn-sm btn-danger">Refuser</button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if ($p['statut'] === 'approuve' && $is_membre && $p['membre_id'] == $user['id']): ?>
                                                    <a href="?rembourser=<?= $p['id'] ?>" class="btn btn-sm btn-primary">Rembourser</a>
                                                <?php endif; ?>

                                                <?php if ($p['statut'] === 'en_retard' && ($is_admin || $is_representant)): ?>
                                                    <a href="sanctions.php?action=appliquer&membre_id=<?= $p['membre_id'] ?>" class="btn btn-sm btn-warning">Sanctionner</a>
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

    <!-- Modal Demander un prêt (pour membre) -->
    <?php if ($is_membre): ?>
        <div id="modalDemander" class="modal">
            <div class="modal-content">
                <h3>Demander un prêt</h3>
                <form method="POST" id="formDemandePret">
                    <div class="form-group">
                        <label>Tontine</label>
                        <select name="tontine_id" required>
                            <option value="">Sélectionner une tontine</option>
                            <?php foreach ($tontines_membre as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Montant (FCFA)</label>
                        <input type="number" name="montant" id="montant_demande" required min="1000" oninput="calculerDemande()">
                    </div>
                    <div class="form-group">
                        <label>Taux d'intérêt (%)</label>
                        <input type="number" name="taux_interet" id="taux_demande" value="5" min="0" step="0.1" oninput="calculerDemande()">
                    </div>
                    <div class="form-group">
                        <label>Durée (mois)</label>
                        <input type="number" name="duree_mois" id="duree_demande" required min="1" oninput="calculerDemande()">
                    </div>
                    <div class="form-group">
                        <label>Date de début</label>
                        <input type="date" name="date_debut" id="date_debut_demande" required onchange="calculerDemande()">
                    </div>
                    <div class="form-group">
                        <label>Objet du prêt</label>
                        <textarea name="motif" rows="3" placeholder="Raison du prêt"></textarea>
                    </div>
                    <div class="preview" id="preview_demande">
                        Montant total : <span id="total_demande">0</span> FCFA<br>
                        Échéance : <span id="echeance_demande">--</span>
                    </div>
                    <button type="submit" name="demander_pret" class="btn btn-primary">Envoyer la demande</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalDemander').style.display='none'">Annuler</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Données pour le formulaire admin/représentant
        var membresData = <?= json_encode($membres_par_tontine) ?>;

        function chargerMembresPret(tontineId) {
            var select = document.getElementById('membre_id');
            select.innerHTML = '<option value="">Sélectionner un membre</option>';
            if (tontineId && membresData[tontineId]) {
                membresData[tontineId].forEach(function(m) {
                    var option = document.createElement('option');
                    option.value = m.id;
                    option.textContent = m.prenom + ' ' + m.nom;
                    select.appendChild(option);
                });
            }
        }

        // Calcul pour le formulaire admin
        function calculerPret() {
            var montant = parseFloat(document.getElementById('montant').value) || 0;
            var taux = parseFloat(document.getElementById('taux').value) || 0;
            var duree = parseInt(document.getElementById('duree').value) || 0;
            var dateDebut = document.getElementById('date_debut').value;

            var interet = montant * taux / 100;
            var total = montant + interet;
            document.getElementById('total').innerText = total.toLocaleString('fr-FR');

            if (dateDebut && duree > 0) {
                var date = new Date(dateDebut);
                date.setMonth(date.getMonth() + duree);
                var jour = ('0' + date.getDate()).slice(-2);
                var mois = ('0' + (date.getMonth() + 1)).slice(-2);
                var an = date.getFullYear();
                document.getElementById('echeance').innerText = jour + '/' + mois + '/' + an;
            } else {
                document.getElementById('echeance').innerText = '--';
            }
        }

        // Calcul pour la demande membre
        function calculerDemande() {
            var montant = parseFloat(document.getElementById('montant_demande').value) || 0;
            var taux = parseFloat(document.getElementById('taux_demande').value) || 0;
            var duree = parseInt(document.getElementById('duree_demande').value) || 0;
            var dateDebut = document.getElementById('date_debut_demande').value;

            var interet = montant * taux / 100;
            var total = montant + interet;
            document.getElementById('total_demande').innerText = total.toLocaleString('fr-FR');

            if (dateDebut && duree > 0) {
                var date = new Date(dateDebut);
                date.setMonth(date.getMonth() + duree);
                var jour = ('0' + date.getDate()).slice(-2);
                var mois = ('0' + (date.getMonth() + 1)).slice(-2);
                var an = date.getFullYear();
                document.getElementById('echeance_demande').innerText = jour + '/' + mois + '/' + an;
            } else {
                document.getElementById('echeance_demande').innerText = '--';
            }
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