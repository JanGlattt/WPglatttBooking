<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_init', 'wpglattt_settings_init' );

function wpglattt_settings_init() {
    add_settings_section(
        'wpglattt_api_section',
        'API Credentials',
        null,
        'wpglattt-booking'
    );

    $fields = [
        'username'    => 'Username',
        'password'    => 'Password',
        'business_id' => 'Business ID',
        'service_id'  => 'Service ID'
    ];
    foreach ( $fields as $key => $label ) {
        add_settings_field(
            'wpglattt_' . $key,
            $label,
            'wpglattt_render_' . $key,
            'wpglattt-booking',
            'wpglattt_api_section'
        );
        register_setting( 'wpglattt-booking', 'wpglattt_' . $key );
    }
}

function wpglattt_render_username() {
    echo "<input type='text' name='wpglattt_username' value='" . esc_attr(get_option('wpglattt_username')) . "' class='regular-text'>";
}
function wpglattt_render_password() {
    echo "<input type='password' name='wpglattt_password' value='" . esc_attr(get_option('wpglattt_password')) . "' class='regular-text'>";
}
function wpglattt_render_business_id() {
    echo "<input type='text' name='wpglattt_business_id' value='" . esc_attr(get_option('wpglattt_business_id')) . "' class='regular-text'>";
}
function wpglattt_render_service_id() {
    echo "<input type='text' name='wpglattt_service_id' value='" . esc_attr(get_option('wpglattt_service_id')) . "' class='regular-text'>";
}

function wpglattt_options_page() {
    echo '<div class="wrap"><h1>GLATTT Bookings Einstellungen</h1><form method="post" action="options.php">';
    settings_fields( 'wpglattt-booking' );
    do_settings_sections( 'wpglattt-booking' );
    submit_button();
    echo '</form></div>';
}