<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once WPGLATTT_PATH . 'includes/class-phorest-api.php';
require_once WPGLATTT_PATH . 'includes/email-sender.php';

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
      <div class="initial-overlay">
  <button id="glattt-start-booking" class="button button-primary start-booking">
    Jetzt Deinen Termin buchen
  </button>
</div>
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

          <div class="booking-summary-card">
            <div class="booking-summary">
              <span class="summary-item">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd"/></svg>
                <strong><span class="sum-date"></span></strong>
              </span>
              <span class="summary-item">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd"/></svg>
                <strong><span class="sum-time"></span></strong>
              </span>
              <span class="summary-item">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="m9.69 18.933.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 0 0 .281-.145c.18-.096.422-.232.702-.408.56-.352 1.286-.868 2.01-1.553C14.747 15.46 16 13.26 16 10.5V5.214A2 2 0 0 0 14.758 3.39l-4.272-1.424a2 2 0 0 0-1.272.004L5.227 3.397A2 2 0 0 0 4 5.214V10.5c0 2.76 1.253 4.96 2.673 6.317a13.06 13.06 0 0 0 2.01 1.553 8.122 8.122 0 0 0 .702.408 5.741 5.741 0 0 0 .281.145l.018.008.006.003ZM10 11.25a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Zm0 1.5a3.75 3.75 0 0 0-3.56 2.563c.67.542 1.392.983 2.065 1.299.34.16.637.281.875.363A6.588 6.588 0 0 0 10 17c.027 0 .26-.014.62-.125.238-.082.535-.203.875-.363a11.56 11.56 0 0 0 2.065-1.299A3.75 3.75 0 0 0 10 12.75Z" clip-rule="evenodd"/></svg>
                <strong><span class="sum-institute"></span></strong>
              </span>
            </div>
          </div>

          <p class="form-disclaimer">Damit wir Deine Beratung individuell auf Dich abstimmen können, benötigen wir vorab ein paar Angaben von Dir.</p>

          <form id="glattt-booking-form">
            <input type="hidden" name="branch" />
            <input type="hidden" name="service" />
            <input type="hidden" name="start" />
            <input type="hidden" name="end" />
            <input type="hidden" name="staff" />

            <div class="form-field-group">
              <label class="group-label">Geschlecht*</label>
              <div class="gender-switch" id="gender-switch">
                <button type="button" data-value="FEMALE" class="gender-option">Weiblich</button>
                <button type="button" data-value="MALE" class="gender-option">Männlich</button>
                <button type="button" data-value="NON_BINARY" class="gender-option">Non-binär</button>
                <button type="button" data-value="PREFER_NOT_TO_SAY" class="gender-option">Keine Angabe</button>
              </div>
              <input type="hidden" name="gender" id="gender" required />
              <div class="validation-tooltip" id="gender-tooltip">
                <span class="validation-tooltip-icon">⚠</span> Bitte wähle Dein Geschlecht aus.
              </div>
            </div>

            <div class="form-field">
              <input type="text" name="firstname" id="firstname" placeholder=" " required />
              <label for="firstname">Vorname*</label>
            </div>
            <div class="form-field">
              <input type="text" name="lastname" id="lastname" placeholder=" " required />
              <label for="lastname">Nachname*</label>
            </div>
            <div class="form-field">
              <input type="date" name="birthday" id="birthday" required max="<?php echo date('Y-m-d'); ?>" />
              <label for="birthday">Geburtsdatum*</label>
            </div>
            <div class="form-field">
              <input type="email" name="email" id="email" placeholder=" " required />
              <label for="email">E-Mail-Adresse*</label>
            </div>
            <div class="form-field">
              <input type="tel" name="phone" id="phone" placeholder=" " minlength="6" required />
              <label for="phone">Handy*</label>
            </div>
            <div class="form-field">
              <input type="text" name="zip" id="zip" placeholder=" " required pattern="[0-9]{5}" maxlength="5" inputmode="numeric" title="Bitte gib eine gültige 5-stellige Postleitzahl ein" />
              <label for="zip">Postleitzahl*</label>
            </div>
            <div class="form-field">
              <input type="text" name="message" id="message" placeholder=" " required />
              <label for="message">Welche Körperzonen willst Du behandeln lassen?*</label>
            </div>
            <div class="form-checkbox">
              <input type="checkbox" name="gdpr" id="gdpr" required />
              <label for="gdpr">Ich akzeptiere die <a target="_blank" rel="noopener noreferrer" href="/datenschutz">Datenschutzbedingungen</a>.</label>
            </div>
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

    // Direkt Abfrage wie im alten Tool, um identische Slots zu erhalten
    $business_id  = get_option( 'glattt_business_id' );
    $user         = get_option( 'glattt_username' );
    $pass         = get_option( 'glattt_password' );
    $auth_header  = 'Basic ' . base64_encode( "{$user}:{$pass}" );

    $availability_response = wp_remote_post(
        "https://platform.phorest.com/third-party-api-server/api/business/{$business_id}/branch/{$branch}/appointments/availability",
        [
            'headers' => [
                'Authorization' => $auth_header,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => wp_json_encode( [
                'clientServiceSelections' => [
                    [
                        'serviceSelections' => [
                            [ 'serviceId' => $service ]
                        ]
                    ]
                ],
                'startTime'             => $monday,
                'endTime'               => $sunday,
                'isOnlineAvailability'  => true,
            ] ),
            'timeout' => 15,
        ]
    );

    // Fehler prüfen
    if ( is_wp_error( $availability_response ) ) {
        wp_send_json_error( $availability_response->get_error_message() );
    }

    $availability_body = wp_remote_retrieve_body( $availability_response );
    $availability_data = json_decode( $availability_body, true );
    
    // Prüfe ob die API-Antwort valide ist (data-Key muss existieren, kann aber leer sein)
    if ( ! isset( $availability_data['data'] ) ) {
        wp_send_json_error( 'Ungültige API-Antwort von Phorest: ' . $availability_body );
    }

    // Leeres Array ist OK (= keine Slots verfügbar), wird als success zurückgegeben
    wp_send_json_success( $availability_data['data'] );
}

