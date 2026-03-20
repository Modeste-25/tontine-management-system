<?php
require_once 'config.php';

$stmt = $pdo->query("
    SELECT t.*, u.prenom, u.nom AS representant_nom
    FROM tontines t
    LEFT JOIN utilisateurs u ON t.representant_id = u.id
    WHERE t.statut = 'active'
    ORDER BY t.date_debut DESC
");
$tontines = $stmt->fetchAll();

$heroSlides = [
    [
        'img' => 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?w=1800&q=85',
        'tag' => 'Votre tontine en ligne',
        'h1'  => 'La tontine qu\'on<br>connaît tous,<br><em>enfin digitale</em>',
        'sub' => "De Douala à Yaoundé, en passant par Bafoussam gérez votre groupe sans stylo ni carnet.",
    ],

      [
        'img' => 'media_file_59.jpg',
        'tag' => 'La tontine, c\'est nous',
        'h1'  => 'Une tradition vivante,<br>portée par<br><em>votre communauté</em>',
        'sub' => "Depuis toujours, nos mères et grands-mères se réunissent pour épargner ensemble. Aujourd'hui, cette solidarité continue avec les outils du numérique.",
    ],

    [
        'img' => 'https://images.unsplash.com/photo-1607863680198-23d4b2565df0?w=1800&q=85',
        'tag' => 'Le représentant décide',
        'h1'  => 'Vous choisissez<br>qui reçoit,<br><em>quand et combien</em>',
        'sub' => "C'est vous le représentant, c'est vous qui attribuez les tours. Afriton enregistre, notifie et garde tout en ordre.",
    ],
    [
        'img' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=1800&q=85',
        'tag' => 'Vos membres informés',
        'h1'  => 'Chaque membre sait<br><em>son tour,</em><br>sans que vous répétiez',
        'sub' => "Dès que vous attribuez un tour, le membre concerné reçoit une notification. Plus besoin d'appeler tout le monde.",
    ],
];

$covers = [
    'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=700&q=80',
    'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?w=700&q=80',
    'https://images.unsplash.com/photo-1521791136064-7986c2920216?w=700&q=80',
    'https://images.unsplash.com/photo-1607863680198-23d4b2565df0?w=700&q=80',
    'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=700&q=80',
    'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=700&q=80',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Afriton – Gestion de tontines</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;0,700;1,600&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>

:root {
    --cream:    #FAF7F1;
    --parchm:   #F2ECE1;
    --white:    #fff;
    --ink:      #180F03;
    --gold:     #BF8C2A;
    --gold-lt:  #E8B94A;
    --forest:   #18392B;
    --border:   rgba(24,15,3,.09);
    --shadow:   0 8px 40px rgba(24,15,3,.10);
    --shadow-h: 0 20px 60px rgba(24,15,3,.18);
    --radius:   18px;
    --font-h:   'Cormorant Garamond', Georgia, serif;
    --font-b:   'Outfit', sans-serif;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: var(--font-b); background: var(--cream); color: var(--ink); overflow-x: hidden; }

h1, h2, h3, h4, h5 { font-family: var(--font-h); }

::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: var(--cream); }
::-webkit-scrollbar-thumb { background: var(--gold); border-radius: 3px; }

.navbar {
    position: sticky;
    top: 0;
    z-index: 1000;
    background: rgba(250,247,241,.93);
    backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--border);
    padding: 0 !important;
}

.navbar .container { height: 64px; }

.navbar-brand {
    font-family: var(--font-h);
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--ink) !important;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 9px;
}

.brand-gem {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--gold), var(--gold-lt));
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: .95rem;
    box-shadow: 0 4px 14px rgba(191,140,42,.38);
}

.nav-link {
    font-size: .875rem;
    font-weight: 500;
    color: rgba(24,15,3,.70) !important;
    border-radius: 8px;
    padding: 6px 13px !important;
    transition: background .2s, color .2s;
}
.nav-link:hover { background: rgba(191,140,42,.09); color: var(--gold) !important; }

