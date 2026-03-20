<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion – Afriton</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: #FAF7F1;
            font-family: Arial, sans-serif;
            min-height: 100vh;
        }

        /* ── BARRE DU HAUT ── */
        .navbar {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 24px;
        }
        .navbar-brand {
            font-size: 1.3rem;
            font-weight: 700;
            color: #180F03;
            text-decoration: none;
        }
        .navbar-brand i { color: #BF8C2A; }

        /* ── TITRE PAGE ── */
        .page-title {
            text-align: center;
            padding: 50px 16px 10px;
        }
        .page-title h1 {
            font-size: 2rem;
            font-weight: 800;
            color: #180F03;
            margin-bottom: 8px;
        }
        .page-title p {
            color: #64748b;
            font-size: .95rem;
        }

        /* ── CARTES ── */
        .cards-container {
            display: flex;
            justify-content: center;
            gap: 24px;
            flex-wrap: wrap;
            padding: 36px 16px 40px;
        }

        .card-choix {
            background: white;
            border-radius: 16px;
            padding: 32px 28px;
            width: 100%;
            max-width: 360px;
            text-decoration: none;
            color: #180F03;
            display: block;
            border: 2px solid #e2e8f0;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card-choix:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 40px rgba(0,0,0,0.10);
        }

        /* Bordure dorée au hover pour membre */
        .card-membre:hover { border-color: #BF8C2A; }

        /* Bordure verte au hover pour représentant */
        .card-repres:hover { border-color: #18392B; }

        /* ── ICÔNE ── */
        .icone {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 20px;
        }
        .icone-or    { background: #FFF8EB; color: #BF8C2A; }
        .icone-verte { background: #EDFAF0; color: #18392B; }

        /* ── TITRE ET DESCRIPTION ── */
        .card-choix h4 {
            text-align: center;
            font-size: 1.3rem;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .card-choix p.desc {
            text-align: center;
            font-size: .87rem;
            color: #64748b;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        /* ── LISTE ── */
        .card-choix ul {
            list-style: none;
            padding: 0;
            margin-bottom: 28px;
        }
        .card-choix ul li {
            font-size: .86rem;
            color: #475569;
            padding: 7px 0;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 9px;
        }
        .card-choix ul li:last-child { border-bottom: none; }

        .check-or   { color: #BF8C2A; }
        .check-vert { color: #18392B; }

        /* ── BOUTONS ── */
        .btn-membre {
            display: block;
            width: 100%;
            padding: 13px;
            border-radius: 10px;
            font-weight: 700;
            font-size: .92rem;
            text-align: center;
            background: linear-gradient(135deg, #BF8C2A, #E8B94A);
            color: #180F03;
            border: none;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .btn-membre:hover { opacity: 0.88; color: #180F03; }

        .btn-repres {
            display: block;
            width: 100%;
            padding: 13px;
            border-radius: 10px;
            font-weight: 700;
            font-size: .92rem;
            text-align: center;
            background: linear-gradient(135deg, #18392B, #2d6a4f);
            color: white;
            border: none;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .btn-repres:hover { opacity: 0.88; color: white; }

        /* ── LIEN BAS DE PAGE ── */
        .lien-bas {
            text-align: center;
            padding-bottom: 40px;
            font-size: .88rem;
            color: #64748b;
            line-height: 2;
        }
        .lien-bas a {
            color: #BF8C2A;
            font-weight: 600;
            text-decoration: none;
        }
        .lien-bas a:hover { text-decoration: underline; }

        /* ── FOOTER ── */
        footer {
            background: #0B0904;
            color: rgba(255,255,255,0.3);
            text-align: center;
            padding: 16px;
            font-size: .82rem;
        }
    </style>
</head>
<body>

<nav class="navbar d-flex justify-content-between align-items-center">
    <a class="navbar-brand" href="index.php">
        <i class="bi bi-coin"></i> Afriton
    </a>
    <a href="index.php" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left"></i> Accueil
    </a>
</nav>

<div class="page-title">
    <h1>Choisissez votre espace</h1>
    <p>Sélectionnez le profil correspondant à votre rôle.</p>
</div>

<div class="cards-container">

    <a href="login_membre.php" class="card-choix card-membre">

        <div class="icone icone-or">
            <i class="bi bi-people-fill"></i>
        </div>

        <h4>Espace Membre</h4>
        <p class="desc">Suivez vos cotisations et consultez vos cycles de tontine.</p>

        <ul>
            <li><i class="bi bi-check-circle-fill check-or"></i> Suivi des cotisations</li>
            <li><i class="bi bi-check-circle-fill check-or"></i> Historique des cycles</li>
            <li><i class="bi bi-check-circle-fill check-or"></i> Réception des notifications</li>
        </ul>

        <span class="btn-membre">Connexion Membre</span>

    </a>
    <a href="login_representant.php" class="card-choix card-repres">

        <div class="icone icone-verte">
            <i class="bi bi-briefcase-fill"></i>
        </div>

        <h4>Espace Représentant</h4>
        <p class="desc">Gérez vos groupes, membres et cycles depuis votre tableau de bord.</p>

        <ul>
            <li><i class="bi bi-check-circle-fill check-vert"></i> Gestion des membres</li>
            <li><i class="bi bi-check-circle-fill check-vert"></i> Tableau de bord analytique</li>
            <li><i class="bi bi-check-circle-fill check-vert"></i> Génération de rapports</li>
        </ul>

        <span class="btn-repres">Connexion Représentant</span>

    </a>

</div>
<p class="lien-bas">
    Pas encore de compte ? <a href="register.php">Créer un compte</a><br>
    <i class="" style="color:#18392B;"></i>
    Connexion sécurisée
</p>
<footer>
    <p>&copy; <?= date('Y') ?> Afriton – Système de gestion de tontines</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>