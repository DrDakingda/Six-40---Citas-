<?php
defined( 'ABSPATH' ) || exit;

/**
 * Google Calendar integration via OAuth2 web flow.
 * Uses the Calendar REST API v3 directly (no PHP client library required).
 */
class Six40_Google_Calendar {

    private function settings(): array {
        return (array) get_option( 'six40_settings', [] );
    }

    private function calendar_id( string $location ): string {
        $cfg = $this->settings();
        return $location === 'malaga'
            ? ( $cfg['google_calendar_malaga'] ?? '' )
            : ( $cfg['google_calendar_torremolinos'] ?? '' );
    }

    /**
     * Creates a Calendar event for a confirmed appointment.
     */
    public function create_event( array $appointment ): bool|WP_Error {
        $calendar_id = $this->calendar_id( $appointment['location'] ?? '' );
        if ( ! $calendar_id ) {
            return new WP_Error( 'no_calendar', 'Google Calendar ID not configured.' );
        }

        $token = $this->get_access_token();
        if ( is_wp_error( $token ) ) return $token;

        $service_label = $appointment['service_label'] ?? ( $appointment['service'] ?? '' );
        $date          = $appointment['date'] ?? '';
        $time_start    = $appointment['time'] ?? '';
        $time_end      = $appointment['end_time'] ?? '';

        if ( ! $date || ! $time_start || ! $time_end ) {
            return new WP_Error( 'invalid_data', 'Appointment data incomplete.' );
        }

        $event = [
            'summary'     => sprintf( '%s — %s', $service_label, $appointment['customer_name'] ?? '' ),
            'description' => sprintf(
                "Cliente: %s\nEmail: %s\nServicio: %s\nBarbero: %s",
                $appointment['customer_name'] ?? '',
                $appointment['customer_email'] ?? '',
                $service_label,
                $appointment['barber_name'] ?? ''
            ),
            'start' => [ 'dateTime' => "{$date}T{$time_start}:00", 'timeZone' => 'Europe/Madrid' ],
            'end'   => [ 'dateTime' => "{$date}T{$time_end}:00",   'timeZone' => 'Europe/Madrid' ],
            'attendees' => [ [ 'email' => $appointment['customer_email'] ?? '' ] ],
            'reminders' => [
                'useDefault' => false,
                'overrides'  => [
                    [ 'method' => 'email', 'minutes' => 60 ],
                    [ 'method' => 'popup', 'minutes' => 30 ],
                ],
            ],
        ];

        $response = wp_remote_post(
            'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode( $calendar_id ) . '/events',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( $event ),
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code >= 400 ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            return new WP_Error( 'calendar_error', $body['error']['message'] ?? "HTTP $code" );
        }

        return true;
    }

    // ── OAuth2 ────────────────────────────────────────────────────────────────

    private function get_access_token(): string|WP_Error {
        $cached = get_transient( 'six40_google_access_token' );
        if ( $cached ) return $cached;

        $cfg           = $this->settings();
        $refresh_token = $cfg['google_refresh_token'] ?? '';

        if ( ! $refresh_token ) {
            return new WP_Error( 'no_refresh_token', 'Google refresh token not configured. See plugin settings → Google Calendar.' );
        }

        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id'     => $cfg['google_client_id'] ?? '',
                'client_secret' => $cfg['google_client_secret'] ?? '',
                'refresh_token' => $refresh_token,
                'grant_type'    => 'refresh_token',
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) return $response;

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['access_token'] ) ) {
            return new WP_Error( 'token_error', $data['error_description'] ?? 'Could not refresh Google token.' );
        }

        $expires = (int) ( $data['expires_in'] ?? 3600 );
        set_transient( 'six40_google_access_token', $data['access_token'], $expires - 60 );

        return $data['access_token'];
    }

    public function get_auth_url(): string {
        $cfg          = $this->settings();
        $redirect_uri = admin_url( 'admin.php?page=six40-settings&oauth=google' );
        return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query( [
            'client_id'     => $cfg['google_client_id'] ?? '',
            'redirect_uri'  => $redirect_uri,
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/calendar',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ] );
    }

    public function exchange_code( string $code ): bool|WP_Error {
        $cfg          = $this->settings();
        $redirect_uri = admin_url( 'admin.php?page=six40-settings&oauth=google' );

        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'body' => [
                'code'          => $code,
                'client_id'     => $cfg['google_client_id'] ?? '',
                'client_secret' => $cfg['google_client_secret'] ?? '',
                'redirect_uri'  => $redirect_uri,
                'grant_type'    => 'authorization_code',
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) return $response;

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['refresh_token'] ) ) {
            return new WP_Error( 'no_refresh', $data['error_description'] ?? 'No refresh token returned.' );
        }

        $cfg['google_refresh_token'] = $data['refresh_token'];
        update_option( 'six40_settings', $cfg );
        set_transient( 'six40_google_access_token', $data['access_token'], (int) ( $data['expires_in'] ?? 3600 ) - 60 );

        return true;
    }
}
