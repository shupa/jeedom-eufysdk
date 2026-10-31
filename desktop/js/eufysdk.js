function eufysdkAjax(action, data, onSuccess) {
  data = data || {}
  data.action = action
  $.ajax({
    type: 'POST',
    url: 'plugins/eufysdk/core/ajax/eufysdk.ajax.php',
    data: data,
    dataType: 'json',
    error: function (request, status, error) {
      handleAjaxError(request, status, error)
    },
    success: function (data) {
      if (data.state != 'ok') {
        $('#div_alert').showAlert({ message: data.result, level: 'danger' })
        return
      }
      if (typeof onSuccess === 'function') onSuccess(data.result)
    }
  })
}

function eufysdkRefreshStatus() {
  eufysdkAjax('daemonStatus', {}, function (result) {
    var badge = $('#eufysdk_daemon_state')
    if (result.state == 'ok') {
      badge.removeClass('label-danger').addClass('label-success').text('OK')
    } else {
      badge.removeClass('label-success').addClass('label-danger').text('NOK')
    }
    $('#eufysdk_daemon_message').text(result.launchable_message || '')
  })
}

$('#bt_eufysdk_saveCredentials').on('click', function () {
  eufysdkAjax('saveCredentials', {
    email: $('#eufysdk_email').value(),
    password: $('#eufysdk_password').value(),
    country: $('#eufysdk_country').value()
  }, function () {
    $('#div_alert').showAlert({ message: '{{Identifiants enregistrés, bridge en cours de (re)démarrage}}', level: 'success' })
    $('#eufysdk_password').value('')
    setTimeout(eufysdkRefreshStatus, 2000)
  })
})

$('#bt_eufysdk_daemonStart').on('click', function () {
  eufysdkAjax('daemonStart', {}, function () {
    $('#div_alert').showAlert({ message: '{{Bridge démarré}}', level: 'success' })
    eufysdkRefreshStatus()
  })
})

$('#bt_eufysdk_daemonStop').on('click', function () {
  eufysdkAjax('daemonStop', {}, function () {
    $('#div_alert').showAlert({ message: '{{Bridge arrêté}}', level: 'success' })
    eufysdkRefreshStatus()
  })
})

$('#bt_eufysdk_testAuth').on('click', function () {
  eufysdkAjax('authStatus', {}, function (result) {
    $('#eufysdk_auth_result').text(JSON.stringify(result))
  })
})

$('#bt_eufysdk_authSubmit').on('click', function () {
  eufysdkAjax('authSubmit', {
    code: $('#eufysdk_auth_code').value(),
    captcha: $('#eufysdk_auth_captcha').value()
  }, function (result) {
    $('#div_alert').showAlert({ message: '{{Code envoyé}}', level: 'success' })
    $('#eufysdk_auth_result').text(JSON.stringify(result))
    $('#eufysdk_auth_code').value('')
    $('#eufysdk_auth_captcha').value('')
  })
})

$('#bt_eufysdk_authRetrigger').on('click', function () {
  eufysdkAjax('authRetrigger', {}, function (result) {
    $('#div_alert').showAlert({ message: '{{Nouvelle demande envoyée}}', level: 'success' })
    $('#eufysdk_auth_result').text(JSON.stringify(result))
  })
})

$('#bt_eufysdk_syncNow').on('click', function () {
  eufysdkAjax('syncNow', {}, function () {
    $('#div_alert').showAlert({ message: '{{Synchronisation lancée}}', level: 'success' })
  })
})

if ($('#eufysdk_daemon_state').length) {
  eufysdkRefreshStatus()
}
