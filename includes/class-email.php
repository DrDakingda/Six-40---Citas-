<?php
defined( 'ABSPATH' ) || exit;

/**
 * Email notifications via Resend API (fallback: wp_mail).
 */
class Six40_Email {

    private function settings() {
        return (array) get_option( 'six40_settings', [] );
    }

    public function send_confirmation( $appointment ) {
        $email = $appointment['customer_email'] ?? '';
        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', 'Invalid customer email.' );
        }

        $loc_labels = [ 'malaga' => 'Málaga', 'torremolinos' => 'Torremolinos' ];
        $svc_labels = [ 'barba' => 'Barba', 'corte' => 'Corte', 'corte_barba' => 'Corte + Barba' ];

        $data = [
            'customer_name'  => $appointment['customer_name'] ?? '',
            'location_label' => $loc_labels[ $appointment['location'] ?? '' ] ?? '',
            'service_label'  => $appointment['service_label'] ?? $svc_labels[ $appointment['service'] ?? '' ] ?? '',
            'date_fmt'       => $this->format_date( $appointment['date'] ?? '' ),
            'time_fmt'       => substr( $appointment['time'] ?? '', 0, 5 ),
            'barber_name'    => $appointment['barber_name'] ?? '',
        ];

        return $this->send(
            $email,
            $data['customer_name'],
            'Confirmación de cita — Six40 Barbería',
            $this->tpl_confirmation( $data )
        );
    }

    public function send_cancellation( $appointment ) {
        $email = $appointment['customer_email'] ?? '';
        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', 'Invalid customer email.' );
        }
        return $this->send(
            $email,
            $appointment['customer_name'] ?? '',
            'Tu cita ha sido cancelada — Six40 Barbería',
            $this->tpl_cancellation( $appointment )
        );
    }

    // ── Resend / wp_mail ───────────────────────────────────────────────────────

    private function send( $to, $name, $subject, $html ) {
        $cfg     = $this->settings();
        $api_key = $cfg['resend_api_key'] ?? '';

        if ( ! $api_key ) {
            return wp_mail( $to, $subject, $html, [ 'Content-Type: text/html; charset=UTF-8' ] )
                ? true
                : new WP_Error( 'mail_failed', 'wp_mail failed.' );
        }

        $from = sprintf( '%s <%s>',
            $cfg['email_from_name'] ?? 'Six40 Barbería',
            $cfg['email_from'] ?? 'noreply@six40.katibu.es'
        );

        $response = wp_remote_post( 'https://api.resend.com/emails', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( [
                'from'    => $from,
                'to'      => [ "$name <$to>" ],
                'subject' => $subject,
                'html'    => $html,
            ] ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code >= 400 ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            return new WP_Error( 'resend_error', $body['message'] ?? "HTTP $code" );
        }

        return true;
    }

    // ── Templates ─────────────────────────────────────────────────────────────

    private function tpl_confirmation( $d ) {
        ob_start(); ?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:'Helvetica Neue',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:40px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;max-width:600px;width:100%;">
      <tr><td style="background:#1a1a1a;padding:32px 40px;text-align:center;">
        <h1 style="color:#e8c866;margin:0;font-size:28px;letter-spacing:3px;">SIX40</h1>
        <p style="color:#888;margin:4px 0 0;font-size:12px;letter-spacing:1px;">BARBERÍA · MÁLAGA &amp; TORREMOLINOS</p>
      </td></tr>
      <tr><td style="padding:40px;">
        <h2 style="color:#1a1a1a;font-size:22px;margin:0 0 8px;">¡Cita confirmada! ✂️</h2>
        <p style="color:#555;font-size:15px;line-height:1.6;margin:0 0 28px;">
          Hola <strong><?= esc_html( $d['customer_name'] ) ?></strong>, tu reserva está confirmada.
        </p>
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9f9f9;border-radius:6px;border-left:4px solid #e8c866;">
          <tr><td style="padding:24px 28px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="padding:7px 0;color:#888;font-size:13px;width:130px;">📍 Local</td>
                <td style="padding:7px 0;color:#1a1a1a;font-size:15px;font-weight:700;"><?= esc_html( $d['location_label'] ) ?></td>
              </tr>
              <tr>
                <td style="padding:7px 0;color:#888;font-size:13px;">✂️ Servicio</td>
                <td style="padding:7px 0;color:#1a1a1a;font-size:15px;font-weight:700;"><?= esc_html( $d['service_label'] ) ?></td>
              </tr>
              <tr>
                <td style="padding:7px 0;color:#888;font-size:13px;">📅 Fecha</td>
                <td style="padding:7px 0;color:#1a1a1a;font-size:15px;font-weight:700;"><?= esc_html( $d['date_fmt'] ) ?></td>
              </tr>
              <tr>
                <td style="padding:7px 0;color:#888;font-size:13px;">🕐 Hora</td>
                <td style="padding:7px 0;color:#1a1a1a;font-size:15px;font-weight:700;"><?= esc_html( $d['time_fmt'] ) ?></td>
              </tr>
              <?php if ( $d['barber_name'] ) : ?>
              <tr>
                <td style="padding:7px 0;color:#888;font-size:13px;">💈 Barbero/a</td>
                <td style="padding:7px 0;color:#1a1a1a;font-size:15px;font-weight:700;"><?= esc_html( $d['barber_name'] ) ?></td>
              </tr>
              <?php endif; ?>
            </table>
          </td></tr>
        </table>
        <p style="color:#aaa;font-size:13px;margin:28px 0 0;line-height:1.6;">
          ¿Necesitas cancelar o cambiar tu cita? Responde a este email y te ayudamos.
        </p>
      </td></tr>
      <tr><td style="background:#1a1a1a;padding:20px 40px;text-align:center;">
        <p style="color:#555;font-size:12px;margin:0;">© <?= date('Y') ?> Six40 Barbería · Málaga &amp; Torremolinos</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body></html>
        <?php return ob_get_clean();
    }

    private function tpl_cancellation( $a ) {
        $name = $a['customer_name'] ?? 'Cliente';
        ob_start(); ?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:40px;background:#f5f5f5;font-family:Arial,sans-serif;">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;">
  <div style="background:#1a1a1a;padding:32px;text-align:center;">
    <h1 style="color:#e8c866;margin:0;font-size:24px;letter-spacing:3px;">SIX40</h1>
  </div>
  <div style="padding:40px;">
    <h2 style="color:#1a1a1a;">Tu cita ha sido cancelada</h2>
    <p style="color:#555;line-height:1.6;">
      Hola <strong><?= esc_html( $name ) ?></strong>, confirmamos que tu cita ha sido cancelada.
    </p>
    <p style="color:#555;line-height:1.6;">
      Puedes reservar una nueva cita en
      <a href="<?= esc_url( home_url( '/pide-cita/' ) ) ?>" style="color:#e8c866;">six40.katibu.es/pide-cita</a>.
    </p>
  </div>
  <div style="background:#1a1a1a;padding:20px;text-align:center;">
    <p style="color:#555;font-size:12px;margin:0;">© <?= date('Y') ?> Six40 Barbería</p>
  </div>
</div>
</body></html>
        <?php return ob_get_clean();
    }

    private function format_date( $date ) {
        if ( ! $date ) return '';
        $months = [ '01'=>'enero','02'=>'febrero','03'=>'marzo','04'=>'abril','05'=>'mayo','06'=>'junio',
                    '07'=>'julio','08'=>'agosto','09'=>'septiembre','10'=>'octubre','11'=>'noviembre','12'=>'diciembre' ];
        $days   = [ 'Monday'=>'lunes','Tuesday'=>'martes','Wednesday'=>'miércoles',
                    'Thursday'=>'jueves','Friday'=>'viernes','Saturday'=>'sábado','Sunday'=>'domingo' ];
        $dt = \DateTime::createFromFormat( 'Y-m-d', $date );
        if ( ! $dt ) return $date;
        return ucfirst( ( $days[ $dt->format('l') ] ?? '' ) . ', ' . $dt->format('j') . ' de ' . ( $months[ $dt->format('m') ] ?? '' ) . ' de ' . $dt->format('Y') );
    }
}
