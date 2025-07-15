<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * includes/frontend-booking.php
 * Frontend Shortcode & AJAX-Endpunkte
 */

add_action( 'init', function() {
    add_shortcode( 'glattt_booking', 'glattt_render_booking_shortcode' );
});

add_action( 'wp_enqueue_scripts', function() {
    global $post;
    if ( isset( $post->post_content ) && has_shortcode( $post->post_content, 'glattt_booking' ) ) {
        wp_enqueue_style( 'glattt-booking-frontend', WPGLATTT_URL . 'assets/css/booking-frontend.css', [], WPGLATTT_VER );
        wp_enqueue_script( 'glattt-booking-frontend', WPGLATTT_URL . 'assets/js/booking-frontend.js', ['jquery'], WPGLATTT_VER, true );
        wp_localize_script( 'glattt-booking-frontend', 'glatttFrontend', [
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce_get'  => wp_create_nonce( 'glattt_get_availability' ),
            'nonce_book' => wp_create_nonce( 'glattt_book_appointment' ),
        ] );
    }
});

/**
 * Shortcode-Ausgabe
 */
function glattt_render_booking_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'branch'    => '',
        'branch-id' => '',
    ], $atts, 'glattt_booking' );

    $default_branch = $atts['branch-id'] !== '' ? $atts['branch-id'] : $atts['branch'];

    ob_start(); ?>
    <div id="glattt-booking-widget"<?php if ( $default_branch ): ?> data-default-branch="<?php echo esc_attr( $default_branch ); ?>"<?php endif; ?>>
      <div class="steps-wrapper">
        <!-- STEP 1 -->
        <div class="step-1">
          <div class="institute-selector">
            <button type="button" class="institute-prev">&lt;</button>
            <div class="institute-display">
              <div class="institute-info">
                <h3 class="institute-name"></h3>
                <p class="institute-address"></p>
              </div>
            </div>
            <button type="button" class="institute-next">&gt;</button>
          </div>
          <input type="hidden" id="glattt-branch" name="branch" value="" />

          <div class="service-select">
            <label for="glattt-service">Service wählen:</label>
            <select id="glattt-service" name="service" disabled></select>
          </div>

          <div class="week-nav">
            <button class="prev-week" disabled>&larr;</button>
            <span class="week-range"></span>
            <button class="next-week">&rarr;</button>
          </div>

          <div class="timeslots"></div>
        </div>

        <!-- STEP 2 -->
        <div class="step-2 hidden">
          <div class="institute-selector small">
            <button type="button" class="institute-prev">&lt;</button>
            <div class="institute-display">
              <div class="institute-info">
                <h3 class="institute-name"></h3>
                <p class="institute-address"></p>
              </div>
            </div>
            <button type="button" class="institute-next">&gt;</button>
          </div>
          <div class="go-back-link go-back">← Zur Terminauswahl</div>
          <h3>Deine Buchungsdaten</h3>

          <div class="booking-summary">
            <p>Datum: <strong><span class="sum-date"></span></strong><br>
            Uhrzeit: <strong><span class="sum-time"></span></strong><br>
            Institut: <strong><span class="sum-institute"></span></strong></p>
          </div>

          <form id="glattt-booking-form">
            <input type="hidden" name="branch" />
            <input type="hidden" name="service" />
            <input type="hidden" name="start" />
            <input type="hidden" name="end" />
            <input type="hidden" name="staff" />

            <label>Vorname*<br><input type="text" name="firstname" required /></label>
            <label>Nachname*<br><input type="text" name="lastname" required /></label>
            <label>E-Mail-Adresse*<br><input type="email" name="email" required /></label>
            <label>Handy<br><input type="tel" name="phone" minlength="6" /></label>
            <label>Welche Körperzonen willst Du behandeln lassen?*<br><input type="text" name="message" required /></label>
            <label><input type="checkbox" name="gdpr" required /> Ich akzeptiere die <a target="_blank" rel="noopener noreferrer" href="/datenschutz">Datenschutzbedingungen</a>.</label></br>
            <button type="submit" class="button button-primary">Jetzt buchen</button>
          </form>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

