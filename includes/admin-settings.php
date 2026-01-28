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