<?php
require_once 'TCPDF-main/tcpdf.php';
require_once 'config.php';
require_login();
check_user_type('admin'); // Seul admin peut exporter

// Vérifier le type d'export
if (!isset($_GET['type'])) {
    die("Type d'export non spécifié.");
}

$type = $_GET['type'];

// Créer un nouveau PDF
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Afriton');
$pdf->SetTitle('Export ' . ucfirst($type));
$pdf->SetHeaderData('', 0, 'Afriton', 'Export ' . ucfirst($type));
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
$pdf->AddPage();

// Initialiser le contenu HTML
$html = '<h2>Export ' . ucfirst($type) . '</h2>';

switch ($type) {
    case 'membres':
        $stmt = $pdo->query("SELECT id, nom, prenom, email, telephone, date_inscription, statut FROM utilisateurs WHERE type_utilisateur = 'membre' ORDER BY id");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html .= '<table border="1" cellpadding="4">
                    <tr style="background-color:#f2f2f2;">
                        <th>ID</th><th>Nom</th><th>Prénom</th><th>Email</th><th>Téléphone</th><th>Date inscription</th><th>Statut</th>
                    </tr>';
        foreach ($data as $row) {
            $html .= '<tr>
                        <td>'.$row['id'].'</td>
                        <td>'.$row['nom'].'</td>
                        <td>'.$row['prenom'].'</td>
                        <td>'.$row['email'].'</td>
                        <td>'.$row['telephone'].'</td>
                        <td>'.$row['date_inscription'].'</td>
                        <td>'.$row['statut'].'</td>
                      </tr>';
        }
        $html .= '</table>';
        break;

    case 'tontines':
        $stmt = $pdo->query("SELECT t.id, t.nom, t.description, t.montant_cotisation, t.frequence, t.date_debut, t.statut, u.nom as representant_nom, u.prenom as representant_prenom FROM tontines t LEFT JOIN utilisateurs u ON t.representant_id = u.id ORDER BY t.id");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html .= '<table border="1" cellpadding="4">
                    <tr style="background-color:#f2f2f2;">
                        <th>ID</th><th>Nom</th><th>Description</th><th>Montant cotisation</th><th>Fréquence</th><th>Date début</th><th>Statut</th><th>Représentant</th>
                    </tr>';
        foreach ($data as $row) {
            $rep = $row['representant_prenom'].' '.$row['representant_nom'];
            $html .= '<tr>
                        <td>'.$row['id'].'</td>
                        <td>'.$row['nom'].'</td>
                        <td>'.$row['description'].'</td>
                        <td>'.$row['montant_cotisation'].'</td>
                        <td>'.$row['frequence'].'</td>
                        <td>'.$row['date_debut'].'</td>
                        <td>'.$row['statut'].'</td>
                        <td>'.$rep.'</td>
                      </tr>';
        }
        $html .= '</table>';
        break;

    case 'cotisations':
        $stmt = $pdo->query("SELECT c.id, c.montant, c.date_paiement, c.date_echeance, c.statut, c.penalite, u.nom, u.prenom, t.nom as tontine_nom FROM cotisations c JOIN utilisateurs u ON c.membre_id = u.id JOIN tontines t ON c.tontine_id = t.id ORDER BY c.id");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html .= '<table border="1" cellpadding="4">
                    <tr style="background-color:#f2f2f2;">
                        <th>ID</th><th>Montant</th><th>Date paiement</th><th>Date échéance</th><th>Statut</th><th>Pénalité</th><th>Membre</th><th>Tontine</th>
                    </tr>';
        foreach ($data as $row) {
            $membre = $row['prenom'].' '.$row['nom'];
            $html .= '<tr>
                        <td>'.$row['id'].'</td>
                        <td>'.$row['montant'].'</td>
                        <td>'.$row['date_paiement'].'</td>
                        <td>'.$row['date_echeance'].'</td>
                        <td>'.$row['statut'].'</td>
                        <td>'.$row['penalite'].'</td>
                        <td>'.$membre.'</td>
                        <td>'.$row['tontine_nom'].'</td>
                      </tr>';
        }
        $html .= '</table>';
        break;

    case 'prets':
        $stmt = $pdo->query("SELECT p.id, p.montant, p.taux_interet, p.montant_interet, p.montant_total, p.date_demande, p.date_approbation, p.date_echeance, p.statut, u.nom, u.prenom, t.nom as tontine_nom FROM prets p JOIN utilisateurs u ON p.membre_id = u.id JOIN tontines t ON p.tontine_id = t.id ORDER BY p.id");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html .= '<table border="1" cellpadding="4">
                    <tr style="background-color:#f2f2f2;">
                        <th>ID</th><th>Montant</th><th>Taux intérêt</th><th>Intérêt</th><th>Total</th><th>Date demande</th><th>Date approbation</th><th>Date échéance</th><th>Statut</th><th>Membre</th><th>Tontine</th>
                    </tr>';
        foreach ($data as $row) {
            $membre = $row['prenom'].' '.$row['nom'];
            $html .= '<tr>
                        <td>'.$row['id'].'</td>
                        <td>'.$row['montant'].'</td>
                        <td>'.$row['taux_interet'].'</td>
                        <td>'.$row['montant_interet'].'</td>
                        <td>'.$row['montant_total'].'</td>
                        <td>'.$row['date_demande'].'</td>
                        <td>'.$row['date_approbation'].'</td>
                        <td>'.$row['date_echeance'].'</td>
                        <td>'.$row['statut'].'</td>
                        <td>'.$membre.'</td>
                        <td>'.$row['tontine_nom'].'</td>
                      </tr>';
        }
        $html .= '</table>';
        break;

    default:
        die("Type d'export inconnu.");
}

// Générer le PDF
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('export_' . $type . '_' . date('Y-m-d') . '.pdf', 'I');
?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Exportation de données</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <div class="dashboard-container">
            <?php include 'sidebar_admin.php'; ?>
            <div class="main-content">
                <?php include 'topbar.php'; ?>
                <div class="content-area">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Exporter des données</h2>
                        </div>
                        <div class="module-grid">
                            <a href="?type=membres" class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Exporter les membres</div>
                                <div class="module-desc">Télécharger la liste des membres au format CSV</div>
                            </a>
                            <a href="?type=tontines" class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Exporter les tontines</div>
                                <div class="module-desc">Télécharger la liste des tontines au format CSV</div>
                            </a>
                            <a href="?type=cotisations" class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Exporter les cotisations</div>
                                <div class="module-desc">Télécharger toutes les cotisations</div>
                            </a>
                            <a href="?type=prets" class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Exporter les prêts</div>
                                <div class="module-desc">Télécharger la liste des prêts</div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
 