<?php
/**
 * Plugin Name:     GLATTT Booking
 * Description:     Phorest-Buchungs-API als WordPress-Plugin.
 * Version:         0.1.0
 * Author:          Dein Name
 * Text Domain:     wpglattt-booking
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Konstanten
if ( ! defined( 'WPGLATTT_VER' ) ) define( 'WPGLATTT_VER', '0.1.0' );
if ( ! defined( 'WPGLATTT_PATH' ) ) define( 'WPGLATTT_PATH', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'WPGLATTT_URL' ) ) define( 'WPGLATTT_URL', plugin_dir_url( __FILE__ ) );

// Includes
require_once WPGLATTT_PATH . 'includes/admin-settings.php';
require_once WPGLATTT_PATH . 'includes/class-phorest-api.php';
require_once WPGLATTT_PATH . 'includes/shortcodes.php';

// Hooks: Frontend Assets
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'wpglattt-style', WPGLATTT_URL . 'assets/css/style.css', array(), WPGLATTT_VER );
    wp_enqueue_script( 'fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js', array(), null, true );
    wp_enqueue_script( 'wpglattt-calendar', WPGLATTT_URL . 'assets/js/calendar.js', array('fullcalendar'), WPGLATTT_VER, true );
    wp_enqueue_script( 'wpglattt-booking', WPGLATTT_URL . 'assets/js/booking.js', array('jquery'), WPGLATTT_VER, true );
});