<?php
require_once 'config.php';
require_login();

$user = get_current_user();
$is_admin = (isset($user['type_utilisateur']) && $user['type_utilisateur'] === 'admin');
$is_representant = (isset($user['type_utilisateur']) && $user['type_utilisateur'] === 'representant');
$is_membre = (isset($user['type_utilisateur']) && $user['type_utilisateur'] === 'membre');

$id = $_GET['id'] ?? $user['id'];

// Vérifier les droits
if (!$is_admin) {
    if ($is_representant) {
        $stmt = $pdo->prepare("SELECT id FROM tontines WHERE representant_id = ?");
        $stmt->execute([$user['id']]);
        $tontine = $stmt->fetch();

        if ($tontine) {
            $stmt = $pdo->prepare("SELECT id FROM membres_tontines WHERE tontine_id = ? AND membre_id = ?");
            $stmt->execute([$tontine['id'], $id]);
            $check = $stmt->fetch();

            if (!$check) {
                header('Location: dashboard_representant.php');
                exit();
            }
        } else {
            header('Location: dashboard_representant.php');
            exit();
        }
    } elseif ($is_membre && $id != $user['id']) {
        header('Location: dashboard_membre.php');
        exit();
    }
}

// Récupérer les informations du membre
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$id]);
$membre = $stmt->fetch();

if (!$membre) {
    header('Location: ' . ($is_admin ? 'membres.php' : 'dashboard.php'));
    exit();
}

