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

    function defaultTab(){
      return String($tabs.first().data('so-ssl-tab') || 'ssl');
    }

    function validTab(tab){
      tab = String(tab || '');
      return $panels.filter('[data-so-ssl-tab-panel="' + tab + '"]').length ? tab : '';
    }

    function getQueryTab(){
      try {
        var params = new URLSearchParams(window.location.search || '');
        return validTab(params.get('so_ssl_tab'));
      } catch (e) {
        var match = (window.location.search || '').match(/[?&]so_ssl_tab=([^&]+)/);
        return match ? validTab(decodeURIComponent(match[1].replace(/\+/g, ' '))) : '';
      }
    }

    function getSavedTab(){
      var queryTab = getQueryTab();
      if (queryTab) { return queryTab; }
      if (window.location.hash && $(window.location.hash).hasClass('so-ssl-tab-panel')) {
        return validTab(window.location.hash.replace('#so-ssl-tab-', '')) || defaultTab();
      }
      if (typeof window.getUserSetting === 'function') {
        return validTab(window.getUserSetting('so_ssl_settings_tab', defaultTab())) || defaultTab();
      }
      try {
        return validTab(window.localStorage.getItem('so_ssl_settings_tab')) || defaultTab();
      } catch (e) {
        return defaultTab();
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

    function updateAddress(tab){
      if (!window.history || !window.history.replaceState) { return; }
      try {
        var url = new URL(window.location.href);
        url.searchParams.set('page', 'so-ssl');
        url.searchParams.set('so_ssl_tab', tab);
        url.hash = '';
        history.replaceState(null, document.title, url.toString());
      } catch (e) {
        history.replaceState(null, document.title, 'admin.php?page=so-ssl&so_ssl_tab=' + encodeURIComponent(tab));
      }
    }

    function activateTab(tab, updateUrl){
      tab = validTab(tab) || defaultTab();
      $tabs.removeClass('nav-tab-active').attr('aria-selected', 'false');
      $tabs.filter('[data-so-ssl-tab="' + tab + '"]').addClass('nav-tab-active').attr('aria-selected', 'true');
      $panels.removeClass('is-active').attr('hidden', 'hidden');
      $panels.filter('[data-so-ssl-tab-panel="' + tab + '"]').addClass('is-active').removeAttr('hidden');
      saveTab(tab);
      if (updateUrl) { updateAddress(tab); }
    }

    $tabs.on('click', function(e){
      e.preventDefault();
      activateTab($(this).data('so-ssl-tab'), true);
    });

    activateTab(getSavedTab(), false);
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
