<?php
require_once __DIR__ . '/../inc/admin-funktioner.php';
kraev_login();

$site = site();

/* ==========================================================
   Handlinger (POST). Efter hver gemning sendes brugeren videre,
   så en genindlæsning ikke gemmer det samme igen.
   ========================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tjek_csrf();
    $handling = $_POST['handling'] ?? '';

    /* ---------- Arrangement ---------- */
    if ($handling === 'gem_arr') {
        $fil   = hent_data('arrangementer.json');
        $liste = $fil['arrangementer'] ?? [];
        $id    = $_POST['id'] ?? '';
        $titel = trim($_POST['titel'] ?? '');
        $dato  = $_POST['dato'] ?? '';

        if ($titel === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dato)) {
            saet_besked('Titel og dato skal udfyldes.', 'fejl');
            videre_til('arrangementer');
        }

        $gammel = null;
        foreach ($liste as $a) if (($a['id'] ?? '') === $id) $gammel = $a;

        $billede = $gammel['billede'] ?? null;
        $modtaget = modtag_billede('billede', 'arrangementer', $titel);
        if (isset($modtaget['fejl'])) { saet_besked($modtaget['fejl'], 'fejl'); videre_til('arrangementer'); }
        if (isset($modtaget['sti'])) $billede = $modtaget['sti'];

        $type = $_POST['tilmelding_type'] ?? 'ingen';
        $ny = [
            'id'           => $id ?: $dato . '-' . slug($titel, 30),
            'dato'         => $dato,
            'tid'          => $_POST['tid'] ?? '',
            'titel'        => $titel,
            'resume'       => trim($_POST['resume'] ?? ''),
            'tekst'        => trim($_POST['tekst'] ?? ''),
            'medvirkende'  => trim($_POST['medvirkende'] ?? '') ?: null,
            'sted'         => trim($_POST['sted'] ?? ''),
            'pris'         => trim($_POST['pris'] ?? '') ?: null,
            'tilmelding'   => [
                'type'   => $type,
                'vaerdi' => $type === 'ingen' ? null : (trim($_POST['tilmelding_vaerdi'] ?? '') ?: null),
                'frist'  => $_POST['frist'] ?? null ?: null,
            ],
            'billede'      => $billede,
            'billedtekst'  => trim($_POST['billedtekst'] ?? '') ?: null,
            'aflyst'       => !empty($_POST['aflyst']),
            'synlig'       => !empty($_POST['synlig']),
            'oprettet'     => $gammel['oprettet'] ?? date('Y-m-d'),
            'aendret'      => date('Y-m-d'),
            'aendret_af'   => $_SESSION['bruger'],
        ];

        if ($gammel) {
            foreach ($liste as $i => $a) if (($a['id'] ?? '') === $id) $liste[$i] = $ny;
        } else {
            $liste[] = $ny;
        }
        $fil['arrangementer'] = $liste;

        if (gem_json('arrangementer.json', $fil)) {
            skriv_log(($gammel ? 'rettede' : 'oprettede') . ' arrangementet ' . $ny['titel']);
            saet_besked('Arrangementet er gemt.');
        } else {
            saet_besked('Der kunne ikke skrives til arrangementer.json. Intet er ændret.', 'fejl');
        }
        videre_til('arrangementer');
    }

    /* ---------- Nyhed ---------- */
    if ($handling === 'gem_nyh') {
        $fil   = hent_data('nyheder.json');
        $liste = $fil['nyheder'] ?? [];
        $id    = $_POST['id'] ?? '';
        $titel = trim($_POST['titel'] ?? '');
        $dato  = $_POST['dato'] ?: date('Y-m-d');

        if ($titel === '') { saet_besked('Overskriften skal udfyldes.', 'fejl'); videre_til('nyheder'); }

        $gammel = null;
        foreach ($liste as $n) if (($n['id'] ?? '') === $id) $gammel = $n;

        $billede = $gammel['billede'] ?? null;
        $modtaget = modtag_billede('billede', 'nyheder', $titel);
        if (isset($modtaget['fejl'])) { saet_besked($modtaget['fejl'], 'fejl'); videre_til('nyheder'); }
        if (isset($modtaget['sti'])) $billede = $modtaget['sti'];

        $ny = [
            'id'          => $id ?: $dato . '-' . slug($titel, 30),
            'dato'        => $dato,
            'titel'       => $titel,
            'resume'      => trim($_POST['resume'] ?? ''),
            'tekst'       => trim($_POST['tekst'] ?? ''),
            'forfatter'   => trim($_POST['forfatter'] ?? ''),
            'billede'     => $billede,
            'billedtekst' => trim($_POST['billedtekst'] ?? '') ?: null,
            'fremhaevet'  => !empty($_POST['fremhaevet']),
            'synlig'      => !empty($_POST['synlig']),
            'oprettet'    => $gammel['oprettet'] ?? date('Y-m-d'),
            'aendret'     => date('Y-m-d'),
            'aendret_af'  => $_SESSION['bruger'],
        ];

        if ($gammel) {
            foreach ($liste as $i => $n) if (($n['id'] ?? '') === $id) $liste[$i] = $ny;
        } else {
            $liste[] = $ny;
        }
        $fil['nyheder'] = $liste;

        if (gem_json('nyheder.json', $fil)) {
            skriv_log(($gammel ? 'rettede' : 'skrev') . ' nyheden ' . $ny['titel']);
            saet_besked('Nyheden er gemt.');
        } else {
            saet_besked('Der kunne ikke skrives til nyheder.json. Intet er ændret.', 'fejl');
        }
        videre_til('nyheder');
    }

    /* ---------- Ny side fra en bygger ---------- */
    if ($handling === 'gem_sid') {
        $fil   = hent_data('sider.json');
        $liste = $fil['sider'] ?? [];
        $menu  = $_POST['menu'] ?? '';

        $modtaget = modtag_side('sidefil');
        if ($modtaget === null) { saet_besked('Vælg HTML-filen fra byggeren.', 'fejl'); videre_til('sider'); }
        if (isset($modtaget['fejl'])) { saet_besked($modtaget['fejl'], 'fejl'); videre_til('sider'); }

        $titel = trim($_POST['titel'] ?? '') ?: ($modtaget['titel'] ?: 'Uden titel');

        $under = array_filter($liste, fn($s) => ($s['menu'] ?? '') === $menu);
        $tal   = array_map(fn($s) => (int)($s['raekkefoelge'] ?? 0), $under);
        $plads = $under
            ? (($_POST['placering'] ?? 'nederst') === 'oeverst' ? min($tal) - 10 : max($tal) + 10)
            : 10;

        $liste[] = [
            'id'           => slug($titel, 30) . '-' . substr(bin2hex(random_bytes(2)), 0, 4),
            'titel'        => $titel,
            'menu'         => $menu,
            'raekkefoelge' => $plads,
            'fil'          => $modtaget['sti'],
            'resume'       => trim($_POST['resume'] ?? ''),
            'billede'      => null,
            'synlig'       => !empty($_POST['synlig']),
            'oprettet'     => date('Y-m-d'),
            'aendret'      => date('Y-m-d'),
            'aendret_af'   => $_SESSION['bruger'],
            'vaerktoej'    => trim($_POST['vaerktoej'] ?? '') ?: null,
        ];
        $fil['sider'] = $liste;

        if (gem_json('sider.json', $fil)) {
            skriv_log('lagde siden ' . $titel . ' ind');
            saet_besked('Siden er lagt ind.');
        } else {
            saet_besked('Filen blev gemt, men sider.json kunne ikke opdateres.', 'fejl');
        }
        videre_til('sider');
    }

    /* ---------- Erstat filen bag en side ---------- */
    if ($handling === 'erstat_sid') {
        $fil   = hent_data('sider.json');
        $liste = $fil['sider'] ?? [];
        $id    = $_POST['id'] ?? '';

        $nr = null;
        foreach ($liste as $i => $s) if (($s['id'] ?? '') === $id) $nr = $i;
        if ($nr === null) { saet_besked('Siden findes ikke.', 'fejl'); videre_til('sider'); }

        $navn = basename($liste[$nr]['fil']);
        $modtaget = modtag_side('sidefil', $navn);
        if ($modtaget === null) { saet_besked('Vælg den rettede fil.', 'fejl'); videre_til('sider'); }
        if (isset($modtaget['fejl'])) { saet_besked($modtaget['fejl'], 'fejl'); videre_til('sider'); }

        $liste[$nr]['aendret']    = date('Y-m-d');
        $liste[$nr]['aendret_af'] = $_SESSION['bruger'];
        $fil['sider'] = $liste;
        gem_json('sider.json', $fil);
        skriv_log('erstattede filen bag ' . $liste[$nr]['titel']);
        saet_besked('Filen er erstattet. Den forrige udgave ligger som ' . $navn . '.bak');
        videre_til('sider');
    }

    /* ---------- Vis eller skjul ---------- */
    if ($handling === 'synlig') {
        [$filnavn, $noegle] = noegler($_POST['type'] ?? '');
        $fil   = hent_data($filnavn);
        $liste = $fil[$noegle] ?? [];
        foreach ($liste as $i => $p) {
            if (($p['id'] ?? '') === ($_POST['id'] ?? '')) {
                $liste[$i]['synlig'] = empty($p['synlig']);
                $fil[$noegle] = $liste;
                gem_json($filnavn, $fil);
                skriv_log(($liste[$i]['synlig'] ? 'viste ' : 'skjulte ') . $p['titel']);
                saet_besked($liste[$i]['synlig'] ? 'Vises nu på hjemmesiden.' : 'Er nu skjult for besøgende.');
                break;
            }
        }
        videre_til($_POST['vis'] ?? 'arrangementer');
    }

    /* ---------- Slet ---------- */
    if ($handling === 'slet') {
        [$filnavn, $noegle] = noegler($_POST['type'] ?? '');
        $fil   = hent_data($filnavn);
        $liste = $fil[$noegle] ?? [];
        $titel = '';
        foreach ($liste as $p) if (($p['id'] ?? '') === ($_POST['id'] ?? '')) $titel = $p['titel'] ?? '';

        $fil[$noegle] = array_values(array_filter($liste, fn($p) => ($p['id'] ?? '') !== ($_POST['id'] ?? '')));
        if (gem_json($filnavn, $fil)) {
            skriv_log('slettede ' . $titel);
            saet_besked('Slettet. Den forrige udgave af datafilen ligger som ' . $filnavn . '.bak');
        } else {
            saet_besked('Kunne ikke skrive til ' . $filnavn . '. Intet er slettet.', 'fejl');
        }
        videre_til($_POST['vis'] ?? 'arrangementer');
    }

    videre_til('arrangementer');
}

