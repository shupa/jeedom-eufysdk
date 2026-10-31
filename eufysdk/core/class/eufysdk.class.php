<?php
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

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
        $args = array($python, $script, '--url', self::bridgeUrl(), '--cmd', $command, '--fields', json_encode($fields));
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
