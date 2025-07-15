<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function glattt_institutes_page() {
    global $wpdb;

    echo '<div class="wrap"><h1>Institute</h1>';
    echo '<div class="glattt-cards">';

    $api         = new GLATTT_Phorrest_API();
    $branches    = $api->get_branches();
    $active_map  = get_option( 'glattt_active_institutes', [] );

    if ( is_wp_error( $branches ) || empty( $branches ) ) {
        echo '<p>Keine Institute gefunden.</p>';
    } else {
        foreach ( $branches as $b ) {
            $id       = $b['branchId']        ?? '';
            $name     = $b['name']            ?? '(kein Name)';
            $street   = $b['streetAddress1'] ?? '';
            $street2  = $b['streetAddress2'] ?? '';
            $city     = $b['city']            ?? '';
            $country  = $b['country']         ?? '';

            // Adresse-Teile sammeln
            $parts = [];
            if ( $street )  $parts[] = $street . ( $street2 ? ' ' . $street2 : '' );
            if ( $city )    $parts[] = $city;
            if ( $country ) $parts[] = $country;

            // Bild aus Meta laden
            $row     = $wpdb->get_row(
                $wpdb->prepare( "SELECT image_id FROM {$wpdb->prefix}glattt_institute_meta WHERE branch_id = %s", $id ),
                ARRAY_A
            );
            $img_url = ! empty( $row['image_id'] )
                ? wp_get_attachment_image_url( $row['image_id'], 'medium' )
                : '';

            // Aktiv-Status
            $is_active = ! isset( $active_map[ $id ] ) || 1 === intval( $active_map[ $id ] );

            // Karte ausgeben
            echo '<div class="glattt-card' . ( $is_active ? '' : ' inactive' ) . '">';

            // Toggle-Button (AJAX)
            echo '<button class="toggle-btn" data-branch="' . esc_attr( $id ) . '" title="'
                 . ( $is_active ? 'Deaktivieren' : 'Aktivieren' ) . '">';
            echo '<span class="dashicons ' . ( $is_active ? 'dashicons-yes' : 'dashicons-no-alt' ) . '"></span>';
            echo '</button>';

            // Bildbereich
            if ( $img_url ) {
                echo '<div class="card-img" style="background-image:url(' . esc_url( $img_url ) . ');"></div>';
            } else {
                echo '<div class="card-img empty"></div>';
            }

            // Textbereich
            echo '<div class="card-content">';
            echo '<div class="name">' . esc_html( $name ) . '</div>';
            // Adresse mit Zeilenumbrüchen statt Kommas
            echo '<div class="address">' . implode( '<br>', array_map( 'esc_html', $parts ) ) . '</div>';
            echo '<div class="id">ID: ' . esc_html( $id ) . '</div>';
            echo '</div>';

            // Bearbeiten-Link
            $edit_url = admin_url( 'admin.php?page=glattt-institute-details&branch=' . urlencode( $id ) );
            echo '<a href="' . esc_url( $edit_url ) . '" class="edit-btn dashicons dashicons-edit" title="Bearbeiten"></a>';

            echo '</div>'; // .glattt-card
        }
    }

    echo '</div>'; // .glattt-cards
    echo '</div>'; // .wrap
}