<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Hauptmenü und Untermenüs registrieren
add_action( 'admin_menu', 'wpglattt_register_menus' );
function wpglattt_register_menus() {
    // Top-Level Menü
    add_menu_page(
        'glattt Bookings',       // Page Title
        'glattt Bookings',       // Menu Label (Groß-/Kleinschreibung)
        'manage_options',        // Capability
        'wpglattt-booking',      // Menu Slug
        'wpglattt_options_page', // Function aus admin-settings.php
        'dashicons-calendar-alt',// Icon
        60                       // Position
    );

    // Untermenü: Einstellungen (zeigt dieselbe Seite wie Top-Level)
    add_submenu_page(
        'wpglattt-booking',      // Parent slug
        'Einstellungen',        // Page Title
        'Einstellungen',        // Menu Label
        'manage_options',        // Capability
        'wpglattt-booking',      // Menu Slug
        'wpglattt_options_page'  // Callback
    );

    // Untermenü: Institute
    add_submenu_page(
        'wpglattt-booking',      // Parent slug
        'Institute',             // Page Title
        'Institute',             // Menu Label
        'manage_options',        // Capability
        'wpglattt-institutes',   // Menu Slug
        'wpglattt_institutes_page' // Callback in institutes.php
    );
}