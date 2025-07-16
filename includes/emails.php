<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// -----------------------------------------
// E-Mail Versand Logik
// -----------------------------------------

// 1) Buchungsbestätigung versenden, wenn eine Buchung erfolgreich abgeschlossen wurde.
add_action( 'glattt_booking_success', 'glattt_send_booking_confirmation_email', 10, 2 );
function glattt_send_booking_confirmation_email( $client_id, $booking ) {
    global $wpdb;
    // Lade alle Templates mit E-Mail-Typ = 1 (Buchung)
    $table    = $wpdb->prefix . 'glattt_email_templates';
    $templates = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$table} WHERE email_type = %d",
        1
    ) );
    if ( empty( $templates ) ) {
        return;
    }

    // Client-Daten abrufen (besorgt darüber, ob die Phorest-API eine get_client-Methode anbietet)
    $api    = new GLATTT_Phorrest_API();
    $client = method_exists( $api, 'get_client' )
        ? $api->get_client( $client_id )
        : null;
    if ( is_wp_error( $client ) || empty( $client ) ) {
        return;
    }

    // Platzhalter füllen
    $placeholders = [
        '{CUSTOMER_NAME}'        => $client['firstName'] . ' ' . $client['lastName'],
        '{CUSTOMER_EMAIL}'       => $client['email'],
        '{APPOINTMENT_DATE}'     => date_i18n( 'd.m.Y', intval( $booking['startTime'] ) / 1000 ),
        '{APPOINTMENT_TIME_START}' => date_i18n( 'H:i', intval( $booking['startTime'] ) / 1000 ),
        '{APPOINTMENT_TIME_END}'   => date_i18n( 'H:i', intval( $booking['endTime'] ) / 1000 ),
        // Institute placeholders können hier ergänzt werden, falls nötig
        '{INSTITUTE_NAME}'       => '',
        '{INSTITUTE_EMAIL}'      => '',
        '{INSTITUTE_ADDRESS}'    => '',
    ];

    foreach ( $templates as $tmpl ) {
        // Betreff und Inhalt ersetzen
        $subject = strtr( $tmpl->subject, $placeholders );
        $content = strtr( $tmpl->content, $placeholders );

        // Empfänger ermitteln
        $to_addresses = strtr( $tmpl->to_address ?? '', $placeholders );
        $cc_addresses = strtr( $tmpl->cc_address ?? '', $placeholders );
        $bcc_addresses = strtr( $tmpl->bcc_address ?? '', $placeholders );

        $to = array_filter(array_map('trim', explode(',', $to_addresses)));
        $cc = array_filter(array_map('trim', explode(',', $cc_addresses)));
        $bcc = array_filter(array_map('trim', explode(',', $bcc_addresses)));

        if ( empty( $to ) ) {
            continue;
        }

        // Absender-Adresse aus Template (ggf. mit Platzhaltern ersetzt)
        $from_address = ! empty( $tmpl->from_address ) 
            ? strtr( $tmpl->from_address, $placeholders ) 
            : get_option( 'admin_email' );

        // HTML-Header mit From:
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_address,
        ];

        if ( ! empty( $cc ) ) {
            $headers[] = 'Cc: ' . implode( ', ', $cc );
        }
        if ( ! empty( $bcc ) ) {
            $headers[] = 'Bcc: ' . implode( ', ', $bcc );
        }

        // Versand
        wp_mail( $to, $subject, $content, $headers );
    }
}

// 2) Erinnerung-Mails planen: Stunde-basierter Cron
add_action( 'wp', function() {
    if ( ! wp_next_scheduled( 'glattt_send_reminder_emails' ) ) {
        wp_schedule_event( time(), 'hourly', 'glattt_send_reminder_emails' );
    }
} );
add_action( 'glattt_send_reminder_emails', 'glattt_send_reminder_emails' );
function glattt_send_reminder_emails() {
    // Hier später implementieren: Buchungen abfragen, die in X Stunden stattfinden, und E-Mails senden.
}

/**
 * Admin-Seite: Liste der E-Mail Vorlagen
 */
add_action( 'admin_menu', 'glattt_register_email_menus' );
function glattt_register_email_menus() {
    // 5) E-Mails
    add_submenu_page(
        'glattt-booking',
        'E-Mails',
        'E-Mails',
        'manage_options',
        'glattt-emails',
        'glattt_emails_page'
    );
    // 6) E-Mail-Details
    add_submenu_page(
        'glattt-booking',
        'E-Mail-Details',
        'E-Mail-Details',
        'manage_options',
        'glattt-email-details',
        'glattt_email_details_page'
    );
}

