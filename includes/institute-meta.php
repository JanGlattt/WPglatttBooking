<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function glattt_institute_details_page() {
    global $wpdb;

    // Branch-ID aus GET oder POST ermitteln
    $branch = '';
    if ( isset( $_POST['branch'] ) ) {
        $branch = sanitize_text_field( $_POST['branch'] );
    } elseif ( isset( $_GET['branch'] ) ) {
        $branch = sanitize_text_field( $_GET['branch'] );
    }

    // Speichern-Logik vor Ausgabe
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['glattt_save_institute'] ) && $branch ) {
        // a) Metadaten speichern
        $data = [
            'branch_id'     => $branch,
            'custom_name'   => sanitize_text_field( $_POST['custom_name'] ),
            'email'         => sanitize_email(      $_POST['email'] ),
            'phone'         => sanitize_text_field( $_POST['phone'] ),
            'whatsapp'      => sanitize_text_field( $_POST['whatsapp'] ),
            'image_id'      => intval(              $_POST['image_id'] ),
            'open_mon_from' => $_POST['open_mon_from'],
            'open_mon_to'   => $_POST['open_mon_to'],
            'open_tue_from' => $_POST['open_tue_from'],
            'open_tue_to'   => $_POST['open_tue_to'],
            'open_wed_from' => $_POST['open_wed_from'],
            'open_wed_to'   => $_POST['open_wed_to'],
            'open_thu_from' => $_POST['open_thu_from'],
            'open_thu_to'   => $_POST['open_thu_to'],
            'open_fri_from' => $_POST['open_fri_from'],
            'open_fri_to'   => $_POST['open_fri_to'],
            'open_sat_from' => $_POST['open_sat_from'],
            'open_sat_to'   => $_POST['open_sat_to'],
            'open_sun_from' => $_POST['open_sun_from'],
            'open_sun_to'   => $_POST['open_sun_to'],
        ];
        $formats = array_map( function( $col ) {
            return $col === 'image_id' ? '%d' : '%s';
        }, array_keys( $data ) );
        $table = $wpdb->prefix . 'glattt_institute_meta';
        $wpdb->replace( $table, $data, $formats );

        // b) Aktiv-Status speichern
        $active_map = get_option( 'glattt_active_institutes', [] );
        $active_map[ $branch ] = isset( $_POST['active'] ) ? 1 : 0;
        update_option( 'glattt_active_institutes', $active_map );

        // c) Erfolgsmeldung und Übersicht
        $api      = new GLATTT_Phorrest_API();
        $branches = $api->get_branches();
        $institute_name = '';
        if ( ! is_wp_error( $branches ) ) {
            foreach ( $branches as $b ) {
                if ( isset( $b['branchId'] ) && $b['branchId'] === $branch ) {
                    $institute_name = $b['name'];
                    break;
                }
            }
        }
        echo '<div class="wrap"><div class="notice notice-success"><p>';
        echo 'Änderungen für das Institut <strong>' . esc_html( $institute_name ) . '</strong> gespeichert.';
        echo '</p></div></div>';

        require_once WPGLATTT_PATH . 'includes/institutes.php';
        glattt_institutes_page();
        return;
    }

    // Ausgabe Detail-Formular
    echo '<div class="wrap">';
    echo '<h1>Institut Details</h1>';

    // Dropdown der Institute
    $api      = new GLATTT_Phorrest_API();
    $branches = $api->get_branches();
    echo '<form method="get" style="margin-bottom:24px;">';
    echo '<input type="hidden" name="page" value="glattt-institute-details">';
    echo '<select name="branch" style="width:300px;padding:4px;" onchange="this.form.submit()">';
    echo '<option value="">-- Institut wählen --</option>';
    if ( ! is_wp_error( $branches ) ) {
        foreach ( $branches as $b ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $b['branchId'] ),
                selected( $branch, $b['branchId'], false ),
                esc_html( $b['name'] )
            );
        }
    }
    echo '</select>';
    echo '</form>';

    if ( ! $branch ) {
        echo '<p>Bitte wähle oben ein Institut aus.</p>';
        echo '</div>';
        return;
    }

    // Laden bestehender Daten
    $row = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}glattt_institute_meta WHERE branch_id = %s", $branch ),
        ARRAY_A
    );
    $active_map = get_option( 'glattt_active_institutes', [] );
    $is_active  = ! isset( $active_map[ $branch ] ) || 1 === intval( $active_map[ $branch ] );

    // Bearbeitungsformular
    echo '<form method="post">';
    echo '<input type="hidden" name="branch" value="' . esc_attr( $branch ) . '">';

    // Name
    echo '<p><label>Name:<br>';
    echo '<input type="text" name="custom_name" value="' . esc_attr( $row['custom_name'] ?? '' ) . '" class="regular-text" style="width:300px;">';
    echo '</label></p>';

    // Aktiv
    echo '<p><label style="display:flex;align-items:center;gap:8px;">';
    echo '<input type="checkbox" name="active" value="1" ' . checked( $is_active, true, false ) . '>';
    echo '<span>Aktiv?</span>';
    echo '</label></p>';

    // Öffnungszeiten
    echo '<h2 style="margin-top:24px;">Öffnungszeiten</h2>';
    $days = [
        'mon' => 'Montag',
        'tue' => 'Dienstag',
        'wed' => 'Mittwoch',
        'thu' => 'Donnerstag',
        'fri' => 'Freitag',
        'sat' => 'Samstag',
        'sun' => 'Sonntag',
    ];
    foreach ( $days as $key => $label ) {
        $from = esc_attr( $row[ "open_{$key}_from" ] ?? '' );
        $to   = esc_attr( $row[ "open_{$key}_to"   ] ?? '' );
        echo '<p style="display:flex;align-items:center;gap:8px;">';
        echo '<strong style="width:100px;">' . esc_html( $label ) . ':</strong>';
        echo '<input type="time" name="open_' . $key . '_from" value="' . $from . '">';
        echo '<span>bis</span>';
        echo '<input type="time" name="open_' . $key . '_to" value="' . $to . '">';
        echo '</p>';
    }

    // Bild
    $img_id = $row['image_id'] ?? '';
    echo '<h2 style="margin-top:24px;">Bild des Standorts</h2>';
    echo '<p>' . ( $img_id ? wp_get_attachment_image( $img_id, 'medium' ) : '' ) . '</p>';
    echo '<p>';
    echo '<button class="button" id="glattt_select_image" style="margin-bottom:16px;">Bild auswählen</button>';
    echo '<input type="hidden" id="glattt_image_id" name="image_id" value="' . esc_attr( $img_id ) . '">';
    echo '</p>';

    // E-Mail-Adresse
    echo '<h2 style="margin-top:24px;">E-Mail-Adresse</h2>';
    echo '<p>';
    echo '<input type="email" name="email" value="' . esc_attr( $row['email'] ?? '' ) . '" class="regular-text" style="width:300px;">';
    echo '</p>';

    // Telefonnummer
    echo '<h2 style="margin-top:24px;">Telefonnummer</h2>';
    echo '<p>';
    echo '<input type="tel" name="phone" value="' . esc_attr( $row['phone'] ?? '' ) . '" class="regular-text" style="width:300px;">';
    echo '</p>';
    // WhatsApp-Nummer
    echo '<h2 style="margin-top:24px;">WhatsApp-Nummer</h2>';
    echo '<p>';
    echo '<input type="tel" name="whatsapp" value="' . esc_attr( $row['whatsapp'] ?? '' ) . '" class="regular-text" style="width:300px;">';
    echo '</p>';

    // Speichern
    echo '<p style="margin-top:16px;">';
    echo '<button type="submit" name="glattt_save_institute" class="button button-primary">Speichern</button>';
    echo '</p>';

    echo '</form>';
    echo '</div>';

    // JS für Media-Uploader
    ?>
    <script>
    jQuery(function($){
        var frame;
        $('#glattt_select_image').on('click', function(e){
            e.preventDefault();
            if ( frame ) { frame.open(); return; }
            frame = wp.media({ title: 'Standort-Bild wählen', multiple: false });
            frame.on('select', function(){
                var att = frame.state().get('selection').first().toJSON();
                $('#glattt_image_id').val(att.id);
                $('p:has(#glattt_select_image)').prepend(
                    '<img src="'+att.sizes.medium.url+'" style="max-width:100%;margin-bottom:16px;">'
                );
            });
            frame.open();
        });
    });
    </script>
<?php
}