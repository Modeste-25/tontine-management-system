<?php
require_once 'config.php';
require_login();
check_user_type('admin'); // Seul admin peut voir tous les rapports

$user = get_logged_user();

// Validation des paramètres GET
$allowed_types = ['cotisations','prets','membres','sanctions','tontines'];
$type = $_GET['type'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$tontine_id = $_GET['tontine_id'] ?? null;

if (isset($_GET['generate'])) {

    if (!in_array($type, $allowed_types)) die('Type de rapport invalide');
    if (!strtotime($date_debut) || !strtotime($date_fin)) die('Dates invalides');

    // Génération des données
    switch ($type) {
        case 'cotisations':
            $sql = "SELECT c.*, u.nom, u.prenom, t.nom as tontine_nom 
                    FROM cotisations c 
                    JOIN utilisateurs u ON c.membre_id = u.id 
                    JOIN tontines t ON c.tontine_id = t.id 
                    WHERE c.date_paiement BETWEEN ? AND ?";
            $params = [$date_debut, $date_fin];
            if ($tontine_id) { $sql .= " AND c.tontine_id = ?"; $params[] = $tontine_id; }
            $sql .= " ORDER BY c.date_paiement DESC";
            break;

        case 'prets':
            $sql = "SELECT p.*, u.nom, u.prenom, t.nom as tontine_nom 
                    FROM prets p 
                    JOIN utilisateurs u ON p.membre_id = u.id 
                    JOIN tontines t ON p.tontine_id = t.id 
                    WHERE p.date_demande BETWEEN ? AND ?";
            $params = [$date_debut, $date_fin];
            if ($tontine_id) { $sql .= " AND p.tontine_id = ?"; $params[] = $tontine_id; }
            $sql .= " ORDER BY p.date_demande DESC";
            break;

        case 'membres':
            $sql = "SELECT u.*, 
                        (SELECT COUNT(*) FROM cotisations WHERE membre_id = u.id AND statut = 'payee') as cotisations_payees,
                        (SELECT COUNT(*) FROM prets WHERE membre_id = u.id) as prets_total,
                        (SELECT COUNT(*) FROM sanctions WHERE membre_id = u.id) as sanctions_total
                    FROM utilisateurs u 
                    WHERE u.date_inscription BETWEEN ? AND ? 
                    ORDER BY u.date_inscription DESC";
            $params = [$date_debut, $date_fin];
            break;

        case 'sanctions':
            $sql = "SELECT s.*, u.nom, u.prenom, t.nom as tontine_nom, a.prenom as admin_prenom, a.nom as admin_nom
                    FROM sanctions s
                    JOIN utilisateurs u ON s.membre_id = u.id
                    JOIN tontines t ON s.tontine_id = t.id
                    LEFT JOIN utilisateurs a ON s.imposee_par = a.id
                    WHERE s.date_sanction BETWEEN ? AND ?
                    ORDER BY s.date_sanction DESC";
            $params = [$date_debut, $date_fin];
            if ($tontine_id) { $sql .= " AND s.tontine_id = ?"; $params[] = $tontine_id; }
            break;

        case 'tontines':
            $sql = "SELECT t.*, u.prenom, u.nom as representant_nom,
                        (SELECT COUNT(*) FROM membres_tontines WHERE tontine_id = t.id) as nb_membres,
                        (SELECT SUM(montant) FROM cotisations WHERE tontine_id = t.id AND statut = 'payee') as total_cotisations
                    FROM tontines t
                    LEFT JOIN utilisateurs u ON t.representant_id = u.id
                    WHERE t.date_creation BETWEEN ? AND ?
                    ORDER BY t.date_creation DESC";
            $params = [$date_debut, $date_fin];
            break;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Export Excel / CSV
    if (isset($_GET['format']) && $_GET['format'] === 'excel') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="rapport_'.$type.'_'.date('Y-m-d').'.csv"');

        $output = fopen('php://output', 'w');
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
        exit();
    }

    // Export PDF HTML
    if (isset($_GET['format']) && $_GET['format'] === 'pdf') {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Rapport <?php echo $type; ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #16a34a; }
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
            </style>
        </head>
        <body>
            <h1>Rapport <?php echo ucfirst($type); ?></h1>
            <p>Période du <?php echo date('d/m/Y', strtotime($date_debut)); ?> au <?php echo date('d/m/Y', strtotime($date_fin)); ?></p>
            <table>
                <thead>
                    <tr>
                        <?php if (!empty($data)): ?>
                        <?php foreach (array_keys($data[0]) as $header): ?>
                        <th><?php echo ucfirst(str_replace('_',' ',$header)); ?></th>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                        <td><?php echo htmlspecialchars($cell); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p>Généré le <?php echo date('d/m/Y H:i'); ?></p>
            <script>window.print();</script>
        </body>
        </html>
        <?php
        exit();
    }
}

// Récupérer toutes les tontines pour filtrage
$tontines_list = $pdo->query("SELECT id, nom FROM tontines WHERE statut='active'")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports et Statistiques</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="dashboard-container">
    <?php include 'sidebar_admin.php'; ?>

    <div class="main-content">
        <?php include 'topbar.php'; ?>

        <div class="content-area">
            <div class="section active">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Génération de Rapports</h2>
                    </div>

                    <form method="GET" style="margin-bottom: 30px;">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Type de rapport</label>
                                <select name="type" required>
                                    <option value="cotisations">Cotisations</option>
                                    <option value="prets">Prêts</option>
                                    <option value="membres">Membres</option>
                                    <option value="sanctions">Sanctions</option>
                                    <option value="tontines">Tontines</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Date début</label>
                                <input type="date" name="date_debut" required>
                            </div>

                            <div class="form-group">
                                <label>Date fin</label>
                                <input type="date" name="date_fin" required>
                            </div>

                            <div class="form-group">
                                <label>Filtrer par tontine (optionnel)</label>
                                <select name="tontine_id">
                                    <option value="">Toutes</option>
                                    <?php foreach ($tontines_list as $t): ?>
                                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nom']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Format d'export</label>
                                <select name="format">
                                    <option value="html">HTML</option>
                                    <option value="excel">Excel (CSV)</option>
                                    <option value="pdf">PDF</option>
                                </select>
                            </div>

                            <div class="form-group" style="align-self: flex-end;">
                                <button type="submit" name="generate" value="1" class="btn btn-primary">Générer le rapport</button>
                            </div>
                        </div>
                    </form>

                    <?php if (isset($data)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Rapport : <?php echo ucfirst($type); ?></h2>
                            <div>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['format' => 'excel'])); ?>" class="btn btn-success">Exporter Excel</a>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['format' => 'pdf'])); ?>" class="btn btn-danger">Exporter PDF</a>
                            </div>
                        </div>

                        <div style="max-height: 500px; overflow-y: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <?php if (!empty($data)): ?>
                                        <?php foreach (array_keys($data[0]) as $header): ?>
                                        <th><?php echo ucfirst(str_replace('_',' ',$header)); ?></th>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $cell): ?>
                                        <td><?php echo htmlspecialchars($cell); ?></td>
                                        <?php endforeach; ?>
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
</div>
</body>
</html>
