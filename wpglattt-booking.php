<?php
/**
 * Plugin Name:     glattt Bookings
 * Description:     Phorest-Buchungs-API als WordPress-Plugin.
 * Version:         0.3.1
 * Author:          Dein Name
 * Text Domain:     glattt-booking
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Version & Pfade
if ( ! defined( 'WPGLATTT_VER' ) )   define( 'WPGLATTT_VER', '0.3.1' );
if ( ! defined( 'WPGLATTT_PATH' ) )  define( 'WPGLATTT_PATH', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'WPGLATTT_URL' ) )   define( 'WPGLATTT_URL', plugin_dir_url( __FILE__ ) );

// Includes
require_once WPGLATTT_PATH . 'includes/admin-menus.php';
require_once WPGLATTT_PATH . 'includes/admin-settings.php';
require_once WPGLATTT_PATH . 'includes/class-phorest-api.php';
require_once WPGLATTT_PATH . 'includes/institutes.php';
require_once WPGLATTT_PATH . 'includes/services.php';
require_once WPGLATTT_PATH . 'includes/institute-meta.php';
require_once WPGLATTT_PATH . 'includes/shortcodes.php';
require_once WPGLATTT_PATH . 'includes/frontend-booking.php';
require_once WPGLATTT_PATH . 'includes/emails.php';
require_once WPGLATTT_PATH . 'includes/email-details.php';

/**
 * Enqueue Frontend Assets for Booking Shortcode
 */
add_action( 'wp_enqueue_scripts', 'glattt_enqueue_booking_frontend_assets' );
function glattt_enqueue_booking_frontend_assets() {
    if ( is_singular() && has_shortcode( get_post()->post_content, 'glattt_booking' ) ) {
        wp_enqueue_style( 'glattt-style', WPGLATTT_URL . 'assets/css/style.css', [], WPGLATTT_VER );
        wp_enqueue_style( 'glattt-booking-frontend', WPGLATTT_URL . 'assets/css/booking-frontend.css', [], WPGLATTT_VER );
        wp_enqueue_script( 'fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js', [], null, true );
        wp_enqueue_script( 'glattt-booking-frontend', WPGLATTT_URL . 'assets/js/booking-frontend.js', ['jquery'], WPGLATTT_VER, true );

        wp_localize_script( 'glattt-booking-frontend', 'glatttFrontend', [
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce_get'  => wp_create_nonce( 'glattt_get_availability' ),
            'nonce_book' => wp_create_nonce( 'glattt_book_appointment' ),
        ] );
    }
}

/**
 * Admin Styles & Scripts
 */
add_action( 'admin_enqueue_scripts', 'glattt_enqueue_admin_assets' );
function glattt_enqueue_admin_assets( $hook ) {
    if ( strpos( $hook, 'glattt' ) !== false ) {
        wp_enqueue_style( 'glattt-admin-style', WPGLATTT_URL . 'assets/css/style.css', [], WPGLATTT_VER );
    }

    if ( strpos( $hook, 'glattt-institutes' ) !== false ) {
        wp_enqueue_script( 'glattt-admin-institutes', WPGLATTT_URL . 'assets/js/admin-institutes.js', ['jquery'], WPGLATTT_VER, true );
        wp_localize_script( 'glattt-admin-institutes', 'glatttAjax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'glattt_toggle_institute' ),
        ] );
    }

    if ( strpos( $hook, 'glattt-emails' ) !== false || strpos( $hook, 'glattt-email-details' ) !== false ) {
        // Admin Scripts für E-Mail-Verwaltung
        wp_enqueue_script( 'glattt-admin-emails', WPGLATTT_URL . 'assets/js/admin-emails.js', ['jquery'], WPGLATTT_VER, true );
        // Select2 für Dropdown-Auswahl
        wp_enqueue_script( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true );
        wp_enqueue_style( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], null );
    }
    if ( strpos( $hook, 'glattt-emails' ) !== false
  || strpos( $hook, 'glattt-email-details' ) !== false ) {

    wp_enqueue_script(
        'glattt-admin-emails',
        WPGLATTT_URL . 'assets/js/admin-emails.js',
        ['jquery'],
        WPGLATTT_VER,
        true
    );

    // <-- Hier fügst Du die CSS-Einbindung hinzu:
    wp_enqueue_style(
        'glattt-admin-emails-style',
        WPGLATTT_URL . 'assets/css/admin-emails.css',
        [],
        WPGLATTT_VER
    );
}
}

