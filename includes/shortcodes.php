<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_shortcode( 'wpglattt_booking', 'wpglattt_booking_shortcode' );

function wpglattt_booking_shortcode( $atts ) {
    $atts = shortcode_atts([ 'store'=> '' ], $atts, 'wpglattt_booking' );
    ob_start(); ?>
    <div id="wpglattt-calendar" data-store="<?php echo esc_attr($atts['store']); ?>"></div>
    <?php return ob_get_clean();
}