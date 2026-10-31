<?php
if (!isConnect('admin')) throw new Exception('{{401 - Accès non autorisé}}');
?>
<form class="form-horizontal">
 <fieldset><legend>Bridge mega-yfue (Docker externe)</legend>
  <div class="form-group"><label class="col-lg-3 control-label">Hôte / IP du bridge</label><div class="col-lg-4"><input class="configKey form-control" data-l1key="bridge_host" placeholder="127.0.0.1" /></div></div>
  <div class="form-group"><label class="col-lg-3 control-label">Port</label><div class="col-lg-2"><input class="configKey form-control" data-l1key="bridge_port" placeholder="3000" /></div></div>
  <div class="form-group"><label class="col-lg-3 control-label">Authentification</label><div class="col-lg-4"><span class="configKey" data-l1key="auth_state"></span> (consulter le log eufysdk)</div></div>
  <div class="form-group"><label class="col-lg-3 control-label">Dernière synchro</label><div class="col-lg-4"><span class="configKey" data-l1key="last_sync"></span></div></div>
 </fieldset>
</form>
<p>Pour synchroniser immédiatement, lancer <code>eufysdk::sync()</code> depuis le moteur de scénario/code ou attendre le cron (1 minute). Pour 2FA, voir la documentation du plugin.</p>
