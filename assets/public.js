
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
  $(function(){
    wireModal('#so-ssl-privacy-modal', 'SoSSL');
    wireModal('#so-ssl-admin-agreement-modal', 'SoSSLAdmin');
  });
})(jQuery);