function noegler($type) {
    return match ($type) {
        'nyhed' => ['nyheder.json', 'nyheder'],
        'side'  => ['sider.json', 'sider'],
        default => ['arrangementer.json', 'arrangementer'],
    };
}

/* ==========================================================
   Visning
   ========================================================== */

$vis     = $_GET['vis'] ?? 'arrangementer';
$rediger = $_GET['rediger'] ?? null;
$besked  = tag_besked();

$alle_arr  = alle_arr();
$alle_nyh  = alle_nyheder();
$alle_sid  = alle_sider();
$idag      = date('Y-m-d');

$kommende = array_filter($alle_arr, fn($a) => ($a['dato'] ?? '') >= $idag);
$afholdte = array_filter($alle_arr, fn($a) => ($a['dato'] ?? '') < $idag);
usort($kommende, fn($a, $b) => strcmp($a['dato'], $b['dato']));
usort($afholdte, fn($a, $b) => strcmp($b['dato'], $a['dato']));
usort($alle_nyh, fn($a, $b) => strcmp($b['dato'], $a['dato']));

function find($liste, $id) {
    foreach ($liste as $p) if (($p['id'] ?? '') === $id) return $p;
    return null;
}
?><!DOCTYPE html>
<html lang="da">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Redigering · <?= e($site['forening']['navn'] ?? '') ?></title>
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
      <a href="?vis=arrangementer" data-farve="terracotta" <?= $vis === 'arrangementer' ? 'aria-current="page"' : '' ?>>
        Arrangementer <span class="antal"><?= count($kommende) ?></span></a>
      <a href="?vis=nyheder" data-farve="salvie" <?= $vis === 'nyheder' ? 'aria-current="page"' : '' ?>>
        Nyheder <span class="antal"><?= count($alle_nyh) ?></span></a>
      <a href="?vis=sider" data-farve="okker" <?= $vis === 'sider' ? 'aria-current="page"' : '' ?>>
        Sider <span class="antal"><?= count($alle_sid) ?></span></a>
      <div class="adskil"></div>
      <a href="?vis=kopi" data-farve="brand" <?= $vis === 'kopi' ? 'aria-current="page"' : '' ?>>Sikkerhedskopi</a>
      <a href="brugere.php" data-farve="brand">Brugere</a>
    </nav>
    <div class="adskil"></div>
    <p class="fod">Ændringer slår igennem med det samme. Den forrige udgave af hver fil
      gemmes automatisk som .bak.</p>
  </div>

  <main>
  <?php if ($besked): ?>
    <p class="besked <?= $besked['type'] === 'fejl' ? 'fejl' : '' ?>" role="status"><?= e($besked['tekst']) ?></p>
  <?php endif; ?>

  <?php
  /* ---------------- Arrangementer ---------------- */
  if ($vis === 'arrangementer'):
      if ($rediger !== null):
          $a = $rediger === 'ny' ? null : find($alle_arr, $rediger);
          $t = $a['tilmelding'] ?? ['type' => 'ingen', 'vaerdi' => '', 'frist' => ''];
  ?>
      <a class="tilbage" href="?vis=arrangementer">‹ Tilbage til listen</a>
      <h1><?= $a ? 'Redigér arrangement' : 'Nyt arrangement' ?></h1>

      <form class="formular" method="post" enctype="multipart/form-data">
        <?= csrf_felt() ?>
        <input type="hidden" name="handling" value="gem_arr">
        <input type="hidden" name="id" value="<?= e($a['id'] ?? '') ?>">

        <div class="felt">
          <label for="titel">Titel</label>
          <input type="text" id="titel" name="titel" required value="<?= e($a['titel'] ?? '') ?>">
        </div>

        <div class="par">
          <div class="felt">
            <label for="dato">Dato</label>
            <input type="date" id="dato" name="dato" required value="<?= e($a['dato'] ?? '') ?>">
          </div>
          <div class="felt">
            <label for="tid">Klokkeslæt</label>
            <input type="time" id="tid" name="tid" value="<?= e($a['tid'] ?? '19:30') ?>">
          </div>
        </div>

        <div class="felt">
          <label for="resume">Kort beskrivelse
            <span class="hjaelp">Én sætning. Den står på forsiden og i kalenderen.</span></label>
          <input type="text" id="resume" name="resume" value="<?= e($a['resume'] ?? '') ?>">
        </div>

        <div class="felt">
          <label for="tekst">Uddybende tekst
            <span class="hjaelp">Du kan bruge **fed**, *kursiv* og lister med bindestreg.</span></label>
          <textarea id="tekst" name="tekst"><?= e($a['tekst'] ?? '') ?></textarea>
        </div>

        <div class="par">
          <div class="felt">
            <label for="medvirkende">Foredragsholder</label>
            <input type="text" id="medvirkende" name="medvirkende" value="<?= e($a['medvirkende'] ?? '') ?>">
          </div>
          <div class="felt">
            <label for="sted">Sted</label>
            <input type="text" id="sted" name="sted" value="<?= e($a['sted'] ?? 'Roskilde Rådhus, Rådhusbuen 1') ?>">
          </div>
        </div>

        <div class="felt">
          <label for="pris">Pris</label>
          <input type="text" id="pris" name="pris" value="<?= e($a['pris'] ?? '') ?>">
        </div>

        <div class="felt">
          <label for="tilmelding_type">Tilmelding</label>
          <select id="tilmelding_type" name="tilmelding_type">
            <option value="ingen" <?= ($t['type'] ?? '') === 'ingen' ? 'selected' : '' ?>>Ingen tilmelding nødvendig</option>
            <option value="email" <?= ($t['type'] ?? '') === 'email' ? 'selected' : '' ?>>Tilmelding på e-mail</option>
            <option value="link"  <?= ($t['type'] ?? '') === 'link'  ? 'selected' : '' ?>>Tilmelding via link</option>
          </select>
        </div>

        <div class="par">
          <div class="felt">
            <label for="tilmelding_vaerdi">E-mail eller adresse
              <span class="hjaelp">Bruges kun hvis der er tilmelding.</span></label>
            <input type="text" id="tilmelding_vaerdi" name="tilmelding_vaerdi"
                   value="<?= e($t['vaerdi'] ?? $site['forening']['email'] ?? '') ?>">
          </div>
          <div class="felt">
            <label for="frist">Tilmeldingsfrist</label>
            <input type="date" id="frist" name="frist" value="<?= e($t['frist'] ?? '') ?>">
          </div>
        </div>

        <div class="felt">
          <label for="billede">Billede</label>
          <div class="filfelt">
            <?php if (!empty($a['billede'])): ?>
              <p class="nuvaerende">Nu: <?= e($a['billede']) ?>. Vælg en ny fil for at erstatte det.</p>
            <?php endif; ?>
            <input type="file" id="billede" name="billede" accept="image/jpeg,image/png,image/webp">
            <span class="hjaelp">jpg, png eller webp. Husk fotograf og årstal i billedteksten.</span>
          </div>
        </div>

        <div class="felt">
          <label for="billedtekst">Billedtekst</label>
          <input type="text" id="billedtekst" name="billedtekst" value="<?= e($a['billedtekst'] ?? '') ?>">
        </div>

        <div class="afkryds">
          <input type="checkbox" id="synlig" name="synlig" <?= ($a === null || !empty($a['synlig'])) ? 'checked' : '' ?>>
          <label for="synlig">Vis arrangementet på hjemmesiden</label>
        </div>
        <div class="afkryds">
          <input type="checkbox" id="aflyst" name="aflyst" <?= !empty($a['aflyst']) ? 'checked' : '' ?>>
          <label for="aflyst">Aflyst
            <span class="hjaelp">Arrangementet bliver stående, men markeres tydeligt som aflyst.</span></label>
        </div>

        <div class="gem-raekke">
          <button type="submit" class="knap">Gem arrangement</button>
          <a class="knap stille" href="?vis=arrangementer">Fortryd</a>
        </div>
      </form>

  <?php else: ?>
      <div class="hoved">
        <div>
          <h1>Arrangementer</h1>
          <p>De tre næste vises på forsiden. Når datoen er passeret, flytter arrangementet
             selv ned i arkivet.</p>
        </div>
        <a class="knap" href="?vis=arrangementer&amp;rediger=ny">Opret arrangement</a>
      </div>

      <div class="liste">
        <?php if (!$kommende): ?>
          <div class="tom">Der er ingen kommende arrangementer. Opret det første, så kommer
            det på forsiden med det samme.</div>
        <?php endif; ?>
        <?php foreach ($kommende as $nr => $a): ?>
          <?php vis_post($a, 'arr', 'arrangementer', $nr < 3); ?>
        <?php endforeach; ?>
      </div>

      <?php if ($afholdte): ?>
        <details class="arkiv">
          <summary>Afholdte arrangementer (<?= count($afholdte) ?>)</summary>
          <div class="liste">
            <?php foreach ($afholdte as $a) vis_post($a, 'arr', 'arrangementer', false); ?>
          </div>
        </details>
      <?php endif; ?>
  <?php endif; ?>

  <?php
  /* ---------------- Nyheder ---------------- */
  elseif ($vis === 'nyheder'):
      if ($rediger !== null):
          $n = $rediger === 'ny' ? null : find($alle_nyh, $rediger);
  ?>
      <a class="tilbage" href="?vis=nyheder">‹ Tilbage til listen</a>
      <h1><?= $n ? 'Redigér nyhed' : 'Ny nyhed' ?></h1>

      <form class="formular" method="post" enctype="multipart/form-data">
        <?= csrf_felt() ?>
        <input type="hidden" name="handling" value="gem_nyh">
        <input type="hidden" name="id" value="<?= e($n['id'] ?? '') ?>">

        <div class="felt">
          <label for="titel">Overskrift</label>
          <input type="text" id="titel" name="titel" required value="<?= e($n['titel'] ?? '') ?>">
        </div>

        <div class="par">
          <div class="felt">
            <label for="dato">Dato</label>
            <input type="date" id="dato" name="dato" value="<?= e($n['dato'] ?? date('Y-m-d')) ?>">
          </div>
          <div class="felt">
            <label for="forfatter">Skrevet af</label>
            <input type="text" id="forfatter" name="forfatter" value="<?= e($n['forfatter'] ?? bruger_navn()) ?>">
          </div>
        </div>

        <div class="felt">
          <label for="resume">Manchet
            <span class="hjaelp">Én sætning under overskriften på forsiden.</span></label>
          <input type="text" id="resume" name="resume" value="<?= e($n['resume'] ?? '') ?>">
        </div>

        <div class="felt">
          <label for="tekst">Tekst
            <span class="hjaelp">Samme skrivemåde som i byggerne: **fed**, *kursiv*, lister og links.</span></label>
          <textarea id="tekst" name="tekst"><?= e($n['tekst'] ?? '') ?></textarea>
        </div>

        <div class="felt">
          <label for="billede">Billede</label>
          <div class="filfelt">
            <?php if (!empty($n['billede'])): ?>
              <p class="nuvaerende">Nu: <?= e($n['billede']) ?>. Vælg en ny fil for at erstatte det.</p>
            <?php endif; ?>
            <input type="file" id="billede" name="billede" accept="image/jpeg,image/png,image/webp">
          </div>
        </div>

        <div class="felt">
          <label for="billedtekst">Billedtekst</label>
          <input type="text" id="billedtekst" name="billedtekst" value="<?= e($n['billedtekst'] ?? '') ?>">
        </div>

        <div class="afkryds">
          <input type="checkbox" id="fremhaevet" name="fremhaevet" <?= !empty($n['fremhaevet']) ? 'checked' : '' ?>>
          <label for="fremhaevet">Fremhæv øverst på forsiden</label>
        </div>
        <div class="afkryds">
          <input type="checkbox" id="synlig" name="synlig" <?= ($n === null || !empty($n['synlig'])) ? 'checked' : '' ?>>
          <label for="synlig">Vis nyheden på hjemmesiden</label>
        </div>

        <div class="gem-raekke">
          <button type="submit" class="knap">Gem nyhed</button>
          <a class="knap stille" href="?vis=nyheder">Fortryd</a>
        </div>
      </form>

  <?php else: ?>
      <div class="hoved">
        <div>
          <h1>Nyheder</h1>
          <p>Den nyeste vises øverst på forsiden. Sæt flueben ved “fremhæv”, hvis en nyhed
             skal blive liggende øverst.</p>
        </div>
        <a class="knap" href="?vis=nyheder&amp;rediger=ny">Skriv nyhed</a>
      </div>

      <div class="liste">
        <?php if (!$alle_nyh): ?>
          <div class="tom">Der er ingen nyheder endnu.</div>
        <?php endif; ?>
        <?php foreach ($alle_nyh as $n) vis_post($n, 'nyhed', 'nyheder', false); ?>
      </div>
  <?php endif; ?>

  <?php
  /* ---------------- Sider ---------------- */
  elseif ($vis === 'sider'):
      if ($rediger === 'ny'):
  ?>
      <a class="tilbage" href="?vis=sider">‹ Tilbage til listen</a>
      <h1>Tilføj side</h1>

      <form class="formular" method="post" enctype="multipart/form-data">
        <?= csrf_felt() ?>
        <input type="hidden" name="handling" value="gem_sid">

        <div class="felt">
          <label for="sidefil">HTML-filen fra byggeren</label>
          <div class="filfelt">
            <input type="file" id="sidefil" name="sidefil" accept=".html,text/html" required>
            <span class="hjaelp">Er titlen tom nedenfor, hentes den ud af filens egne metadata.</span>
          </div>
        </div>

        <div class="felt">
          <label for="titel">Titel i menuen</label>
          <input type="text" id="titel" name="titel">
        </div>

        <div class="par">
          <div class="felt">
            <label for="menu">Hører under</label>
            <select id="menu" name="menu">
              <?php foreach (menupunkter() as $m): if (($m['type'] ?? '') === 'system') continue; ?>
                <option value="<?= e($m['id']) ?>"><?= e($m['titel']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="felt">
            <label for="placering">Placering</label>
            <select id="placering" name="placering">
              <option value="nederst">Nederst i menupunktet</option>
              <option value="oeverst">Øverst i menupunktet</option>
            </select>
          </div>
        </div>

        <div class="felt">
          <label for="resume">Kort beskrivelse
            <span class="hjaelp">Vises i oversigten over sider under menupunktet.</span></label>
          <input type="text" id="resume" name="resume">
        </div>

        <div class="felt">
          <label for="vaerktoej">Lavet i
            <span class="hjaelp">Fx Sidebygger 2.1. Står kun til jeres eget overblik.</span></label>
          <input type="text" id="vaerktoej" name="vaerktoej" value="Sidebygger 2.1">
        </div>

        <div class="afkryds">
          <input type="checkbox" id="synlig" name="synlig" checked>
          <label for="synlig">Vis siden i menuen
            <span class="hjaelp">Fjern fluebenet, hvis siden skal ligge klar men ikke være fremme endnu.</span></label>
        </div>

        <div class="gem-raekke">
          <button type="submit" class="knap">Læg siden ind</button>
          <a class="knap stille" href="?vis=sider">Fortryd</a>
        </div>
      </form>

  <?php else: ?>
      <div class="hoved">
        <div>
          <h1>Sider</h1>
          <p>Sider laves i Sidebygger. Vil du rette en side, henter du filen, åbner den i
             byggeren og lægger den ind igen.</p>
        </div>
        <a class="knap" href="?vis=sider&amp;rediger=ny">Tilføj side</a>
      </div>

      <?php foreach (menupunkter() as $m):
        if (($m['type'] ?? '') === 'system') continue;
        $under = array_filter($alle_sid, fn($s) => ($s['menu'] ?? '') === $m['id']);
        usort($under, fn($x, $y) => ($x['raekkefoelge'] ?? 0) <=> ($y['raekkefoelge'] ?? 0));
      ?>
        <div class="gruppe">
          <h2><?= e($m['titel']) ?></h2>
          <div class="liste">
            <?php if (!$under): ?>
              <div class="tom">Ingen sider under dette menupunkt endnu.</div>
            <?php endif; ?>
            <?php foreach ($under as $s): ?>
              <div class="post <?= empty($s['synlig']) ? 'skjult' : '' ?>">
                <div class="krop">
                  <h3><a href="../<?= e($s['fil']) ?>" target="_blank" rel="noopener"><?= e($s['titel']) ?></a>
                    <?= empty($s['synlig']) ? '<span class="maerke skjult">Ikke i menuen</span>' : '' ?></h3>
                  <p class="meta"><?= e($s['fil']) ?>
                    <?= !empty($s['vaerktoej']) ? '· lavet i ' . e($s['vaerktoej']) : '' ?>
                    · rettet <?= e(dansk_dato($s['aendret'] ?? '', true)) ?> af <?= e($s['aendret_af'] ?? '') ?></p>
                </div>
                <div class="handling">
                  <a class="knap stille lille" href="hent.php?fil=<?= urlencode(basename($s['fil'])) ?>">Hent til redigering</a>
                  <form method="post" enctype="multipart/form-data" class="erstat">
                    <?= csrf_felt() ?>
                    <input type="hidden" name="handling" value="erstat_sid">
                    <input type="hidden" name="id" value="<?= e($s['id']) ?>">
                    <label class="knap stille lille" for="erstat-<?= e($s['id']) ?>">Erstat fil</label>
                    <input type="file" id="erstat-<?= e($s['id']) ?>" name="sidefil" accept=".html,text/html"
                           onchange="this.form.submit()">
                  </form>
                  <?php knap_synlig($s, 'side', 'sider'); ?>
                  <?php knap_slet($s, 'side', 'sider'); ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
  <?php endif; ?>

  <?php
  /* ---------------- Sikkerhedskopi ---------------- */
  else: ?>
      <div class="hoved">
        <div>
          <h1>Sikkerhedskopi</h1>
          <p>Alt indhold ligger som almindelige filer. Der skal ikke bruges særligt udstyr
             for at læse en kopi.</p>
        </div>
      </div>

      <div class="kasse">
        <h2>Hent hele hjemmesiden</h2>
        <p>Én zip-fil med alle sider, billeder og datafiler. Den kan pakkes ud og åbnes
           i en browser, også uden internet.</p>
        <a class="knap" href="hent.php?alt=1">Hent som zip</a>
        <a class="knap stille" href="hent.php?data=1">Hent kun datafilerne</a>
      </div>

      <div class="kasse">
        <h2>Seneste ændringer</h2>
        <p>De sidste 30 ændringer. Den forrige udgave af hver datafil ligger ved siden af
           den nuværende med .bak efter navnet.</p>
        <?php $log = hent_log(); ?>
        <?php if ($log): ?>
          <ul class="log">
            <?php foreach ($log as $l): ?>
              <li>
                <time><?= e(date('j. M H:i', strtotime($l['tid']))) ?></time>
                <span><?= e($l['bruger']) ?> <?= e($l['tekst']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="meta">Der er ikke registreret nogen ændringer endnu.</p>
        <?php endif; ?>
      </div>
  <?php endif; ?>
  </main>
</div>

<?php
/* ---------- Små byggeklodser til listerne ---------- */

function vis_post($p, $type, $vis, $paa_forsiden) {
    $er_arr = $type === 'arr';
    $synlig = !empty($p['synlig']);

    /* Hvor ligger den ude på hjemmesiden? Arrangementer har ikke deres egen
       side, men et anker på programsiden. Skjulte nyheder kan forhåndsvises. */
    if ($er_arr) {
        $link = '../arrangement.php?id=' . urlencode($p['id']) . ($synlig ? '' : '&forhaandsvis=1');
    } else {
        $link = '../nyheder.php?id=' . urlencode($p['id']) . ($synlig ? '' : '&forhaandsvis=1');
    }
    ?>
    <div class="post <?= $synlig ? '' : 'skjult' ?>">
      <div class="dato-flise">
        <span class="dag"><?= e(dag_tal($p['dato'] ?? '')) ?></span>
        <span class="maaned"><?= e(maaned_kort($p['dato'] ?? '')) ?></span>
      </div>
      <div class="krop">
        <h3>
          <?php if ($link): ?>
            <a href="<?= e($link) ?>" target="_blank" rel="noopener"><?= e($p['titel'] ?? '') ?></a>
          <?php else: ?>
            <?= e($p['titel'] ?? '') ?>
          <?php endif; ?>
          <?= !empty($p['aflyst']) ? '<span class="maerke aflyst">Aflyst</span>' : '' ?>
          <?= !empty($p['fremhaevet']) ? '<span class="maerke fremhaevet">Fremhævet</span>' : '' ?>
          <?= $synlig ? '' : '<span class="maerke skjult">Skjult</span>' ?>
        </h3>
        <p class="under"><?= e($p['resume'] ?? '') ?></p>
        <p class="meta">
          <?php if ($er_arr): ?>
            <?= !empty($p['tid']) ? 'kl. ' . e($p['tid']) . ' · ' : '' ?><?= e($p['sted'] ?? '') ?>
            <?= !empty($p['medvirkende']) ? ' · ' . e($p['medvirkende']) : '' ?>
            <?= $paa_forsiden ? ' · står på forsiden' : '' ?>
          <?php else: ?>
            <?= e($p['forfatter'] ?? '') ?>
          <?php endif; ?>
          · rettet <?= e(dansk_dato($p['aendret'] ?? '', true)) ?> af <?= e($p['aendret_af'] ?? '') ?>
        </p>
      </div>
      <div class="handling">
        <a class="knap stille lille" href="?vis=<?= e($vis) ?>&amp;rediger=<?= urlencode($p['id']) ?>">Redigér</a>
        <?php if ($link): ?>
          <a class="knap stille lille" href="<?= e($link) ?>" target="_blank" rel="noopener">Se</a>
        <?php endif; ?>
        <?php knap_synlig($p, $type === 'arr' ? 'arrangement' : $type, $vis); ?>
        <?php knap_slet($p, $type === 'arr' ? 'arrangement' : $type, $vis); ?>
      </div>
    </div>
    <?php
}

function knap_synlig($p, $type, $vis) { ?>
  <form method="post">
    <?= csrf_felt() ?>
    <input type="hidden" name="handling" value="synlig">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <input type="hidden" name="vis" value="<?= e($vis) ?>">
    <input type="hidden" name="id" value="<?= e($p['id']) ?>">
    <button type="submit" class="knap stille lille"><?= empty($p['synlig']) ? 'Vis' : 'Skjul' ?></button>
  </form>
<?php }

function knap_slet($p, $type, $vis) { ?>
  <form method="post" onsubmit="return confirm('Slet «<?= e(addslashes($p['titel'] ?? '')) ?>»?\n\nDer gemmes en kopi af datafilen, som kan hentes frem igen.')">
    <?= csrf_felt() ?>
    <input type="hidden" name="handling" value="slet">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <input type="hidden" name="vis" value="<?= e($vis) ?>">
    <input type="hidden" name="id" value="<?= e($p['id']) ?>">
    <button type="submit" class="knap fare lille">Slet</button>
  </form>
<?php }
?>

</body>
</html>