.btn-nav {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 20px;
    border-radius: 50px;
    font-weight: 600;
    font-size: .875rem;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all .2s;
}
.btn-nav.outline {
    background: transparent;
    color: var(--ink);
    border: 1.5px solid var(--border);
}
.btn-nav.outline:hover { border-color: var(--gold); color: var(--gold); }
.btn-nav.solid {
    background: var(--ink);
    color: #fff;
    box-shadow: 0 4px 14px rgba(24,15,3,.20);
}
.btn-nav.solid:hover { background: var(--gold); transform: translateY(-2px); color: #fff; }

.hero-wrap {
    position: relative;
    height: 100svh;
    min-height: 620px;
    overflow: hidden;
}

.hero-swiper { width: 100%; height: 100%; }

.hero-swiper .swiper-slide { position: relative; overflow: hidden; }

.hero-swiper .swiper-slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: scale(1.10);
    transition: transform 8s ease-out;
}
.hero-swiper .swiper-slide-active img { transform: scale(1); }

.hero-swiper .swiper-slide::after {
    content: '';
    position: absolute;
    inset: 0;
    z-index: 1;
    background:
        linear-gradient(105deg, rgba(24,15,3,.76) 0%, rgba(24,15,3,.26) 52%, transparent 100%),
        linear-gradient(0deg, rgba(24,15,3,.60) 0%, transparent 55%);
}

.hero-swiper .swiper-slide::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    z-index: 3;
    background: linear-gradient(180deg, transparent, var(--gold-lt) 25%, var(--gold) 75%, transparent);
}

.hero-cap {
    position: absolute;
    inset: 0;
    z-index: 2;
    display: flex;
    align-items: center;
    padding-bottom: 130px;
}

.hero-inner {
    opacity: 0;
    transform: translateY(40px);
    transition: opacity .9s .2s ease, transform .9s .2s ease;
}
.swiper-slide-active .hero-inner { opacity: 1; transform: none; }

.hero-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(191,140,42,.18);
    border: 1px solid rgba(232,185,74,.35);
    color: var(--gold-lt);
    border-radius: 50px;
    padding: 5px 16px;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: 1.8px;
    text-transform: uppercase;
    margin-bottom: 22px;
}

.hero-h1 {
    font-size: clamp(2.4rem, 6vw, 4.4rem);
    font-weight: 700;
    color: #fff;
    line-height: 1.07;
    margin-bottom: 20px;
}
.hero-h1 em { font-style: italic; color: var(--gold-lt); }

.hero-sub {
    font-size: 1.05rem;
    font-weight: 300;
    color: rgba(255,255,255,.72);
    line-height: 1.75;
    max-width: 500px;
    margin-bottom: 38px;
}

.hero-btns { display: flex; flex-wrap: wrap; gap: 14px; }

.hero-wrap .swiper-pagination {
    position: absolute !important;
    right: 28px !important;
    left: auto !important;
    top: 50% !important;
    bottom: auto !important;
    transform: translateY(-50%) !important;
    width: auto !important;
    display: flex !important;
    flex-direction: column;
    gap: 10px;
}
.hero-wrap .swiper-pagination-bullet {
    width: 5px;
    height: 22px !important;
    border-radius: 3px;
    background: rgba(255,255,255,.32);
    opacity: 1;
    margin: 0 !important;
    transition: height .35s, background .35s;
}
.hero-wrap .swiper-pagination-bullet-active {
    background: var(--gold-lt);
    height: 44px !important;
}

.hero-stats {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    z-index: 3;
    background: rgba(24,15,3,.52);
    backdrop-filter: blur(14px);
    border-top: 1px solid rgba(255,255,255,.08);
}
.hstat {
    padding: 17px 0;
    text-align: center;
    border-right: 1px solid rgba(255,255,255,.07);
}
.hstat:last-child { border-right: none; }
.hstat-n {
    font-family: var(--font-h);
    font-size: 1.65rem;
    font-weight: 700;
    color: var(--gold-lt);
}
.hstat-l {
    font-size: .68rem;
    font-weight: 500;
    letter-spacing: .9px;
    text-transform: uppercase;
    color: rgba(255,255,255,.45);
    margin-top: 5px;
}

.btn-gold {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, var(--gold), var(--gold-lt));
    color: var(--ink);
    font-weight: 700;
    font-size: .92rem;
    padding: 14px 32px;
    border-radius: 50px;
    text-decoration: none;
    box-shadow: 0 6px 24px rgba(191,140,42,.44);
    transition: transform .2s, box-shadow .2s;
}
.btn-gold:hover { transform: translateY(-3px); box-shadow: 0 14px 38px rgba(191,140,42,.52); color: var(--ink); }

