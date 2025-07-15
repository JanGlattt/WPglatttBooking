// Datei: assets/js/admin-emails.js
jQuery(function($){
    // Branch-Auswahl: Services-Anzeige umschalten
    $('input[name="branches[]"]').on('change', function(){
        var branch = $(this).val();
        if ($(this).is(':checked')) {
            $('#services_for_' + branch).slideDown();
        } else {
            $('#services_for_' + branch).slideUp();
        }
    });
    // "Alle Services" Checkbox: Services-Felder aktivieren/deaktivieren
    $('#all_services').on('change', function(){
        if ($(this).is(':checked')) {
            $('#service_selection_fieldset').prop('disabled', true);
        } else {
            $('#service_selection_fieldset').prop('disabled', false);
        }
    });
    // E-Mail-Typ Radiobuttons: Trigger-Felder ein-/ausblenden
    $('input[name="email_type"]').on('change', function(){
        var typeVal = $(this).val();
        if (typeVal == '2') { // Erinnerung
            $('#reminder_fields').show();
            $('#admin_fields').hide();
            $('#trigger_row').show();
        } else if (typeVal == '4') { // Administration
            $('#reminder_fields').hide();
            $('#admin_fields').show();
            $('#trigger_row').show();
        } else {
            $('#trigger_row').hide();
        }
    });
    // Rhythmus-Auswahl bei "Administration": Wochentag-Feld umschalten
    $('#schedule_interval_select').on('change', function(){
        if ($(this).val() == 'weekly') {
            $('#weekly_day_field').show();
        } else {
            $('#weekly_day_field').hide();
        }
    });
    // Test-Mail Button: Pop-up Abfrage und Formular absenden
    $('#glattt-send-test').on('click', function(){
        var email = prompt('Bitte geben Sie die E-Mail-Adresse für den Test ein:');
        if ( email && email.length > 0 ) {
            $('#test_email_field').val(email);
            $('#glattt-email-form').submit();
        }
    });
});

jQuery(document).ready(function($) {
  $('.glattt-select2-service').select2();

  $('#all_services_global').on('change', function() {
    const isGlobal = $(this).is(':checked');
    $('#service_selection_fieldset').prop('disabled', isGlobal);
  });

  $('.all_services_per_branch').on('change', function() {
    const branch = $(this).data('branch');
    const disabled = $(this).is(':checked');
    $('#services_' + branch).prop('disabled', disabled);
  });
});