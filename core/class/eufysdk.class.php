<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class eufysdk extends eqLogic {
    public static function bridgeUrl() {
        $host = trim(config::byKey('bridge_host', 'eufysdk', '127.0.0.1'));
        $port = (int) config::byKey('bridge_port', 'eufysdk', '3000');
        if (!filter_var($host, FILTER_VALIDATE_IP) && !preg_match('/^[a-zA-Z0-9.-]+$/', $host)) {
            throw new Exception('Adresse du bridge invalide');
        }
        if ($port < 1 || $port > 65535) throw new Exception('Port invalide');
        return 'ws://' . $host . ':' . $port . '/ws';
    }
    public static function request($command, $fields = array()) {
        $allowed = array('auth.status','auth.submit','auth.retrigger','devices.list','device.state','device.properties','device.set','device.action');
        if (!in_array($command, $allowed, true)) throw new Exception('Commande non autorisée');
        $python = dirname(__FILE__) . '/../python/.venv/bin/python';
        if (!is_executable($python)) throw new Exception('Dépendances non installées : lancer installation des dépendances');
        $script = dirname(__FILE__) . '/../python/bridge_cli.py';
        $args = array($python, $script, '--url', self::bridgeUrl(), '--cmd', $command, '--fields', json_encode($fields, JSON_FORCE_OBJECT));
        $quoted = array_map('escapeshellarg', $args);
        $output = array(); $status = 0;
        exec(implode(' ', $quoted) . ' 2>&1', $output, $status);
        $decoded = json_decode(implode("\n", $output), true);
        if (!is_array($decoded)) throw new Exception('Réponse bridge illisible : ' . implode(' ', $output));
        if (empty($decoded['ok'])) throw new Exception('Bridge : ' . ($decoded['error'] ?? 'erreur inconnue'));
        return $decoded;
    }
    public static function dependancy_info() {
        $py = dirname(__FILE__) . '/../python/.venv/bin/python';
        return array('state' => is_executable($py) ? 'ok' : 'nok');
    }
    public static function dependancy_install() {
        return array('script' => dirname(__FILE__) . '/../../plugin_info/install_dependencies.sh', 'log' => 'eufysdk_dep');
    }

    /* ---------------------------------------------------------------------
     * Gestion du bridge Docker (mega-yfue) directement depuis le plugin.
     * Le conteneur est traité comme le "démon" du plugin (hasOwnDeamon) :
     * - deamon_info()/deamon_start()/deamon_stop() suivent la convention Jeedom.
     * - Nécessite que docker/docker compose soit accessible par l'utilisateur
     *   du serveur web (groupe docker, ou sudoers configuré côté Jeedom).
     * Si le bridge tourne sur une machine distincte (NAS, autre VM), cette
     * gestion automatique ne s'applique pas : garder les champs email/mot de
     * passe vides et continuer la configuration manuelle via docker compose
     * (voir docs/README.md), seuls bridge_host/bridge_port sont alors utiles.
     * ------------------------------------------------------------------- */

    public static function pluginDir() {
        return realpath(dirname(__FILE__) . '/../..');
    }
    public static function dockerComposeFile() {
        return self::pluginDir() . '/docker/docker-compose.yml';
    }
    public static function dockerEnvFile() {
        return self::pluginDir() . '/docker/.env';
    }
    private static function run($cmd) {
        $output = array(); $status = 0;
        exec($cmd . ' 2>&1', $output, $status);
        return array('status' => $status, 'output' => implode("\n", $output));
    }
    public static function dockerBin() {
        static $bin = null;
        if ($bin !== null) return $bin;
        $bin = trim(self::run('command -v docker 2>/dev/null')['output']);
        return $bin;
    }
    public static function dockerComposeCmd() {
        $docker = self::dockerBin();
        if ($docker === '') return null;
        $prefix = system::getCmdSudo();
        $base = $prefix . escapeshellarg($docker) . ' compose';
        $check = self::run($base . ' version');
        if ($check['status'] === 0) return $base;
        $standalone = trim(self::run('command -v docker-compose 2>/dev/null')['output']);
        if ($standalone === '') return null;
        $base = $prefix . escapeshellarg($standalone);
        $check = self::run($base . ' version');
        return $check['status'] === 0 ? $base : null;
    }
    public static function dockerAvailable() {
        try {
            return self::dockerComposeCmd() !== null;
        } catch (Throwable $e) {
            log::add('eufysdk', 'error', 'dockerAvailable: ' . $e->getMessage());
            return false;
        }
    }
    public static function dockerCompose($args) {
        $compose = self::dockerComposeCmd();
        if ($compose === null) throw new Exception('Docker (ou docker compose) introuvable ou inaccessible pour l\'utilisateur du serveur web');
        $dir = self::pluginDir() . '/docker';
        $cmd = $compose
            . ' --project-directory ' . escapeshellarg($dir)
            . ' -f ' . escapeshellarg(self::dockerComposeFile())
            . ' --env-file ' . escapeshellarg(self::dockerEnvFile())
            . ' ' . $args;
        $result = self::run($cmd);
        log::add('eufysdk', 'info', 'docker compose ' . $args . ' => ' . $result['output']);
        if ($result['status'] !== 0) throw new Exception('Commande docker en échec : ' . $result['output']);
        return $result['output'];
    }
    public static function containerRunning() {
        try {
            $ids = trim(self::dockerCompose('ps -q'));
            if ($ids === '') return false;
            $id = strtok($ids, "\n");
            $docker = self::dockerBin();
            $prefix = system::getCmdSudo();
            $result = self::run($prefix . escapeshellarg($docker) . ' inspect -f ' . escapeshellarg('{{.State.Running}}') . ' ' . escapeshellarg($id));
            return trim($result['output']) === 'true';
        } catch (Throwable $e) {
            return false;
        }
    }
    public static function writeBridgeEnv() {
        $email = trim((string) config::byKey('eufy_email', 'eufysdk'));
        $password = (string) utils::decrypt(config::byKey('eufy_password', 'eufysdk'));
        $country = strtoupper(trim((string) config::byKey('eufy_country', 'eufysdk', 'BE')));
        if ($email === '' || $password === '') throw new Exception('Identifiants Eufy manquants');
        if (!preg_match('/^[A-Z]{2}$/', $country)) throw new Exception('Code pays Eufy invalide (2 lettres, ex: BE)');
        $strip = function ($v) { return str_replace(array("\r", "\n"), '', $v); };
        $content = 'EUFY_EMAIL=' . $strip($email) . "\n"
            . 'EUFY_PASSWORD=' . $strip($password) . "\n"
            . 'EUFY_COUNTRY=' . $strip($country) . "\n";
        $file = self::dockerEnvFile();
        file_put_contents($file, $content);
        chmod($file, 0600);
    }
    public static function saveCredentials($email, $password, $country) {
        $email = trim((string) $email);
        $country = strtoupper(trim((string) $country));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Email Eufy invalide');
        if (!preg_match('/^[A-Z]{2}$/', $country)) throw new Exception('Code pays invalide (2 lettres, ex: BE, FR)');
        $password = trim((string) $password);
        if ($password !== '') {
            config::save('eufy_password', utils::encrypt($password), 'eufysdk');
        } elseif (trim((string) utils::decrypt(config::byKey('eufy_password', 'eufysdk'))) === '') {
            throw new Exception('Mot de passe Eufy requis');
        }
        config::save('eufy_email', $email, 'eufysdk');
        config::save('eufy_country', $country, 'eufysdk');
        return self::deamon_start();
    }
    public static function deamon_info() {
        $return = array('log' => 'eufysdk', 'state' => 'nok', 'launchable' => 'ok');
        try {
            if (!self::dockerAvailable()) {
                $return['launchable'] = 'nok';
                $return['launchable_message'] = __('Docker (ou docker compose) est introuvable ou inaccessible pour l\'utilisateur du serveur web. Installer Docker et autoriser www-data (groupe docker ou sudoers), voir documentation.', __FILE__);
                return $return;
            }
            if (trim((string) config::byKey('eufy_email', 'eufysdk')) === '') {
                $return['launchable'] = 'nok';
                $return['launchable_message'] = __('Identifiants Eufy manquants dans la configuration du plugin', __FILE__);
                return $return;
            }
            $return['state'] = self::containerRunning() ? 'ok' : 'nok';
            return $return;
        } catch (Throwable $e) {
            log::add('eufysdk', 'error', 'deamon_info: ' . $e->getMessage());
            $return['launchable'] = 'nok';
            $return['launchable_message'] = __('Erreur interne : ', __FILE__) . $e->getMessage();
            return $return;
        }
    }
    public static function deamonRunning() {
        $info = self::deamon_info();
        return $info['state'] === 'ok';
    }
    public static function deamon_start() {
        $info = self::deamon_info();
        if ($info['launchable'] !== 'ok') throw new Exception($info['launchable_message']);
        self::writeBridgeEnv();
        self::dockerCompose('up -d');
        $i = 0;
        while ($i < 30) {
            if (self::containerRunning()) break;
            sleep(1);
            $i++;
        }
        if (!self::containerRunning()) {
            log::add('eufysdk', 'error', __('Impossible de démarrer le bridge Docker, consulter le log', __FILE__));
            return false;
        }
        log::add('eufysdk', 'info', 'Bridge Eufy démarré');
        return true;
    }
    public static function deamon_stop() {
        try {
            self::dockerCompose('down');
        } catch (Throwable $e) {
            log::add('eufysdk', 'warning', $e->getMessage());
        }
    }

    private static function syncActions($eq, $sn) {
        $manifest = self::request('device.properties', array('sn' => $sn));
        foreach (($manifest['properties'] ?? array()) as $prop) {
            if (empty($prop['writable']) || ($prop['type'] ?? '') !== 'bool' || empty($prop['name'])) continue;
            foreach (array(true, false) as $on) {
                $key = 'set:' . $prop['name'] . ':' . ($on ? 'on' : 'off');
                $cmd = eufysdkCmd::byEqLogicIdAndLogicalId($eq->getId(), $key);
                if (is_object($cmd)) continue;
                $cmd = new eufysdkCmd();
                $cmd->setEqLogic_id($eq->getId()); $cmd->setLogicalId($key);
                $cmd->setName($prop['name'] . ' ' . ($on ? 'ON' : 'OFF'));
                $cmd->setType('action'); $cmd->setSubType('other');
                $cmd->setConfiguration('bridge_action', 'device.set');
                $cmd->setConfiguration('property', $prop['name']);
                $cmd->setConfiguration('value', $on ? 1 : 0);
                $cmd->setIsVisible(1); $cmd->save();
            }
        }
    }
    public static function sync() {
        $auth = self::request('auth.status');
        $state = $auth['auth']['state'] ?? 'unknown';
        config::save('auth_state', $state, 'eufysdk');
        if ($state !== 'ok') {
            log::add('eufysdk','warning','Authentification Eufy : '.$state);
            return;
        }
        $response = self::request('devices.list');
        foreach (($response['devices'] ?? array()) as $device) {
            if (empty($device['sn']) || isset($device['error'])) continue;
            $sn = (string) $device['sn'];
            $eq = self::byLogicalId($sn, 'eufysdk');
            if (!is_object($eq)) {
                $eq = new self();
                $eq->setEqType_name('eufysdk');
                $eq->setLogicalId($sn);
                $eq->setIsEnable(1);
                $eq->setIsVisible(1);
            }
            $eq->setName($device['name'] ?? $sn);
            $eq->setConfiguration('model', $device['model'] ?? '');
            $eq->setConfiguration('capabilities', $device['capabilities'] ?? array());
            $eq->save();
            try { self::syncActions($eq, $sn); } catch (Throwable $e) { log::add('eufysdk', 'warning', 'Manifest ' . $sn . ' : ' . $e->getMessage()); }
            $states = $device['state'] ?? array();
            if (isset($device['streaming'])) $states['streaming'] = $device['streaming'];
            foreach ($states as $key => $value) {
                if (!is_scalar($value) && $value !== null) continue;
                $cmd = eufysdkCmd::byEqLogicIdAndLogicalId($eq->getId(), 'state:' . $key);
                if (!is_object($cmd)) {
                    $cmd = new eufysdkCmd();
                    $cmd->setEqLogic_id($eq->getId());
                    $cmd->setLogicalId('state:' . $key);
                    $cmd->setName($key);
                    $cmd->setType('info');
                    $cmd->setSubType(is_bool($value) ? 'binary' : (is_numeric($value) ? 'numeric' : 'string'));
                    $cmd->setIsVisible(1);
                    $cmd->save();
                }
                $eq->checkAndUpdateCmd('state:' . $key, is_bool($value) ? (int)$value : (string)($value ?? ''));
            }
            // HTTP snapshot URL shown as informational command; use stored mode to spare battery.
            if (in_array('snapshot', $device['capabilities'] ?? array(), true)) {
                $cmd = eufysdkCmd::byEqLogicIdAndLogicalId($eq->getId(), 'snapshot');
                if (!is_object($cmd)) {
                    $cmd = new eufysdkCmd(); $cmd->setEqLogic_id($eq->getId());
                    $cmd->setLogicalId('snapshot'); $cmd->setName('Snapshot URL');
                    $cmd->setType('info'); $cmd->setSubType('string'); $cmd->save();
                }
                $host = config::byKey('bridge_host', 'eufysdk', '127.0.0.1');
                $port = (int) config::byKey('bridge_port','eufysdk','3000');
                $eq->checkAndUpdateCmd('snapshot', 'http://' . $host . ':' . $port . '/snapshot/' . rawurlencode($sn) . '?mode=stored');
            }
        }
        config::save('last_sync', date('Y-m-d H:i:s'), 'eufysdk');
    }
    public static function cron() {
        try { self::sync(); } catch (Throwable $e) { log::add('eufysdk','error',$e->getMessage()); }
    }
}

class eufysdkCmd extends cmd {
    public function execute($_options = array()) {
        if ($this->getType() !== 'action') return null;
        $action = $this->getConfiguration('bridge_action', '');
        if ($action !== 'device.set') throw new Exception('Action non autorisée');
        $fields = array('sn' => $this->getEqLogic()->getLogicalId(), 'name' => $this->getConfiguration('property'));
        if ($this->getSubType() === 'slider') $fields['value'] = (float)($_options['slider'] ?? 0);
        elseif ($this->getSubType() === 'select') $fields['value'] = $_options['select'] ?? '';
        else $fields['value'] = (bool)$this->getConfiguration('value', false);
        return eufysdk::request('device.set', $fields);
    }
}
