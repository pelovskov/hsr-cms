<?php
require_once __DIR__ . '/inc/funktioner.php';
$site = site();
$aktiv = '';
$id = $_GET['id'] ?? '';

/* Er man logget ind i admin, kan man også slå en skjult nyhed op. Det bruges
   til at se en nyhed igennem, før den sættes synlig. */
$forhaandsvis = false;
if ($id && !empty($_GET['forhaandsvis'])) {
    require_once __DIR__ . '/inc/admin-funktioner.php';
    $forhaandsvis = er_logget_ind();
}

$enkelt = $id ? nyhed($id, $forhaandsvis) : null;

if ($id && !$enkelt) {
    http_response_code(404);
}

$sidetitel = $enkelt ? $enkelt['titel'] : 'Nyheder';
require __DIR__ . '/inc/hoved.php';
?>

<?php if ($id && !$enkelt): ?>

  <div class="indre">
    <div class="artikel">
      <h1>Nyheden findes ikke</h1>
      <p>Den er måske taget ned igen. Prøv oversigten over nyheder i stedet.</p>
      <a class="knap" href="nyheder.php">Se alle nyheder</a>
    </div>
  </div>

<?php elseif ($enkelt): ?>

  <div class="indre">
    <?php if ($forhaandsvis && empty($enkelt['synlig'])): ?>
      <p class="forhaandsvisning">Forhåndsvisning. Nyheden er skjult og kan ikke ses af besøgende.</p>
    <?php endif; ?>
    <article class="artikel">
      <p class="dato"><?= e(dansk_dato($enkelt['dato'])) ?></p>
      <h1><?= e($enkelt['titel']) ?></h1>
      <?php if (!empty($enkelt['resume'])): ?>
        <p class="manchet"><?= e($enkelt['resume']) ?></p>
      <?php endif; ?>

      <?php if (!empty($enkelt['billede'])): ?>
        <figure>
          <?= billede($enkelt['billede'], $enkelt['billedtekst'] ?? $enkelt['titel']) ?>
          <?php if (!empty($enkelt['billedtekst'])): ?>
            <figcaption><?= e($enkelt['billedtekst']) ?></figcaption>
          <?php endif; ?>
        </figure>
      <?php endif; ?>

      <?= markdown($enkelt['tekst'] ?? '') ?>

      <?php if (!empty($enkelt['forfatter'])): ?>
        <p class="praktisk">Skrevet af <?= e($enkelt['forfatter']) ?></p>
      <?php endif; ?>

      <a class="tilbage-link" href="nyheder.php">‹ Alle nyheder</a>
    </article>
  </div>

<?php else: ?>

  <section class="hero">
    <div class="indre">
      <p class="over">Nyheder</p>
      <h1>Nyt fra foreningen</h1>
    </div>
  </section>

  <section class="sektion">
    <div class="indre">
      <?php $liste = nyheder_sorteret(); ?>
      <?php if ($liste): ?>
        <div class="nyheds-raekke">
          <?php foreach ($liste as $n): ?>
            <article class="nyhed-kort">
              <p class="dato"><?= e(dansk_dato($n['dato'], true)) ?></p>
              <h3><a href="nyheder.php?id=<?= urlencode($n['id']) ?>"><?= e($n['titel']) ?></a></h3>
              <p><?= e($n['resume'] ?? '') ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="tom">Der er ingen nyheder endnu.</div>
      <?php endif; ?>
    </div>
  </section>

<?php endif; ?>

<?php require __DIR__ . '/inc/fod.php'; ?>
