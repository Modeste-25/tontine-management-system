<?php
require_once 'config.php';
require_login();

$user    = get_logged_user();
$user_id = $user['id'];
$type    = $user['type_utilisateur'];

// Seuls les représentants actifs peuvent créer/gérer
if ($type !== 'representant' && $type !== 'admin') {
    header('Location: index.php');
    exit();
}

$action = $_GET['action'] ?? 'list';
$flash  = (isset($_SESSION['flash']) && is_array($_SESSION['flash'])) ? $_SESSION['flash'] : null;
unset($_SESSION['flash']);

// ── CRÉER UNE TONTINE ──
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom               = secure_input($_POST['nom'] ?? '');
    $description       = secure_input($_POST['description'] ?? '');
    $montant           = (float) ($_POST['montant_cotisation'] ?? 0);
    $frequence         = $_POST['frequence'] ?? 'mensuelle';
    $date_debut        = $_POST['date_debut'] ?? '';
    $date_fin          = $_POST['date_fin'] ?? '';
    $nb_membres_max    = (int) ($_POST['nb_membres_max'] ?? 0);
    $regles            = secure_input($_POST['regles'] ?? '');

    $erreurs = [];
    if (empty($nom))        $erreurs[] = "Le nom de la tontine est obligatoire.";
    if ($montant <= 0)      $erreurs[] = "Le montant de cotisation doit être supérieur à 0.";
    if (empty($date_debut)) $erreurs[] = "La date de début est obligatoire.";

    $frequences_valides = ['hebdomadaire','bimensuelle','mensuelle','trimestrielle','annuelle'];
    if (!in_array($frequence, $frequences_valides)) $frequence = 'mensuelle';

    if (empty($erreurs)) {
        $stmt = $pdo->prepare("
            INSERT INTO tontines
                (nom, description, representant_id, montant_cotisation, frequence,
                 date_debut, date_fin, nb_membres_max, regles, statut)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $nom, $description, $user_id, $montant, $frequence,
            $date_debut,
            !empty($date_fin) ? $date_fin : null,
            $nb_membres_max > 0 ? $nb_membres_max : null,
            $regles
        ]);
        $tontine_id = $pdo->lastInsertId();
        log_action('Création tontine', "Tontine « $nom » créée par représentant $user_id");
        $_SESSION['flash'] = ['type' => 'success', 'msg' => " La tontine « $nom » a été créée avec succès !"];
        header('Location: dashboard_representant.php');
        exit();
    }
    // Erreurs → rester sur le formulaire avec les messages
    $action = 'create';
}

// ── MODIFIER UNE TONTINE ──
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tontine_id = (int) ($_POST['tontine_id'] ?? 0);

    // Vérifier propriété
    $check = $pdo->prepare("SELECT * FROM tontines WHERE id = ? AND representant_id = ?");
    $check->execute([$tontine_id, $user_id]);
    $tontine_edit = $check->fetch();

    if ($tontine_edit) {
        $nom            = secure_input($_POST['nom'] ?? '');
        $description    = secure_input($_POST['description'] ?? '');
        $montant        = (float) ($_POST['montant_cotisation'] ?? 0);
        $frequence      = $_POST['frequence'] ?? 'mensuelle';
        $date_debut     = $_POST['date_debut'] ?? '';
        $date_fin       = $_POST['date_fin'] ?? '';
        $nb_membres_max = (int) ($_POST['nb_membres_max'] ?? 0);
        $regles         = secure_input($_POST['regles'] ?? '');
        $statut         = $_POST['statut'] ?? 'active';

        $stmt = $pdo->prepare("
            UPDATE tontines SET
                nom = ?, description = ?, montant_cotisation = ?, frequence = ?,
                date_debut = ?, date_fin = ?, nb_membres_max = ?, regles = ?, statut = ?
            WHERE id = ? AND representant_id = ?
        ");
        $stmt->execute([
            $nom, $description, $montant, $frequence,
            $date_debut,
            !empty($date_fin) ? $date_fin : null,
            $nb_membres_max > 0 ? $nb_membres_max : null,
            $regles, $statut,
            $tontine_id, $user_id
        ]);
        log_action('Modification tontine', "Tontine $tontine_id modifiée");
        $_SESSION['flash'] = ['type' => 'success', 'msg' => "✅ Tontine modifiée avec succès."];
        header('Location: tontine.php');
        exit();
    }
}

