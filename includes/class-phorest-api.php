<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GLATTT_Phorrest_API {
    private $user;
    private $pass;
    private $business_id;
    public $last_response_body;

    public function __construct() {
        $this->user             = get_option( 'glattt_username' );
        $this->pass             = get_option( 'glattt_password' );
        $this->business_id      = get_option( 'glattt_business_id' );
        $this->last_response_body = '';
    }

    /**
     * Liefert alle Institute (Branches)
     * @return array|WP_Error
     */
    public function get_branches() {
        if ( empty( $this->user ) || empty( $this->pass ) || empty( $this->business_id ) ) {
            return new WP_Error( 'missing_credentials', 'API Credentials sind nicht vollständig konfiguriert.' );
        }
        $url = sprintf(
            'https://api-gateway-eu.phorest.com/third-party-api-server/api/business/%s/branch/',
            esc_attr( $this->business_id )
        );
        $resp = wp_remote_get( $url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( "{$this->user}:{$this->pass}" ),
                'Accept'        => 'application/json'
            ],
            'timeout' => 20
        ]);
        if ( is_wp_error( $resp ) ) {
            return $resp;
        }
        $body = wp_remote_retrieve_body( $resp );
        $this->last_response_body = $body;
        $code = wp_remote_retrieve_response_code( $resp );
        if ( 200 !== $code ) {
            return new WP_Error( 'api_error', "HTTP $code: " . wp_remote_retrieve_response_message( $resp ) );
        }
        $data = json_decode( $body, true );
        if ( null === $data ) {
            return new WP_Error( 'json_error', "Ungültige JSON: $body" );
        }
        if ( isset( $data['_embedded']['branches'] ) ) {
            return (array) $data['_embedded']['branches'];
        }
        if ( isset( $data['data'] ) ) {
            return (array) $data['data'];
        }
        return [];
    }

    /**
     * Liefert alle Service-Kategorien eines Branches
     * @param string $branchId
     * @return array|WP_Error
     */
    public function get_service_categories( $branchId ) {
        if ( empty( $this->user ) || empty( $this->pass ) || empty( $this->business_id ) ) {
            return new WP_Error( 'missing_credentials', 'API Credentials fehlen.' );
        }
        $url = sprintf(
            'https://api-gateway-eu.phorest.com/third-party-api-server/api/business/%s/branch/%s/service-category',
            esc_attr( $this->business_id ),
            esc_attr( $branchId )
        );
        $resp = wp_remote_get( $url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( "{$this->user}:{$this->pass}" ),
                'Accept'        => 'application/json'
            ],
            'timeout' => 20
        ]);
        if ( is_wp_error( $resp ) ) {
            return $resp;
        }
        $body = wp_remote_retrieve_body( $resp );
        $this->last_response_body = $body;
        $code = wp_remote_retrieve_response_code( $resp );
        if ( 200 !== $code ) {
            return new WP_Error( 'api_error', "HTTP $code: " . wp_remote_retrieve_response_message( $resp ) );
        }
        $data = json_decode( $body, true );
        if ( null === $data ) {
            return new WP_Error( 'json_error', "Ungültige JSON: $body" );
        }
        return isset( $data['_embedded']['serviceCategories'] )
            ? (array) $data['_embedded']['serviceCategories']
            : [];
    }

    /**
     * Liefert alle Services für einen Branch
     * @param string $branchId
     * @return array|WP_Error
     */
    public function get_services( $branchId ) {
        if ( empty( $this->user ) || empty( $this->pass ) || empty( $this->business_id ) ) {
            return new WP_Error( 'missing_credentials', 'API Credentials fehlen.' );
        }
        $pageSize = 100;
        $page = 0;
        $all = [];
        do {
            $url = sprintf(
                'https://api-gateway-eu.phorest.com/third-party-api-server/api/business/%s/branch/%s/service?page=%d&size=%d',
                esc_attr( $this->business_id ),
                esc_attr( $branchId ),
                $page,
                $pageSize
            );
            $resp = wp_remote_get( $url, [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode( "{$this->user}:{$this->pass}" ),
                    'Accept'        => 'application/json'
                ],
                'timeout' => 20
            ]);
            if ( is_wp_error( $resp ) ) {
                return $resp;
            }
            $body = wp_remote_retrieve_body( $resp );
            $this->last_response_body = $body;
            $code = wp_remote_retrieve_response_code( $resp );
            if ( 200 !== $code ) {
                return new WP_Error( 'api_error', "HTTP $code: " . wp_remote_retrieve_response_message( $resp ) );
            }
            $data = json_decode( $body, true );
            if ( null === $data ) {
                return new WP_Error( 'json_error', "Ungültige JSON: $body" );
            }
            $services = isset( $data['_embedded']['services'] )
                ? $data['_embedded']['services']
                : ( isset( $data['data'] ) ? $data['data'] : [] );
            $all = array_merge( $all, (array) $services );
            $page++;
        } while ( count( $services ) === $pageSize );
        return $all;
    }

    /**
     * Holt verfügbare Slots im gegebenen Zeitraum
     * @param string $branchId
     * @param string $serviceId
     * @param int $mondayTimestamp
     * @param int $sundayTimestamp
     * @return array|WP_Error
     */
    public function get_availability( $branchId, $serviceId, $mondayTimestamp, $sundayTimestamp ) {
        if ( empty( $this->user ) || empty( $this->pass ) || empty( $this->business_id ) ) {
            return new WP_Error( 'missing_credentials', 'API Credentials fehlen.' );
        }
        $url = sprintf(
            'https://api-gateway-eu.phorest.com/third-party-api-server/api/business/%s/branch/%s/appointments/availability',
            esc_attr( $this->business_id ),
            esc_attr( $branchId )
        );
        $body = [
            'clientServiceSelections' => [
                [ 'serviceSelections' => [ [ 'serviceId' => $serviceId ] ] ]
            ],
            'startTime'            => intval( $mondayTimestamp ),
            'endTime'              => intval( $sundayTimestamp ),
            'isOnlineAvailability' => true,
        ];
        $resp = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( "{$this->user}:{$this->pass}" ),
                'Content-Type'  => 'application/json'
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 20
        ]);
        if ( is_wp_error( $resp ) ) {
            return $resp;
        }
        $this->last_response_body = wp_remote_retrieve_body( $resp );
        $code = wp_remote_retrieve_response_code( $resp );
        if ( $code !== 200 ) {
            return new WP_Error( 'api_error', "HTTP $code: " . wp_remote_retrieve_response_message( $resp ) );
        }
        $data = json_decode( $this->last_response_body, true );
        if ( null === $data ) {
            return new WP_Error( 'json_error', "Ungültige JSON: {$this->last_response_body}" );
        }
        return $data['data'] ?? [];
    }

    /**
     * Normalisiert eine deutsche Telefonnummer ins Format 491234567890
     * @param string $phone Telefonnummer in beliebigem Format
     * @return string Normalisierte Telefonnummer
     */
    private function normalize_german_phone( $phone ) {
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
     * Sucht einen Kunden per Telefonnummer (mobile)
     * @param string $phone Telefonnummer
     * @return array|WP_Error Array mit clientId wenn gefunden, leeres Array wenn nicht gefunden
     */
    public function search_client_by_phone( $phone ) {
        if ( empty( $this->user ) || empty( $this->pass ) || empty( $this->business_id ) ) {
            return new WP_Error( 'missing_credentials', 'API Credentials fehlen.' );
        }
        // Telefonnummer ins deutsche Format normalisieren
        $normalized_phone = $this->normalize_german_phone( $phone );
        
        // Debug-Log
        error_log( "🔍 Phorest Client-Suche - Original: {$phone}, Normalisiert: {$normalized_phone}" );
        
        // Phorest Client Search API - Parameter heißt "phone" nicht "mobile"!
        $url = sprintf(
            'https://api-gateway-eu.phorest.com/third-party-api-server/api/business/%s/client?phone=%s',
            esc_attr( $this->business_id ),
            urlencode( $normalized_phone )
        );
        
        error_log( "🔍 API-URL: {$url}" );
        
        $resp = wp_remote_get( $url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( "{$this->user}:{$this->pass}" ),
                'Accept'        => 'application/json'
            ],
            'timeout' => 20
        ]);
        if ( is_wp_error( $resp ) ) {
            return $resp;
        }
        $body = wp_remote_retrieve_body( $resp );
        $this->last_response_body = $body;
        $code = wp_remote_retrieve_response_code( $resp );
        
        error_log( "🔍 API Response Code: {$code}" );
        error_log( "🔍 API Response Body: " . substr( $body, 0, 500 ) );
        
        if ( 200 !== $code ) {
            return new WP_Error( 'api_error', "HTTP $code: " . wp_remote_retrieve_response_message( $resp ) );
        }
        $data = json_decode( $body, true );
        if ( null === $data ) {
            return new WP_Error( 'json_error', "Ungültige JSON: $body" );
        }
        // Wenn Kunden gefunden wurden
        if ( isset( $data['_embedded']['clients'] ) && ! empty( $data['_embedded']['clients'] ) ) {
            error_log( "🔍 Anzahl gefundener Kunden: " . count( $data['_embedded']['clients'] ) );
            
            // Die API könnte mehrere Ergebnisse zurückgeben - prüfe auf exakte Übereinstimmung
            foreach ( $data['_embedded']['clients'] as $client ) {
                // Normalisiere auch die gefundene Telefonnummer zum Vergleich
                $client_phone = isset( $client['mobile'] ) ? $this->normalize_german_phone( $client['mobile'] ) : '';
                error_log( "🔍 Vergleich - Gesucht: {$normalized_phone}, Gefunden: {$client_phone}, ClientId: " . ( $client['clientId'] ?? 'N/A' ) );
                
                if ( $client_phone === $normalized_phone ) {
                    error_log( "✅ Exakte Übereinstimmung gefunden! ClientId: " . $client['clientId'] );
                    return $client; // Exakte Übereinstimmung gefunden!
                }
            }
            // Falls keine exakte Übereinstimmung, nehme ersten Treffer
            error_log( "⚠️ Keine exakte Übereinstimmung - verwende ersten Treffer" );
            return $data['_embedded']['clients'][0];
        }
        // Kein Kunde gefunden
        error_log( "❌ Kein Kunde gefunden für: {$normalized_phone}" );
        return [];
    }

    /**
     * Legt eine Buchung an
     * @param array $data (branch, service, start, end, firstname, lastname, email, phone, message)
     * @return array ['ok'=>bool, 'message'=>mixed]
     */
    public function create_booking( $data ) {
        if ( empty( $this->user ) || empty( $this->pass ) || empty( $this->business_id ) ) {
            return [ 'ok' => false, 'message' => 'API Credentials fehlen.' ];
        }
        $branch  = sanitize_text_field( $data['branch']  ?? '' );
        $service = sanitize_text_field( $data['service'] ?? '' );
        $start   = intval( $data['start'] );
        $end     = intval( $data['end'] );

        $url = sprintf(
            'https://api-gateway-eu.phorest.com/third-party-api-server/api/business/%s/branch/%s/booking',
            esc_attr( $this->business_id ),
            esc_attr( $branch )
        );
        $body = [
            'bookingStatus' => 'ACTIVE',
            'clientAppointmentSchedules' => [
                [
                    'clientId' => null,
                    'serviceSchedules' => [
                        [
                            'serviceId' => $service,
                            'staffId'   => null,
                            'startTime' => $start,
                            'endTime'   => $end,
                        ]
                    ]
                ]
            ],
            'note' => sanitize_textarea_field( $data['message'] ?? '' ),
        ];
        $resp = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( "{$this->user}:{$this->pass}" ),
                'Content-Type'  => 'application/json'
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 20
        ]);
        if ( is_wp_error( $resp ) ) {
            return [ 'ok' => false, 'message' => $resp->get_error_message() ];
        }
        $code = wp_remote_retrieve_response_code( $resp );
        $body = wp_remote_retrieve_body( $resp );
        $data = json_decode( $body, true );
        $this->last_response_body = $body;
        if ( $code !== 200 || isset( $data['statusCode'] ) ) {
            return [ 'ok' => false, 'message' => $data ];
        }
        return [ 'ok' => true, 'message' => $data ];
    }
}