// Récupérer les tontines
$stmt = $pdo->prepare("
    SELECT t.*, mt.solde, mt.statut as statut_adhesion, mt.date_adhesion
    FROM tontines t
    JOIN membres_tontines mt ON t.id = mt.tontine_id
    WHERE mt.membre_id = ?
");
$stmt->execute([$id]);
$tontines = $stmt->fetchAll();

// Récupérer les cotisations
$stmt = $pdo->prepare("
    SELECT c.*, t.nom as tontine_nom
    FROM cotisations c
    JOIN tontines t ON c.tontine_id = t.id
    WHERE c.membre_id = ?
    ORDER BY c.date_echeance DESC
");
$stmt->execute([$id]);
$cotisations = $stmt->fetchAll();

// Récupérer les prêts
$stmt = $pdo->prepare("
    SELECT p.*, t.nom as tontine_nom
    FROM prets p
    JOIN tontines t ON p.tontine_id = t.id
    WHERE p.membre_id = ?
    ORDER BY p.date_demande DESC
");
$stmt->execute([$id]);
$prets = $stmt->fetchAll();

// Récupérer les sanctions
$stmt = $pdo->prepare("
    SELECT s.*, t.nom as tontine_nom
    FROM sanctions s
    JOIN tontines t ON s.tontine_id = t.id
    WHERE s.membre_id = ?
    ORDER BY s.date_sanction DESC
");
$stmt->execute([$id]);
$sanctions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail du membre</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="style.css">
    <style>
        .tabs { display: flex; border-bottom: 2px solid #e2e8f0; margin-bottom: 20px; cursor: pointer; }
        .tab { padding: 10px 20px; border-bottom: 2px solid transparent; }
        .tab.active { border-bottom-color: #16a34a; color: #16a34a; font-weight: bold; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2">
            <?php
            if ($is_admin) include 'sidebar_admin.php';
            elseif ($is_representant) include 'sidebar_representant.php';
            else include 'sidebar_membres.php';
            ?>
        </div>
        <div class="col-md-10">
            <?php include 'topbar.php'; ?>

            <div class="p-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h3>Profil de <?php echo htmlspecialchars($membre['prenom'] . ' ' . $membre['nom']); ?></h3>
                        <a href="membre_edit.php?action=edit&id=<?php echo $membre['id']; ?>" class="btn btn-primary btn-sm mb-3">Modifier</a>
                        <div class="row">
                            <div class="col-md-6"><strong>Nom :</strong> <?php echo htmlspecialchars($membre['nom']); ?></div>
                            <div class="col-md-6"><strong>Prénom :</strong> <?php echo htmlspecialchars($membre['prenom']); ?></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6"><strong>Email :</strong> <?php echo htmlspecialchars($membre['email']); ?></div>
                            <div class="col-md-6"><strong>Téléphone :</strong> <?php echo htmlspecialchars($membre['telephone']); ?></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6"><strong>Date d'inscription :</strong> <?php echo date('d/m/Y H:i', strtotime($membre['date_inscription'])); ?></div>
                            <div class="col-md-6"><strong>Statut :</strong> 
                                <?php
                                $badge_class = 'secondary';
                                if ($membre['statut'] === 'actif') $badge_class = 'success';
                                elseif ($membre['statut'] === 'inactif') $badge_class = 'warning';
                                elseif ($membre['statut'] === 'suspendu') $badge_class = 'danger';
                                ?>
                                <span class="badge bg-<?php echo $badge_class; ?>"><?php echo ucfirst($membre['statut']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="card">
                    <div class="card-body">
                        <div class="tabs mb-3">
                            <div class="tab active" onclick="showTab('tontines', this)">Tontines</div>
                            <div class="tab" onclick="showTab('cotisations', this)">Cotisations</div>
                            <div class="tab" onclick="showTab('prets', this)">Prêts</div>
                            <div class="tab" onclick="showTab('sanctions', this)">Sanctions</div>
                        </div>

                        <div id="tontines" class="tab-content active">
                            <h5>Tontines du membre</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Tontine</th>
                                            <th>Date adhésion</th>
                                            <th>Solde</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tontines as $t): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($t['nom']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($t['date_adhesion'])); ?></td>
                                            <td><?php echo number_format($t['solde'], 0, ',', ' '); ?> FCFA</td>
                                            <td><span class="badge bg-<?php echo $t['statut_adhesion']=='actif'?'success':'warning'; ?>"><?php echo ucfirst($t['statut_adhesion']); ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if(empty($tontines)) echo '<tr><td colspan="4">Aucune tontine.</td></tr>'; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div id="cotisations" class="tab-content">
                            <h5>Historique des cotisations</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date échéance</th>
                                            <th>Tontine</th>
                                            <th>Montant</th>
                                            <th>Date paiement</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cotisations as $c): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($c['date_echeance'])); ?></td>
                                            <td><?php echo htmlspecialchars($c['tontine_nom']); ?></td>
                                            <td><?php echo number_format($c['montant'],0,',',' '); ?> FCFA</td>
                                            <td><?php echo $c['date_paiement']?date('d/m/Y', strtotime($c['date_paiement'])):'--'; ?></td>
                                            <td>
                                                <?php
                                                $stat_class='warning';
                                                if($c['statut']=='payee') $stat_class='success';
                                                elseif($c['statut']=='en_retard') $stat_class='danger';
                                                ?>
                                                <span class="badge bg-<?php echo $stat_class;?>"><?php echo ucfirst($c['statut']); ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if(empty($cotisations)) echo '<tr><td colspan="5">Aucune cotisation.</td></tr>'; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Prêts -->
                        <div id="prets" class="tab-content">
                            <h5>Prêts du membre</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date demande</th>
                                            <th>Tontine</th>
                                            <th>Montant</th>
                                            <th>Total dû</th>
                                            <th>Échéance</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($prets as $p): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($p['date_demande'])); ?></td>
                                            <td><?php echo htmlspecialchars($p['tontine_nom']); ?></td>
                                            <td><?php echo number_format($p['montant'],0,',',' '); ?> FCFA</td>
                                            <td><?php echo number_format($p['montant_total'],0,',',' '); ?> FCFA</td>
                                            <td><?php echo date('d/m/Y', strtotime($p['date_echeance'])); ?></td>
                                            <td>
                                                <?php
                                                $badge='secondary';
                                                switch($p['statut']){
                                                    case 'en_attente': $badge='warning'; break;
                                                    case 'approuve': $badge='success'; break;
                                                    case 'refuse': $badge='danger'; break;
                                                    case 'rembourse': $badge='info'; break;
                                                    case 'en_retard': $badge='danger'; break;
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $badge;?>"><?php echo ucfirst($p['statut']); ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if(empty($prets)) echo '<tr><td colspan="6">Aucun prêt.</td></tr>'; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Sanctions -->
                        <div id="sanctions" class="tab-content">
                            <h5>Sanctions reçues</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Tontine</th>
                                            <th>Motif</th>
                                            <th>Montant</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sanctions as $s): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($s['date_sanction'])); ?></td>
                                            <td><?php echo htmlspecialchars($s['tontine_nom']); ?></td>
                                            <td><?php echo htmlspecialchars($s['motif']); ?></td>
                                            <td><?php echo number_format($s['montant_penalite'],0,',',' '); ?> FCFA</td>
                                            <td>
                                                <span class="badge bg-<?php echo ($s['statut']=='active')?'danger':'success'; ?>">
                                                    <?php echo ucfirst($s['statut']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if(empty($sanctions)) echo '<tr><td colspan="5">Aucune sanction.</td></tr>'; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function showTab(tabId, element){
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    element.classList.add('active');
}
</script>
</body>
</html>
