<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'gestion_tontines');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// Fonction pour sécuriser les données envoyées par formulaire
if (!function_exists('secure')) {
    function secure($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}


function secure_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generateCodeMembre($pdo) {
    $prefix = "MBR";

    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM utilisateurs");
    $maxId = $stmt->fetchColumn();
    $nextId = $maxId + 1;

    return $prefix . str_pad($nextId, 5, "0", STR_PAD_LEFT);
}


function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_logged_user() {
    global $pdo;

    if (is_logged_in()) {
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }

    return null;
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: index.php');
        exit();
    }
}

function check_user_type($required_type) {
    $user = get_logged_user();

    if (!$user) {
        header('Location: index.php');
        exit();
    }

    if ($user['type_utilisateur'] !== $required_type) {
        redirect_after_login($user['type_utilisateur']);
    }
}

function add_notification($user_id, $titre, $message, $type = 'info') {
    global $pdo;

    $stmt = $pdo->prepare(
        "INSERT INTO notifications (utilisateur_id, titre, message, type)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$user_id, $titre, $message, $type]);
}

function log_action($action, $details = null) {
    global $pdo;

    $user_id = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    $stmt = $pdo->prepare(
        "INSERT INTO historiques (action, details, utilisateur_id, ip_address)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$action, $details, $user_id, $ip]);
}

function redirect_after_login($user_type) {
    switch ($user_type) {
        case 'admin':
            header('Location: dashboard.php');
            break;
        case 'representant':
            header('Location: dashboard_representant.php');
            break;
        case 'membre':
            header('Location: dashboard_membre.php');
            break;
        default:
            header('Location: index.php');
    }
    exit();
}


function isAdminLoggedIn() {
    return isset($_SESSION['user_id']) && get_logged_user()['type_utilisateur'] === 'admin';
}

function isRepresentantLoggedIn() {
    return isset($_SESSION['user_id']) && get_logged_user()['type_utilisateur'] === 'representant';
}


function isMembreLoggedIn() {
    return isset($_SESSION['user_id']) && get_logged_user()['type_utilisateur'] === 'membre';
}

?>