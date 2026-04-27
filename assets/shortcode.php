<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers the [six40_booking_form] shortcode.
 *
 * Usage: add [six40_booking_form] to any page or the /pide-cita/ page.
 */
add_shortcode( 'six40_booking_form', 'six40_render_booking_form' );

function six40_render_booking_form( array $atts = [] ): string {
    // Basic config check: warn admins if Supabase is not configured.
    if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
        $cfg = (array) get_option( 'six40_settings', [] );
        if ( empty( $cfg['supabase_url'] ) || empty( $cfg['supabase_key'] ) ) {
            return '<div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:16px;font-family:sans-serif;">'
                 . '⚠️ <strong>Six40 Booking:</strong> El plugin no está configurado. '
                 . '<a href="' . esc_url( admin_url( 'admin.php?page=six40-settings' ) ) . '">Ir a Configuración →</a>'
                 . '</div>';
        }
    }

    ob_start();
    require SIX40_PLUGIN_DIR . 'public/booking-form.php';
    return ob_get_clean();
}
