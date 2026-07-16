<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gestión de la cita por el cliente (cancelar / modificar) mediante un enlace
 * con token: home_url/?six40_manage=TOKEN. Página autocontenida + AJAX.
 */
class Six40_Manage {

    public static function init() {
        add_action( 'template_redirect', [ __CLASS__, 'maybe_render' ] );
        foreach ( [ 'slots', 'cancel', 'reschedule' ] as $a ) {
            add_action( "wp_ajax_six40_manage_$a",        [ __CLASS__, "ajax_$a" ] );
            add_action( "wp_ajax_nopriv_six40_manage_$a", [ __CLASS__, "ajax_$a" ] );
        }
    }

    /** URL de gestión para un token. */
    public static function url( $token ) {
        return add_query_arg( 'six40_manage', rawurlencode( $token ), home_url( '/' ) );
    }

    private static function find( $token ) {
        return ( new Six40_Booking_API() )->get_appointment_by_token( $token );
    }

    private static function is_manageable( $appt ) {
        return $appt
            && ( $appt['status'] ?? '' ) === 'confirmed'
            && ( $appt['date'] ?? '' ) >= wp_date( 'Y-m-d' );
    }

    /** Todos los eventos de Google de la cita (barbero + local). */
    private static function events_of( $appt ) {
        $list = json_decode( $appt['google_events'] ?? '', true );
        if ( is_array( $list ) && $list ) {
            return $list;
        }
        // Compat citas antiguas: solo el evento del barbero.
        if ( ! empty( $appt['google_event_id'] ) && ! empty( $appt['google_calendar_id'] ) ) {
            return [ [ 'event_id' => $appt['google_event_id'], 'calendar_id' => $appt['google_calendar_id'] ] ];
        }
        return [];
    }

    private static function fmt_date( $d ) {
        if ( ! $d ) return '';
        $dias   = [ 'Monday'=>'lunes','Tuesday'=>'martes','Wednesday'=>'miércoles','Thursday'=>'jueves','Friday'=>'viernes','Saturday'=>'sábado','Sunday'=>'domingo' ];
        $meses  = [ 1=>'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre' ];
        $dt = \DateTime::createFromFormat( 'Y-m-d', $d );
        if ( ! $dt ) return $d;
        return ucfirst( $dias[ $dt->format('l') ] ?? '' ) . ', ' . $dt->format('j') . ' de ' . ( $meses[ (int) $dt->format('n') ] ?? '' ) . ' de ' . $dt->format('Y');
    }

    // ── Página ────────────────────────────────────────────────────────────────

    public static function maybe_render() {
        if ( empty( $_GET['six40_manage'] ) ) {
            return;
        }
        $token = sanitize_text_field( wp_unslash( $_GET['six40_manage'] ) );
        self::render_page( $token, self::find( $token ) );
        exit;
    }

    private static function render_page( $token, $appt ) {
        $loc_labels = [ 'malaga' => 'Málaga', 'torremolinos' => 'Torremolinos' ];
        $ok  = self::is_manageable( $appt );
        $js  = [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'six40_manage' ),
            'token'   => $token,
            'today'   => wp_date( 'Y-m-d' ),
            'max'     => wp_date( 'Y-m-d', strtotime( '+60 days' ) ),
        ];
        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );
        ?><!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gestiona tu cita · Six40</title>
