<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * includes/class-email-sender.php
 * Zentrale Klasse für E-Mail Versand
 */
class GLATTT_Email_Sender {
    /**
     * Lädt eine E-Mail-Vorlage aus der DB und ersetzt Platzhalter.
     * @param int $template_id
     * @param array $placeholders Key=>Value
     * @return array [subject, content, headers]
     */
    public static function prepare_email( $template_id, array $placeholders ) {
        global $wpdb;
        $table = $wpdb->prefix . 'glattt_email_templates';
        $tpl   = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $template_id) );
        if ( ! $tpl ) {
            return null;
        }
        // Betreff und Inhalt
        $subject = strtr( $tpl->subject, $placeholders );
        $content = strtr( $tpl->content, $placeholders );
        // Absender
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        // Empfänger
        $to  = array_map('trim', explode(',', strtr( $tpl->to_addresses, $placeholders )));
        $cc  = array_map('trim', explode(',', strtr( $tpl->cc_addresses, $placeholders )));
        $bcc = array_map('trim', explode(',', strtr( $tpl->bcc_addresses, $placeholders )));
        return [
            'to'      => $to,
            'cc'      => $cc,
            'bcc'     => $bcc,
            'subject' => $subject,
            'body'    => $content,
            'headers' => $headers,
        ];
    }

    /**
     * Sendet eine E-Mail anhand einer Template-ID.
     * @param int $template_id
     * @param array $placeholders
     * @return bool
     */
    public static function send_template( $template_id, array $placeholders ) {
        $mail = self::prepare_email( $template_id, $placeholders );
        if ( ! $mail ) {
            return false;
        }
        // Filter leere Empfänger
        $to_clean  = array_filter( $mail['to'] );
        $cc_clean  = array_filter( $mail['cc'] );
        $bcc_clean = array_filter( $mail['bcc'] );
        if ( empty( $to_clean ) ) {
            return false; // kein To
        }
        if ( ! empty( $cc_clean ) ) {
            $mail['headers'][] = 'Cc: ' . implode( ',', $cc_clean );
        }
        if ( ! empty( $bcc_clean ) ) {
            $mail['headers'][] = 'Bcc: ' . implode( ',', $bcc_clean );
        }
        return wp_mail( implode( ',', $to_clean ), $mail['subject'], $mail['body'], $mail['headers'] );
    }
}
