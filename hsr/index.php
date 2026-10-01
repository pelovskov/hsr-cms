<?php
require_once __DIR__ . '/inc/funktioner.php';
$site = site();
$fs = $site['forside'] ?? [];
$aktiv = 'forside';
$sidetitel = $site['forening']['navn'] ?? '';
require __DIR__ . '/inc/hoved.php';

$kommende  = kommende_arr(3);
$nyheder   = nyheder_sorteret(3);
$fremhaev  = side_med_id($fs['fremhaevet_side'] ?? '');
?>

<?php $hero_billede = billede($fs['billede'] ?? null, $fs['billedtekst'] ?? ($fs['rubrik'] ?? ''), 'hero-foto'); ?>
<section class="hero <?= $hero_billede ? 'med-billede' : '' ?>">
  <div class="indre">
    <div class="hero-tekst">
      <p class="over"><?= e($fs['overrubrik'] ?? '') ?></p>
      <h1><?= e($fs['rubrik'] ?? '') ?></h1>
      <p class="manchet"><?= e($fs['manchet'] ?? '') ?></p>
      <div class="knapper">
        <a class="knap" href="<?= e($fs['knap_primaer']['sti'] ?? '#') ?>"><?= e($fs['knap_primaer']['tekst'] ?? '') ?></a>
        <a class="knap stille" href="<?= e($fs['knap_sekundaer']['sti'] ?? '#') ?>"><?= e($fs['knap_sekundaer']['tekst'] ?? '') ?></a>
      </div>
    </div>

    <?php if ($hero_billede): ?>
      <figure class="hero-figur">
        <?= $hero_billede ?>
        <?php if (!empty($fs['billedtekst'])): ?>
          <figcaption><?= e($fs['billedtekst']) ?></figcaption>
        <?php endif; ?>
      </figure>
    <?php endif; ?>
  </div>
</section>

<section class="sektion">
  <div class="indre">
    <div class="sektion-hoved">
      <h2>Næste arrangementer</h2>
      <a href="arrangementer.php">Se hele programmet</a>
    </div>

    <?php if ($kommende): ?>
      <div class="arr-raekke">
        <?php foreach ($kommende as $a): ?>
          <article class="arr-kort">
            <p class="dato"><?= e(dansk_dato($a['dato'], true)) ?><?php if (!empty($a['tid'])): ?> kl. <?= e($a['tid']) ?><?php endif; ?></p>
            <h3><a href="arrangement.php?id=<?= urlencode($a['id']) ?>"><?= e($a['titel']) ?></a><?= !empty($a['aflyst']) ? '<span class="maerke-aflyst">Aflyst</span>' : '' ?></h3>
            <p><?= e($a['resume'] ?? '') ?></p>
            <p class="praktisk">
              <?= e($a['sted'] ?? '') ?><?php if (!empty($a['medvirkende'])): ?><br><?= e($a['medvirkende']) ?><?php endif; ?>
            </p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="tom">Der er ikke flere arrangementer i kalenderen lige nu. Programmet for næste sæson lægges op i august.</div>
    <?php endif; ?>
  </div>
</section>

<?php if ($fremhaev):
  /* Billedet skal være der i virkeligheden, ikke bare stå i sider.json.
     Ellers står der en tom mørk flade, hvor fotoet skulle have været. */
  $fremhaev_billede = billede($fremhaev['billede'] ?? null, $fremhaev['titel']);
  $maerkat = $fs['fremhaevet_maerkat'] ?? $fs['fremhaevet_mærkat'] ?? '';
?>
<section class="sektion">
  <div class="indre">
    <div class="fremhaevet <?= $fremhaev_billede ? '' : 'uden-billede' ?>">
      <div class="tekst">
        <?php if ($maerkat !== ''): ?>
          <p class="over"><?= e($maerkat) ?></p>
        <?php endif; ?>
        <h2><?= e($fremhaev['titel']) ?></h2>
        <p><?= e($fremhaev['resume'] ?? '') ?></p>
        <p><a class="knap" href="<?= e(side_url($fremhaev['fil'])) ?>">Åbn siden</a></p>
      </div>
      <?php if ($fremhaev_billede): ?>
        <figure><?= $fremhaev_billede ?></figure>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="sektion">
  <div class="indre">
    <div class="sektion-hoved">
      <h2>Nyt fra foreningen</h2>
      <a href="nyheder.php">Alle nyheder</a>
    </div>

    <?php if ($nyheder): ?>
      <div class="nyheds-raekke">
        <?php foreach ($nyheder as $n): ?>
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

<section class="sektion">
  <div class="indre">
    <div class="sektion-hoved">
      <h2>Viden &amp; arkiv</h2>
      <a href="menu.php?m=viden">Gå i arkivet</a>
    </div>
    <div class="arkivtal">
      <?php foreach ($site['arkivtal'] ?? [] as $k): ?>
        <a href="<?= e($k['sti']) ?>">
          <span class="tal"><?= e($k['tal']) ?></span>
          <span class="titel"><?= e($k['titel']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/inc/fod.php'; ?>
