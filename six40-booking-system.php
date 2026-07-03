<?php
/**
 * Plugin Name: Six40 Booking System
 * Plugin URI:  https://six40.katibu.es/
 * Description: Sistema de citas para Sixcuarenta 640 Barbería (Málaga y Torremolinos).
 * Version:     1.4.3
 * Author:      Katibu
 * Author URI:  https://katibu.es/
 * License:     GPL-2.0+
 * Text Domain: six40-booking
 */

defined( 'ABSPATH' ) || exit;

// ── Constants ──────────────────────────────────────────────────────────────────
define( 'SIX40_VERSION',    '1.4.3' );
define( 'SIX40_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIX40_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SIX40_PLUGIN_FILE', __FILE__ );

// ── Autoload ───────────────────────────────────────────────────────────────────
require_once SIX40_PLUGIN_DIR . 'includes/class-booking-api.php';
require_once SIX40_PLUGIN_DIR . 'includes/class-google-calendar.php';
require_once SIX40_PLUGIN_DIR . 'includes/class-email.php';
require_once SIX40_PLUGIN_DIR . 'includes/class-admin-panel.php';
require_once SIX40_PLUGIN_DIR . 'assets/shortcode.php';

// Carga configuración local si existe (nunca va a git).
if ( file_exists( SIX40_PLUGIN_DIR . 'six40-config.php' ) ) {
    require_once SIX40_PLUGIN_DIR . 'six40-config.php';
}

// ── Activation / Deactivation ──────────────────────────────────────────────────
register_activation_hook( __FILE__, 'six40_activate' );
register_deactivation_hook( __FILE__, 'six40_deactivate' );

function six40_activate() {
    if ( ! get_option( 'six40_settings' ) ) {
        update_option( 'six40_settings', [
            'supabase_url'                 => '',
            'supabase_key'                 => '',
            'google_client_id'             => '',
            'google_client_secret'         => '',
            'google_calendar_malaga'       => '',
            'google_calendar_torremolinos' => '',
            'resend_api_key'               => '',
            'email_from'                   => 'noreply@six40.katibu.es',
            'email_from_name'              => 'Six40 Barbería',
        ] );
    }
    flush_rewrite_rules();
}

function six40_deactivate() {
    flush_rewrite_rules();
}

// ── Boot ───────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'six40_init' );

function six40_init() {
    if ( is_admin() ) {
        Six40_Admin_Panel::get_instance();
    }
    add_action( 'wp_enqueue_scripts',              'six40_register_public_assets' );
    add_action( 'wp_ajax_six40_get_services',        'six40_ajax_get_services' );
    add_action( 'wp_ajax_nopriv_six40_get_services', 'six40_ajax_get_services' );
    add_action( 'wp_ajax_six40_get_slots',         'six40_ajax_get_slots' );
    add_action( 'wp_ajax_nopriv_six40_get_slots',  'six40_ajax_get_slots' );
    add_action( 'wp_ajax_six40_submit_booking',        'six40_ajax_submit_booking' );
    add_action( 'wp_ajax_nopriv_six40_submit_booking', 'six40_ajax_submit_booking' );
}

// ── Public assets ──────────────────────────────────────────────────────────────
// Se registran en wp_enqueue_scripts y se encolan desde el shortcode
// (six40_render_booking_form). Así funcionan siempre, aunque el shortcode viva
// dentro de un page builder como Avada/Fusion Builder, donde has_shortcode() sobre
// $post->post_content no lo detecta de forma fiable.
function six40_register_public_assets() {
    wp_register_style( 'six40-booking', SIX40_PLUGIN_URL . 'public/css/booking.css', [], SIX40_VERSION );
    wp_register_script( 'six40-booking', SIX40_PLUGIN_URL . 'public/js/booking.js', [ 'jquery' ], SIX40_VERSION, true );
    wp_localize_script( 'six40-booking', 'six40Ajax', [
        'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
        'nonce'          => wp_create_nonce( 'six40_booking_nonce' ),
        'holidays'       => array_values( (array) get_option( 'six40_holidays', [] ) ),
        'closedWeekdays' => [ 0 ], // 0 = domingo (JS getDay). Sábado abierto.
        'strings' => [
            'selectDate' => __( 'Selecciona una fecha', 'six40-booking' ),
            'selectTime' => __( 'Selecciona una hora', 'six40-booking' ),
            'noSlots'    => __( 'No hay horas disponibles para este día', 'six40-booking' ),
            'loading'    => __( 'Cargando…', 'six40-booking' ),
            'success'    => __( '¡Cita confirmada! Revisa tu correo.', 'six40-booking' ),
            'error'      => __( 'Ha ocurrido un error. Inténtalo de nuevo.', 'six40-booking' ),
        ],
    ] );
}

// Encola los assets ya registrados. Se llama desde el shortcode.
function six40_enqueue_public_assets() {
    // Si el shortcode se procesa antes/fuera de wp_enqueue_scripts, garantizamos
    // el registro on-demand.
    if ( ! wp_style_is( 'six40-booking', 'registered' ) ) {
        six40_register_public_assets();
    }
    wp_enqueue_style( 'six40-booking' );
    wp_enqueue_script( 'six40-booking' );
}

