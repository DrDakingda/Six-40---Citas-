<?php
defined( 'ABSPATH' ) || exit;

class Six40_Admin_Panel {

    private static ?self $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_post_six40_save_settings',      [ $this, 'handle_save_settings' ] );
        add_action( 'wp_ajax_six40_update_appt_status',    [ $this, 'ajax_update_appt_status' ] );
        add_action( 'wp_ajax_six40_update_barber_status',  [ $this, 'ajax_update_barber_status' ] );
        add_action( 'wp_ajax_six40_get_appointments_json', [ $this, 'ajax_get_appointments_json' ] );
        add_action( 'admin_init',            [ $this, 'handle_oauth_callback' ] );
    }

    public function register_menus() {
        add_menu_page( 'Six40 Booking', 'Six40 Booking', 'manage_options', 'six40-dashboard',
            [ $this, 'page_dashboard' ], 'dashicons-calendar-alt', 26 );
        add_submenu_page( 'six40-dashboard', 'Dashboard',    'Dashboard',    'manage_options', 'six40-dashboard', [ $this, 'page_dashboard' ] );
        add_submenu_page( 'six40-dashboard', 'Citas',        'Citas',        'manage_options', 'six40-citas',     [ $this, 'page_citas' ] );
        add_submenu_page( 'six40-dashboard', 'Barberos',     'Barberos',     'manage_options', 'six40-barberos',  [ $this, 'page_barberos' ] );
        add_submenu_page( 'six40-dashboard', 'Configuración','Configuración','manage_options', 'six40-settings',  [ $this, 'page_settings' ] );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'six40' ) === false ) return;
        wp_enqueue_style(  'six40-admin', SIX40_PLUGIN_URL . 'admin/css/admin.css', [], SIX40_VERSION );
        wp_enqueue_script( 'six40-admin', SIX40_PLUGIN_URL . 'admin/js/admin.js',  [ 'jquery' ], SIX40_VERSION, true );
        wp_localize_script( 'six40-admin', 'six40Admin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'six40_admin_nonce' ),
        ] );
    }

    // ── Pages ─────────────────────────────────────────────────────────────────

    public function page_dashboard() {
        $api         = new Six40_Booking_API();
        $today       = wp_date( 'Y-m-d' );
        $today_appts = $api->get_appointments( [ 'date' => $today ] );
        if ( is_wp_error( $today_appts ) ) $today_appts = [];

        $statuses           = $api->get_barber_statuses();
        $malaga_count       = count( array_filter( $today_appts, fn($a) => $a['location'] === 'malaga' ) );
        $torremolinos_count = count( array_filter( $today_appts, fn($a) => $a['location'] === 'torremolinos' ) );

        require SIX40_PLUGIN_DIR . 'admin/dashboard.php';
    }

    public function page_citas() {
        $api      = new Six40_Booking_API();
        $location = sanitize_text_field( $_GET['location'] ?? '' );
        $date_from = sanitize_text_field( $_GET['date_from'] ?? '' );
        $status_f  = sanitize_text_field( $_GET['status'] ?? '' );

        $filters = [];
        if ( $location ) $filters['location'] = $location;
        if ( $status_f ) $filters['status']   = $status_f;

        $appointments = $api->get_appointments( $filters );
        if ( is_wp_error( $appointments ) ) $appointments = [];
        if ( $date_from ) {
            $appointments = array_values( array_filter( $appointments, fn($a) => ( $a['date'] ?? '' ) >= $date_from ) );
        }

        require SIX40_PLUGIN_DIR . 'admin/dashboard.php';
    }

    public function page_barberos() {
        $api      = new Six40_Booking_API();
        $statuses = $api->get_barber_statuses();
        require SIX40_PLUGIN_DIR . 'admin/dashboard.php';
    }

    public function page_settings() {
        $cfg       = (array) get_option( 'six40_settings', [] );
        $calendar  = new Six40_Google_Calendar();
        $auth_url  = $calendar->get_auth_url();
        $has_token = ! empty( $cfg['google_refresh_token'] );
        require SIX40_PLUGIN_DIR . 'admin/dashboard.php';
    }

    // ── Handlers ──────────────────────────────────────────────────────────────

    public function handle_save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'six40_save_settings' );

        $cfg = (array) get_option( 'six40_settings', [] );
        foreach ( [ 'supabase_url','supabase_key','google_client_id','google_client_secret',
                    'google_calendar_malaga','google_calendar_torremolinos',
                    'resend_api_key','email_from','email_from_name' ] as $f ) {
            $cfg[ $f ] = sanitize_text_field( $_POST[ $f ] ?? '' );
        }
        update_option( 'six40_settings', $cfg );
        wp_redirect( admin_url( 'admin.php?page=six40-settings&saved=1' ) );
        exit;
    }

    public function handle_oauth_callback() {
        if ( ( $_GET['page'] ?? '' ) !== 'six40-settings' ) return;
        if ( ( $_GET['oauth'] ?? '' ) !== 'google' ) return;
        if ( empty( $_GET['code'] ) || ! current_user_can( 'manage_options' ) ) return;

        $calendar = new Six40_Google_Calendar();
        $result   = $calendar->exchange_code( sanitize_text_field( $_GET['code'] ) );
        $suffix   = is_wp_error( $result ) ? '&oauth_error=1' : '&oauth_success=1';
        wp_redirect( admin_url( 'admin.php?page=six40-settings' . $suffix ) );
        exit;
    }

    // ── AJAX ──────────────────────────────────────────────────────────────────

    public function ajax_update_appt_status() {
        check_ajax_referer( 'six40_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $id     = intval( $_POST['id'] ?? 0 );
        $status = sanitize_text_field( $_POST['status'] ?? '' );
        if ( ! $id || ! in_array( $status, [ 'confirmed','cancelled','completed','no_show' ], true ) ) {
            wp_send_json_error( 'Invalid parameters.' );
        }

        $api    = new Six40_Booking_API();
        $result = $api->update_appointment_status( $id, $status );
        if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );

        if ( $status === 'cancelled' && ! empty( $result[0] ) ) {
            ( new Six40_Email() )->send_cancellation( $result[0] );
        }

        wp_send_json_success( [ 'status' => $status ] );
    }

    public function ajax_update_barber_status() {
        check_ajax_referer( 'six40_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $barber_id = intval( $_POST['barber_id'] ?? 0 );
        $status    = sanitize_text_field( $_POST['status'] ?? '' );
        $api       = new Six40_Booking_API();

        if ( ! $api->update_barber_status( $barber_id, $status ) ) {
            wp_send_json_error( 'Could not update barber status.' );
        }
        wp_send_json_success( [ 'barber_id' => $barber_id, 'status' => $status ] );
    }

    public function ajax_get_appointments_json() {
        check_ajax_referer( 'six40_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $filters  = [];
        $location = sanitize_text_field( $_GET['location'] ?? '' );
        if ( $location ) $filters['location'] = $location;

        $api          = new Six40_Booking_API();
        $appointments = $api->get_appointments( $filters );
        if ( is_wp_error( $appointments ) ) wp_send_json_error( $appointments->get_error_message() );

        $svc_labels    = [ 'barba' => 'Barba', 'corte' => 'Corte', 'corte_barba' => 'Corte + Barba' ];
        $status_colors = [ 'confirmed'=>'#e8c866','completed'=>'#4caf50','cancelled'=>'#e53e3e','no_show'=>'#a0a0a0' ];

        $events = [];
        foreach ( $appointments as $a ) {
            $status   = $a['status'] ?? 'confirmed';
            $service  = $a['service'] ?? '';
            $events[] = [
                'id'              => $a['id'] ?? '',
                'title'           => ( $svc_labels[$service] ?? $service ) . ' — ' . ( $a['customer_name'] ?? '' ),
                'start'           => ( $a['date'] ?? '' ) . 'T' . substr( $a['time'] ?? '', 0, 5 ),
                'end'             => ( $a['date'] ?? '' ) . 'T' . substr( $a['end_time'] ?? '', 0, 5 ),
                'backgroundColor' => $status_colors[$status] ?? '#e8c866',
                'borderColor'     => $status_colors[$status] ?? '#e8c866',
                'textColor'       => $status === 'confirmed' ? '#1a1a1a' : '#ffffff',
                'extendedProps'   => [
                    'status'    => $status,
                    'location'  => $a['location'] ?? '',
                    'barber_id' => $a['barber_id'] ?? '',
                    'email'     => $a['customer_email'] ?? '',
                ],
            ];
        }

        wp_send_json_success( $events );
    }
}