/**
 * Normalisiert eine deutsche Telefonnummer ins Format 491234567890
 * @param string $phone Telefonnummer in beliebigem Format
 * @return string Normalisierte Telefonnummer
 */
function glattt_normalize_german_phone( $phone ) {
    // Alle Nicht-Ziffern entfernen (außer +)
    $phone = preg_replace( '/[^0-9+]/', '', $phone );
    
    // Entferne führendes + falls vorhanden
    $phone = ltrim( $phone, '+' );
    
    // Fall 1: Beginnt mit 0049 -> durch 49 ersetzen
    if ( strpos( $phone, '0049' ) === 0 ) {
        $phone = '49' . substr( $phone, 4 );
    }
    // Fall 2: Beginnt mit 49 -> okay
    elseif ( strpos( $phone, '49' ) === 0 ) {
        // Nichts tun, ist schon richtig
    }
    // Fall 3: Beginnt mit 0 -> 0 entfernen und 49 voranstellen
    elseif ( strpos( $phone, '0' ) === 0 ) {
        $phone = '49' . substr( $phone, 1 );
    }
    // Fall 4: Keine Ländervorwahl -> 49 voranstellen
    else {
        $phone = '49' . $phone;
    }
    
    return $phone;
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
    $gender    = $input['gender'] ?? '';
    $birthday  = $input['birthday'] ?? '';
    $zip       = $input['zip'] ?? '';
    $staffId   = $input['staff'] ?? '';

    // Telefonnummer normalisieren (ins Format 491234567890)
    $normalized_phone = glattt_normalize_german_phone( $phone );
    
    error_log( "📞 Buchung - Original Telefon: {$phone}, Normalisiert: {$normalized_phone}" );

    // 3) Auth-Header – hier die korrekten Optionen verwenden!
    $user        = get_option( 'glattt_username' );
    $pass        = get_option( 'glattt_password' );
    $auth_header = 'Basic ' . base64_encode( "{$user}:{$pass}" );

    // --- A) Zuerst Kunde per Telefonnummer suchen ---
    $api_instance = new GLATTT_Phorrest_API();
    $existing_client = $api_instance->search_client_by_phone( $normalized_phone );
    $client_id = '';
    
    if ( is_wp_error( $existing_client ) ) {
        // Fehler bei der Suche - trotzdem versuchen, neuen Kunden anzulegen
        error_log( "⚠️ Fehler bei Client-Suche: " . $existing_client->get_error_message() );
        $existing_client = [];
    }
    
    if ( ! empty( $existing_client ) && isset( $existing_client['clientId'] ) ) {
        // Kunde existiert bereits - ClientId verwenden
        $client_id = $existing_client['clientId'];
        error_log( "✅ Bestehender Kunde gefunden! ClientId: {$client_id}" );

        // Geschlecht, Geburtsdatum und PLZ aktualisieren (falls neu erfasst)
        $update_payload = [];
        if ( ! empty( $gender ) ) {
            $update_payload['gender'] = $gender;
        }
        if ( ! empty( $birthday ) ) {
            $update_payload['birthDate'] = $birthday;
        }
        if ( ! empty( $zip ) ) {
            $update_payload['address'] = [ 'postalCode' => $zip ];
        }
        if ( ! empty( $update_payload ) ) {
            wp_remote_request(
                "https://api-gateway-eu.phorest.com/third-party-api-server/api/business/{$business_id}/client/{$client_id}",
                [
                    'method'  => 'PATCH',
                    'headers' => [
                        'Authorization' => $auth_header,
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json',
                    ],
                    'body'    => wp_json_encode( $update_payload ),
                    'timeout' => 10,
                ]
            );
        }
    } else {
        // Kunde existiert noch nicht - neu anlegen mit normalisierter Telefonnummer
        error_log( "🆕 Neuen Kunden anlegen mit Telefon: {$normalized_phone}" );
        $client_payload = [
            'firstName' => $firstname,
            'lastName'  => $lastname,
            'email'     => $email,
            'mobile'    => $normalized_phone,
            'smsMarketingConsent'   => true,
            'emailMarketingConsent' => true,
            'smsReminderConsent'    => true,
            'emailReminderConsent'  => true,
        ];
        if ( ! empty( $gender ) ) {
            $client_payload['gender'] = $gender;
        }
        if ( ! empty( $birthday ) ) {
            $client_payload['birthDate'] = $birthday;
        }
        if ( ! empty( $zip ) ) {
            $client_payload['address'] = [ 'postalCode' => $zip ];
        }
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
    }
    
    if ( empty( $client_id ) ) {
        wp_send_json_error([ 'message' => 'Client-ID fehlt im API-Response.' ]);
    }

    // --- B) Termin buchen (200 oder 201 OK) ---
    $booking_note = "Körperzonen: {$message}";
    if ( ! empty( $zip ) ) {
        $booking_note .= " | PLZ: {$zip}";
    }
    
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
        'note'     => $booking_note,
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

    // Decode booking response to extract appointment ID
    $booking_data = json_decode( wp_remote_retrieve_body( $booking_resp ), true );
    $appointment_id = '';
    if ( ! empty( $booking_data['clientAppointmentSchedules'][0]['serviceSchedules'][0]['appointmentId'] ) ) {
        $appointment_id = $booking_data['clientAppointmentSchedules'][0]['serviceSchedules'][0]['appointmentId'];
    }

    // 4) Log & Redirect
    global $wpdb;
    // --- DateTime handling for DB and email ---
    $wp_tz = new DateTimeZone( wp_timezone_string() );
    $start_dt_obj = new DateTime( '@' . (int)($startMs / 1000) );
    $end_dt_obj = new DateTime( '@' . (int)($endMs / 1000) );
    $start_dt_obj->setTimezone( $wp_tz );
    $end_dt_obj->setTimezone( $wp_tz );
    // For DB
    $start_db = $start_dt_obj->format('Y-m-d H:i:s');
    $end_db   = $end_dt_obj->format('Y-m-d H:i:s');
    // For email placeholders
    $start_dt = $start_dt_obj->format('d.m.Y');
    $start_tm = $start_dt_obj->format('H:i');
    $end_tm   = $end_dt_obj->format('H:i');

    $wpdb->insert(
        $wpdb->prefix . 'glattt_booking_logs',
        [
            'timestamp'      => current_time( 'mysql' ),
            'client_id'      => $client_id,
            'branch_id'      => $branchId,
            'service_id'     => $serviceId,
            'start_time'     => $start_db,
            'end_time'       => $end_db,
            'firstname'      => $firstname,
            'lastname'       => $lastname,
            'email'          => $email,
            'phone'          => $phone,
            'message'        => $message,
            'status'         => 'success',
            'error_msg'      => '',
            'referrer'       => sanitize_text_field( $_SERVER['HTTP_REFERER'] ?? '' ),
            'query'          => wp_json_encode( $_GET ),
            'appointment_id' => $appointment_id,
        ]
    );

    // --- Institut-Daten per API holen ---
    $api_br           = new GLATTT_Phorrest_API();
    $branches_list    = $api_br->get_branches();
    $institute_name   = '';
    $institute_address= '';
    $institute_email  = '';
    if ( is_array( $branches_list ) ) {
        foreach ( $branches_list as $binfo ) {
            if ( isset( $binfo['branchId'] ) && $binfo['branchId'] === $branchId ) {
                $institute_name = $binfo['name'] ?? '';
                $address_parts  = [];
                if ( ! empty( $binfo['streetAddress1'] ) ) {
                    $address_parts[] = $binfo['streetAddress1'];
                }
                if ( ! empty( $binfo['city'] ) ) {
                    $address_parts[] = $binfo['city'];
                }
                $institute_address = implode(', ', $address_parts);
                break;
            }
        }
    }
    // Institut-Email aus der Meta-Tabelle
    $meta_email = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT email FROM {$wpdb->prefix}glattt_institute_meta WHERE branch_id = %s",
            $branchId
        )
    );
    $institute_email = $meta_email ?: '';

    // Institut-Bild URL aus Meta-Tabelle
    $meta_table = $wpdb->prefix . 'glattt_institute_meta';
    $image_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT image_id FROM {$meta_table} WHERE branch_id = %s",
            $branchId
        )
    );
    $institute_image_url = $image_id ? wp_get_attachment_url( intval( $image_id ) ) : '';

    // Institut: Telefonnummer und WhatsApp-Nummer aus der Meta-Tabelle