.btn-ghost {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #fff;
    font-weight: 600;
    font-size: .92rem;
    padding: 13px 28px;
    border-radius: 50px;
    border: 1.5px solid rgba(255,255,255,.38);
    text-decoration: none;
    transition: border-color .2s, background .2s;
}
.btn-ghost:hover { border-color: #fff; background: rgba(255,255,255,.1); color: #fff; }

.section { padding: 96px 0; }

.eyebrow {
    display: inline-block;
    font-size: .70rem;
    font-weight: 700;
    letter-spacing: 2.2px;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 10px;
}

.section-title {
    font-size: clamp(1.85rem, 3.5vw, 2.85rem);
    font-weight: 700;
    line-height: 1.1;
    color: var(--ink);
}

.section-intro {
    font-size: .98rem;
    font-weight: 300;
    line-height: 1.78;
    color: rgba(24,15,3,.70);
    max-width: 520px;
}

.reveal { opacity: 0; transform: translateY(26px); transition: opacity .6s ease, transform .6s ease; }
.reveal.visible { opacity: 1; transform: none; }

.features { background: var(--white); }

.feat-card {
    background: var(--cream);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 32px 26px;
    height: 100%;
    position: relative;
    overflow: hidden;
    transition: box-shadow .3s, transform .3s, border-color .3s;
}

.feat-card::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, var(--gold-lt), var(--gold));
    transform: scaleY(0);
    transform-origin: top;
    transition: transform .35s ease;
}
.feat-card:hover { box-shadow: var(--shadow-h); transform: translateY(-5px); border-color: rgba(191,140,42,.2); }
.feat-card:hover::before { transform: scaleY(1); }

.feat-icon {
    width: 50px;
    height: 50px;
    border-radius: 13px;
    background: #FFF8EB;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: var(--gold);
    margin-bottom: 18px;
    transition: background .3s, color .3s;
}
.feat-card:hover .feat-icon { background: var(--gold); color: #fff; }

.feat-card h5 { font-size: 1.12rem; font-weight: 700; margin-bottom: 9px; }
.feat-card p { font-size: .855rem; font-weight: 300; color: rgba(24,15,3,.70); line-height: 1.68; margin: 0; }

.how-it-works { background: var(--forest); }

.step-card {
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: var(--radius);
    padding: 36px 28px;
    height: 100%;
    transition: background .3s, border-color .3s;
}
.step-card:hover {
    background: rgba(191,140,42,.10);
    border-color: rgba(232,185,74,.30);
}

.step-num {
    font-family: var(--font-h);
    font-size: 3.2rem;
    font-weight: 700;
    color: rgba(232,185,74,.15);
    line-height: 1;
    margin-bottom: 14px;
    transition: color .3s;
}
.step-card:hover .step-num { color: var(--gold-lt); }

.step-card h5 { font-size: 1.12rem; font-weight: 700; color: #fff; margin-bottom: 8px; }
.step-card p { font-size: .875rem; font-weight: 300; color: rgba(255,255,255,.52); line-height: 1.72; margin: 0; }

.tontines { background: var(--parchm); }

.swiper-arrow {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: var(--white);
    border: 1px solid var(--border);
    color: var(--ink);
    font-size: 1rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all .2s;
}
.swiper-arrow:hover { background: var(--gold); border-color: var(--gold); color: #fff; }
.swiper-arrow:disabled { opacity: .3; pointer-events: none; }

.tontines-swiper { padding: 12px 4px 58px !important; }
.tontines-swiper .swiper-pagination-bullet { background: rgba(24,15,3,.12); opacity: 1; }
.tontines-swiper .swiper-pagination-bullet-active { background: var(--gold); }

.tontine-card {
    background: var(--white);
    border-radius: var(--radius);
    overflow: hidden;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    height: 100%;
    transition: box-shadow .35s, transform .35s;
}
.tontine-card:hover { box-shadow: var(--shadow-h); transform: translateY(-7px); }

.tontine-img {
    position: relative;
    height: 215px;
    overflow: hidden;
    flex-shrink: 0;
}
.tontine-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .65s ease;
}
.tontine-card:hover .tontine-img img { transform: scale(1.08); }

.badge-amount {
    position: absolute;
    bottom: 12px;
    left: 12px;
    background: rgba(24,15,3,.70);
    backdrop-filter: blur(10px);
    border-radius: 10px;
    padding: 8px 14px;
    color: var(--gold-lt);
    font-family: var(--font-h);
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.1;
}
.badge-amount span {
    display: block;
    font-family: var(--font-b);
    font-size: .60rem;
    font-weight: 400;
    color: rgba(255,255,255,.48);
}

.badge-live {
    position: absolute;
    top: 12px;
    right: 12px;
    display: flex;
    align-items: center;
    gap: 5px;
    background: rgba(24,15,3,.60);
    backdrop-filter: blur(6px);
    border-radius: 50px;
    padding: 5px 12px;
    font-size: .70rem;
    font-weight: 600;
    color: #fff;
}

.live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #22C55E;
    box-shadow: 0 0 0 3px rgba(34,197,94,.25);
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 3px rgba(34,197,94,.22); }
    50%       { box-shadow: 0 0 0 7px rgba(34,197,94,.08); }
}