/**
 * AJAX Handler für Toggle Institute
 */
add_action( 'wp_ajax_glattt_toggle_institute', 'glattt_ajax_toggle_institute' );
function glattt_ajax_toggle_institute() {
    check_ajax_referer( 'glattt_toggle_institute', 'nonce' );
    $branch     = isset( $_POST['branch'] ) ? sanitize_text_field( $_POST['branch'] ) : '';
    $active_map = get_option( 'glattt_active_institutes', [] );
    $active_map[ $branch ] = empty( $active_map[ $branch ] ) ? 1 : 0;
    update_option( 'glattt_active_institutes', $active_map );
    wp_send_json_success([ 'active' => boolval( $active_map[ $branch ] ) ]);
}

/**
 * Activation Hook: Tabellen anlegen
 */
register_activation_hook( __FILE__, 'glattt_install_tables' );
function glattt_install_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    // 1) Institute-Meta
    $table1 = $wpdb->prefix . 'glattt_institute_meta';
    $sql1 = "CREATE TABLE $table1 (
        branch_id VARCHAR(50) NOT NULL,
        custom_name TEXT,
        email VARCHAR(100),
        image_id BIGINT UNSIGNED,
        open_mon_from TIME, open_mon_to TIME,
        open_tue_from TIME, open_tue_to TIME,
        open_wed_from TIME, open_wed_to TIME,
        open_thu_from TIME, open_thu_to TIME,
        open_fri_from TIME, open_fri_to TIME,
        open_sat_from TIME, open_sat_to TIME,
        open_sun_from TIME, open_sun_to TIME,
        PRIMARY KEY (branch_id)
    ) $charset;";

    // 2) Service-Meta
    $table2 = $wpdb->prefix . 'glattt_service_meta';
    $sql2 = "CREATE TABLE $table2 (
        service_id VARCHAR(50) NOT NULL,
        friendly_name TEXT,
        description TEXT,
        PRIMARY KEY (service_id)
    ) $charset;";

    // 3) Booking Logs
    $table3 = $wpdb->prefix . 'glattt_booking_logs';
    $sql3 = "CREATE TABLE $table3 (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        timestamp DATETIME NOT NULL,
        branch_id VARCHAR(50) NOT NULL,
        service_id VARCHAR(50) NOT NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        firstname VARCHAR(100),
        lastname VARCHAR(100),
        email VARCHAR(100),
        phone VARCHAR(50),
        message TEXT,
        status VARCHAR(20),
        error_msg TEXT,
        referrer TEXT,
        query TEXT,
        PRIMARY KEY (id)
    ) $charset;";

    // 4) E-Mail Templates
    $table4 = $wpdb->prefix . 'glattt_email_templates';
    $sql4 = "CREATE TABLE $table4 (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(190) NOT NULL,
        customer_type TINYINT(1) NOT NULL,
        location_type TINYINT(1) NOT NULL,
        admin_type TINYINT(1) NOT NULL,
        all_locations TINYINT(1) NOT NULL,
        branch_ids TEXT,
        all_services TINYINT(1) NOT NULL,
        service_ids TEXT,
        email_type TINYINT(1) NOT NULL,
        reminder_offset INT,
        schedule_interval VARCHAR(20),
        schedule_day TINYINT(1),
        schedule_time TIME,
        subject VARCHAR(255),
        content LONGTEXT,
        PRIMARY KEY  (id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql1 );
    dbDelta( $sql2 );
    dbDelta( $sql3 );
    dbDelta( $sql4 );
}