// AJAX: Branches holen
add_action( 'wp_ajax_nopriv_glattt_get_branches', 'glattt_get_branches' );
add_action( 'wp_ajax_glattt_get_branches',      'glattt_get_branches' );
function glattt_get_branches() {
    check_ajax_referer( 'glattt_get_availability', 'nonce' );
    global $wpdb;
    $api    = new GLATTT_Phorrest_API();
    $result = $api->get_branches();
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }
    $active_map = get_option( 'glattt_active_institutes', [] );
    $filtered   = array_filter( $result, function( $b ) use ( $active_map ) {
        return isset( $b['branchId'] )
            && ( ! isset( $active_map[ $b['branchId'] ] ) 
                 || 1 === intval( $active_map[ $b['branchId'] ] ) );
    });
    $meta_table = $wpdb->prefix . 'glattt_institute_meta';
    foreach ( $filtered as &$b ) {
        $image_id      = $wpdb->get_var( $wpdb->prepare(
            "SELECT image_id FROM $meta_table WHERE branch_id = %s",
            $b['branchId']
        ) );
        $b['imageUrl'] = $image_id ? wp_get_attachment_url( intval( $image_id ) ) : '';
    }
    unset( $b );
    wp_send_json_success( array_values( $filtered ) );
}

// AJAX: Services holen
add_action( 'wp_ajax_nopriv_glattt_get_services', 'glattt_get_services_callback' );
add_action( 'wp_ajax_glattt_get_services',      'glattt_get_services_callback' );
function glattt_get_services_callback() {
    check_ajax_referer( 'glattt_get_availability', 'nonce' );
    global $wpdb;
    $branch = sanitize_text_field( $_POST['branch'] ?? '' );
    $api    = new GLATTT_Phorrest_API();
    $all    = $api->get_services( $branch );
    if ( is_wp_error( $all ) ) {
        wp_send_json_error( $all->get_error_message() );
    }
    $bookable = get_option( 'glattt_bookable_services_' . $branch, [] );
    $filtered = array_filter( $all, function( $s ) use ( $bookable ) {
        return isset( $s['serviceId'] ) && in_array( $s['serviceId'], $bookable, true );
    });
    $ids = wp_list_pluck( $filtered, 'serviceId' );
    if ( $ids ) {
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT service_id, friendly_name FROM {$wpdb->prefix}glattt_service_meta WHERE service_id IN ($placeholders)",
                ...$ids
            ), ARRAY_A
        );
        $meta_map = [];
        foreach ( $rows as $r ) {
            $meta_map[ $r['service_id'] ] = $r['friendly_name'];
        }
        foreach ( $filtered as &$s ) {
            $sid = $s['serviceId'];
            $s['friendly_name'] = $meta_map[ $sid ] ?? '';
        }
        unset( $s );
    }
    wp_send_json_success( array_values( $filtered ) );
}

// AJAX: Verfügbarkeit
add_action( 'wp_ajax_nopriv_glattt_get_availability', 'glattt_get_availability' );
add_action( 'wp_ajax_glattt_get_availability',      'glattt_get_availability' );
function glattt_get_availability() {
    check_ajax_referer( 'glattt_get_availability', 'nonce' );
    $branch  = sanitize_text_field( $_POST['branch']  ?? '' );
    $service = sanitize_text_field( $_POST['service'] ?? '' );
    $monday  = intval( $_POST['monday'] ?? 0 );
    $sunday  = intval( $_POST['sunday'] ?? 0 );
    $api     = new GLATTT_Phorrest_API();
    $slots   = $api->get_availability( $branch, $service, $monday, $sunday );
    if ( is_wp_error( $slots ) ) {
        wp_send_json_error( $slots->get_error_message() );
    }
    wp_send_json_success( $slots );
}

/**
 * AJAX-Handler: Kunde anlegen & Termin buchen
 */
