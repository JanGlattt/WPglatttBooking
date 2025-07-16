<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin-Seite: E-Mail Vorlage bearbeiten/erstellen
 */
function glattt_email_details_page() {
    global $wpdb;
    $template_id = isset( $_REQUEST['template'] ) ? intval( $_REQUEST['template'] ) : 0;
    $edit_mode   = $template_id > 0;
    $table       = $wpdb->prefix . 'glattt_email_templates';
    $success_msg = '';
    $error_msg   = '';
    $test_msg    = '';

    // Verarbeitung von Speichern oder Test-Mail
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
        // Formularwerte einlesen
        $name               = isset( $_POST['name'] )               ? sanitize_text_field( $_POST['name'] )               : '';
        $customer_type      = isset( $_POST['customer_type'] )      ? intval( $_POST['customer_type'] )                   : 0;
        $location_type      = isset( $_POST['location_type'] )      ? intval( $_POST['location_type'] )                   : 0;
        $admin_type         = isset( $_POST['admin_type'] )         ? intval( $_POST['admin_type'] )                      : 0;

        // Standorte
        $all_locations      = isset( $_POST['all_locations'] )      ? 1                                                   : 0;
        $selected_branches  = isset( $_POST['branches'] ) && is_array( $_POST['branches'] )
                            ? array_map( 'sanitize_text_field', $_POST['branches'] )
                            : [];
        $branch_ids_value   = $all_locations ? '' : implode( ',', $selected_branches );

        // Services
        $all_services       = isset( $_POST['all_services'] )       ? 1                                                   : 0;
        $selected_services  = [];
        if ( ! $all_services && isset( $_POST['services'] ) && is_array( $_POST['services'] ) ) {
            foreach ( $_POST['services'] as $branch_id => $svcs ) {
                $svcs_clean = array_map( 'sanitize_text_field', $svcs );
                if ( in_array( 'all', $svcs_clean, true ) ) {
                    // alle Services dieses Standorts
                    $selected_services[ $branch_id ] = [ 'all' ];
                } else {
                    $selected_services[ $branch_id ] = $svcs_clean;
                }
            }
        }
        $service_ids_value = $all_services ? '' : wp_json_encode( $selected_services );

        // Art der E-Mail
        $email_type         = isset( $_POST['email_type'] )         ? intval( $_POST['email_type'] )                      : 0;

        // Trigger
        $reminder_offset    = isset( $_POST['reminder_offset'] )    ? intval( $_POST['reminder_offset'] )                 : 0;
        $reminder_variant   = isset( $_POST['reminder_variant'] )   ? sanitize_text_field( $_POST['reminder_variant'] )   : 'hours';
        $reminder_days_before = isset( $_POST['reminder_days_before'] ) ? intval( $_POST['reminder_days_before'] )        : 1;
        $reminder_time_fixed  = isset( $_POST['reminder_time_fixed'] )  ? sanitize_text_field( $_POST['reminder_time_fixed'] ) : '';
        $schedule_interval  = isset( $_POST['schedule_interval'] )  ? sanitize_text_field( $_POST['schedule_interval'] )  : '';
        // Capture reminder_time from POST and normalize
        $reminder_time = isset( $_POST['reminder_time'] ) ? sanitize_text_field( $_POST['reminder_time'] ) : '';
        if ( $reminder_time && strlen( $reminder_time ) === 5 ) {
            $reminder_time .= ':00';
        }
        if ( $reminder_time_fixed && strlen( $reminder_time_fixed ) === 5 ) {
            $reminder_time_fixed .= ':00';
        }
        $schedule_day       = isset( $_POST['schedule_day'] )       ? intval( $_POST['schedule_day'] )                    : 0;
        $schedule_time      = isset( $_POST['schedule_time'] )      ? sanitize_text_field( $_POST['schedule_time'] )      : '';
        if ( $schedule_time && strlen( $schedule_time ) === 5 ) {
            $schedule_time .= ':00';
        }

        // Betreff und Inhalt
        $subject            = isset( $_POST['subject'] )            ? sanitize_text_field( $_POST['subject'] )            : '';
        $content            = isset( $_POST['content'] )            ? wp_kses_post( $_POST['content'] )                   : '';
        // Recipients (To, CC, BCC)
        $to_addresses  = isset($_POST['to_addresses'])  ? sanitize_text_field($_POST['to_addresses'])  : '';
        $cc_addresses  = isset($_POST['cc_addresses'])  ? sanitize_text_field($_POST['cc_addresses'])  : '';
        $bcc_addresses = isset($_POST['bcc_addresses']) ? sanitize_text_field($_POST['bcc_addresses']) : '';

        // Test-Mail?
        if ( ! empty( $_POST['test_email'] ) ) {
            $test_address = sanitize_email( $_POST['test_email'] );
            if ( $test_address ) {
                // Platzhalter mit Beispieldaten füllen
                $placeholder = [
                    '{CUSTOMER_NAME}'           => 'Max Mustermann',
                    '{CUSTOMER_FIRSTNAME}'      => 'Max',
                    '{CUSTOMER_LASTNAME}'       => 'Mustermann',
                    '{CUSTOMER_EMAIL}'          => 'max@example.com',
                    '{APPOINTMENT_DATE}'        => date_i18n( 'd.m.Y' ),
                    '{APPOINTMENT_TIME_START}'  => '10:00',
                    '{APPOINTMENT_TIME_END}'    => '11:00',
                    '{INSTITUTE_NAME}'          => '',
                    '{INSTITUTE_EMAIL}'         => '',
                    '{INSTITUTE_ADDRESS}'       => '',
                ];
                // Beispiel-Standort
                $api       = new GLATTT_Phorrest_API();
                $branches  = $api->get_branches();
                if ( ! is_wp_error( $branches ) && ! empty( $branches ) ) {
                    $first_branch                     = $branches[0];
                    $placeholder['{INSTITUTE_NAME}']  = $first_branch['name'] ?? 'Institut';
                    $meta_table                       = $wpdb->prefix . 'glattt_institute_meta';
                    $row                              = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT email FROM $meta_table WHERE branch_id = %s",
                            $first_branch['branchId']
                        )
                    );
                    // Telefonnummer und WhatsApp-Nummer aus Meta
                    $meta_phone = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT phone FROM {$wpdb->prefix}glattt_institute_meta WHERE branch_id = %s",
                            $first_branch['branchId']
                        )
                    );
                    $meta_whatsapp = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT whatsapp FROM {$wpdb->prefix}glattt_institute_meta WHERE branch_id = %s",
                            $first_branch['branchId']
                        )
                    );
                    $branch_phone = $meta_phone;
                    $branch_whatsapp = $meta_whatsapp;
                    if ( $row ) {
                        $placeholder['{INSTITUTE_EMAIL}'] = $row->email;
                    }
                    // Adresse zusammensetzen
                    $street = $first_branch['street'] ?? '';
                    $zip    = $first_branch['zip']    ?? '';
                    $city   = $first_branch['city']   ?? '';
                    $addr   = array_filter([ $street, trim("$zip $city") ]);
                    $placeholder['{INSTITUTE_ADDRESS}'] = implode(', ', $addr);
                    // Neue Platzhalter für Telefon und WhatsApp
                    $placeholder['{INSTITUTE_PHONE}']    = $branch_phone ?? '';
                    $placeholder['{INSTITUTE_WHATSAPP}'] = $branch_whatsapp ?? '';
                } else {
                    // Falls kein Branch, trotzdem Platzhalter initialisieren
                    $placeholder['{INSTITUTE_PHONE}']    = '';
                    $placeholder['{INSTITUTE_WHATSAPP}'] = '';
                }

                $test_subject = strtr( $subject,  $placeholder );
                $test_content = strtr( $content,  $placeholder );
                $headers      = [ 'Content-Type: text/html; charset=UTF-8' ];
                if ( wp_mail( $test_address, $test_subject, $test_content, $headers ) ) {
                    $test_msg = 'Test-E-Mail wurde an ' . esc_html( $test_address ) . ' gesendet.';
                } else {
                    $error_msg = 'Die Test-E-Mail konnte nicht gesendet werden.';
                }
            }
        }
        // Speichern?
        else if ( isset( $_POST['save_email_template'] ) ) {
            $data = [
                'name'              => $name,
                'customer_type'     => $customer_type,
                'location_type'     => $location_type,
                'admin_type'        => $admin_type,
                'all_locations'     => $all_locations,
                'branch_ids'        => $branch_ids_value,
                'all_services'      => $all_services,
                'service_ids'       => $service_ids_value,
                'email_type'        => $email_type,
                'reminder_offset'   => $email_type === 2 ? $reminder_offset : null,
                'schedule_interval' => $email_type === 4 ? $schedule_interval : null,
                'schedule_day'      => $email_type === 4 ? $schedule_day : null,
                'schedule_time'     => $email_type === 4 ? $schedule_time : null,
                'subject'           => $subject,
                'content'           => $content,
                'to_addresses'      => $to_addresses,
                'cc_addresses'      => $cc_addresses,
                'bcc_addresses'     => $bcc_addresses,
            ];
            if ( $edit_mode ) {
                $updated = $wpdb->update( $table, $data, [ 'id' => $template_id ] );
                if ( $updated !== false ) {
                    $success_msg = 'E-Mail Vorlage gespeichert.';
                } else {
                    $error_msg = 'Fehler: Die E-Mail Vorlage konnte nicht gespeichert werden.';
                }
            } else {
                $inserted = $wpdb->insert( $table, $data );
                if ( $inserted !== false ) {
                    $template_id = $wpdb->insert_id;
                    $edit_mode = true;
                    $success_msg = 'E-Mail Vorlage gespeichert.';
                } else {
                    $error_msg = 'Fehler: Die E-Mail Vorlage konnte nicht gespeichert werden.';
                }
            }
        }
    }

    // Beim ersten Laden im Edit-Mode: vorhandene Werte einlesen
    if ( empty( $_POST ) && $edit_mode ) {
        $template = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $template_id ) );
        if ( $template ) {
            $name               = $template->name;
            $customer_type      = intval( $template->customer_type );
            $location_type      = intval( $template->location_type );
            $admin_type         = intval( $template->admin_type );
            $all_locations      = intval( $template->all_locations );
            $branch_ids_value   = $template->branch_ids;
            $all_services       = intval( $template->all_services );
            $service_ids_value  = $template->service_ids;
            $email_type         = intval( $template->email_type );
            $reminder_offset    = $template->reminder_offset;
            $schedule_interval  = $template->schedule_interval;
            $schedule_day       = intval( $template->schedule_day );
            $schedule_time      = $template->schedule_time;
            $subject            = $template->subject;
            $content            = $template->content;
            $to_addresses       = $template->to_addresses  ?? '';
            $cc_addresses       = $template->cc_addresses  ?? '';
            $bcc_addresses      = $template->bcc_addresses ?? '';
            $selected_branches  = ! empty( $branch_ids_value )
                                 ? array_map( 'trim', explode( ',', $branch_ids_value ) )
                                 : [];

            // JSON vs. Komma-Fallback:
            if ( ! empty( $service_ids_value ) ) {
                $decoded = json_decode( $service_ids_value, true );
                if ( is_array( $decoded ) ) {
                    $selected_services = $decoded;
                } else {
                    $flat = array_map( 'trim', explode( ',', $service_ids_value ) );
                    // hier behandeln wir globaler Legacy-Fall
                    $selected_services = [ 'global' => $flat ];
                }
            } else {
                $selected_services = [];
            }
        }
    }

    // Standardwerte, wenn neu
    if ( ! $edit_mode && empty( $_POST ) ) {
        $all_locations     = 0;
        $all_services      = 0;
        $selected_branches = [];
        $selected_services = [];
        $to_addresses      = '';
        $cc_addresses      = '';
        $bcc_addresses     = '';
    }

    // ----- Ausgabe der Admin-Seite -----
    echo '<div class="wrap">';
    echo $edit_mode
        ? '<h1>E-Mail Vorlage bearbeiten: ' . esc_html( $name ) . '</h1>'
        : '<h1>Neue E-Mail Vorlage</h1>';

    if ( $success_msg ) {
        echo '<div class="notice notice-success"><p>' . esc_html( $success_msg ) . '</p></div>';
    }
    if ( $error_msg ) {
        echo '<div class="notice notice-error"><p>' . esc_html( $error_msg ) . '</p></div>';
    }
    if ( $test_msg ) {
        echo '<div class="notice notice-info"><p>' . esc_html( $test_msg ) . '</p></div>';
    }

    echo '<form id="glattt-email-form" method="post">';
    echo '<table class="form-table">';

    // Name der E-Mail
    echo '<tr><th scope="row">Name der E-Mail</th><td>';
    echo '<input name="name" type="text" value="' . esc_attr( $name ?? '' ) . '" class="regular-text" required>';
    echo '</td></tr>';

    // Recipients fields
    echo '<tr><th scope="row">An (To)</th><td>';
    echo '<input name="to_addresses" type="text" value="' . esc_attr($to_addresses ?? '') . '" class="regular-text">';
    echo '<p class="description">Multiple addresses comma-separated. Shortcodes allowed.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row">CC</th><td>';
    echo '<input name="cc_addresses" type="text" value="' . esc_attr($cc_addresses ?? '') . '" class="regular-text">';
    echo '<p class="description">Multiple addresses comma-separated. Shortcodes allowed.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row">BCC</th><td>';
    echo '<input name="bcc_addresses" type="text" value="' . esc_attr($bcc_addresses ?? '') . '" class="regular-text">';
    echo '<p class="description">Multiple addresses comma-separated. Shortcodes allowed.</p>';
    echo '</td></tr>';

    // Standorte
    echo '<tr><th scope="row">Standorte</th><td>';
    $api          = new GLATTT_Phorrest_API();
    $branches_all = $api->get_branches();
    if ( is_wp_error( $branches_all ) || empty( $branches_all ) ) {
        echo '<em>Keine Standorte verfügbar.</em>';
    } else {
        foreach ( $branches_all as $b ) {
            $bid   = $b['branchId'] ?? '';
            $bname = $b['name']     ?? '';
            $ch    = in_array( $bid, $selected_branches, true );
            echo '<label><input type="checkbox" name="branches[]" value="' . esc_attr( $bid ) . '" ' . checked( $ch, true, false ) . '> ' . esc_html( $bname ) . '</label><br>';
        }
    }
    echo '</td></tr>';

    // Services
    echo '<tr><th scope="row">Services</th><td>';
    echo '<label><input type="checkbox" id="all_services" name="all_services" value="1" ' . checked( $all_services, 1, false ) . '> Alle Services global</label><br>';
    echo '<fieldset id="service_selection_fieldset" ' . ( $all_services ? 'disabled' : '' ) . ' style="border:none;padding:0;">';
    if ( ! is_wp_error( $branches_all ) && ! empty( $branches_all ) ) {
        foreach ( $branches_all as $b ) {
            $bid   = $b['branchId'] ?? '';
            $bname = $b['name']     ?? '';
            $svcs  = $api->get_services( $bid );
            $sel   = $selected_services[ $bid ] ?? [];
            $all_ch = in_array( 'all', $sel, true );

            echo '<div style="margin-bottom:1em;"><strong>' . esc_html( $bname ) . '</strong><br>';
            echo '<label><input type="checkbox" class="all_services_per_branch" name="services[' . esc_attr( $bid ) . '][]" value="all" data-branch="' . esc_attr( $bid ) . '" ' . checked( $all_ch, true, false ) . '> Alle Services</label><br>';
            echo '<select name="services[' . esc_attr( $bid ) . '][]" id="services_' . esc_attr( $bid ) . '" class="glattt-select2-service" multiple style="width:100%;max-width:400px;" ' . ( $all_ch ? 'disabled' : '' ) . '>';
            if ( is_wp_error( $svcs ) || empty( $svcs ) ) {
                echo '<option disabled>Keine Services</option>';
            } else {
                foreach ( $svcs as $s ) {
                    $sid   = $s['serviceId'] ?? '';
                    $sname = $s['name']      ?? '';
                    $sel_o = in_array( $sid, $sel, true );
                    echo '<option value="' . esc_attr( $sid ) . '"' . selected( $sel_o, true, false ) . '>' . esc_html( $sname ) . '</option>';
                }
            }
            echo '</select></div>';
        }
    }
    echo '</fieldset>';
    echo '</td></tr>';

    // E-Mail-Typ
    echo '<tr><th scope="row">Art der Mail</th><td>';
    $types = [1=>'Buchung',2=>'Erinnerung',3=>'Absage',4=>'Administration'];
    foreach ( $types as $val=>$lbl ) {
        echo '<label><input type="radio" name="email_type" value="' . $val . '"' . checked( $email_type, $val, false ) . '> ' . esc_html( $lbl ) . '</label><br>';
    }
    echo '</td></tr>';

    // Trigger-Felder
    echo '<tr id="trigger_row" ' . ( in_array( $email_type, [2,4], true ) ? '' : 'style="display:none;"' ) . '><th scope="row">Trigger</th><td>';
    // Erinnerung - NEU
    echo '<div id="reminder_fields" style="' . ( $email_type === 2 ? '' : 'display:none;' ) . '">';
    // Radio selection for variant
    $reminder_variant_val = isset($reminder_variant) ? $reminder_variant : 'hours';
    echo '<label style="margin-right:1em;"><input type="radio" name="reminder_variant" value="hours"' . checked($reminder_variant_val, 'hours', false) . '> Stunden vor Termin</label>';
    echo '<label><input type="radio" name="reminder_variant" value="fixed"' . checked($reminder_variant_val, 'fixed', false) . '> Fester Zeitpunkt</label>';
    // Stunden-Offset Felder
    echo '<div id="reminder_fields_hours" style="' . ($reminder_variant_val === 'hours' ? '' : 'display:none;') . '">';
    echo '<input type="number" name="reminder_offset" value="' . esc_attr($reminder_offset ?? 24) . '" min="0"> Stunden vor dem Termin';
    echo '</div>';
    // Fester Zeitpunkt Felder
    echo '<div id="reminder_fields_fixed" style="' . ($reminder_variant_val === 'fixed' ? '' : 'display:none;') . '">';
    echo '<label><input type="number" name="reminder_days_before" value="' . esc_attr($reminder_days_before ?? 1) . '" min="0" style="width:4em;"> Tage vor Termin um <input type="time" name="reminder_time_fixed" value="' . esc_attr($reminder_time_fixed ?? '08:00:00') . '"></label>';
    echo '</div>';
    echo '</div>';
    // Administration
    echo '<div id="admin_fields" style="' . ( $email_type === 4 ? '' : 'display:none;' ) . '">';
    echo 'Rhythmus: <select name="schedule_interval"><option value="hourly"' . selected( $schedule_interval, 'hourly', false ) . '>stündlich</option><option value="daily"' . selected( $schedule_interval, 'daily', false ) . '>täglich</option><option value="weekly"' . selected( $schedule_interval, 'weekly', false ) . '>wöchentlich</option></select> ';
    echo '<span id="weekly_day_field" style="' . ( $schedule_interval==='weekly' ? '' : 'display:none;' ) . '">Tag: <select name="schedule_day">';
    $days = [1=>'Montag',2=>'Dienstag',3=>'Mittwoch',4=>'Donnerstag',5=>'Freitag',6=>'Samstag',7=>'Sonntag'];
    foreach ( $days as $dval=>$dlabel ) {
        echo '<option value="' . $dval . '"' . selected( $schedule_day, $dval, false ) . '>' . esc_html( $dlabel ) . '</option>';
    }
    echo '</select></span> ';
    echo 'Uhrzeit: <input type="time" name="schedule_time" value="' . esc_attr( $schedule_time ?? '08:00:00' ) . '">';
    echo '</div>';
    echo '</td></tr>';

    echo '</table>';

    // Platzhalter-Hinweis, Betreff, Inhalt
    echo '<p><strong>Verfügbare Platzhalter:</strong> {CUSTOMER_FIRSTNAME}, {CUSTOMER_LASTNAME}, {CUSTOMER_EMAIL}, {APPOINTMENT_DATE}, {APPOINTMENT_TIME_START}, {APPOINTMENT_TIME_END}, {INSTITUTE_NAME}, {INSTITUTE_EMAIL}, {INSTITUTE_ADDRESS}, {INSTITUTE_PHONE}, {INSTITUTE_WHATSAPP}</p>';
    echo '<p><label for="subject"><strong>Betreff:</strong></label><br>';
    echo '<input type="text" name="subject" id="subject" value="' . esc_attr( $subject ?? '' ) . '" class="large-text"></p>';
    echo '<p><label for="email_content"><strong>Inhalt:</strong></label></p>';
    wp_editor( $content ?? '', 'email_content', [ 'textarea_name'=>'content', 'media_buttons'=>true, 'textarea_rows'=>10 ] );

    // Buttons
    echo '<p class="submit">';
    echo '<button type="submit" name="save_email_template" class="button button-primary">Speichern</button> ';
    echo '<button type="button" id="glattt-send-test" class="button">Test-Mail senden</button>';
    echo '</p>';
    echo '<input type="hidden" name="test_email" id="test_email_field" value="">';
    echo '</form></div>';

    // Select2 + Toggle-Script im Footer
    add_action( 'admin_footer', function(){
        $screen = get_current_screen();
        if ( strpos( $screen->id, 'glattt-email-details' ) !== false ) {
            ?>
            <script>
            jQuery(function($){
                // init Select2 für Service-Dropdowns
                $('.glattt-select2-service').select2({ width:'100%' });
                // Toggle pro Branch „Alle Services“
                $('.all_services_per_branch').on('change', function(){
                    var b = $(this).data('branch'),
                        sel = $('#services_'+b);
                    sel.prop('disabled', this.checked);
                });
                // Globaler „Alle Services“
                $('#all_services').on('change', function(){
                    var disable = this.checked;
                    $('.all_services_per_branch').prop('checked', disable);
                    $('.glattt-select2-service').prop('disabled', disable);
                });

                // Reminder variant toggle
                $('input[name="reminder_variant"]').on('change', function(){
                    var val = $(this).val();
                    if (val === 'hours') {
                        $('#reminder_fields_hours').show();
                        $('#reminder_fields_fixed').hide();
                    } else if (val === 'fixed') {
                        $('#reminder_fields_hours').hide();
                        $('#reminder_fields_fixed').show();
                    }
                });
                // On page load, trigger to show correct fields
                $('input[name="reminder_variant"]:checked').trigger('change');
            });
            </script>
            <?php
        }
    });
}