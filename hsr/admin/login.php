<?php
require_once __DIR__ . '/../inc/admin-funktioner.php';
start_session();

if (er_logget_ind()) { header('Location: index.php'); exit; }

$fejl = '';
$foerste_gang = !har_brugere();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tjek_csrf();

    if ($foerste_gang && ($_POST['handling'] ?? '') === 'opret') {
        $kode = $_POST['kodeord'] ?? '';
        if (strlen($kode) < 10) {
            $fejl = 'Adgangskoden skal være mindst 10 tegn. Tre tilfældige ord er nemt at huske og svært at gætte.';
        } elseif ($kode !== ($_POST['kodeord2'] ?? '')) {
            $fejl = 'De to adgangskoder er ikke ens.';
        } elseif (!opret_foerste_bruger($_POST['brugernavn'] ?? '', trim($_POST['fuldt_navn'] ?? ''), $kode)) {
            $fejl = 'Brugeren kunne ikke oprettes. Tjek at mappen data/ må skrives af PHP.';
        } else {
            log_ind($_POST['brugernavn'], $kode);
            header('Location: index.php'); exit;
        }
    } else {
        if (log_ind($_POST['brugernavn'] ?? '', $_POST['kodeord'] ?? '')) {
            header('Location: index.php'); exit;
        }
        $fejl = 'Brugernavn eller adgangskode passer ikke.';
        usleep(400000);
    }
}
$site = site();
?><!DOCTYPE html>
<html lang="da">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log ind · <?= e($site['forening']['navn'] ?? 'Admin') ?></title>
<link rel="stylesheet" href="../assets/admin.css">
</head>
<body class="login-side">

<div class="login-kasse">
  <h1><?= $foerste_gang ? 'Opret den første bruger' : 'Log ind' ?></h1>
  <p class="under"><?= e($site['forening']['navn'] ?? '') ?> · redigering af hjemmesiden</p>

  <?php if ($fejl): ?>
    <p class="fejl" role="alert"><?= e($fejl) ?></p>
  <?php endif; ?>

  <?php if ($foerste_gang): ?>
    <p class="hjaelp">Der er endnu ingen brugere. Den første oprettes her, og derefter
      forsvinder dette skærmbillede af sig selv.</p>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <?= csrf_felt() ?>
    <input type="hidden" name="handling" value="<?= $foerste_gang ? 'opret' : 'login' ?>">

    <div class="felt">
      <label for="brugernavn">Brugernavn</label>
      <input type="text" id="brugernavn" name="brugernavn" required autofocus
             autocapitalize="none" spellcheck="false">
    </div>

    <?php if ($foerste_gang): ?>
      <div class="felt">
        <label for="fuldt_navn">Dit navn<span class="hjaelp">Vises i loggen over ændringer.</span></label>
        <input type="text" id="fuldt_navn" name="fuldt_navn">
      </div>
    <?php endif; ?>

    <div class="felt">
      <label for="kodeord">Adgangskode<?php if ($foerste_gang): ?><span class="hjaelp">Mindst 10 tegn.</span><?php endif; ?></label>
      <input type="password" id="kodeord" name="kodeord" required
             autocomplete="<?= $foerste_gang ? 'new-password' : 'current-password' ?>">
    </div>

    <?php if ($foerste_gang): ?>
      <div class="felt">
        <label for="kodeord2">Adgangskode igen</label>
        <input type="password" id="kodeord2" name="kodeord2" required autocomplete="new-password">
      </div>
    <?php endif; ?>

    <button type="submit" class="knap"><?= $foerste_gang ? 'Opret bruger' : 'Log ind' ?></button>
  </form>

  <p class="retur"><a href="../index.php">Tilbage til hjemmesiden</a></p>
</div>

</body>
</html>
