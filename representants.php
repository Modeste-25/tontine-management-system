<?php
require_once 'config.php';
require_login();
check_user_type('admin');

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ajouter_representant'])) {
        $nom = secure($_POST['nom']);
        $prenom = secure($_POST['prenom']);
        $email = secure($_POST['email']);
        $telephone = secure($_POST['telephone']);
        $mot_de_passe = password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO utilisateurs (nom, prenom, email, telephone, mot_de_passe, type_utilisateur) 
            VALUES (?, ?, ?, ?, ?, 'representant')
        ");
        $stmt->execute([$nom, $prenom, $email, $telephone, $mot_de_passe]);
        
        log_action('Ajout représentant', "Nouveau représentant: $prenom $nom");
        
        header('Location: representants.php?success=1');
        exit();
    }
    
    if (isset($_POST['assigner_tontine'])) {
        $representant_id = $_POST['representant_id'];
        $tontine_id = $_POST['tontine_id'];
        
        $stmt = $pdo->prepare("UPDATE tontines SET representant_id = ? WHERE id = ?");
        $stmt->execute([$representant_id, $tontine_id]);
        
        log_action('Assignation tontine', "Tontine $tontine_id assignée au représentant $representant_id");
        
        header('Location: representants.php?success=2');
        exit();
    }
}

// Récupérer tous les représentants
$representants = $pdo->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM tontines WHERE representant_id = u.id) as tontines_count,
           (SELECT GROUP_CONCAT(nom SEPARATOR ', ') FROM tontines WHERE representant_id = u.id) as tontines_noms
    FROM utilisateurs u 
    WHERE u.type_utilisateur = 'representant' 
    ORDER BY u.nom, u.prenom
")->fetchAll();

// Récupérer les tontines sans représentant
$tontines_sans_representant = $pdo->query("
    SELECT * FROM tontines 
    WHERE representant_id IS NULL OR representant_id = '' 
    AND statut = 'active'
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Représentants</title>
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
                            <h2 class="card-title">Gestion des Représentants</h2>
                            <button onclick="document.getElementById('modalAjouter').style.display='block'" class="btn btn-primary">
                                Ajouter un représentant
                            </button>
                        </div>
                        
                        <!-- Tableau des représentants -->
                        <div style="max-height: 500px; overflow-y: auto; margin-top: 20px;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nom & Prénom</th>
                                        <th>Email</th>
                                        <th>Téléphone</th>
                                        <th>Date inscription</th>
                                        <th>Tontines gérées</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($representants as $rep): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($rep['nom'] . ' ' . $rep['prenom']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($rep['email']); ?></td>
                                        <td><?php echo htmlspecialchars($rep['telephone']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($rep['date_inscription'])); ?></td>
                                        <td>
                                            <span class="badge badge-info"><?php echo $rep['tontines_count']; ?> tontine(s)</span>
                                            <?php if ($rep['tontines_noms']): ?>
                                            <br><small><?php echo htmlspecialchars($rep['tontines_noms']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($rep['statut'] === 'actif'): ?>
                                                <span class="badge badge-success">Actif</span>
                                            <?php elseif ($rep['statut'] === 'inactif'): ?>
                                                <span class="badge badge-warning">Inactif</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Suspendu</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button onclick="assignerTontine(<?php echo $rep['id']; ?>)" class="btn btn-sm btn-primary">Assigner tontine</button>
                                            <a href="membre_edit.php?action=edit&id=<?php echo $rep['id']; ?>" class="btn btn-sm">Éditer</a>
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
    
    <!-- Modal Ajouter représentant -->
    <div id="modalAjouter" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div style="background: white; margin: 5% auto; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Ajouter un représentant</h2>
                <button onclick="document.getElementById('modalAjouter').style.display='none'" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nom</label>
                        <input type="text" name="nom" required>
                    </div>
                    <div class="form-group">
                        <label>Prénom</label>
                        <input type="text" name="prenom" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="text" name="telephone" required>
                    </div>
                    <div class="form-group">
                        <label>Mot de passe</label>
                        <input type="password" name="mot_de_passe" required>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="ajouter_representant" class="btn btn-primary">Ajouter</button>
                    <button type="button" onclick="document.getElementById('modalAjouter').style.display='none'" class="btn">Annuler</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Assigner tontine -->
    <div id="modalAssigner" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div style="background: white; margin: 5% auto; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Assigner une tontine</h2>
                <button onclick="document.getElementById('modalAssigner').style.display='none'" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            
            <form method="POST" id="formAssigner">
                <input type="hidden" name="representant_id" id="representant_id">
                
                <div class="form-group">
                    <label>Sélectionner une tontine</label>
                    <select name="tontine_id" required>
                        <option value="">Sélectionner une tontine</option>
                        <?php foreach ($tontines_sans_representant as $tontine): ?>
                        <option value="<?php echo $tontine['id']; ?>">
                            <?php echo htmlspecialchars($tontine['nom']); ?> 
                            (<?php echo number_format($tontine['montant_cotisation'], 0, ',', ' '); ?> FCFA)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($tontines_sans_representant)): ?>
                    <p style="color: var(--warning); font-size: 0.9em; margin-top: 5px;">
                        Aucune tontine disponible sans représentant
                    </p>
                    <?php endif; ?>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="assigner_tontine" class="btn btn-primary" <?php echo empty($tontines_sans_representant) ? 'disabled' : ''; ?>>Assigner</button>
                    <button type="button" onclick="document.getElementById('modalAssigner').style.display='none'" class="btn">Annuler</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Gestion des modals
        function assignerTontine(representantId) {
            document.getElementById('representant_id').value = representantId;
            document.getElementById('modalAssigner').style.display = 'block';
        }
        
        // Fermer les modals en cliquant à l'extérieur
        window.onclick = function(event) {
            var modalAjouter = document.getElementById('modalAjouter');
            var modalAssigner = document.getElementById('modalAssigner');
            
            if (event.target == modalAjouter) {
                modalAjouter.style.display = "none";
            }
            if (event.target == modalAssigner) {
                modalAssigner.style.display = "none";
            }
        }
        
        // Afficher message de succès
        <?php if (isset($_GET['success'])): ?>
        alert('Opération effectuée avec succès !');
        <?php endif; ?>
    </script>
</body>
</html>