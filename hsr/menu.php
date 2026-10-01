<?php
require_once __DIR__ . '/inc/funktioner.php';
$site = site();

$m = $_GET['m'] ?? '';
$punkt = null;
foreach (menupunkter() as $p) {
    if (($p['id'] ?? '') === $m && ($p['type'] ?? '') !== 'system') { $punkt = $p; break; }
}

if (!$punkt) {
    http_response_code(404);
    $aktiv = '';
    $sidetitel = 'Siden findes ikke';
    require __DIR__ . '/inc/hoved.php';
    echo '<div class="indre"><div class="artikel"><h1>Siden findes ikke</h1>'
       . '<p>Menupunktet er måske omdøbt. Prøv menuen foroven.</p>'
       . '<a class="knap" href="index.php">Til forsiden</a></div></div>';
    require __DIR__ . '/inc/fod.php';
    exit;
}

$aktiv = $punkt['id'];
$sidetitel = $punkt['titel'];
require __DIR__ . '/inc/hoved.php';

$sider = sider_under($punkt['id']);
$hjem  = '../menu.php?m=' . rawurlencode($punkt['id']);
?>

<section class="hero">
  <div class="indre">
    <h1><?= e($punkt['titel']) ?></h1>
  </div>
</section>

<section class="sektion">
  <div class="indre">
    <?php if ($sider): ?>
      <div class="side-liste">
        <?php foreach ($sider as $s): ?>
          <article class="side-kort">
            <?php if (!empty($s['billede'])): ?>
              <?= billede($s['billede'], $s['titel']) ?>
            <?php endif; ?>
            <div class="krop">
              <h3><a href="<?= e(side_url($s['fil'], $hjem)) ?>"><?= e($s['titel']) ?></a></h3>
              <p><?= e($s['resume'] ?? '') ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="tom">Der er endnu ikke lagt sider ind under <?= e($punkt['titel']) ?>.</div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/inc/fod.php'; ?>
