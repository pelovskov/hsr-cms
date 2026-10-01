<?php
require_once __DIR__ . '/inc/funktioner.php';
$site = site();
$aktiv = 'det-sker';
$sidetitel = 'Det Sker';
require __DIR__ . '/inc/hoved.php';

$kommende = kommende_arr();
$afholdte = afholdte_arr(12);
?>

<section class="hero">
  <div class="indre">
    <p class="over">Aktiviteter</p>
    <h1>Foredrag og udflugter</h1>
    <p class="manchet">Foredragene holdes som regel på Roskilde Rådhus og er gratis for medlemmer. Alle er velkomne.</p>
  </div>
</section>

<section class="sektion">
  <div class="indre">
    <div class="sektion-hoved"><h2>Kommende</h2></div>

    <?php if ($kommende): foreach ($kommende as $a): ?>
      <article class="arr-post" id="a-<?= e($a['id'] ?? '') ?>">
        <div class="dato-flise">
          <span class="dag"><?= e(dag_tal($a['dato'])) ?></span>
          <span class="maaned"><?= e(maaned_kort($a['dato'])) ?></span>
        </div>
        <div class="krop">
          <h3><a href="arrangement.php?id=<?= urlencode($a['id']) ?>"><?= e($a['titel']) ?></a><?= !empty($a['aflyst']) ? '<span class="maerke-aflyst">Aflyst</span>' : '' ?></h3>
          <p><?= e($a['resume'] ?? '') ?></p>
          <p class="praktisk">
            <?= e(dansk_dato($a['dato'])) ?><?php if (!empty($a['tid'])): ?> kl. <?= e($a['tid']) ?><?php endif; ?>
            · <?= e($a['sted'] ?? '') ?>
            <?php if (!empty($a['medvirkende'])): ?> · <?= e($a['medvirkende']) ?><?php endif; ?>
            <?php if (!empty($a['pris'])): ?><br><?= e($a['pris']) ?><?php endif; ?>
          </p>
        </div>
        <?php
          $t = $a['tilmelding'] ?? ['type' => 'ingen'];
          if (empty($a['aflyst']) && ($t['type'] ?? 'ingen') !== 'ingen' && !empty($t['vaerdi'])):
            $href = $t['type'] === 'email' ? 'mailto:' . $t['vaerdi'] . '?subject=' . rawurlencode('Tilmelding: ' . $a['titel']) : $t['vaerdi'];
        ?>
          <div class="tilmeld"><a class="knap" href="<?= e($href) ?>">Tilmeld</a></div>
        <?php endif; ?>
      </article>
    <?php endforeach; else: ?>
      <div class="tom">Der er ingen arrangementer i kalenderen lige nu.</div>
    <?php endif; ?>
  </div>
</section>

<?php if ($afholdte): ?>
<section class="sektion">
  <div class="indre">
    <div class="sektion-hoved"><h2>Tidligere arrangementer</h2></div>
    <?php foreach ($afholdte as $a): ?>
      <article class="arr-post" id="a-<?= e($a['id'] ?? '') ?>">
        <div class="dato-flise">
          <span class="dag"><?= e(dag_tal($a['dato'])) ?></span>
          <span class="maaned"><?= e(maaned_kort($a['dato'])) ?></span>
        </div>
        <div class="krop">
          <h3><a href="arrangement.php?id=<?= urlencode($a['id']) ?>"><?= e($a['titel']) ?></a></h3>
          <p><?= e($a['resume'] ?? '') ?></p>
          <p class="praktisk"><?= e(dansk_dato($a['dato'])) ?> · <?= e($a['sted'] ?? '') ?></p>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/inc/fod.php'; ?>
