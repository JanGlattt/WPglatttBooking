<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_init', 'glattt_settings_init' );
function glattt_settings_init() {
    add_settings_section(
        'glattt_api_section',
        'API Credentials',
        null,
        'glattt-booking'
    );

    $fields = [
        'username'    => 'Username',
        'password'    => 'Password',
        'business_id' => 'Business ID'
    ];
    foreach ( $fields as $key => $label ) {
        add_settings_field(
            'glattt_' . $key,
            $label,
            'glattt_render_' . $key,
            'glattt-booking',
            'glattt_api_section'
        );
        register_setting( 'glattt-booking', 'glattt_' . $key );
    }

    // GlattHub API Sektion
    add_settings_section(
        'glattt_hub_api_section',
        'GlattHub API (Booking-Tracking)',
        function() {
            echo '<p>Verbindung zur GlattHub REST-API für Booking-Tracking-Daten.</p>';
        },
        'glattt-booking'
    );

    add_settings_field( 'glattt_hub_api_url', 'API URL', 'glattt_render_hub_api_url', 'glattt-booking', 'glattt_hub_api_section' );
    register_setting( 'glattt-booking', 'glattt_hub_api_url', [
        'sanitize_callback' => 'esc_url_raw',
    ] );

    add_settings_field( 'glattt_hub_api_token', 'API Token', 'glattt_render_hub_api_token', 'glattt-booking', 'glattt_hub_api_section' );
    register_setting( 'glattt-booking', 'glattt_hub_api_token', [
        'sanitize_callback' => 'sanitize_text_field',
    ] );

    // Meta Conversion Tracking Sektion
    add_settings_section(
        'glattt_meta_tracking_section',
        'Meta Conversion Tracking',
        function() {
            echo '<p>Einstellungen für den Meta Pixel Lead-Event. Der Lead-Wert berechnet sich aus: <strong>Ø Vertragswert × Show-Rate × Abschlussrate</strong>.</p>';
        },
        'glattt-booking'
    );

    add_settings_field( 'glattt_meta_lead_value', 'Lead-Wert (€)', 'glattt_render_meta_lead_value', 'glattt-booking', 'glattt_meta_tracking_section' );
    register_setting( 'glattt-booking', 'glattt_meta_lead_value', [
        'sanitize_callback' => function( $val ) {
            return max( 0, floatval( str_replace( ',', '.', $val ) ) );
        },
    ] );
}

function glattt_render_username() {
    $v = esc_attr( get_option( 'glattt_username', '' ) );
    echo "<input type='text' name='glattt_username' value='$v' class='regular-text'>";
}
function glattt_render_password() {
    $v = esc_attr( get_option( 'glattt_password', '' ) );
    echo "<input type='password' name='glattt_password' value='$v' class='regular-text'>";
}
function glattt_render_business_id() {
    $v = esc_attr( get_option( 'glattt_business_id', '' ) );
    echo "<input type='text' name='glattt_business_id' value='$v' class='regular-text'>";
}

function glattt_render_hub_api_url() {
    $v = esc_attr( get_option( 'glattt_hub_api_url', '' ) );
    echo "<input type='url' name='glattt_hub_api_url' value='$v' class='regular-text' placeholder='https://hub.glattt.com/api/v1'>";
    echo "<p class='description'>Basis-URL der GlattHub REST-API (z.B. https://hub.glattt.com/api/v1)</p>";
}

function glattt_render_hub_api_token() {
    $v = esc_attr( get_option( 'glattt_hub_api_token', '' ) );
    echo "<input type='password' name='glattt_hub_api_token' value='$v' class='regular-text' autocomplete='off'>";
    echo "<p class='description'>Bearer-Token mit Scope <code>booking-tracking:write</code></p>";
}

function glattt_render_meta_lead_value() {
    $v = esc_attr( get_option( 'glattt_meta_lead_value', '0' ) );
    echo "<input type='number' name='glattt_meta_lead_value' value='$v' class='small-text' min='0' step='0.01'> €";
    echo "<p class='description'>Erwarteter Wert pro Lead-Buchung. Formel: Ø Vertragswert × Show-Rate × Abschlussrate.<br>Beispiel: 2400 × 0,70 × 0,50 = <strong>840 €</strong></p>";
}

function glattt_options_page() {
    echo '<div class="wrap"><h1>glattt Bookings Einstellungen</h1><form method="post" action="options.php">';
    settings_fields( 'glattt-booking' );
    do_settings_sections( 'glattt-booking' );
    echo '<h2>Shortcodes</h2>';
    echo '<div class="postbox">';
    echo '  <div class="inside">';
    echo '    <p>Verwende den folgenden Shortcode, um das Buchungs-Widget einzubinden:</p>';
    echo '    <textarea class="large-text code" rows="1" readonly>[glattt_booking]</textarea>';
    echo '    <p>Um einen bestimmten Standort vorauszuwählen, gib die Branch-ID an:</p>';
    echo '    <textarea class="large-text code" rows="1" readonly>[glattt_booking branch-id="BRANCH_ID"]</textarea>';
    echo '    <p>Alternativ kannst du die Branch-ID per URL-Parameter setzen:</p>';
    echo '    <textarea class="large-text code" rows="1" readonly">?id=BRANCH_ID</textarea>';
    echo '  </div>';
    echo '</div>';
    echo '<div id="glattt-settings-form">';
    submit_button();
    echo '</div>';
    echo '</form></div>';
}