.tontine-body { padding: 22px 20px; flex: 1; display: flex; flex-direction: column; }

.tontine-body h5 {
    font-size: 1.1rem;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 7px;
}
.tontine-body .desc { font-size: .82rem; font-weight: 300; color: rgba(24,15,3,.70); line-height: 1.65; flex: 1; margin-bottom: 12px; }

.freq-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #FFF8EB;
    color: var(--gold);
    border-radius: 6px;
    padding: 4px 10px;
    font-size: .73rem;
    font-weight: 600;
    margin-bottom: 12px;
}

.tontine-meta { font-size: .79rem; color: rgba(24,15,3,.45); margin-bottom: 18px; }
.tontine-meta i { color: var(--gold); margin-right: 4px; }
.tontine-meta > div + div { margin-top: 4px; }

.btn-join {
    display: block;
    width: 100%;
    text-align: center;
    background: var(--ink);
    color: #fff;
    border-radius: 11px;
    padding: 12px;
    font-weight: 700;
    font-size: .875rem;
    text-decoration: none;
    margin-top: auto;
    transition: background .2s, transform .15s;
}
.btn-join:hover { background: var(--gold); color: var(--ink); transform: translateY(-1px); }

.faq { background: var(--white); }

.faq .accordion-item {
    border: 1px solid var(--border) !important;
    border-radius: 14px !important;
    margin-bottom: 10px;
    overflow: hidden;
    transition: box-shadow .25s;
}
.faq .accordion-item:hover { box-shadow: 0 4px 20px rgba(24,15,3,.07); }
.faq .accordion-button {
    font-family: var(--font-b) !important;
    font-weight: 600 !important;
    font-size: .93rem !important;
    color: var(--ink) !important;
    background: var(--white) !important;
    box-shadow: none !important;
    padding: 18px 22px;
}
.faq .accordion-button:not(.collapsed) {
    background: var(--cream) !important;
    color: var(--gold) !important;
}
.faq .accordion-body {
    font-size: .9rem;
    font-weight: 300;
    line-height: 1.76;
    color: rgba(24,15,3,.70);
    padding: 0 22px 18px;
}

.cta {
    background: var(--ink);
    padding: 110px 0;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.cta::before {
    content: '';
    position: absolute;
    top: -120px; right: -80px;
    width: 500px; height: 500px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(191,140,42,.22), transparent 70%);
}
.cta::after {
    content: '';
    position: absolute;
    bottom: -80px; left: -60px;
    width: 380px; height: 380px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(24,57,43,.65), transparent 70%);
}
.cta .container { position: relative; z-index: 1; }

.cta .eyebrow { color: var(--gold); }
.cta h2 {
    font-size: clamp(2.3rem, 5vw, 3.5rem);
    font-weight: 700;
    color: #fff;
    line-height: 1.08;
    margin-bottom: 16px;
}
.cta h2 em { font-style: italic; color: var(--gold-lt); }
.cta p {
    font-size: 1rem;
    font-weight: 300;
    color: rgba(255,255,255,.56);
    max-width: 480px;
    margin: 0 auto 44px;
    line-height: 1.75;
}

