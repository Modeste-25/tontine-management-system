<?php
require_once 'config.php';
require_login();
check_user_type('admin');

$message = '';

// Fonction secure
function secure($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Générer token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Erreur CSRF détectée !");
    }

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'param_') === 0) {
            $cle = substr($key, 6);
            $valeur = secure($value);

            $stmt = $pdo->prepare("
                INSERT INTO parametres (cle, valeur) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)
            ");
            $stmt->execute([$cle, $valeur]);
        }
    }

    $message = "Paramètres enregistrés avec succès.";
    log_action('Modification paramètres', "Paramètres système modifiés");
}

// Récupérer les paramètres
$stmt = $pdo->query("SELECT cle, valeur FROM parametres ORDER BY cle");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$params = [];
foreach ($rows as $row) {
    $params[$row['cle']] = $row['valeur'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres système</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<div class="container my-4">
    <h2 class="mb-4">Paramètres système</h2>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="mb-3">
                    <label class="form-label">Nom de l'application</label>
                    <input type="text" class="form-control" name="param_app_name" required
                           placeholder="Nom de l'application"
                           value="<?php echo $params['app_name'] ?? 'Gestion de Tontine'; ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Devise</label>
                    <input type="text" class="form-control" name="param_currency" required
                           placeholder="Devise"
                           value="<?php echo $params['currency'] ?? 'FCFA'; ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Pénalité pour retard (FCFA)</label>
                    <input type="number" class="form-control" name="param_penalite_retard" required min="0"
                           value="<?php echo $params['penalite_retard'] ?? '500'; ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Taux d'intérêt par défaut pour les prêts (%)</label>
                    <input type="number" class="form-control" step="0.01" min="0" max="100" name="param_taux_interet_pret" required
                           value="<?php echo $params['taux_interet_pret'] ?? '5'; ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Activer les notifications par email</label>
                    <select class="form-select" name="param_email_notifications">
                        <option value="1" <?php echo ($params['email_notifications'] ?? '1') == '1' ? 'selected' : ''; ?>>Oui</option>
                        <option value="0" <?php echo ($params['email_notifications'] ?? '1') == '0' ? 'selected' : ''; ?>>Non</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Enregistrer les paramètres</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
