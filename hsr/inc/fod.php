</main>

<footer class="fod">
  <div class="indre">
    <div class="spalte">
      <h2><?= e($site['forening']['kortnavn'] ?? '') ?></h2>
      <p><?= e($site['fod']['om'] ?? '') ?></p>
      <?php if (!empty($site['forening']['facebook'])): ?>
        <p><a href="<?= e($site['forening']['facebook']) ?>">Følg os på Facebook</a></p>
      <?php endif; ?>
    </div>

    <div class="spalte">
      <h2>Genveje</h2>
      <ul>
        <?php foreach ($site['fod']['genveje'] ?? [] as $g): ?>
          <li><a href="<?= e(str_starts_with($g['sti'], 'sider/') ? side_url($g['sti']) : $g['sti']) ?>"><?= e($g['titel']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="spalte">
      <h2>Kontakt</h2>
      <p>
        <a href="mailto:<?= e($site['forening']['email'] ?? '') ?>"><?= e($site['forening']['email'] ?? '') ?></a>
      </p>
      <?php if (!empty($site['forening']['sekretaer'])): ?>
        <p>Sekretær: <?= e($site['forening']['sekretaer']) ?></p>
      <?php endif; ?>
    </div>
  </div>
  <p class="bund">&copy; <?= date('Y') ?> <?= e($site['forening']['navn'] ?? '') ?></p>
</footer>

<script>
(function () {
  var rod = document.documentElement;

  /* Tekststørrelse: 100 % som udgangspunkt, 85-150 % i spring af 10. */
  var pct = parseInt(localStorage.getItem('hsr-tekst') || '100', 10);
  function saetTekst(nyt) {
    pct = Math.min(150, Math.max(85, nyt));
    rod.style.fontSize = pct + '%';
    try { localStorage.setItem('hsr-tekst', pct); } catch (e) {}
  }
  saetTekst(pct);
  document.getElementById('tekst-stoerre').addEventListener('click', function(){ saetTekst(pct + 10); });
  document.getElementById('tekst-mindre').addEventListener('click', function(){ saetTekst(pct - 10); });

  /* Mørk visning */
  var mk = document.getElementById('moerk');
  function saetMoerk(til) {
    rod.classList.toggle('moerk', til);
    mk.setAttribute('aria-pressed', til ? 'true' : 'false');
    mk.textContent = til ? 'Lys visning' : 'Mørk visning';
    try { localStorage.setItem('hsr-moerk', til ? '1' : '0'); } catch (e) {}
  }
  saetMoerk(localStorage.getItem('hsr-moerk') === '1');
  mk.addEventListener('click', function () {
    saetMoerk(!rod.classList.contains('moerk'));
  });

  /* Menu på mobil */
  var menuKnap = document.getElementById('menu-knap');
  var menu = document.getElementById('hovedmenu');
  menuKnap.addEventListener('click', function () {
    var aaben = menu.classList.toggle('aaben');
    menuKnap.setAttribute('aria-expanded', aaben ? 'true' : 'false');
  });
})();
</script>
</body>
</html>