// ── AJAX: get services (por local, agrupados por categoría) ──────────────────────
function six40_ajax_get_services() {
    check_ajax_referer( 'six40_booking_nonce', 'nonce' );

    $location = sanitize_text_field( $_POST['location'] ?? '' );
    if ( ! in_array( $location, [ 'malaga', 'torremolinos' ], true ) ) {
        wp_send_json_error( [ 'message' => __( 'Local inválido.', 'six40-booking' ) ] );
    }

    $api      = new Six40_Booking_API();
    $services = $api->get_services( $location );

    if ( is_wp_error( $services ) ) {
        wp_send_json_error( [ 'message' => $services->get_error_message() ] );
    }

    // Etiquetas legibles por categoría, en el orden en que deben mostrarse.
    $cat_labels = [
        'corte'       => 'Corte',
        'barba'       => 'Barba',
        'depilacion'  => 'Depilación',
        'tratamiento' => 'Tratamientos',
    ];

    $grouped = [];
    foreach ( $cat_labels as $key => $label ) {
        $grouped[ $key ] = [ 'label' => $label, 'items' => [] ];
    }

    foreach ( (array) $services as $svc ) {
        $cat = $svc['category'] ?? 'otros';
        if ( ! isset( $grouped[ $cat ] ) ) {
            $grouped[ $cat ] = [ 'label' => ucfirst( $cat ), 'items' => [] ];
        }
        $grouped[ $cat ]['items'][] = [
            'id'         => (int) $svc['id'],
            'name'       => $svc['name'] ?? '',
            'duration'   => (int) ( $svc['duration'] ?? 0 ),
            'price'      => isset( $svc['price'] ) ? (float) $svc['price'] : null,
            'price_from' => ! empty( $svc['price_from'] ),
        ];
    }

    // Quitar categorías vacías (ej. Torremolinos no tiene Depilación).
    $grouped = array_values( array_filter( $grouped, function ( $g ) {
        return ! empty( $g['items'] );
    } ) );

    wp_send_json_success( [ 'categories' => $grouped ] );
}

// ── AJAX: get slots ────────────────────────────────────────────────────────────
function six40_ajax_get_slots() {
    check_ajax_referer( 'six40_booking_nonce', 'nonce' );

    $location    = sanitize_text_field( $_POST['location'] ?? '' );
    $date        = sanitize_text_field( $_POST['date'] ?? '' );
    $service_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $_POST['service_ids'] ?? [] ) ) ) ) );
    $barber_id   = intval( $_POST['barber_id'] ?? 0 );

    if ( ! $location || ! $date || empty( $service_ids ) ) {
        wp_send_json_error( [ 'message' => __( 'Parámetros inválidos.', 'six40-booking' ) ] );
    }
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        wp_send_json_error( [ 'message' => __( 'Fecha inválida.', 'six40-booking' ) ] );
    }

    $api   = new Six40_Booking_API();
    $slots = $api->get_available_slots( $location, $date, $service_ids, $barber_id );

    if ( is_wp_error( $slots ) ) {
        wp_send_json_error( [ 'message' => $slots->get_error_message() ] );
    }

    wp_send_json_success( [ 'slots' => $slots ] );
}

// ── AJAX: submit booking ───────────────────────────────────────────────────────
function six40_ajax_submit_booking() {
    check_ajax_referer( 'six40_booking_nonce', 'nonce' );

    $location       = sanitize_text_field( $_POST['location'] ?? '' );
    $service_ids    = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $_POST['service_ids'] ?? [] ) ) ) ) );
    $date           = sanitize_text_field( $_POST['date'] ?? '' );
    $start_time     = sanitize_text_field( $_POST['start_time'] ?? '' );
    $customer_name  = sanitize_text_field( $_POST['customer_name'] ?? '' );
    $customer_email = sanitize_email( $_POST['customer_email'] ?? '' );
    $customer_phone = sanitize_text_field( $_POST['customer_phone'] ?? '' );
    $barber_id      = intval( $_POST['barber_id'] ?? 0 );

    // Teléfono obligatorio: al menos 9 dígitos.
    $phone_digits = preg_replace( '/\D/', '', $customer_phone );

    if ( ! $location || empty( $service_ids ) || ! $date || ! $start_time || ! $customer_name || ! is_email( $customer_email ) || strlen( $phone_digits ) < 9 ) {
        wp_send_json_error( [ 'message' => __( 'Por favor, completa todos los campos correctamente (incluido el teléfono).', 'six40-booking' ) ] );
    }
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        wp_send_json_error( [ 'message' => __( 'Fecha inválida.', 'six40-booking' ) ] );
    }
    if ( ! preg_match( '/^\d{2}:\d{2}$/', $start_time ) ) {
        wp_send_json_error( [ 'message' => __( 'Hora inválida.', 'six40-booking' ) ] );
    }

    $api = new Six40_Booking_API();
    $result = $api->create_appointment( [
        'location'       => $location,
        'service_ids'    => $service_ids,
        'date'           => $date,
        'start_time'     => $start_time,
        'customer_name'  => $customer_name,
        'customer_email' => $customer_email,
        'customer_phone' => $customer_phone,
        'barber_id'      => $barber_id,
    ] );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }

    // Fire-and-forget integrations.
    ( new Six40_Google_Calendar() )->create_event( $result );
    ( new Six40_Email() )->send_confirmation( $result );

    wp_send_json_success( [
        'message'        => __( '¡Cita confirmada! Recibirás un correo de confirmación.', 'six40-booking' ),
        'appointment_id' => $result['id'] ?? null,
    ] );
}
