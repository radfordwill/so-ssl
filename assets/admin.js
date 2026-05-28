(function($){
  function wireModal(root, nonceSource){
    var $root = $(root);
    if (!$root.length) return;
    var $btn = $root.find('.so-ssl-accept');
    var $check = $root.find('input[type="checkbox"]');
    var $err = $root.find('.so-ssl-modal-error');
    $check.on('change', function(){ $btn.prop('disabled', !this.checked); });
    $btn.on('click', function(e){
      e.preventDefault();
      $err.text('');
      $btn.prop('disabled', true).text('Saving...');
      var cfg = window[nonceSource] || {};
      $.post(cfg.ajax || ajaxurl, { action: $root.data('action'), nonce: cfg.nonce })
        .done(function(resp){
          if (resp && resp.success) { $root.remove(); }
          else { $err.text((resp && resp.data) ? resp.data : 'Unable to save acknowledgment.'); $btn.prop('disabled', false).text('Acknowledge and Continue'); }
        })
        .fail(function(){ $err.text('Request failed. Please refresh and try again.'); $btn.prop('disabled', false).text('Acknowledge and Continue'); });
    });
  }

  function renderTotpQr(){
    var el = document.getElementById('so-ssl-2fa-qr');
    if (!el || !el.getAttribute('data-otpauth')) { return; }
    if (!window.SoSSLQRCodeCore || !window.SoSSLQRErrorCorrectLevel) {
      el.textContent = 'QR code unavailable. Use the manual setup key.';
      return;
    }
    try {
      var qr = new window.SoSSLQRCodeCore(0, window.SoSSLQRErrorCorrectLevel.M);
      qr.addData(el.getAttribute('data-otpauth'));
      qr.make();
      var count = qr.getModuleCount();
      var cell = 4;
      var pad = 4;
      var size = (count + pad * 2) * cell;
      var canvas = document.createElement('canvas');
      canvas.width = size;
      canvas.height = size;
      canvas.setAttribute('aria-label', 'Authenticator app setup QR code');
      var ctx = canvas.getContext('2d');
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, size, size);
      ctx.fillStyle = '#000000';
      for (var row = 0; row < count; row++) {
        for (var col = 0; col < count; col++) {
          if (qr.isDark(row, col)) {
            ctx.fillRect((col + pad) * cell, (row + pad) * cell, cell, cell);
          }
        }
      }
      el.innerHTML = '';
      el.appendChild(canvas);
    } catch (e) {
      el.textContent = 'QR code unavailable. Use the manual setup key.';
    }
  }


  function wireSettingsTabs(){
    var $tabs = $('.so-ssl-tabs .nav-tab');
    var $panels = $('.so-ssl-tab-panel');
    if (!$tabs.length || !$panels.length) { return; }

    function getSavedTab(){
      if (window.location.hash && $(window.location.hash).hasClass('so-ssl-tab-panel')) {
        return window.location.hash.replace('#so-ssl-tab-', '');
      }
      if (typeof window.getUserSetting === 'function') {
        return window.getUserSetting('so_ssl_settings_tab', 'ssl');
      }
      try {
        return window.localStorage.getItem('so_ssl_settings_tab') || 'ssl';
      } catch (e) {
        return 'ssl';
      }
    }

    function saveTab(tab){
      if (typeof window.setUserSetting === 'function') {
        window.setUserSetting('so_ssl_settings_tab', tab);
      }
      try {
        window.localStorage.setItem('so_ssl_settings_tab', tab);
      } catch (e) {}
    }

    function activateTab(tab){
      if (!$panels.filter('[data-so-ssl-tab-panel="' + tab + '"]').length) {
        tab = $tabs.first().data('so-ssl-tab');
      }
      $tabs.removeClass('nav-tab-active').attr('aria-selected', 'false');
      $tabs.filter('[data-so-ssl-tab="' + tab + '"]').addClass('nav-tab-active').attr('aria-selected', 'true');
      $panels.removeClass('is-active').attr('hidden', 'hidden');
      $panels.filter('[data-so-ssl-tab-panel="' + tab + '"]').addClass('is-active').removeAttr('hidden');
      saveTab(tab);
    }

    $tabs.on('click', function(e){
      e.preventDefault();
      var tab = $(this).data('so-ssl-tab');
      activateTab(tab);
      if (history && history.replaceState) {
        history.replaceState(null, document.title, '#so-ssl-tab-' + tab);
      }
    });

    activateTab(getSavedTab());
  }

  function wireTwoFactorMasterToggle(){
    var $master = $('input[name="so_ssl_options[two_factor_master_enabled]"]');
    var $children = $('.so-ssl-2fa-child-control');
    if (!$master.length || !$children.length) { return; }
    function sync(){
      var disabled = !$master.is(':checked');
      $children.toggleClass('is-disabled', disabled).attr('aria-disabled', disabled ? 'true' : 'false');
    }
    $master.on('change', sync);
    sync();
  }

  function wireAuthenticatorLinks(){
    $(document).on('click', '.so-ssl-open-authenticator-link', function(e){
      e.preventDefault();
      var url = $(this).siblings('.so-ssl-authenticator-links').val();
      if (url) { window.open(url, '_blank', 'noopener'); }
    });
  }

  $(function(){
    wireModal('#so-ssl-privacy-modal', 'SoSSL');
    wireModal('#so-ssl-admin-agreement-modal', 'SoSSLAdmin');
    renderTotpQr();
    wireAuthenticatorLinks();
    wireSettingsTabs();
    wireTwoFactorMasterToggle();
  });
})(jQuery);
