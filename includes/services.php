<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function glattt_services_page() {
    global $wpdb;
    echo '<div class="wrap"><h1>Services je Institut</h1>';

    $api             = new GLATTT_Phorrest_API();
    $branches        = $api->get_branches();
    $selected_branch = isset( $_REQUEST['branch'] ) ? sanitize_text_field( $_REQUEST['branch'] ) : '';

    // Speichern
    if ( 'POST' === $_SERVER['REQUEST_METHOD']
      && isset( $_POST['glattt_save_services'] )
      && $selected_branch
    ) {
        // Checkboxen
        $sel = [];
        if ( ! empty( $_POST['selected_services'] ) && is_array( $_POST['selected_services'] ) ) {
            foreach ( $_POST['selected_services'] as $val ) {
                $sel[] = sanitize_text_field( $val );
            }
        }
        update_option( 'glattt_bookable_services_' . $selected_branch, $sel );

        // Service-Meta
        $svc_table = $wpdb->prefix . 'glattt_service_meta';
        $friendly  = isset( $_POST['friendly_name'] ) ? $_POST['friendly_name'] : [];
        $descr     = isset( $_POST['description'] )    ? $_POST['description']    : [];

        $services = $api->get_services( $selected_branch );
        foreach ( $services as $s ) {
            $sid = isset( $s['serviceId'] ) ? $s['serviceId'] : '';
            if ( ! $sid ) continue;

            $data = [
                'service_id'    => $sid,
                'friendly_name' => isset( $friendly[ $sid ] ) ? sanitize_text_field( $friendly[ $sid ] ) : '',
                'description'   => isset( $descr[ $sid ] )    ? sanitize_textarea_field( $descr[ $sid ] )    : '',
            ];

            // alle Spalten sind Strings
            $formats = [ '%s', '%s', '%s' ];

            if ( $data['friendly_name'] === '' && $data['description'] === '' ) {
                $wpdb->delete( $svc_table, [ 'service_id' => $sid ], [ '%s' ] );
            } else {
                $wpdb->replace( $svc_table, $data, $formats );
            }
        }

        echo '<div class="notice notice-success"><p>';
        echo 'Services gespeichert.';
        echo '</p></div>';
    }

    // Branch-Form
    echo '<form method="get" style="margin-bottom:1em;">';
    echo '<input type="hidden" name="page" value="glattt-services">';
    echo '<select name="branch" onchange="this.form.submit()">';
    echo '<option value="">-- Institut wählen --</option>';
    if ( ! is_wp_error( $branches ) ) {
        foreach ( $branches as $b ) {
            $id = isset( $b['branchId'] ) ? $b['branchId'] : ( $b['id'] ?? '' );
            $n  = isset( $b['name'] )     ? $b['name']     : '(kein Name)';
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $id ),
                selected( $selected_branch, $id, false ),
                esc_html( $n )
            );
        }
    }
    echo '</select>';
    echo '</form>';

    if ( $selected_branch ) {
        // Kategorien laden
        $cats = $api->get_service_categories( $selected_branch );
        $cat_map = [];
        if ( ! is_wp_error( $cats ) ) {
            foreach ( $cats as $c ) {
                $cid = isset( $c['categoryId'] ) ? $c['categoryId'] : ( $c['id'] ?? '' );
                $cat_map[ $cid ] = isset( $c['name'] ) ? $c['name'] : '(kein Name)';
            }
        }

        // Kategorie-Filter
        $selected_cat = isset( $_REQUEST['category'] ) ? sanitize_text_field( $_REQUEST['category'] ) : '';
        echo '<form method="get" style="margin-bottom:1em;">';
        echo '<input type="hidden" name="page" value="glattt-services">';
        echo '<input type="hidden" name="branch" value="' . esc_attr( $selected_branch ) . '">';
        echo '<select name="category" onchange="this.form.submit()">';
        echo '<option value="">-- Alle Kategorien --</option>';
        foreach ( $cat_map as $cid => $cname ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $cid ),
                selected( $selected_cat, $cid, false ),
                esc_html( $cname )
            );
        }
        echo '</select>';
        echo '</form>';

        // bereits gespeicherte Buchbaren
        $bookable = get_option( 'glattt_bookable_services_' . $selected_branch, [] );

        // Services & Meta laden
        $services = $api->get_services( $selected_branch );
        $ids = [];
        foreach ( $services as $s ) {
            if ( isset( $s['serviceId'] ) ) {
                $ids[] = $s['serviceId'];
            }
        }
        $meta_map = [];
        if ( $ids ) {
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}glattt_service_meta WHERE service_id IN ($placeholders)",
                    ... $ids
                ),
                ARRAY_A
            );
            foreach ( $rows as $r ) {
                $meta_map[ $r['service_id'] ] = $r;
            }
        }

        // Tabelle
        echo '<form method="post">';
        echo '<input type="hidden" name="page"   value="glattt-services">';
        echo '<input type="hidden" name="branch" value="' . esc_attr( $selected_branch ) . '">';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th width="5%">Online Buchbar</th>';
        echo '<th>Name</th>';
        echo '<th>ID</th>';
        echo '<th>Kategorie</th>';
        echo '<th>Dauer (Min.)</th>';
        echo '<th>Puffer (Min.)</th>';
        echo '<th>Friendly Name</th>';
        echo '<th>Beschreibung</th>';
        echo '</tr></thead><tbody>';

        foreach ( $services as $s ) {
            $sid = isset( $s['serviceId'] ) ? $s['serviceId'] : '';
            if ( $selected_cat && ( $s['categoryId'] ?? '' ) !== $selected_cat ) {
                continue;
            }
            $sname    = isset( $s['name'] ) ? $s['name'] : '(kein Name)';
            $cat_name = isset( $cat_map[ $s['categoryId'] ?? '' ] ) ? $cat_map[ $s['categoryId'] ] : '';
            $mid      = $meta_map[ $sid ]['friendly_name'] ?? '';
            $md       = $meta_map[ $sid ]['description']   ?? '';
            $duration = intval( $s['duration'] ?? 0 );
            $gap      = intval( $s['gapTime']  ?? 0 );

            echo '<tr>';
            printf(
                '<td><input type="checkbox" name="selected_services[]" value="%s"%s></td>',
                esc_attr( $sid ),
                checked( in_array( $sid, $bookable ), true, false )
            );
            echo '<td>' . esc_html( $sname ) . '</td>';
            echo '<td>' . esc_html( $sid ) . '</td>';
            echo '<td>' . esc_html( $cat_name ) . '</td>';
            printf( '<td>%d</td>', $duration );
            printf( '<td>%d</td>', $gap );
            printf(
                '<td><input type="text" name="friendly_name[%1$s]" value="%2$s" class="regular-text"></td>',
                esc_attr( $sid ),
                esc_html( $mid )
            );
            printf(
                '<td><textarea name="description[%1$s]" rows="2" class="large-text">%2$s</textarea></td>',
                esc_attr( $sid ),
                esc_html( $md )
            );
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p><button type="submit" name="glattt_save_services" class="button button-primary">Buchbare Services speichern</button></p>';
        echo '</form>';
    }

    echo '</div>';
}