$meta_phone = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT phone FROM {$wpdb->prefix}glattt_institute_meta WHERE branch_id = %s",
        $branchId
    )
);
$meta_whatsapp = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT whatsapp FROM {$wpdb->prefix}glattt_institute_meta WHERE branch_id = %s",
        $branchId
    )
);
$institute_phone    = $meta_phone    ?: '';
$institute_whatsapp = $meta_whatsapp ?: '';

    // --- C) E-Mail Versand bei Buchung (nun zentral mit Standort-Filterung) ---
    if ( class_exists( 'GLATTT_Email_Sender' ) ) {
        // Hole alle Templates vom Typ "Buchung" (email_type = 1)
        $all_templates = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, all_locations, branch_ids, all_services, service_ids 
                 FROM {$wpdb->prefix}glattt_email_templates 
                 WHERE email_type = %d",
                1
            ),
            ARRAY_A
        );
        
        foreach ( $all_templates as $tpl ) {
            $tpl_id = $tpl['id'];
            $send_this_template = false;
            
            // 1) Check Standort-Filter
            if ( intval($tpl['all_locations']) === 1 ) {
                // Template gilt für alle Standorte
                $send_this_template = true;
            } elseif ( ! empty( $tpl['branch_ids'] ) ) {
                // Template gilt nur für bestimmte Standorte
                $allowed_branches = array_map('trim', explode(',', $tpl['branch_ids']));
                if ( in_array( $branchId, $allowed_branches, true ) ) {
                    $send_this_template = true;
                }
            }
            
            // 2) Check Service-Filter (wenn Standort passt)
            if ( $send_this_template ) {
                if ( intval($tpl['all_services']) === 1 ) {
                    // Template gilt für alle Services
                    $send_this_template = true;
                } elseif ( ! empty( $tpl['service_ids'] ) ) {
                    // Template gilt nur für bestimmte Services
                    $service_config = json_decode( $tpl['service_ids'], true );
                    if ( is_array( $service_config ) ) {
                        // Check if service is allowed for this branch
                        if ( isset( $service_config[$branchId] ) ) {
                            $allowed_services = $service_config[$branchId];
                            if ( in_array('all', $allowed_services, true) || in_array($serviceId, $allowed_services, true) ) {
                                $send_this_template = true;
                            } else {
                                $send_this_template = false;
                            }
                        } else {
                            // Branch not in service config, don't send
                            $send_this_template = false;
                        }
                    }
                }
            }
            
            // 3) Template senden, wenn alle Filter passen
            if ( $send_this_template ) {
                GLATTT_Email_Sender::send_template(
                    $tpl_id,
                    [
                        '{CUSTOMER_FIRSTNAME}'     => $firstname,
                        '{CUSTOMER_LASTNAME}'      => $lastname,
                        '{CUSTOMER_NAME}'          => "{$firstname} {$lastname}",
                        '{CUSTOMER_EMAIL}'         => $email,
                        '{APPOINTMENT_DATE}'       => $start_dt,
                        '{APPOINTMENT_TIME_START}' => $start_tm,
                        '{APPOINTMENT_TIME_END}'   => $end_tm,
                        '{INSTITUTE_NAME}'         => $institute_name,
                        '{INSTITUTE_EMAIL}'        => $institute_email,
                        '{INSTITUTE_ADDRESS}'      => $institute_address,
                        '{INSTITUTE_IMAGE_URL}'    => $institute_image_url,
                        '{INSTITUTE_PHONE}'        => $institute_phone,
                        '{INSTITUTE_WHATSAPP}'     => $institute_whatsapp,
                    ]
                );
            }
        }
    }

    // --- D) Tracking-Daten an GlattHub API senden ---
    glattt_send_booking_tracking( $appointment_id, $branchId, $serviceId, $client_id );

    wp_send_json_success([ 'redirect' => site_url( '/danke-buchung' ) ]);
}
// Cronjob: Jeden Sonntag den Plugin-Cache löschen
add_action('init', 'glattt_setup_cache_clear');
function glattt_setup_cache_clear() {
    if (! wp_next_scheduled('glattt_clear_cache')) {
        // nächsten Sonntag um Mitternacht planen
        $timestamp = strtotime('next sunday 00:00:00');
        wp_schedule_event($timestamp, 'weekly', 'glattt_clear_cache');
    }
}