// ── CHARGER LES TONTINES DU REPRÉSENTANT ──
$stmt = $pdo->prepare("
    SELECT t.*,
           COUNT(mt.id) AS nb_membres,
           SUM(CASE WHEN c.statut = 'payee' THEN c.montant ELSE 0 END) AS total_collecte
    FROM tontines t
    LEFT JOIN membres_tontines mt ON mt.tontine_id = t.id AND mt.statut = 'actif'
    LEFT JOIN cotisations c ON c.tontine_id = t.id
    WHERE t.representant_id = ?
    GROUP BY t.id
    ORDER BY t.date_debut DESC
");
$stmt->execute([$user_id]);
$tontines = $stmt->fetchAll();

// ── CHARGER UNE TONTINE POUR ÉDITION ──
$tontine_edit = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM tontines WHERE id = ? AND representant_id = ?");
    $stmt->execute([(int)$_GET['id'], $user_id]);
    $tontine_edit = $stmt->fetch();
    if (!$tontine_edit) { header('Location: tontine.php'); exit(); }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $action === 'create' ? 'Créer une tontine' : ($action === 'edit' ? 'Modifier la tontine' : 'Mes tontines') ?> – Afriton</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --gold:    #BF8C2A;
            --gold-lt: #E8B94A;
            --forest:  #18392B;
            --ink:     #180F03;
            --cream:   #FAF7F1;
        }
        body { font-family: 'Outfit', sans-serif; background: #f1f5f9; }

        /* ── FORMULAIRE ── */
        .form-page { min-height: 100vh; display: flex; align-items: flex-start; justify-content: center; padding: 40px 16px 60px; }
        .form-card {
            background: white; border-radius: 20px;
            width: 100%; max-width: 700px;
            box-shadow: 0 24px 70px rgba(24,15,3,.12);
            overflow: hidden;
        }
        .form-top {
            background: linear-gradient(135deg, var(--forest), #2d6a4f);
            padding: 36px 44px 32px; color: white;
        }
        .form-top .icon-wrap {
            width: 56px; height: 56px; border-radius: 14px;
            background: rgba(255,255,255,.12);
            border: 1.5px solid rgba(255,255,255,.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; margin-bottom: 16px;
        }
        .form-top h2 { font-family: 'Cormorant Garamond', serif; font-size: 1.7rem; font-weight: 700; margin: 0 0 6px; }
        .form-top p  { color: rgba(255,255,255,.65); font-size: .875rem; margin: 0; }
        .form-body   { padding: 36px 44px 44px; }

        .divider-label {
            display: flex; align-items: center; gap: 10px;
            color: #94a3b8; font-size: .72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1.2px;
            margin: 28px 0 18px;
        }
        .divider-label::before, .divider-label::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }

        .form-label { font-size: .82rem; font-weight: 600; color: #334155; margin-bottom: 5px; }
        .form-control, .form-select {
            border: 1.5px solid #e2e8f0; border-radius: 10px;
            padding: 11px 14px; font-size: .9rem; color: #1e293b;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--forest);
            box-shadow: 0 0 0 3px rgba(24,57,43,.12);
            outline: none;
        }
        textarea.form-control { resize: vertical; min-height: 90px; }

        .btn-submit {
            background: linear-gradient(135deg, var(--forest), #2d6a4f);
            color: white; border: none; border-radius: 11px;
            width: 100%; padding: 14px; font-size: 1rem;
            font-weight: 700; cursor: pointer;
            transition: opacity .2s, box-shadow .2s, transform .2s;
        }
        .btn-submit:hover { opacity: .92; box-shadow: 0 8px 28px rgba(24,57,43,.3); transform: translateY(-1px); }

        .freq-options { display: flex; flex-wrap: wrap; gap: 10px; }
        .freq-opt input { display: none; }
        .freq-opt label {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 18px; border-radius: 50px;
            border: 1.5px solid #e2e8f0;
            font-size: .85rem; font-weight: 600; color: #475569;
            cursor: pointer; transition: all .2s;
        }
        .freq-opt input:checked + label {
            background: var(--forest); color: white; border-color: var(--forest);
        }
        .freq-opt label:hover { border-color: var(--forest); color: var(--forest); }

        /* ── LISTE DES TONTINES ── */
        .t-card {
            background: white; border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 22px 24px;
            margin-bottom: 16px;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,.04);
            transition: box-shadow .25s, transform .2s;
        }
        .t-card:hover { box-shadow: 0 8px 30px rgba(0,0,0,.1); transform: translateY(-2px); }
        .t-card.active-t   { border-left: 4px solid #22c55e; }
        .t-card.inactive-t { border-left: 4px solid #94a3b8; }
        .t-card.terminee-t { border-left: 4px solid var(--gold); }
        .t-nom { font-family: 'Cormorant Garamond', serif; font-size: 1.2rem; font-weight: 700; color: var(--ink); }
        .t-meta { font-size: .8rem; color: #64748b; margin-top: 4px; }
        .t-meta i { color: var(--gold); margin-right: 3px; }
        .kpi-row { display: flex; gap: 24px; flex-wrap: wrap; }
        .kpi-item { text-align: center; }
        .kpi-item .v { font-size: 1.1rem; font-weight: 800; color: var(--ink); }
        .kpi-item .l { font-size: .68rem; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; }

        .badge-s { border-radius: 20px; padding: 3px 11px; font-size: .72rem; font-weight: 700; }
        .badge-active   { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #f1f5f9; color: #475569; }
        .badge-terminee { background: #fef3c7; color: #92400e; }

        .empty-state { text-align: center; padding: 70px 20px; }
        .empty-icon { font-size: 3rem; color: #cbd5e1; margin-bottom: 16px; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include 'sidebar_representant.php'; ?>
    <div class="main-content">
        <?php include 'topbar.php'; ?>
        <div class="content-area">

            <!-- Flash -->
            <?php if ($flash && isset($flash['type'], $flash['msg'])): ?>
            <?php $ok = $flash['type'] === 'success'; ?>
            <div style="background:<?= $ok?'#d1fae5':'#fee2e2' ?>;border:1px solid <?= $ok?'#22c55e':'#f87171' ?>;
                        color:<?= $ok?'#065f46':'#991b1b' ?>;border-radius:10px;padding:12px 18px;
                        margin-bottom:20px;display:flex;align-items:center;gap:10px;
                        font-size:.9rem;font-weight:600;">
                <i class="bi <?= $ok?'bi-check-circle-fill':'bi-x-circle-fill' ?>"></i>
                <?= htmlspecialchars((string)$flash['msg']) ?>
            </div>
            <?php endif; ?>

            <?php if ($action === 'create' || $action === 'edit'): ?>
            <!-- ══════════ FORMULAIRE ══════════ -->
            <div class="form-page" style="background:transparent;padding:0;min-height:unset;">
            <div class="form-card">

                <div class="form-top">
                    <div class="icon-wrap">
                        <i class="bi <?= $action === 'edit' ? 'bi-pencil-square' : 'bi-plus-circle-fill' ?>"></i>
                    </div>
                    <h2><?= $action === 'edit' ? 'Modifier la tontine' : 'Créer une nouvelle tontine' ?></h2>
                    <p><?= $action === 'edit' ? 'Modifiez les paramètres de votre tontine.' : 'Remplissez les informations pour lancer votre tontine.' ?></p>
                </div>

                <div class="form-body">

                    <!-- Erreurs -->
                    <?php if (!empty($erreurs)): ?>
                    <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;
                                padding:14px 16px;margin-bottom:24px;">
                        <?php foreach ($erreurs as $e): ?>
                        <div style="font-size:.85rem;color:#991b1b;margin-bottom:4px;">
                            <i class="bi bi-exclamation-circle me-1"></i><?= $e ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="tontine.php?action=<?= $action ?><?= $action === 'edit' ? '&id='.$tontine_edit['id'] : '' ?>">
                        <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="tontine_id" value="<?= $tontine_edit['id'] ?>">
                        <?php endif; ?>

                        <div class="divider-label">Informations générales</div>

                        <div class="mb-3">
                            <label class="form-label">Nom de la tontine <span style="color:#ef4444">*</span></label>
                            <input type="text" class="form-control" name="nom" required
                                   placeholder="Ex : Tontine des femmes de Bonanjo"
                                   value="<?= htmlspecialchars($tontine_edit['nom'] ?? $_POST['nom'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description"
                                      placeholder="Décrivez brièvement votre tontine..."><?= htmlspecialchars($tontine_edit['description'] ?? $_POST['description'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Règlement interne</label>
                            <textarea class="form-control" name="regles" style="min-height:75px;"
                                      placeholder="Règles de fonctionnement, sanctions, conditions..."><?= htmlspecialchars($tontine_edit['regles'] ?? $_POST['regles'] ?? '') ?></textarea>
                        </div>

                        <div class="divider-label">Paramètres financiers</div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-7">
                                <label class="form-label">Montant de la cotisation (FCFA) <span style="color:#ef4444">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="montant_cotisation"
                                           required min="100" step="100"
                                           placeholder="Ex : 10000"
                                           value="<?= htmlspecialchars($tontine_edit['montant_cotisation'] ?? $_POST['montant_cotisation'] ?? '') ?>">
                                    <span class="input-group-text" style="border-color:#e2e8f0;background:#f8fafc;font-size:.85rem;font-weight:600;">FCFA</span>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Membres maximum</label>
                                <input type="number" class="form-control" name="nb_membres_max"
                                       min="2" placeholder="Illimité si vide"
                                       value="<?= htmlspecialchars($tontine_edit['nb_membres_max'] ?? $_POST['nb_membres_max'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Fréquence de cotisation <span style="color:#ef4444">*</span></label>
                            <div class="freq-options">
                                <?php
                                $freqs = [
                                    'hebdomadaire'  => ['bi-calendar-week', 'Hebdo'],
                                    'bimensuelle'   => ['bi-calendar2-range', '2×/mois'],
                                    'mensuelle'     => ['bi-calendar-month', 'Mensuelle'],
                                    'trimestrielle' => ['bi-calendar3', 'Trimestrielle'],
                                    'annuelle'      => ['bi-calendar-year', 'Annuelle'],
                                ];
                                $sel = $tontine_edit['frequence'] ?? $_POST['frequence'] ?? 'mensuelle';
                                foreach ($freqs as $val => [$ico, $lbl]):
                                ?>
                                <div class="freq-opt">
                                    <input type="radio" name="frequence" id="f_<?= $val ?>"
                                           value="<?= $val ?>" <?= $sel === $val ? 'checked' : '' ?>>
                                    <label for="f_<?= $val ?>">
                                        <i class="bi <?= $ico ?>"></i> <?= $lbl ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="divider-label">Dates</div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Date de début <span style="color:#ef4444">*</span></label>
                                <input type="date" class="form-control" name="date_debut" required
                                       value="<?= htmlspecialchars($tontine_edit['date_debut'] ?? $_POST['date_debut'] ?? date('Y-m-d')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date de fin <span style="color:#94a3b8;font-weight:400;">(optionnelle)</span></label>
                                <input type="date" class="form-control" name="date_fin"
                                       value="<?= htmlspecialchars($tontine_edit['date_fin'] ?? $_POST['date_fin'] ?? '') ?>">
                            </div>
                        </div>

                        <?php if ($action === 'edit'): ?>
                        <div class="mb-4">
                            <label class="form-label">Statut</label>
                            <select name="statut" class="form-select">
                                <option value="active"    <?= ($tontine_edit['statut'] ?? '') === 'active'    ? 'selected' : '' ?>>🟢 Active</option>
                                <option value="inactive"  <?= ($tontine_edit['statut'] ?? '') === 'inactive'  ? 'selected' : '' ?>>⚪ Inactive</option>
                                <option value="terminee"  <?= ($tontine_edit['statut'] ?? '') === 'terminee'  ? 'selected' : '' ?>>🏁 Terminée</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <button type="submit" class="btn-submit">
                            <i class="bi <?= $action === 'edit' ? 'bi-floppy-fill' : 'bi-plus-circle-fill' ?> me-2"></i>
                            <?= $action === 'edit' ? 'Enregistrer les modifications' : 'Créer la tontine' ?>
                        </button>

                        <div style="text-align:center;margin-top:16px;">
                            <a href="tontine.php" style="color:#64748b;font-size:.85rem;text-decoration:none;">
                                <i class="bi bi-arrow-left me-1"></i>Retour à mes tontines
                            </a>
                        </div>
                    </form>

                </div>
            </div>
            </div>

            <?php else: ?>
            <!-- ══════════ LISTE ══════════ -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
                <div>
                    <h1 style="font-family:'Cormorant Garamond',serif;font-size:1.6rem;font-weight:700;color:#1e293b;margin:0 0 4px;">
                        Mes tontines
                    </h1>
                    <p style="color:#64748b;font-size:.875rem;margin:0;"><?= count($tontines) ?> tontine(s) créée(s)</p>
                </div>
                <a href="tontine.php?action=create"
                   style="display:inline-flex;align-items:center;gap:8px;
                          background:linear-gradient(135deg,#18392b,#2d6a4f);
                          color:white;border-radius:11px;padding:11px 22px;
                          font-weight:700;font-size:.9rem;text-decoration:none;
                          box-shadow:0 4px 18px rgba(24,57,43,.28);transition:all .2s;">
                    <i class="bi bi-plus-circle-fill"></i> Nouvelle tontine
                </a>
            </div>

            <?php if (empty($tontines)): ?>
            <div style="background:white;border-radius:16px;border:1px solid #e2e8f0;">
                <div class="empty-state">
                    <div class="empty-icon"><i class="bi bi-collection"></i></div>
                    <h4 style="font-family:'Cormorant Garamond',serif;font-weight:700;color:#1e293b;margin-bottom:8px;">
                        Aucune tontine encore
                    </h4>
                    <p style="color:#64748b;font-size:.9rem;margin-bottom:24px;">
                        Créez votre première tontine pour commencer à gérer vos membres et cotisations.
                    </p>
                    <a href="tontine.php?action=create"
                       style="display:inline-flex;align-items:center;gap:8px;
                              background:linear-gradient(135deg,#18392b,#2d6a4f);
                              color:white;border-radius:11px;padding:12px 28px;
                              font-weight:700;text-decoration:none;">
                        <i class="bi bi-plus-circle-fill"></i> Créer ma première tontine
                    </a>
                </div>
            </div>

            <?php else: ?>
            <?php foreach ($tontines as $t): ?>
            <?php
            $css_class = match($t['statut']) {
                'active'   => 'active-t',
                'inactive' => 'inactive-t',
                default    => 'terminee-t'
            };
            $badge_class = match($t['statut']) {
                'active'   => 'badge-active',
                'inactive' => 'badge-inactive',
                default    => 'badge-terminee'
            };
            $badge_lbl = match($t['statut']) {
                'active'   => 'Active',
                'inactive' => 'Inactive',
                default    => 'Terminée'
            };
            ?>
            <div class="t-card <?= $css_class ?>">
                <!-- Infos principales -->
                <div style="flex:1;min-width:200px;">
                    <div class="t-nom"><?= htmlspecialchars($t['nom']) ?></div>
                    <div class="t-meta">
                        <i class="bi bi-arrow-repeat"></i><?= ucfirst($t['frequence']) ?> ·
                        <i class="bi bi-calendar3"></i><?= date('d/m/Y', strtotime($t['date_debut'])) ?>
                        <?php if ($t['date_fin']): ?>
                            → <?= date('d/m/Y', strtotime($t['date_fin'])) ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($t['description']): ?>
                    <div style="font-size:.8rem;color:#94a3b8;margin-top:5px;max-width:380px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= htmlspecialchars_decode(htmlspecialchars(mb_substr($t['description'], 0, 82))) ?>…
                    </div>
                    <?php endif; ?>
                </div>

                <!-- KPIs -->
                <div class="kpi-row">
                    <div class="kpi-item">
                        <div class="v"><?= $t['nb_membres'] ?></div>
                        <div class="l">Membres</div>
                    </div>
                    <div class="kpi-item">
                        <div class="v" style="color:#1d4ed8;"><?= number_format($t['montant_cotisation'], 0, ',', ' ') ?></div>
                        <div class="l">FCFA/cotis.</div>
                    </div>
                    <div class="kpi-item">
                        <div class="v" style="color:#16a34a;"><?= number_format($t['total_collecte'] ?? 0, 0, ',', ' ') ?></div>
                        <div class="l">Total collecté</div>
                    </div>
                </div>

                <!-- Statut + Actions -->
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span class="badge-s <?= $badge_class ?>"><?= $badge_lbl ?></span>

                    <a href="membres_tontines.php?id=<?= $t['id'] ?>"
                       style="display:inline-flex;align-items:center;gap:5px;
                              padding:7px 14px;border-radius:8px;border:1px solid #e2e8f0;
                              background:white;color:#334155;font-size:.8rem;font-weight:600;
                              text-decoration:none;transition:all .2s;"
                       onmouseover="this.style.borderColor='#1d4ed8';this.style.color='#1d4ed8'"
                       onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#334155'">
                        <i class="bi bi-people"></i> Membres
                    </a>

                    <a href="tontine.php?action=edit&id=<?= $t['id'] ?>"
                       style="display:inline-flex;align-items:center;gap:5px;
                              padding:7px 14px;border-radius:8px;
                              background:linear-gradient(135deg,#18392b,#2d6a4f);
                              color:white;font-size:.8rem;font-weight:600;
                              text-decoration:none;transition:all .2s;">
                        <i class="bi bi-pencil"></i> Modifier
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>