<?php
require_once __DIR__ . '/inc/funktioner.php';
$site = site();
$aktiv = 'det-sker';

$id = $_GET['id'] ?? '';

/* Er man logget ind i admin, kan et skjult arrangement ses igennem først. */
$forhaandsvis = false;
if ($id && !empty($_GET['forhaandsvis'])) {
    require_once __DIR__ . '/inc/admin-funktioner.php';
    $forhaandsvis = er_logget_ind();
}

$a = $id ? arrangement($id, $forhaandsvis) : null;
if (!$a) http_response_code(404);

$sidetitel = $a ? $a['titel'] : 'Arrangementet findes ikke';
require __DIR__ . '/inc/hoved.php';
?>

<?php if (!$a): ?>

  <div class="indre">
    <div class="artikel">
      <h1>Arrangementet findes ikke</h1>
      <p>Det er måske aflyst og taget ned igen. Prøv programmet i stedet.</p>
      <a class="knap" href="arrangementer.php">Se programmet</a>
    </div>
  </div>

<?php else: ?>

  <div class="indre">
    <?php if ($forhaandsvis && empty($a['synlig'])): ?>
      <p class="forhaandsvisning">Forhåndsvisning. Arrangementet er skjult og kan ikke ses af besøgende.</p>
    <?php endif; ?>

    <article class="artikel">
      <p class="dato dato-arr">
        <?= e(dansk_dato($a['dato'])) ?><?= !empty($a['tid']) ? ' kl. ' . e($a['tid']) : '' ?>
      </p>
      <h1><?= e($a['titel']) ?><?= !empty($a['aflyst']) ? '<span class="maerke-aflyst">Aflyst</span>' : '' ?></h1>

      <?php if (!empty($a['resume'])): ?>
        <p class="manchet"><?= e($a['resume']) ?></p>
      <?php endif; ?>

      <?php if (!empty($a['billede'])): ?>
        <figure>
          <?= billede($a['billede'], $a['billedtekst'] ?? $a['titel']) ?>
          <?php if (!empty($a['billedtekst'])): ?>
            <figcaption><?= e($a['billedtekst']) ?></figcaption>
          <?php endif; ?>
        </figure>
      <?php endif; ?>

      <?= markdown($a['tekst'] ?? '') ?>

      <dl class="praktisk-liste">
        <dt>Hvornår</dt>
        <dd><?= e(dansk_dato($a['dato'])) ?><?= !empty($a['tid']) ? ' kl. ' . e($a['tid']) : '' ?></dd>

        <?php if (!empty($a['sted'])): ?>
          <dt>Hvor</dt><dd><?= e($a['sted']) ?></dd>
        <?php endif; ?>

        <?php if (!empty($a['medvirkende'])): ?>
          <dt>Medvirkende</dt><dd><?= e($a['medvirkende']) ?></dd>
        <?php endif; ?>

        <?php if (!empty($a['pris'])): ?>
          <dt>Pris</dt><dd><?= e($a['pris']) ?></dd>
        <?php endif; ?>

        <?php
          $t = $a['tilmelding'] ?? ['type' => 'ingen'];
          if (($t['type'] ?? 'ingen') === 'ingen'):
        ?>
          <dt>Tilmelding</dt><dd>Ikke nødvendig</dd>
        <?php elseif (!empty($t['frist'])): ?>
          <dt>Tilmeldingsfrist</dt><dd><?= e(dansk_dato($t['frist'])) ?></dd>
        <?php endif; ?>
      </dl>

      <?php
        if (empty($a['aflyst']) && ($t['type'] ?? 'ingen') !== 'ingen' && !empty($t['vaerdi'])):
          $href = $t['type'] === 'email'
                ? 'mailto:' . $t['vaerdi'] . '?subject=' . rawurlencode('Tilmelding: ' . $a['titel'])
                : $t['vaerdi'];
      ?>
        <p><a class="knap" href="<?= e($href) ?>">Tilmeld dig</a></p>
      <?php endif; ?>

      <a class="tilbage-link" href="arrangementer.php">‹ Hele programmet</a>
    </article>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/inc/fod.php'; ?>