function glattt_emails_page() {
    global $wpdb;
    echo '<div class="wrap"><h1 class="wp-heading-inline">E-Mail Vorlagen</h1>';
    echo ' <a href="' . esc_url( admin_url( 'admin.php?page=glattt-email-details' ) ) . '" class="page-title-action">Neu hinzufügen</a>';
    echo '<hr class="wp-header-end">';

    // Daten abrufen
    $table = $wpdb->prefix . 'glattt_email_templates';
    $rows = $wpdb->get_results( "SELECT * FROM $table" );
    // Hilfsdaten nur einmal laden
    $api = new GLATTT_Phorrest_API();
    $all_branches = $api->get_branches();
    $inst_meta_table = $wpdb->prefix . 'glattt_institute_meta';
    $inst_meta_all = $wpdb->get_results( "SELECT branch_id, custom_name FROM $inst_meta_table", OBJECT_K );
    $svc_meta_table = $wpdb->prefix . 'glattt_service_meta';
    $svc_meta_all = $wpdb->get_results( "SELECT service_id, friendly_name FROM $svc_meta_table", OBJECT_K );

    if ( empty( $rows ) ) {
        echo '<p>Keine E-Mail Vorlagen vorhanden.</p>';
        echo '</div>';
        return;
    }

    // Tabelle ausgeben
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th scope="col">Name der E-Mail</th>';
    echo '<th scope="col">Empfänger</th>';
    echo '<th scope="col">Standorte</th>';
    echo '<th scope="col">Verknüpfter Service</th>';
    echo '<th scope="col">Art der Mail</th>';
    echo '<th scope="col" style="width:50px; text-align:center;">Bearbeiten</th>';
    echo '</tr></thead><tbody>';

    foreach ( $rows as $row ) {
        $id = intval( $row->id );
        $name = esc_html( $row->name );
        // Empfänger zusammensetzen
        $recipients = [];
        $types = [ 1 => 'An', 2 => 'CC', 3 => 'BCC' ];
        if ( $row->customer_type > 0 ) {
            $recipients[] = 'Kunde (' . $types[ intval( $row->customer_type ) ] . ')';
        }
        if ( $row->location_type > 0 ) {
            $recipients[] = 'Standort (' . $types[ intval( $row->location_type ) ] . ')';
        }
        if ( $row->admin_type > 0 ) {
            $recipients[] = 'Administration (' . $types[ intval( $row->admin_type ) ] . ')';
        }
        $recipients_text = ! empty( $recipients ) ? implode( ', ', $recipients ) : 'Keine';

        // Standorte aufbereiten
        $locations_text = 'Keine';
        if ( isset( $row->all_locations ) && $row->all_locations ) {
            $locations_text = 'Alle';
        } elseif ( ! empty( $row->branch_ids ) ) {
            $branch_ids = array_map( 'trim', explode( ',', $row->branch_ids ) );
            $branch_names = [];
            if ( ! is_wp_error( $all_branches ) && ! empty( $all_branches ) ) {
                foreach ( $all_branches as $b ) {
                    $bid = $b['branchId'] ?? '';
                    if ( ! $bid || ! in_array( $bid, $branch_ids ) ) continue;
                    $bname = $b['name'] ?? '';
                    if ( isset( $inst_meta_all[ $bid ] ) && ! empty( $inst_meta_all[ $bid ]->custom_name ) ) {
                        $bname = $inst_meta_all[ $bid ]->custom_name;
                    }
                    if ( empty( $bname ) ) $bname = '(unbenannter Standort)';
                    $branch_names[] = esc_html( $bname );
                }
            }
            if ( ! empty( $branch_names ) ) {
                $locations_text = implode( ', ', $branch_names );
            }
        }

        // Services aufbereiten
        $services_text = 'Keine';
        if ( isset( $row->all_services ) && $row->all_services ) {
            $services_text = 'Alle';
        } elseif ( ! empty( $row->service_ids ) ) {
            $service_ids = array_map( 'trim', explode( ',', $row->service_ids ) );
            $service_names = [];
            foreach ( $service_ids as $sid ) {
                if ( isset( $svc_meta_all[ $sid ] ) && ! empty( $svc_meta_all[ $sid ]->friendly_name ) ) {
                    $service_names[] = $svc_meta_all[ $sid ]->friendly_name;
                } else {
                    $service_names[] = 'Service ' . $sid;
                }
            }
            if ( count( $service_names ) > 1 ) {
                $services_text = count( $service_names ) . ' Services';
            } elseif ( ! empty( $service_names ) ) {
                $services_text = $service_names[0];
            }
        }

        // Art der Mail ermitteln
        $type_label = '';
        switch ( intval( $row->email_type ) ) {
            case 1: $type_label = 'Buchung'; break;
            case 2: $type_label = 'Erinnerung'; break;
            case 3: $type_label = 'Absage'; break;
            case 4: $type_label = 'Administration'; break;
        }

        // Zeile ausgeben
        $edit_url = admin_url( 'admin.php?page=glattt-email-details&template=' . $id );
        echo '<tr>';
        echo '<td>' . $name . '</td>';
        echo '<td>' . esc_html( $recipients_text ) . '</td>';
        echo '<td>' . esc_html( $locations_text ) . '</td>';
        echo '<td>' . esc_html( $services_text ) . '</td>';
        echo '<td>' . esc_html( $type_label ) . '</td>';
        echo '<td style="text-align:center;"><a href="' . esc_url( $edit_url ) . '"><span class="dashicons dashicons-edit"></span></a></td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}