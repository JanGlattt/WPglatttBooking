<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GLATTT_Phorrest_API {
    private $user;
    private $pass;
    private $business_id;
    private $service_id;

    public function __construct() {
        $this->user        = get_option('wpglattt_username');
        $this->pass        = get_option('wpglattt_password');
        $this->business_id = get_option('wpglattt_business_id');
        $this->service_id  = get_option('wpglattt_service_id');
    }

    // Verfügbarkeit holen
    public function get_availability( $branch, $monday, $sunday ) {
        $url = "https://api-gateway-eu.phorest.com/third-party-api-server/api/business/{$this->business_id}/branch/{$branch}/appointments/availability";
        $body = [
            'clientServiceSelections' => [[ 'serviceSelections' => [[ 'serviceId' => $this->service_id ]] ]],
            'startTime' => intval($monday),
            'endTime'   => intval($sunday),
            'isOnlineAvailability' => true
        ];
        $response = wp_remote_post( $url, [
            'headers' => [ 'Authorization' => 'Basic ' . base64_encode("{$this->user}:{$this->pass}"), 'Content-Type'=>'application/json' ],
            'body'    => wp_json_encode($body)
        ]);
        if ( is_wp_error($response) ) return [];
        $data = json_decode( wp_remote_retrieve_body($response), true );
        return $data['data'] ?? [];
    }

    // Institute (Branches) auflisten
    public function get_branches() {
        $url = "https://api-gateway-eu.phorest.com/third-party-api-server/api/business/{$this->business_id}/branch";
        $response = wp_remote_get( $url, [
            'headers' => [ 'Authorization' => 'Basic ' . base64_encode("{$this->user}:{$this->pass}"), 'Content-Type'=>'application/json' ]
        ]);
        if ( is_wp_error($response) ) return [];
        $data = json_decode( wp_remote_retrieve_body($response), true );
        return $data['data'] ?? [];
    }

    // Buchung erstellen bleibt später
}