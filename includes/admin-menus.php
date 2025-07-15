<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Haupt- und Untermenüs registrieren
 */
add_action( 'admin_menu', 'glattt_register_menus' );
function glattt_register_menus() {
    // Top-Level Menü
    add_menu_page(
        'glattt Bookings',
        'glattt Bookings',
        'manage_options',
        'glattt-booking',
        'glattt_options_page',
        'dashicons-calendar-alt',
        60
    );

    // 1) Einstellungen
    add_submenu_page(
        'glattt-booking',
        'Einstellungen',
        'Einstellungen',
        'manage_options',
        'glattt-booking',
        'glattt_options_page'
    );

    // 2) Institute
    add_submenu_page(
        'glattt-booking',
        'Institute',
        'Institute',
        'manage_options',
        'glattt-institutes',
        'glattt_institutes_page'
    );

    // 3) Institut-Details
    add_submenu_page(
        'glattt-booking',
        'Institut-Details',
        'Institut-Details',
        'manage_options',
        'glattt-institute-details',
        'glattt_institute_details_page'
    );

    // 4) Services
    add_submenu_page(
        'glattt-booking',
        'Services',
        'Services',
        'manage_options',
        'glattt-services',
        'glattt_services_page'
    );
}