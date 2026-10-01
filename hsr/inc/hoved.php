<?php
require_once __DIR__ . '/funktioner.php';
$site = site();
$f = $site['forening'] ?? [];
$sidetitel = $sidetitel ?? ($f['navn'] ?? '');
$aktiv = $aktiv ?? '';
?><!DOCTYPE html>
<html lang="da">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($sidetitel) ?><?= $sidetitel !== ($f['navn'] ?? '') ? ' · ' . e($f['navn'] ?? '') : '' ?></title>
<meta name="description" content="<?= e($site['fod']['om'] ?? '') ?>">
<link rel="stylesheet" href="assets/style.css">
<script>
/* Køres før siden tegnes, så en gemt indstilling ikke blinker hvidt først. */
try {
  if (localStorage.getItem('hsr-moerk') === '1') document.documentElement.classList.add('moerk');
  var gemtTekst = localStorage.getItem('hsr-tekst');
  if (gemtTekst) document.documentElement.style.fontSize = gemtTekst + '%';
} catch (e) {}
</script>
</head>
<body>

<a class="spring-over" href="#indhold">Spring til indhold</a>

<div class="tilgang">
  <div class="indre">
    <div class="gruppe">
      <span class="etiket">Tekststørrelse</span>
      <button type="button" id="tekst-mindre" aria-label="Mindre tekst">A&minus;</button>
      <button type="button" id="tekst-stoerre" aria-label="Større tekst">A+</button>
    </div>
    <button type="button" id="moerk" aria-pressed="false">Mørk visning</button>
  </div>
</div>

<header class="top">
  <div class="indre">
    <a class="logo" href="index.php">
      <span class="maerke" aria-hidden="true">H</span>
      <span class="navn"><?= e($f['navn'] ?? '') ?><small><?= e($f['undertitel'] ?? '') ?></small></span>
    </a>

    <button type="button" class="menu-knap" id="menu-knap" aria-expanded="false" aria-controls="hovedmenu">
      Menu
    </button>

    <nav id="hovedmenu" aria-label="Hovedmenu">
      <?php foreach (menupunkter() as $m):
        $sti = ($m['type'] ?? '') === 'system'
             ? ($m['sti'] ?? 'index.php')
             : 'menu.php?m=' . urlencode($m['id']);
        $er_aktiv = ($aktiv === $m['id']);
      ?>
        <a href="<?= e($sti) ?>"<?= $er_aktiv ? ' aria-current="page"' : '' ?>><?= e($m['titel']) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>

<main id="indhold">