.site-footer {
    background: #0B0904;
    padding: 42px 0 28px;
    text-align: center;
    color: rgba(255,255,255,.35);
    font-size: .83rem;
}
.site-footer .brand-footer {
    font-family: var(--font-h);
    font-size: 1.3rem;
    font-weight: 700;
    color: rgba(255,255,255,.75);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.site-footer .brand-footer i { color: var(--gold); }
.footer-links { display: flex; justify-content: center; gap: 28px; margin-top: 12px; }
.footer-links a { color: rgba(255,255,255,.32); text-decoration: none; transition: color .2s; }
.footer-links a:hover { color: var(--gold-lt); }

@media (max-width: 991px) {
    .navbar-collapse { padding: 12px 0 18px; }
    .navbar-collapse .nav-link { padding: 10px 8px !important; border-bottom: 1px solid var(--border); }
    .navbar-collapse .d-flex { flex-direction: column; gap: 8px; margin-top: 12px; }
    .btn-nav { text-align: center; justify-content: center; }
}

@media (max-width: 767px) {
    .hero-wrap .swiper-pagination { right: 14px !important; }
    .hero-cap { padding: 0 0 100px; }
}

@media (max-width: 576px) {
    .hero-stats { display: none; }
    .hero-cap { padding-bottom: 48px; }
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg">
  <div class="container">

    <a class="navbar-brand" href="index.php">
      <span class="brand-gem"><i class="bi bi-coin"></i></span>
      Afriton
    </a>

    <button class="navbar-toggler border-0 shadow-none" type="button"
      data-bs-toggle="collapse" data-bs-target="#mainNav">
      <i class="bi bi-list" style="font-size:1.3rem; color:var(--ink)"></i>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav mx-auto gap-1">
        <li class="nav-item"><a class="nav-link" href="#features">Fonctionnalités</a></li>
        <li class="nav-item"><a class="nav-link" href="#steps">Fonctionnement</a></li>
        <?php if (!empty($tontines)): ?>
        <li class="nav-item"><a class="nav-link" href="#tontines">Tontines</a></li>
        <?php endif; ?>
        <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
        <li class="nav-item"><a class="nav-link" href="about.php">À propos</a></li>
      </ul>
      <div class="d-flex align-items-center gap-2 mt-2 mt-lg-0">
        <?php if (is_logged_in()): ?>
          <a href="dashboard.php" class="btn-nav outline">Mon espace</a>
          <a href="logout.php"    class="btn-nav solid">Déconnexion</a>
        <?php else: ?>
          <a href="login.php"    class="btn-nav outline">Connexion</a>
          <a href="register.php" class="btn-nav solid">
            <i class="bi bi-person-plus-fill"></i> S'inscrire
          </a>
        <?php endif; ?>
      </div>
    </div>

  </div>
</nav>


<!-- HERO -->
<div class="hero-wrap">

  <div class="swiper hero-swiper">
    <div class="swiper-wrapper">
      <?php foreach ($heroSlides as $sl): ?>
      <div class="swiper-slide">
        <img src="<?= $sl['img'] ?>" alt="Afriton" loading="eager">

        <div class="hero-cap">
          <div class="container">
            <div class="hero-inner">
              <div class="hero-tag">
                <i class="bi bi-stars"></i> <?= $sl['tag'] ?>
              </div>
              <h1 class="hero-h1"><?= $sl['h1'] ?></h1>
              <p class="hero-sub"><?= $sl['sub'] ?></p>
              <div class="hero-btns">
                <a href="register.php" class="btn-gold">
                  <i class=""></i> Créer mon compte
                </a>
                <a href="#tontines" class="btn-ghost">
                  <i class=""></i> Voir les tontines
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="swiper-pagination"></div>
  </div>

  <!-- Stats -->
  <div class="hero-stats">
    <div class="container">
      <div class="row g-0">
        <div class="col-3 hstat"><div class="hstat-n"></div><div class="hstat-l">Tontines créées</div></div>
        <div class="col-3 hstat"><div class="hstat-n"></div><div class="hstat-l">Membres actifs</div></div>
        <div class="col-3 hstat"><div class="hstat-n"></div><div class="hstat-l">Satisfaction</div></div>
        <div class="col-3 hstat"><div class="hstat-n"></div><div class="hstat-l">Chiffrement</div></div>
      </div>
    </div>
  </div>

</div>


<!-- FONCTIONNALITÉS -->
<section class="section features" id="features">
  <div class="container">

    <div class="row align-items-end mb-5 g-4">
      <div class="col-lg-5 reveal">
        <span class="eyebrow">Pourquoi Afriton ?</span>
        <h2 class="section-title mt-1">Tout ce dont votre<br>communauté a besoin</h2>
      </div>
      <div class="col-lg-5 offset-lg-1 reveal">
        <p class="section-intro">
          Une application de tontine pensée pour simplifier, sécuriser et moderniser chaque aspect
          de la tontine de l'invitation jusqu'au dernier versement.
        </p>
      </div>
    </div>

    <div class="row g-3">
      <?php
      $features = [
        ['bi-people-fill',      'Gestion des membres',  "Invitation, validation et suivi individuel de chaque participant en temps réel."],
        ['bi-arrow-repeat',     'Cycles',  "Tours et rappels de paiement gérés selon votre calendrier."],
        ['bi-graph-up-arrow',   'Tableau de bord',      "Cotisations, distributions et historiques visualisés en un coup d'œil."],
        ['bi-bell-fill',        'Notifications',        "Rappels email avant chaque échéance plus personne n'oublie jamais."],
        ['bi-phone-fill',       '100 % Responsive',     "Accessible sur mobile, tablette et ordinateur, sans rien installer."],
      ];
      foreach ($features as $i => [$ico, $titre, $desc]):
      ?>
      <div class="col-md-6 col-lg-4 reveal" style="transition-delay: <?= $i * .07 ?>s">
        <div class="feat-card">
          <div class="feat-icon"><i class="bi <?= $ico ?>"></i></div>
          <h5><?= $titre ?></h5>
          <p><?= $desc ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>


<!-- COMMENT ÇA MARCHE -->
<section class="section how-it-works" id="steps">
  <div class="container">

    <div class="text-center mb-5 reveal">
      <span class="eyebrow" style="color: var(--gold-lt)">Simple & rapide</span>
      <h2 class="section-title mt-1" style="color: #fff">Comment ça marche ?</h2>
    </div>

    <div class="row g-3">
      <?php
      $etapes = [
        ["Créer le groupe",           "Configurez votre tontine : montant, fréquence, durée et règles du groupe."],
        ["Inviter les membres",        "Partagez un lien ou code d'accès — vos proches rejoignent en quelques secondes."],
        ["Collecter les cotisations",  "Suivi automatisé des paiements avec rappels et tableau de bord en direct."],
        ["Distribuer les fonds",       "Distribution selon le planning convenu, en toute transparence pour tous."],
      ];
      foreach ($etapes as $i => [$titre, $desc]):
      ?>
      <div class="col-sm-6 col-lg-3 reveal" style="transition-delay: <?= $i * .09 ?>s">
        <div class="step-card">
          <div class="step-num">0<?= $i + 1 ?></div>
          <h5><?= $titre ?></h5>
          <p><?= $desc ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>


<!-- TONTINES ACTIVES -->
<?php if (!empty($tontines)): ?>
<section class="section tontines" id="tontines">
  <div class="container">

    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-5">
      <div class="reveal">
        <span class="eyebrow">Disponibles maintenant</span>
        <h2 class="section-title mt-1">Tontines actives</h2>
        <p class="section-intro mt-2 mb-0">
          Rejoignez une tontine et commencez à épargner avec votre communauté.
        </p>
      </div>
      <div class="d-flex gap-2 reveal">
        <button class="swiper-arrow" id="btnPrev"><i class="bi bi-arrow-left"></i></button>
        <button class="swiper-arrow" id="btnNext"><i class="bi bi-arrow-right"></i></button>
      </div>
    </div>

    <div class="swiper tontines-swiper" id="tontinesSwiper">
      <div class="swiper-wrapper">
        <?php foreach ($tontines as $i => $t):
          $img = $covers[$i % count($covers)];
        ?>
        <div class="swiper-slide" style="height: auto">
          <div class="tontine-card">

            <div class="tontine-img">
              <img src="<?= $img ?>" alt="<?= htmlspecialchars($t['nom']) ?>" loading="lazy">
              <div class="badge-amount">
                <?= number_format($t['montant_cotisation'], 0, ',', ' ') ?> FCFA
                <span>par cotisation</span>
              </div>
              <div class="badge-live">
                <span class="live-dot"></span> Active
              </div>
            </div>

            <div class="tontine-body">
              <h5 title="<?= htmlspecialchars($t['nom']) ?>"><?= htmlspecialchars($t['nom']) ?></h5>
              <p class="desc">
                <?php if ($t['description']): ?>
                  <?= mb_substr(strip_tags($t['description']), 0, 82) ?>…
                <?php else: ?>
                  Rejoignez ce groupe d'épargne et cotisez ensemble en toute confiance.
                <?php endif; ?>
              </p>
              <div class="freq-chip">
                <i class="bi bi-arrow-repeat"></i> <?= ucfirst($t['frequence']) ?>
              </div>
              <div class="tontine-meta">
                <div><i class="bi bi-calendar3"></i> Début : <?= date('d/m/Y', strtotime($t['date_debut'])) ?></div>
                <?php if ($t['representant_nom']): ?>
                  <div>
                    <i class="bi bi-person-circle"></i>
                    <?= htmlspecialchars($t['prenom'] . ' ' . $t['representant_nom']) ?>
                  </div>
                <?php endif; ?>
              </div>
              <a href="register_membre.php" class="btn-join">
                Rejoindre <i class="bi bi-arrow-right ms-1"></i>
              </a>
            </div>

          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="swiper-pagination"></div>
    </div>

  </div>
</section>
<?php endif; ?>


<!-- FAQ -->
<section class="section faq" id="faq">
  <div class="container" style="max-width: 700px">

    <div class="text-center mb-5 reveal">
      <span class="eyebrow">On répond à tout</span>
      <h2 class="section-title mt-1">Questions fréquentes</h2>
    </div>

    <div class="accordion reveal" id="faqAccordion">
      <?php
      $questions = [
        ['q1', "Un représentant peut-il gérer plusieurs tontines ?",
         "Oui. Depuis un tableau de bord unique vous visualisez et administrez toutes vos tontines avec une vue consolidée en temps réel."],
        ['q2', "Les inscriptions sont-elles gratuites ?",
         "L'ouverture d'un compte est entièrement gratuite. Des plans avancés sont disponibles pour les très grandes tontines."],
      ];
      foreach ($questions as $k => [$id, $question, $reponse]):
      ?>
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button <?= $k > 0 ? 'collapsed' : '' ?>"
            type="button" data-bs-toggle="collapse" data-bs-target="#<?= $id ?>">
            <?= $question ?>
          </button>
        </h2>
        <div id="<?= $id ?>" class="accordion-collapse collapse <?= $k === 0 ? 'show' : '' ?>"
          data-bs-parent="#faqAccordion">
          <div class="accordion-body"><?= $reponse ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>


<!-- CTA -->
<section class="cta">
  <div class="container">
    <div class="reveal">
      <span class="eyebrow">Rejoignez la communauté</span>
      <h2>Prêt à épargner <em>ensemble</em> ?</h2>
      <p>Créez votre compte gratuitement et lancez votre première tontine aujourd'hui.</p>
      <div class="d-flex flex-wrap justify-content-center gap-3">
        <a href="register.php" class="btn-gold">
          <i class=""></i> Créer mon compte
        </a>
        <a href="#features" class="btn-ghost">
          <i class=""></i> En savoir plus
        </a>
      </div>
    </div>
  </div>
</section>


<!-- FOOTER -->
<footer class="site-footer">
  <div class="brand-footer"><i class="bi bi-coin"></i> Afriton</div>
  <p>&copy; <?= date('Y') ?> Afriton – Système de gestion de tontines</p>
  <nav class="footer-links">
    <a href="login.php">Connexion</a>
    <a href="register.php">Inscription</a>
    <a href="about.php">À propos</a>
  </nav>
</footer>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
new Swiper('.hero-swiper', {
    loop: true,
    speed: 1300,
    autoplay: { delay: 6500, disableOnInteraction: false },
    effect: 'fade',
    fadeEffect: { crossFade: true },
    pagination: { el: '.hero-swiper .swiper-pagination', clickable: true },
});

new Swiper('#tontinesSwiper', {
    loop: <?php echo count($tontines) > 3 ? 'true' : 'false'; ?>,
    speed: 720,
    grabCursor: true,
    autoplay: { delay: 4500, disableOnInteraction: false, pauseOnMouseEnter: true },
    slidesPerView: 1,
    spaceBetween: 20,
    pagination: { el: '#tontinesSwiper .swiper-pagination', clickable: true, dynamicBullets: true },
    navigation: { nextEl: '#btnNext', prevEl: '#btnPrev' },
    breakpoints: {
        580:  { slidesPerView: 2, spaceBetween: 18 },
        992:  { slidesPerView: 3, spaceBetween: 22 },
        1280: { slidesPerView: 3.15, spaceBetween: 26 },
    },
});

const observer = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            e.target.classList.add('visible');
            observer.unobserve(e.target);
        }
    });
}, { threshold: 0.11 });

document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
</script>

</body>
</html>