add_action('glattt_clear_cache', 'glattt_clear_plugin_cache');
function glattt_clear_plugin_cache() {
    global $wpdb;
    // Alle Plugin-Transients löschen
    $prefix = $wpdb->esc_like('_transient_glattt_');
    $wpdb->query("
        DELETE FROM {$wpdb->options}
        WHERE option_name LIKE '_transient_glattt_%'
           OR option_name LIKE '_transient_timeout_glattt_%'
    ");
    // WordPress Object Cache flushen
    if ( function_exists('wp_cache_flush') ) {
        wp_cache_flush();
    }
}

/**
 * Sendet Booking-Tracking-Daten an die GlattHub REST-API.
 * Fire-and-forget: Fehler werden nur geloggt, blockieren die Buchung nicht.
 */
function glattt_send_booking_tracking( $appointment_id, $branch_id, $service_id, $client_id ) {
    $api_url   = rtrim( get_option( 'glattt_hub_api_url', '' ), '/' );
    $api_token = get_option( 'glattt_hub_api_token', '' );

    error_log( '[glattt-booking] Tracking gestartet - URL: ' . $api_url . ' | Token: ' . ( empty($api_token) ? 'LEER' : 'vorhanden' ) . ' | AppointmentID: ' . $appointment_id );

    if ( empty( $api_url ) || empty( $api_token ) || empty( $appointment_id ) ) {
        error_log( '[glattt-booking] Tracking abgebrochen - fehlende Daten: URL=' . (empty($api_url)?'LEER':'ok') . ', Token=' . (empty($api_token)?'LEER':'ok') . ', AppID=' . (empty($appointment_id)?'LEER':'ok') );
        return;
    }

    // Tracking-Daten aus dem Frontend-JS (als JSON-String mitgeschickt)
    $tracking_json = isset( $_POST['tracking_data'] ) ? wp_unslash( $_POST['tracking_data'] ) : '{}';
    $tracking      = json_decode( $tracking_json, true );
    if ( ! is_array( $tracking ) ) {
        $tracking = [];
    }

    $body = array_merge( $tracking, [
        'appointment_id' => sanitize_text_field( $appointment_id ),
        'branch_id'      => sanitize_text_field( $branch_id ),
        'service_id'     => sanitize_text_field( $service_id ),
        'client_id'      => sanitize_text_field( $client_id ),
        'booked_at'      => current_time( 'c' ),
    ] );

    $response = wp_remote_post( $api_url . '/booking-tracking', [
        'timeout' => 5,
        'headers' => [
            'Authorization' => 'Bearer ' . $api_token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ],
        'body' => wp_json_encode( $body ),
    ] );

    if ( is_wp_error( $response ) ) {
        error_log( '[glattt-booking] Tracking API Fehler: ' . $response->get_error_message() );
    } else {
        $code = wp_remote_retrieve_response_code( $response );
        error_log( '[glattt-booking] Tracking API HTTP ' . $code );
        if ( $code !== 201 ) {
            error_log( '[glattt-booking] Tracking API Body: ' . wp_remote_retrieve_body( $response ) );
        }
    }
}