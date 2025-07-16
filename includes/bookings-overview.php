<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Synchronize all bookings' statuses with the Phorest API
 */
function glattt_sync_all_bookings() {
    global $wpdb;
    $table        = $wpdb->prefix . 'glattt_booking_logs';

    $business_id = get_option( 'glattt_business_id' );
    $user        = get_option( 'glattt_username' );
    $pass        = get_option( 'glattt_password' );
    $auth_header = 'Basic ' . base64_encode( "{$user}:{$pass}" );

    $now_timestamp = time();

    $bookings = $wpdb->get_results( "SELECT * FROM {$table}" );
    if ( ! $bookings ) {
        return;
    }

    foreach ( $bookings as $booking ) {
        if ( empty( $booking->appointment_id ) || empty( $booking->branch_id ) ) {
            continue;
        }
        // Only sync bookings that are still marked 'success'
        if ( isset( $booking->status ) && $booking->status !== 'success' ) {
            continue;
        }

        $appointment_id = rawurlencode( $booking->appointment_id );
        $branch_id = rawurlencode( $booking->branch_id );

        $url = "https://api-gateway-eu.phorest.com/third-party-api-server/api/business/{$business_id}/branch/{$branch_id}/appointments?appointmentId={$appointment_id}";

        $response = wp_remote_get( $url, [
            'headers' => [
                'Authorization' => $auth_header,
                'Accept'        => 'application/json',
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( '[glattt][SYNC] WP_Error: ' . $response->get_error_message() );
            continue;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $status_to_set = null;

        if ( $code === 200 ) {
            $data = json_decode( $body, true );
            $items = is_array( $data ) && isset( $data['data'] ) ? $data['data'] : [];
            if ( empty( $items ) ) {
                // Appointment no longer exists -> canceled
                $status_to_set = 'Abgesagt';
            } else {
                $item = $items[0];
                $state = $item['state'] ?? '';
                $startTime = $item['startTime'] ?? '';
                if ( $state === 'BOOKED' && $startTime !== '' ) {
                    $start_timestamp = strtotime( $startTime );
                    if ( $start_timestamp !== false && $start_timestamp < $now_timestamp ) {
                        $status_to_set = 'NO SHOW';
                    }
                } elseif ( $state === 'PAID' ) {
                    $status_to_set = 'Termin stattgefunden';
                }
            }
        }

        if ( $status_to_set !== null && $booking->status !== $status_to_set ) {
            $updated = $wpdb->update(
                $table,
                [ 'status' => $status_to_set ],
                [ 'appointment_id' => $booking->appointment_id ],
                [ '%s' ],
                [ '%s' ]
            );
            if ( $updated !== false ) {
                error_log( "[glattt][SYNC] Updated appointment_id {$booking->appointment_id} status to {$status_to_set}" );
            }
        }
    }
}

add_action('wp_ajax_glattt_cancel_booking', 'glattt_ajax_cancel_booking');
add_action('wp_ajax_glattt_sync_bookings', 'glattt_ajax_sync_bookings');

/**
 * Admin-Seite: Buchungs-Übersicht anzeigen
 */
function glattt_bookings_overview_page() {
    glattt_sync_all_bookings();

    global $wpdb;
    $table        = $wpdb->prefix . 'glattt_booking_logs';
    $api          = new GLATTT_Phorrest_API();

    // Phorest Business-ID & Auth-Header
    $business_id = get_option( 'glattt_business_id' );
    $user        = get_option( 'glattt_username' );
    $pass        = get_option( 'glattt_password' );
    $auth_header = 'Basic ' . base64_encode( "{$user}:{$pass}" );

    $branches_all = $api->get_branches();

    // Filterwerte aus GET holen
    $firstname  = isset( $_GET['firstname'] ) ? sanitize_text_field( $_GET['firstname'] ) : '';
    $email      = isset( $_GET['email'] )     ? sanitize_email( $_GET['email'] )         : '';
    $branch     = isset( $_GET['branch'] )    ? sanitize_text_field( $_GET['branch'] )   : '';
    $date_from  = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
    $date_to    = isset( $_GET['date_to'] )   ? sanitize_text_field( $_GET['date_to'] )   : '';

    // WHERE-Klausel aufbauen
    $where = [ '1=1' ];
    $args  = [];

    if ( $firstname ) {
        $where[] = 'firstname LIKE %s';
        $args[]  = '%' . $wpdb->esc_like( $firstname ) . '%';
    }
    if ( $email ) {
        $where[] = 'email LIKE %s';
        $args[]  = '%' . $wpdb->esc_like( $email ) . '%';
    }
    if ( $branch ) {
        $where[] = 'branch_id = %s';
        $args[]  = $branch;
    }
    if ( $date_from ) {
        $where[] = 'DATE(timestamp) >= %s';
        $args[]  = $date_from;
    }
    if ( $date_to ) {
        $where[] = 'DATE(timestamp) <= %s';
        $args[]  = $date_to;
    }

    $where_sql = implode( ' AND ', $where );
    $sql       = $wpdb->prepare(
        "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY timestamp DESC",
        $args
    );
    $results   = $wpdb->get_results( $sql );

    echo '<div class="wrap">';
    echo '<h1>Buchungs-Übersicht</h1>';
    echo '<p><button id="glattt-sync-bookings" class="button">Abgleich starten</button></p>';

    // Filterformular
    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="glattt-bookings-overview"/>';
    echo '<table class="form-table"><tr>';
    echo '<th><label for="firstname">Vorname:</label></th>';
    echo '<td><input type="text" name="firstname" id="firstname" value="' . esc_attr( $firstname ) . '"/></td>';
    echo '<th><label for="email">E-Mail:</label></th>';
    echo '<td><input type="text" name="email" id="email" value="' . esc_attr( $email ) . '"/></td>';
    echo '<th><label for="branch">Institut:</label></th>';
    echo '<td><select name="branch" id="branch">';
    echo '<option value="">Alle</option>';
    foreach ( $branches_all as $b ) {
        echo '<option value="' . esc_attr( $b['branchId'] ) . '" ' . selected( $branch, $b['branchId'], false ) . '>' . esc_html( $b['name'] ) . '</option>';
    }
    echo '</select></td>';
    echo '</tr><tr>';
    echo '<th><label for="date_from">Von:</label></th>';
    echo '<td><input type="date" name="date_from" value="' . esc_attr( $date_from ) . '"/></td>';
    echo '<th><label for="date_to">Bis:</label></th>';
    echo '<td><input type="date" name="date_to" value="' . esc_attr( $date_to ) . '"/></td>';
    echo '<td colspan="2"><button class="button" type="submit">Filtern</button></td>';
    echo '</tr></table>';
    echo '</form>';

    // Ergebnisse-Tabelle
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>ID</th><th>Client-ID</th><th>Kundennummer</th><th>Zeitstempel</th><th>Vorname</th><th>Nachname</th><th>E-Mail</th>';
    echo '<th>Telefon</th><th>Institut</th><th>Service</th><th>Terminstart</th><th>Status</th>';
    echo '<th>Aktion</th>';
    echo '</tr></thead><tbody>';

    if ( $results ) {
        foreach ( $results as $row ) {
            // Branch-Name ermitteln
            $branch_name = '';
            foreach ( $branches_all as $b ) {
                if ( $b['branchId'] === $row->branch_id ) {
                    $branch_name = $b['name'];
                    break;
                }
            }
            // Kundennummer (externalID) über interne Client-ID
            $client_number = '';
            if ( ! empty( $row->client_id ) ) {
                $cid  = rawurlencode( $row->client_id );
                $url  = "https://api-gateway-eu.phorest.com/third-party-api-server/api/business/{$business_id}/client/{$cid}";
                $resp = wp_remote_get( $url, [
                    'headers' => [ 'Authorization' => $auth_header ],
                    'timeout' => 15,
                ] );
                if ( ! is_wp_error( $resp ) && wp_remote_retrieve_response_code( $resp ) === 200 ) {
                    $data = json_decode( wp_remote_retrieve_body( $resp ), true );
                    $client_number = $data['externalId'] ?? '';
                }
            }
            // Service-Name ermitteln
            $service_name = $row->service_id;
            $services_list = $api->get_services( $row->branch_id );
            if ( ! is_wp_error( $services_list ) ) {
                foreach ( $services_list as $svc ) {
                    if ( $svc['serviceId'] === $row->service_id ) {
                        $service_name = $svc['name'] ?? $service_name;
                        break;
                    }
                }
            }
            echo '<tr>';
            echo '<td>' . intval( $row->id ) . '</td>';
            echo '<td>' . esc_html( $row->client_id ) . '</td>';
            echo '<td>' . esc_html( $client_number ) . '</td>';
            echo '<td>' . esc_html( $row->timestamp ) . '</td>';
            echo '<td>' . esc_html( $row->firstname ) . '</td>';
            echo '<td>' . esc_html( $row->lastname ) . '</td>';
            echo '<td>' . esc_html( $row->email ) . '</td>';
            echo '<td>' . esc_html( $row->phone ) . '</td>';
            echo '<td>' . esc_html( $branch_name ) . '</td>';
            echo '<td>' . esc_html( $service_name ) . '</td>';
            echo '<td>' . esc_html( $row->start_time ) . '</td>';
            echo '<td>' . esc_html( $row->status ) . '</td>';
            // Aktion: Termin stornieren (nur wenn Status "success")
            echo '<td>';
            if ( $row->status === 'success' ) {
                echo '<button type="button" class="button glattt-cancel-booking" style="color:red;" data-appointment-id="' . esc_attr( $row->appointment_id ?? '' ) . '" data-branch-id="' . esc_attr( $row->branch_id ) . '">✕</button>';
            }
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="11">Keine Buchungen gefunden.</td></tr>';
    }

    echo '</tbody></table>';

    // JS: Sync and cancel handlers
    echo "<script>
    jQuery(function($){
        console.log('🔔 bookings-overview.js loaded');
        $('#glattt-sync-bookings').on('click', function(e){
            console.log('🔄 Sync bookings clicked');
            e.preventDefault();
            $.post(ajaxurl, { action:'glattt_sync_bookings' }, function(resp){
                console.log('🔄 Sync response:', resp);
                if(resp.success) location.reload(); else alert('Abgleich fehlgeschlagen.');
            });
        });
        $('.glattt-cancel-booking').on('click', function(e){
            var btn = $(this);
            var appt = btn.data('appointment-id');
            var branchId = btn.data('branch-id');
            console.log('❌ Cancel booking clicked for appointment ID:', appt);
            e.preventDefault();
            if(!appt) return;
            if(!confirm('Termin wirklich stornieren?')) return;
            $.post(ajaxurl, { action:'glattt_cancel_booking', appointment_id: appt, branch_id: branchId }, function(resp){
                console.log('❌ Cancel response:', resp);
                if(resp.success) location.reload(); else alert('Stornierung fehlgeschlagen.');
            });
        });
    });
    </script>";

    echo '</div>';
}

function glattt_ajax_sync_bookings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Keine Berechtigung.' );
    }
    glattt_sync_all_bookings();
    wp_send_json_success();
}

function glattt_ajax_cancel_booking() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Keine Berechtigung.' );
    }

    if ( empty( $_POST['appointment_id'] ) ) {
        wp_send_json_error( 'Keine Termin-ID übergeben.' );
    }

    $appointment_id = sanitize_text_field( wp_unslash( $_POST['appointment_id'] ) );
    $branch_id = isset($_POST['branch_id']) ? sanitize_text_field(wp_unslash($_POST['branch_id'])) : '';

    global $wpdb;
    $business_id = get_option( 'glattt_business_id' );
    $user        = get_option( 'glattt_username' );
    $pass        = get_option( 'glattt_password' );
    $auth_header = 'Basic ' . base64_encode( "{$user}:{$pass}" );

    // Build cancel URL with query parameter for appointment ID
    $url = "https://api-gateway-eu.phorest.com/third-party-api-server/api/business/{$business_id}/branch/{$branch_id}/appointment/cancel?appointment_id=" . rawurlencode( $appointment_id );

    error_log( "[glattt][CANCEL] URL: $url" );

    $response = wp_remote_post( $url, [
        'headers' => [
            'Authorization' => $auth_header,
            'Accept'        => 'application/json',
        ],
        'timeout' => 15,
    ] );

    // Log the raw response
    if ( is_wp_error( $response ) ) {
        error_log( '[glattt][CANCEL] WP_Error: ' . $response->get_error_message() );
    } else {
        $code = wp_remote_retrieve_response_code( $response );
        $resp_body = wp_remote_retrieve_body( $response );
        error_log( "[glattt][CANCEL] HTTP $code – RESPONSE BODY: $resp_body" );
    }

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'Fehler bei der API-Anfrage.' );
    }

    $code = wp_remote_retrieve_response_code( $response );
    if ( ! in_array( $code, [200, 204], true ) ) {
        wp_send_json_error( 'Stornierung fehlgeschlagen. HTTP Status: ' . $code );
    }

    $table = $wpdb->prefix . 'glattt_booking_logs';
    $updated = $wpdb->update(
        $table,
        [ 'status' => 'Abgesagt' ],
        [ 'appointment_id' => $appointment_id ],
        [ '%s' ],
        [ '%s' ]
    );

    wp_send_json_success();
}