<style>
  :root{--bg:#000;--red:#b11a2d;--white:#f8f1f1;--muted:rgba(248,241,241,.5);--border:rgba(248,241,241,.14)}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;background:var(--bg);color:var(--white);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;display:flex;align-items:flex-start;justify-content:center;padding:40px 16px}
  .m-card{width:100%;max-width:520px}
  .m-logo{text-align:center;margin-bottom:24px}
  .m-logo img{width:160px;max-width:60%;height:auto;display:inline-block}
  .m-box{border:1px solid var(--border);border-radius:14px;padding:24px}
  h1{font-size:20px;margin:0 0 4px}
  .m-sub{color:var(--muted);font-size:14px;margin:0 0 20px}
  .m-row{display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);font-size:14px}
  .m-row:last-of-type{border-bottom:0}
  .m-row .k{width:110px;color:var(--muted);flex-shrink:0}
  .m-row .v{font-weight:600}
  .m-actions{display:flex;gap:10px;margin-top:22px;flex-wrap:wrap}
  .m-btn{flex:1;min-width:140px;border:none;border-radius:8px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;text-align:center}
  .m-btn-red{background:var(--red);color:#fff}
  .m-btn-ghost{background:transparent;border:1px solid var(--border);color:var(--white)}
  .m-btn:hover{opacity:.9}
  .m-btn:disabled{opacity:.5;cursor:not-allowed}
  .m-hidden{display:none}
  .m-modify{margin-top:20px;border-top:1px solid var(--border);padding-top:18px}
  .m-label{font-size:13px;color:var(--muted);display:block;margin-bottom:6px}
  input[type=date]{width:100%;background:transparent;border:1px solid var(--border);border-radius:8px;color:var(--white);padding:10px;font-size:15px;color-scheme:dark}
  .m-slots{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:14px}
  .m-slot{border:1px solid var(--border);background:rgba(248,241,241,.04);color:var(--white);border-radius:6px;padding:11px 4px;font-weight:600;cursor:pointer;font-size:14px}
  .m-slot:hover{border-color:var(--red)}
  .m-slot.sel{background:var(--red);border-color:var(--red);color:#fff}
  .m-msg{margin-top:14px;font-size:14px;padding:12px 14px;border-radius:8px}
  .m-msg.err{background:rgba(255,107,107,.12);color:#ff9b9b}
  .m-msg.ok{background:rgba(76,175,80,.14);color:#8fe19a}
  .m-note{color:var(--muted);font-size:12px;margin-top:14px;text-align:center}
</style></head>
<body>
<div class="m-card">
  <div class="m-logo"><img src="https://six40.katibu.es/wp-content/uploads/2026/04/Six40-Logotipo-1.png" alt="Six40 Barbería"></div>
  <div class="m-box">
<?php if ( ! $ok ) : ?>
    <h1>Cita no disponible</h1>
    <p class="m-sub">Esta cita no existe, ya fue cancelada o ya ha pasado. Si crees que es un error, contacta con la barbería.</p>
<?php else :
    $loc = $loc_labels[ $appt['location'] ?? '' ] ?? '';
?>
    <h1>Gestiona tu cita</h1>
    <p class="m-sub">Hola <?= esc_html( $appt['customer_name'] ?? '' ) ?>, aquí puedes cancelar o cambiar tu cita.</p>

    <div id="m-details">
      <div class="m-row"><span class="k">📍 Local</span><span class="v"><?= esc_html( $loc ) ?></span></div>
      <div class="m-row"><span class="k">✂️ Servicios</span><span class="v"><?= esc_html( $appt['services_label'] ?: '—' ) ?></span></div>
      <div class="m-row"><span class="k">📅 Fecha</span><span class="v" id="m-cur-date"><?= esc_html( self::fmt_date( $appt['date'] ?? '' ) ) ?></span></div>
      <div class="m-row"><span class="k">🕐 Hora</span><span class="v" id="m-cur-time"><?= esc_html( substr( $appt['start_time'] ?? '', 0, 5 ) ) ?></span></div>
    </div>

    <div class="m-actions" id="m-actions">
      <button class="m-btn m-btn-ghost" id="m-modify-btn">Cambiar fecha/hora</button>
      <button class="m-btn m-btn-red" id="m-cancel-btn">Cancelar cita</button>
    </div>

    <div class="m-modify m-hidden" id="m-modify">
      <label class="m-label" for="m-date">Elige un nuevo día</label>
      <input type="date" id="m-date" min="<?= esc_attr( $js['today'] ) ?>" max="<?= esc_attr( $js['max'] ) ?>">
      <div class="m-slots" id="m-slots"></div>
    </div>

    <div class="m-msg m-hidden" id="m-msg"></div>
    <p class="m-note">Los cambios se aplican al instante.</p>
<?php endif; ?>
  </div>
</div>

<?php if ( $ok ) : ?>
<script>
(function(){
  var CFG = <?= wp_json_encode( $js ) ?>;
  var $ = function(id){ return document.getElementById(id); };
  var selectedSlot = null;

  function post(action, data, cb){
    data = data || {}; data.action = 'six40_manage_' + action; data.nonce = CFG.nonce; data.token = CFG.token;
    var body = Object.keys(data).map(function(k){ return encodeURIComponent(k)+'='+encodeURIComponent(data[k]); }).join('&');
    fetch(CFG.ajaxUrl, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body })
      .then(function(r){ return r.json(); }).then(cb).catch(function(){ cb({success:false,data:{message:'Error de conexión.'}}); });
  }
  function msg(text, type){ var m=$('m-msg'); m.textContent=text; m.className='m-msg '+(type||''); }
  function hide(el){ el.classList.add('m-hidden'); } function show(el){ el.classList.remove('m-hidden'); }

  // Cancelar
  $('m-cancel-btn').addEventListener('click', function(){
    if(!confirm('¿Seguro que quieres cancelar tu cita?')) return;
    this.disabled = true;
    post('cancel', {}, function(res){
      if(res.success){ hide($('m-actions')); hide($('m-modify')); msg(res.data.message,'ok'); show($('m-msg')); }
      else { msg((res.data&&res.data.message)||'No se pudo cancelar.','err'); show($('m-msg')); $('m-cancel-btn').disabled=false; }
    });
  });

  // Mostrar modificar
  $('m-modify-btn').addEventListener('click', function(){ show($('m-modify')); this.classList.add('m-hidden'); });

  // Elegir día → cargar horas
  $('m-date').addEventListener('change', function(){
    var date = this.value; selectedSlot = null; $('m-slots').innerHTML = '';
    if(!date) return;
    $('m-slots').innerHTML = '<span style="color:var(--muted);font-size:14px">Cargando horas…</span>';
    post('slots', { date:date }, function(res){
      if(!res.success || !res.data || !res.data.slots || !res.data.slots.length){
        $('m-slots').innerHTML = '<span style="color:var(--muted);font-size:14px">No hay horas disponibles ese día.</span>'; return;
      }
      $('m-slots').innerHTML = '';
      res.data.slots.forEach(function(s){
        var b = document.createElement('button'); b.className='m-slot'; b.textContent=s;
        b.addEventListener('click', function(){
          Array.prototype.forEach.call(document.querySelectorAll('.m-slot'), function(x){ x.classList.remove('sel'); });
          b.classList.add('sel'); selectedSlot = s; doReschedule(date, s, b);
        });
        $('m-slots').appendChild(b);
      });
    });
  });

  function doReschedule(date, start, btn){
    if(!confirm('¿Cambiar tu cita al '+date+' a las '+start+'?')){ btn.classList.remove('sel'); return; }
    post('reschedule', { date:date, start:start }, function(res){
      if(res.success){
        hide($('m-modify')); hide($('m-actions')); msg(res.data.message,'ok'); show($('m-msg'));
      } else { msg((res.data&&res.data.message)||'No se pudo cambiar.','err'); show($('m-msg')); btn.classList.remove('sel'); }
    });
  }
})();
</script>
<?php endif; ?>
</body></html>
        <?php
    }

    // ── AJAX ──────────────────────────────────────────────────────────────────

    public static function ajax_slots() {
        check_ajax_referer( 'six40_manage', 'nonce' );
        $token = sanitize_text_field( $_POST['token'] ?? '' );
        $date  = sanitize_text_field( $_POST['date'] ?? '' );
        $appt  = self::find( $token );
        if ( ! self::is_manageable( $appt ) ) {
            wp_send_json_error( [ 'message' => 'Cita no disponible.' ] );
        }
        $api = new Six40_Booking_API();
        $slots = $api->get_available_slots(
            $appt['location'] ?? '',
            $date,
            $api->appointment_service_ids( $appt ),
            (int) ( $appt['barber_id'] ?? 0 )
        );
        if ( is_wp_error( $slots ) ) {
            wp_send_json_error( [ 'message' => $slots->get_error_message() ] );
        }
        wp_send_json_success( [ 'slots' => $slots ] );
    }

    public static function ajax_cancel() {
        check_ajax_referer( 'six40_manage', 'nonce' );
        $appt = self::find( sanitize_text_field( $_POST['token'] ?? '' ) );
        if ( ! self::is_manageable( $appt ) ) {
            wp_send_json_error( [ 'message' => 'Cita no disponible.' ] );
        }
        $api = new Six40_Booking_API();
        $res = $api->update_appointment_status( $appt['id'], 'cancelled' );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( [ 'message' => $res->get_error_message() ] );
        }
        $gcal = new Six40_Google_Calendar();
        foreach ( self::events_of( $appt ) as $ev ) {
            if ( ! empty( $ev['calendar_id'] ) && ! empty( $ev['event_id'] ) ) {
                $gcal->delete_event( $ev['calendar_id'], $ev['event_id'] );
            }
        }
        ( new Six40_Email() )->send_cancellation( $appt );
        wp_send_json_success( [ 'message' => 'Tu cita ha sido cancelada. ¡Gracias por avisar!' ] );
    }

    public static function ajax_reschedule() {
        check_ajax_referer( 'six40_manage', 'nonce' );
        $token = sanitize_text_field( $_POST['token'] ?? '' );
        $date  = sanitize_text_field( $_POST['date'] ?? '' );
        $start = sanitize_text_field( $_POST['start'] ?? '' );
        $appt  = self::find( $token );
        if ( ! self::is_manageable( $appt ) ) {
            wp_send_json_error( [ 'message' => 'Cita no disponible.' ] );
        }
        $api = new Six40_Booking_API();
        $r   = $api->reschedule_appointment( $appt, $date, $start );
        if ( is_wp_error( $r ) ) {
            wp_send_json_error( [ 'message' => $r->get_error_message() ] );
        }
        // Mover todos los eventos de Google (barbero + local).
        $gcal = new Six40_Google_Calendar();
        foreach ( self::events_of( $appt ) as $ev ) {
            if ( ! empty( $ev['calendar_id'] ) && ! empty( $ev['event_id'] ) ) {
                $gcal->update_event_time( $ev['calendar_id'], $ev['event_id'], $date, $start, $r['end_time'] );
            }
        }
        // Email con los nuevos datos.
        $svcs = [];
        foreach ( (array) ( $appt['appointment_services'] ?? [] ) as $as ) {
            if ( ! empty( $as['services']['name'] ) ) {
                $svcs[] = [ 'name' => $as['services']['name'] ];
            }
        }
        $appt['services']   = $svcs;
        $appt['date']       = $date;
        $appt['start_time'] = $start;
        $appt['end_time']   = $r['end_time'];
        $appt['duration']   = $r['duration'];
        ( new Six40_Email() )->send_confirmation( $appt );

        wp_send_json_success( [ 'message' => 'Tu cita se ha cambiado al ' . self::fmt_date( $date ) . ' a las ' . $start . '.' ] );
    }
}
