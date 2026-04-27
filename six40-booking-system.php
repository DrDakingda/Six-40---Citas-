<?php
/**
 * Plugin Name: Six40 Booking System
 * Plugin URI:  https://six40.katibu.es/
 * Description: Sistema de citas para Sixcuarenta 640 Barbería (Málaga y Torremolinos).
 * Version:     1.0.0
 * Author:      Six40
 * License:     GPL-2.0+
 * Text Domain: six40-booking
 */

defined( 'ABSPATH' ) || exit;

// ── Constants ──────────────────────────────────────────────────────────────────
define( 'SIX40_VERSION',    '1.0.0' );
define( 'SIX40_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIX40_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SIX40_PLUGIN_FILE', __FILE__ );

// ── Autoload ───────────────────────────────────────────────────────────────────
require_once SIX40_PLUGIN_DIR . 'includes/class-booking-api.php';
require_once SIX40_PLUGIN_DIR . 'includes/class-google-calendar.php';
require_once SIX40_PLUGIN_DIR . 'includes/class-email.php';
require_once SIX40_PLUGIN_DIR . 'includes/class-admin-panel.php';
require_once SIX40_PLUGIN_DIR . 'assets/shortcode.php';

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
    add_action( 'wp_enqueue_scripts',              'six40_enqueue_public_assets' );
    add_action( 'wp_ajax_six40_get_slots',         'six40_ajax_get_slots' );
    add_action( 'wp_ajax_nopriv_six40_get_slots',  'six40_ajax_get_slots' );
    add_action( 'wp_ajax_six40_submit_booking',        'six40_ajax_submit_booking' );
    add_action( 'wp_ajax_nopriv_six40_submit_booking', 'six40_ajax_submit_booking' );
}

// ── Public assets ──────────────────────────────────────────────────────────────
function six40_enqueue_public_assets() {
    global $post;
    if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'six40_booking_form' ) ) {
        return;
    }
    wp_enqueue_style( 'six40-booking', SIX40_PLUGIN_URL . 'public/css/booking.css', [], SIX40_VERSION );
    wp_enqueue_script( 'six40-booking', SIX40_PLUGIN_URL . 'public/js/booking.js', [ 'jquery' ], SIX40_VERSION, true );
    wp_localize_script( 'six40-booking', 'six40Ajax', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'six40_booking_nonce' ),
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

// ── AJAX: get slots ────────────────────────────────────────────────────────────
function six40_ajax_get_slots() {
    check_ajax_referer( 'six40_booking_nonce', 'nonce' );

    $location = sanitize_text_field( $_POST['location'] ?? '' );
    $date     = sanitize_text_field( $_POST['date'] ?? '' );
    $service  = sanitize_text_field( $_POST['service'] ?? '' );

    if ( ! $location || ! $date || ! $service ) {
        wp_send_json_error( [ 'message' => __( 'Parámetros inválidos.', 'six40-booking' ) ] );
    }
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        wp_send_json_error( [ 'message' => __( 'Fecha inválida.', 'six40-booking' ) ] );
    }

    $api   = new Six40_Booking_API();
    $slots = $api->get_available_slots( $location, $date, $service );

    if ( is_wp_error( $slots ) ) {
        wp_send_json_error( [ 'message' => $slots->get_error_message() ] );
    }

    wp_send_json_success( [ 'slots' => $slots ] );
}

// ── AJAX: submit booking ───────────────────────────────────────────────────────
function six40_ajax_submit_booking() {
    check_ajax_referer( 'six40_booking_nonce', 'nonce' );

    $location  = sanitize_text_field( $_POST['location'] ?? '' );
    $service   = sanitize_text_field( $_POST['service'] ?? '' );
    $date      = sanitize_text_field( $_POST['date'] ?? '' );
    $time      = sanitize_text_field( $_POST['time'] ?? '' );
    $name      = sanitize_text_field( $_POST['name'] ?? '' );
    $email     = sanitize_email( $_POST['email'] ?? '' );
    $barber_id = intval( $_POST['barber_id'] ?? 0 );

    if ( ! $location || ! $service || ! $date || ! $time || ! $name || ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => __( 'Por favor, completa todos los campos correctamente.', 'six40-booking' ) ] );
    }
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        wp_send_json_error( [ 'message' => __( 'Fecha inválida.', 'six40-booking' ) ] );
    }
    if ( ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
        wp_send_json_error( [ 'message' => __( 'Hora inválida.', 'six40-booking' ) ] );
    }

    $api    = new Six40_Booking_API();
    $result = $api->create_appointment( compact( 'location', 'service', 'date', 'time', 'name', 'email', 'barber_id' ) );

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
