<?php
if (!isConnect('admin')) throw new Exception('{{401 - Accès non autorisé}}');
$daemon = eufysdk::deamon_info();
$eufy_email = config::byKey('eufy_email', 'eufysdk', '');
$eufy_country = config::byKey('eufy_country', 'eufysdk', 'BE');
?>
<form class="form-horizontal">
 <fieldset><legend>Connexion au cloud Eufy</legend>
  <p>Ces identifiants permettent au plugin de démarrer et configurer lui-même le bridge Docker (mega-yfue) sur <strong>cette machine Jeedom</strong>. Si le bridge tourne sur une autre machine (NAS, VM distincte), laisser ces champs vides et configurer le bridge manuellement (voir documentation) : seuls l'hôte/port ci-dessous sont alors utiles.</p>
  <div class="form-group"><label class="col-lg-3 control-label">Email du compte Eufy</label><div class="col-lg-4"><input class="form-control" id="eufysdk_email" value="<?php echo htmlspecialchars($eufy_email, ENT_QUOTES, 'UTF-8'); ?>" placeholder="vous@exemple.com" /></div></div>
  <div class="form-group"><label class="col-lg-3 control-label">Mot de passe Eufy</label><div class="col-lg-4"><input class="form-control" type="password" id="eufysdk_password" placeholder="(laisser vide pour conserver l'existant)" /></div></div>
  <div class="form-group"><label class="col-lg-3 control-label">Pays du compte Eufy</label><div class="col-lg-2"><input class="form-control" id="eufysdk_country" value="<?php echo htmlspecialchars($eufy_country, ENT_QUOTES, 'UTF-8'); ?>" placeholder="BE" /></div></div>
  <div class="form-group">
   <div class="col-lg-offset-3 col-lg-9">
    <a class="btn btn-primary" id="bt_eufysdk_saveCredentials"><i class="fas fa-save"></i> {{Enregistrer et (re)démarrer le bridge}}</a>
   </div>
  </div>
 </fieldset>

 <fieldset><legend>Bridge mega-yfue (Docker)</legend>
  <div class="form-group"><label class="col-lg-3 control-label">Hôte / IP du bridge</label><div class="col-lg-4"><input class="configKey form-control" data-l1key="bridge_host" placeholder="127.0.0.1" /></div></div>
  <div class="form-group"><label class="col-lg-3 control-label">Port</label><div class="col-lg-2"><input class="configKey form-control" data-l1key="bridge_port" placeholder="3000" /></div></div>
  <div class="form-group">
   <label class="col-lg-3 control-label">État du bridge</label>
   <div class="col-lg-9">
    <span class="label <?php echo $daemon['state'] === 'ok' ? 'label-success' : 'label-danger'; ?>" id="eufysdk_daemon_state"><?php echo $daemon['state'] === 'ok' ? 'OK' : 'NOK'; ?></span>
    <span id="eufysdk_daemon_message" class="text-muted"><?php echo htmlspecialchars($daemon['launchable_message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
    <div style="margin-top:8px;">
     <a class="btn btn-default btn-sm" id="bt_eufysdk_daemonStart"><i class="fas fa-play"></i> {{Démarrer le bridge}}</a>
     <a class="btn btn-default btn-sm" id="bt_eufysdk_daemonStop"><i class="fas fa-stop"></i> {{Arrêter le bridge}}</a>
     <a class="btn btn-default btn-sm" id="bt_eufysdk_syncNow"><i class="fas fa-sync"></i> {{Synchroniser maintenant}}</a>
    </div>
   </div>
  </div>
  <div class="form-group"><label class="col-lg-3 control-label">Authentification</label><div class="col-lg-9"><span class="configKey" data-l1key="auth_state"></span> (consulter le log eufysdk)</div></div>
  <div class="form-group"><label class="col-lg-3 control-label">Dernière synchro</label><div class="col-lg-9"><span class="configKey" data-l1key="last_sync"></span></div></div>
 </fieldset>

 <fieldset><legend>Test de connexion / 2FA / CAPTCHA</legend>
  <div class="form-group">
   <div class="col-lg-offset-3 col-lg-9">
    <a class="btn btn-default btn-sm" id="bt_eufysdk_testAuth"><i class="fas fa-plug"></i> {{Tester la connexion}}</a>
    <a class="btn btn-default btn-sm" id="bt_eufysdk_authRetrigger"><i class="fas fa-redo"></i> {{Renvoyer le code 2FA}}</a>
   </div>
  </div>
  <div class="form-group"><label class="col-lg-3 control-label">Code reçu par 2FA</label><div class="col-lg-3"><input class="form-control" id="eufysdk_auth_code" placeholder="123456" /></div></div>
  <div class="form-group"><label class="col-lg-3 control-label">Réponse CAPTCHA</label><div class="col-lg-3"><input class="form-control" id="eufysdk_auth_captcha" placeholder="texte affiché" /></div></div>
  <div class="form-group">
   <div class="col-lg-offset-3 col-lg-9">
    <a class="btn btn-primary btn-sm" id="bt_eufysdk_authSubmit"><i class="fas fa-check"></i> {{Valider}}</a>
   </div>
  </div>
  <div class="form-group"><label class="col-lg-3 control-label">Résultat brut</label><div class="col-lg-9"><span id="eufysdk_auth_result" class="text-muted"></span></div></div>
 </fieldset>
</form>
<p>Si le bridge est géré sur une autre machine, les boutons démarrer/arrêter échoueront (Docker non accessible depuis Jeedom) : c'est attendu, continuer alors via <code>docker compose</code> directement sur cette machine. Voir <code>docs/README.md</code>.</p>
