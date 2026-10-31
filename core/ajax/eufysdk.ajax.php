<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');

try {
    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

    if (init('action') == 'saveCredentials') {
        $started = eufysdk::saveCredentials(init('email'), init('password'), init('country', 'BE'));
        ajax::success(array('started' => $started));
    }

    if (init('action') == 'daemonStart') {
        ajax::success(array('started' => eufysdk::deamon_start()));
    }

    if (init('action') == 'daemonStop') {
        eufysdk::deamon_stop();
        ajax::success();
    }

    if (init('action') == 'daemonStatus') {
        ajax::success(eufysdk::deamon_info());
    }

    if (init('action') == 'authStatus') {
        ajax::success(eufysdk::request('auth.status'));
    }

    if (init('action') == 'authSubmit') {
        $fields = array();
        if (init('code') != '') $fields['code'] = init('code');
        if (init('captcha') != '') $fields['captcha'] = init('captcha');
        ajax::success(eufysdk::request('auth.submit', $fields));
    }

    if (init('action') == 'authRetrigger') {
        ajax::success(eufysdk::request('auth.retrigger'));
    }

    if (init('action') == 'syncNow') {
        eufysdk::sync();
        ajax::success();
    }

    ajax::init();
    throw new Exception(__('Aucune méthode correspondante à', __FILE__) . ' : ' . init('action'));
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
