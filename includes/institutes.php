<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function wpglattt_institutes_page() {
    $api = new GLATTT_Phorrest_API();
    $branches = $api->get_branches();
    echo '<div class="wrap"><h1>Institute</h1><table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Name</th><th>ID</th></tr></thead><tbody>';
    foreach ( $branches as $b ) {
        echo '<tr>';
        echo '<td>' . esc_html($b['Name'] ?? $b['name'] ?? '') . '</td>';
        echo '<td>' . esc_html($b['id'] ?? '') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}