add_action( 'wp_ajax_nopriv_glattt_book_appointment', 'glattt_book_appointment' );
add_action( 'wp_ajax_glattt_book_appointment',      'glattt_book_appointment' );
function glattt_book_appointment() {
    // 1) Business-ID
    $business_id = get_option( 'glattt_business_id' );
    if ( empty( $business_id ) ) {
        wp_send_json_error([ 'message' => 'Phorest Business-ID ist leer.' ]);
    }
    check_ajax_referer( 'glattt_book_appointment', 'nonce' );

    // 2) Formular-Daten
    $input     = array_map( 'sanitize_text_field', $_POST );
    $branchId  = $input['branch'];
    $serviceId = $input['service'];
    $startMs   = intval( $input['start'] );
    $endMs     = intval( $input['end'] );
    $firstname = $input['firstname'];
    $lastname  = $input['lastname'];
    $email     = $input['email'];
    $phone     = $input['phone'];
    $message   = $input['message'];
    $staffId   = $input['staff'] ?? '';

    // 3) Auth-Header – hier die korrekten Optionen verwenden!
    $user        = get_option( 'glattt_username' );
    $pass        = get_option( 'glattt_password' );
    $auth_header = 'Basic ' . base64_encode( "{$user}:{$pass}" );

    // --- A) Kunde anlegen (200 oder 201 OK) ---
    $client_payload = [
        'firstName' => $firstname,
        'lastName'  => $lastname,
        'email'     => $email,
        'mobile'    => $phone,
    ];
    $client_resp = wp_remote_post(
        "https://api-gateway-eu.phorest.com/third-party-api-server/api/business/{$business_id}/client",
        [
            'headers' => [
                'Authorization' => $auth_header,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => wp_json_encode( $client_payload ),
            'timeout' => 15,
        ]
    );
    $client_code = wp_remote_retrieve_response_code( $client_resp );
    if ( is_wp_error( $client_resp ) || ! in_array( $client_code, [200,201], true ) ) {
        $err = is_wp_error( $client_resp )
             ? $client_resp->get_error_message()
             : wp_remote_retrieve_body( $client_resp );
        wp_send_json_error([ 'message' => "Konnte Client nicht anlegen (HTTP {$client_code}): {$err}" ]);
    }
    $client_data = json_decode( wp_remote_retrieve_body( $client_resp ), true );
    $client_id   = $client_data['clientId'] ?? '';
    if ( empty( $client_id ) ) {
        wp_send_json_error([ 'message' => 'Client-ID fehlt im API-Response.' ]);
    }

    // --- B) Termin buchen (200 oder 201 OK) ---
    $booking_payload = [
        'bookingStatus' => 'ACTIVE',
        'clientAppointmentSchedules' => [
            [
                'clientId'         => $client_id,
                'serviceSchedules' => [
                    [
                        'serviceId' => $serviceId,
                        'staffId'   => $staffId ?: null,
                        'startTime' => date( DATE_ISO8601, $startMs / 1000 ),
                        'endTime'   => date( DATE_ISO8601, $endMs   / 1000 ),
                    ],
                ],
            ],
        ],
        'clientId' => $client_id,
        'note'     => $message,
    ];
    $booking_resp = wp_remote_post(
        "https://api-gateway-eu.phorest.com/third-party-api-server/api/business/{$business_id}/branch/{$branchId}/booking",
        [
            'headers' => [
                'Authorization' => $auth_header,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => wp_json_encode( $booking_payload ),
            'timeout' => 15,
        ]
    );
    $booking_code = wp_remote_retrieve_response_code( $booking_resp );
    if ( is_wp_error( $booking_resp ) || ! in_array( $booking_code, [200,201], true ) ) {
        $err = is_wp_error( $booking_resp )
             ? $booking_resp->get_error_message()
             : wp_remote_retrieve_body( $booking_resp );
        wp_send_json_error([ 'message' => "Fehler bei der Buchung (HTTP {$booking_code}): {$err}" ]);
    }

    // 4) Log & Redirect
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'glattt_booking_logs',
        [
            'timestamp'  => current_time( 'mysql' ),
            'branch_id'  => $branchId,
            'service_id' => $serviceId,
            'start_time' => date( 'Y-m-d H:i:s', $startMs / 1000 ),
            'end_time'   => date( 'Y-m-d H:i:s', $endMs   / 1000 ),
            'firstname'  => $firstname,
            'lastname'   => $lastname,
            'email'      => $email,
            'phone'      => $phone,
            'message'    => $message,
            'status'     => 'success',
            'error_msg'  => '',
            'referrer'   => sanitize_text_field( $_SERVER['HTTP_REFERER'] ?? '' ),
            'query'      => wp_json_encode( $_GET ),
        ]
    );

    wp_send_json_success([ 'redirect' => site_url( '/danke-buchung' ) ]);
}