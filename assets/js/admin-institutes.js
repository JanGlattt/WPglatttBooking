// Datei: assets/js/admin-institutes.js
jQuery(function($){
  $('.toggle-btn').on('click', function(e){
    e.preventDefault();
    var btn    = $(this);
    var card   = btn.closest('.glattt-card');
    var icon   = btn.find('.dashicons');
    var branch = btn.data('branch');

    btn.prop('disabled', true);

    $.post(
      glatttAjax.ajax_url,
      {
        action: 'glattt_toggle_institute',
        branch: branch,
        nonce:  glatttAjax.nonce
      },
      function(resp){
        btn.prop('disabled', false);
        if ( resp.success ) {
          // Karte ein-/ausblenden
          card.toggleClass('inactive', ! resp.data.active);

          // Icon wechseln
          if ( resp.data.active ) {
            icon.removeClass('dashicons-no-alt').addClass('dashicons-yes');
            btn.attr('title', 'Deaktivieren');
          } else {
            icon.removeClass('dashicons-yes').addClass('dashicons-no-alt');
            btn.attr('title', 'Aktivieren');
          }
        }
      }
    );
  });
});