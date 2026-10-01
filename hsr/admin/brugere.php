<?php
require_once __DIR__ . '/../inc/admin-funktioner.php';
kraev_login();

$site = site();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tjek_csrf();
    $handling = $_POST['handling'] ?? '';

    if ($handling === 'gem_bruger') {
        $navn1 = $_POST['kodeord'] ?? '';
        $navn2 = $_POST['kodeord2'] ?? '';
        if ($navn1 !== $navn2) {
            saet_besked('De to adgangskoder er ikke ens.', 'fejl');
        } else {
            $svar = gem_bruger($_POST['brugernavn'] ?? '', $_POST['fuldt_navn'] ?? '', $navn1);
            if ($svar === true) {
                skriv_log('oprettede eller rettede brugeren ' . strtolower($_POST['brugernavn']));
                saet_besked('Brugeren er gemt.');
            } else {
                saet_besked($svar, 'fejl');
            }
        }
    }

    if ($handling === 'slet_bruger') {
        $svar = slet_bruger($_POST['brugernavn'] ?? '');
        if ($svar === true) {
            skriv_log('slettede brugeren ' . $_POST['brugernavn']);
            saet_besked('Brugeren er slettet.');
        } else {
            saet_besked($svar, 'fejl');
        }
    }

    header('Location: brugere.php');
    exit;
}

$besked   = tag_besked();
$liste    = brugere();
$mig      = $_SESSION['bruger'] ?? '';
$rediger  = $_GET['rediger'] ?? null;
$den      = $rediger && isset($liste[$rediger]) ? $liste[$rediger] : null;
?><!DOCTYPE html>
<html lang="da">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brugere · <?= e($site['forening']['navn'] ?? '') ?></title>
<link rel="stylesheet" href="../assets/admin.css">
</head>
<body>

<div class="top">
  <div class="navn"><?= e($site['forening']['navn'] ?? '') ?><span>Redigering af hjemmesiden</span></div>
  <div class="bruger">
    <a href="../index.php">Se hjemmesiden</a>
    Logget ind som <?= e(bruger_navn()) ?>
    <a class="ud" href="logud.php">Log ud</a>
  </div>
</div>

<div class="ramme">
  <div class="side">
    <nav>
      <a href="index.php?vis=arrangementer" data-farve="terracotta">Arrangementer</a>
      <a href="index.php?vis=nyheder" data-farve="salvie">Nyheder</a>
      <a href="index.php?vis=sider" data-farve="okker">Sider</a>
      <div class="adskil"></div>
      <a href="index.php?vis=kopi" data-farve="brand">Sikkerhedskopi</a>
      <a href="brugere.php" data-farve="brand" aria-current="page">Brugere <span class="antal"><?= count($liste) ?></span></a>
    </nav>
    <div class="adskil"></div>
    <p class="fod">Adgangskoder gemmes aldrig som tekst, kun som en hash der ikke kan
      regnes tilbage. Glemmer nogen sin kode, laver du en ny her.</p>
  </div>

  <main>
    <?php if ($besked): ?>
      <p class="besked <?= $besked['type'] === 'fejl' ? 'fejl' : '' ?>" role="status"><?= e($besked['tekst']) ?></p>
    <?php endif; ?>

    <div class="hoved">
      <div>
        <h1>Brugere</h1>
        <p>Alle der er her, kan rette alt på hjemmesiden. Der er ikke forskellige
           rettigheder, så opret kun dem der skal kunne det.</p>
      </div>
    </div>

    <div class="liste">
      <?php foreach ($liste as $navn => $b): ?>
        <div class="post">
          <div class="krop">
            <h3><?= e($b['navn']) ?>
              <?= $navn === $mig ? '<span class="maerke fremhaevet">Dig</span>' : '' ?></h3>
            <p class="meta">Brugernavn: <?= e($navn) ?></p>
          </div>
          <div class="handling">
            <a class="knap stille lille" href="?rediger=<?= urlencode($navn) ?>">Ny adgangskode</a>
            <?php if ($navn !== $mig && count($liste) > 1): ?>
              <form method="post" onsubmit="return confirm('Slet brugeren <?= e(addslashes($b['navn'])) ?>?\n\nPersonen kan ikke længere logge ind. Intet indhold slettes.')">
                <?= csrf_felt() ?>
                <input type="hidden" name="handling" value="slet_bruger">
                <input type="hidden" name="brugernavn" value="<?= e($navn) ?>">
                <button type="submit" class="knap fare lille">Slet</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="kasse" style="margin-top:34px">
      <h2><?= $den ? 'Ny adgangskode til ' . e($den['navn']) : 'Opret ny bruger' ?></h2>
      <p>
        <?= $den
            ? 'Brugernavnet kan ikke ændres. Lader du felterne stå tomme, beholdes den nuværende adgangskode.'
            : 'Vælg en adgangskode på mindst 10 tegn. Tre tilfældige ord er nemt at huske og svært at gætte.' ?>
      </p>

      <form class="formular" method="post" autocomplete="off" style="margin-top:0">
        <?= csrf_felt() ?>
        <input type="hidden" name="handling" value="gem_bruger">

        <div class="par">
          <div class="felt">
            <label for="brugernavn">Brugernavn
              <span class="hjaelp">Små bogstaver og tal, fx birgitte.</span></label>
            <input type="text" id="brugernavn" name="brugernavn" required
                   autocapitalize="none" spellcheck="false"
                   value="<?= e($rediger ?? '') ?>" <?= $den ? 'readonly' : '' ?>>
          </div>
          <div class="felt">
            <label for="fuldt_navn">Navn
              <span class="hjaelp">Vises i loggen over ændringer.</span></label>
            <input type="text" id="fuldt_navn" name="fuldt_navn" value="<?= e($den['navn'] ?? '') ?>">
          </div>
        </div>

        <div class="par">
          <div class="felt">
            <label for="kodeord">Adgangskode</label>
            <input type="password" id="kodeord" name="kodeord" autocomplete="new-password" <?= $den ? '' : 'required' ?>>
          </div>
          <div class="felt">
            <label for="kodeord2">Adgangskode igen</label>
            <input type="password" id="kodeord2" name="kodeord2" autocomplete="new-password" <?= $den ? '' : 'required' ?>>
          </div>
        </div>

        <div class="gem-raekke">
          <button type="submit" class="knap"><?= $den ? 'Gem ny adgangskode' : 'Opret bruger' ?></button>
          <?php if ($den): ?><a class="knap stille" href="brugere.php">Fortryd</a><?php endif; ?>
        </div>
      </form>
    </div>
  </main>
</div>

</body>
</html>
