</main>
<footer class="foot">
  <div class="wrap foot-in">
    <div><strong><?= e(PORTAL_NAME) ?></strong><br><?= e(PORTAL_ADDRESS) ?><br>Tel. <?= e(PORTAL_PHONE) ?> · <?= e(PORTAL_HOURS) ?></div>
    <div class="foot-note"><?= !empty($publicArea) ? 'Precios incluyen IVA. Sujetos a cambio sin previo aviso.' : 'Uso interno · Toda actividad es monitoreada' ?></div>
  </div>
</footer>
<script src="<?= url('assets/app.js') ?>"></script>
</body>
</html>
