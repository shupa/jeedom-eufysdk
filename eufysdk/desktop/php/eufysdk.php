<?php
if (!isConnect()) throw new Exception('{{401 - Accès non autorisé}}');
$eqLogics = eufysdk::byType('eufysdk');
?>
<div class="row row-overflow"><div class="col-xs-12">
<h3>Eufy SDK — Équipements</h3>
<p>Synchronisation automatique chaque minute ; état d'authentification visible sur la configuration du plugin.</p>
<?php foreach ($eqLogics as $eq) { ?>
  <div class="eqLogicDisplayCard cursor" data-eqLogic_id="<?php echo $eq->getId(); ?>" style="display:inline-block;padding:12px;border:1px solid #888;margin:6px;border-radius:6px">
  <strong><?php echo htmlspecialchars($eq->getName(), ENT_QUOTES, 'UTF-8'); ?></strong><br />
  <?php echo htmlspecialchars($eq->getConfiguration('model', ''), ENT_QUOTES, 'UTF-8'); ?><br />
  <small><?php echo htmlspecialchars($eq->getLogicalId(), ENT_QUOTES, 'UTF-8'); ?></small>
  </div>
<?php } ?>
</div></div>
