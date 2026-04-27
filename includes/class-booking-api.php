<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles all communication with Supabase and the slot-availability logic.
 */
class Six40_Booking_API {

    // ── Barbero data (hardcoded) ───────────────────────────────────────────────
    const BARBERS = [
        1 => [ 'id' => 1, 'name' => 'Barbero 1', 'location' => 'malaga' ],
        2 => [ 'id' => 2, 'name' => 'Barbero 2', 'location' => 'malaga' ],
        3 => [ 'id' => 3, 'name' => 'Barbero 3', 'location' => 'malaga' ],
        4 => [ 'id' => 4, 'name' => 'Barbero 4', 'location' => 'malaga' ],
        5 => [ 'id' => 5, 'name' => 'Barbero 5', 'location' => 'torremolinos' ],
        6 => [ 'id' => 6, 'name' => 'Barbero 6', 'location' => 'torremolinos' ],
        7 => [ 'id' => 7, 'name' => 'Barbero 7', 'location' => 'torremolinos' ],
        8 => [ 'id' => 8, 'name' => 'Barbero 8', 'location' => 'torremolinos' ],
    ];

    // ── Services ──────────────────────────────────────────────────────────────
    const SERVICES = [
        'barba'        => [ 'label' => 'Barba',          'duration' => 15, 'slots' => 1 ],
        'corte'        => [ 'label' => 'Corte',          'duration' => 30, 'slots' => 1 ],
        'corte_barba'  => [ 'label' => 'Corte + Barba',  'duration' => 45, 'slots' => 2 ],
    ];

    const OPEN_HOUR  = 9;   // 09:00
    const CLOSE_HOUR = 19;  // 19:00
    const SLOT_MINS  = 30;

    // ── Supabase helpers ──────────────────────────────────────────────────────

    private function settings(): array {
        return (array) get_option( 'six40_settings', [] );
    }

    private function supabase_request( string $method, string $endpoint, array $body = [], array $query = [] ): array|WP_Error {
        $cfg = $this->settings();
        $url = rtrim( $cfg['supabase_url'] ?? '', '/' ) . '/rest/v1/' . ltrim( $endpoint, '/' );

        if ( $query ) {
            $url .= '?' . http_build_query( $query );
        }

        $args = [
            'method'  => strtoupper( $method ),
            'headers' => [
                'apikey'        => $cfg['supabase_key'] ?? '',
                'Authorization' => 'Bearer ' . ( $cfg['supabase_key'] ?? '' ),
                'Content-Type'  => 'application/json',
                'Prefer'        => 'return=representation',
            ],
            'timeout' => 15,
        ];

        if ( $body ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            $msg = $data['message'] ?? $data['error'] ?? "HTTP $code";
            return new WP_Error( 'supabase_error', $msg );
        }

        return $data ?? [];
    }

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Returns available time slots (HH:MM strings) for a given location/date/service.
     *
     * @return string[]|WP_Error
     */
    public function get_available_slots( string $location, string $date, string $service ): array|WP_Error {
        if ( ! isset( self::SERVICES[ $service ] ) ) {
            return new WP_Error( 'invalid_service', __( 'Servicio inválido.', 'six40-booking' ) );
        }

        // Reject past dates.
        $today = wp_date( 'Y-m-d' );
        if ( $date < $today ) {
            return [];
        }

        $slots_needed = self::SERVICES[ $service ]['slots'];

        // Get barber availability statuses for the location.
        $barber_statuses = $this->get_barber_statuses( $location );

        $available_barbers = array_filter(
            self::BARBERS,
            fn( $b ) => $b['location'] === $location
                && ( $barber_statuses[ $b['id'] ] ?? 'available' ) === 'available'
        );

        if ( empty( $available_barbers ) ) {
            return [];
        }

        // Fetch existing appointments for this location/date from Supabase.
        $appointments = $this->supabase_request( 'GET', 'appointments', [], [
            'location' => 'eq.' . $location,
            'date'     => 'eq.' . $date,
            'status'   => 'neq.cancelled',
            'select'   => 'barber_id,time,service',
        ] );

        if ( is_wp_error( $appointments ) ) {
            return $appointments;
        }

        // Build occupied slot sets per barber.
        $occupied = []; // [ barber_id => [ 'HH:MM' => true, … ] ]
        foreach ( $appointments as $appt ) {
            $bid  = (int) $appt['barber_id'];
            $slot = substr( $appt['time'], 0, 5 );
            $svc  = $appt['service'] ?? 'corte';
            $n    = self::SERVICES[ $svc ]['slots'] ?? 1;
            $dt   = \DateTime::createFromFormat( 'H:i', $slot );
            for ( $i = 0; $i < $n; $i++ ) {
                $occupied[ $bid ][ $dt->format( 'H:i' ) ] = true;
                $dt->modify( '+' . self::SLOT_MINS . ' minutes' );
            }
        }

        // Generate all slots in the day.
        $all_slots    = $this->generate_day_slots();
        $free_slots   = [];
        $total_slots  = count( $all_slots );

        foreach ( $all_slots as $idx => $slot ) {
            // For multi-slot services, check that there are enough consecutive slots.
            if ( $idx + $slots_needed > $total_slots ) {
                break;
            }

            // Check if at least one barber is free for all required consecutive slots.
            foreach ( $available_barbers as $barber ) {
                $bid  = $barber['id'];
                $free = true;
                for ( $s = 0; $s < $slots_needed; $s++ ) {
                    if ( isset( $occupied[ $bid ][ $all_slots[ $idx + $s ] ] ) ) {
                        $free = false;
                        break;
                    }
                }
                if ( $free ) {
                    $free_slots[] = $slot;
                    break;
                }
            }
        }

        // On today, remove slots already past (add 30-min buffer).
        if ( $date === $today ) {
            $now_plus = ( new \DateTime( 'now' ) )->modify( '+30 minutes' )->format( 'H:i' );
            $free_slots = array_values( array_filter( $free_slots, fn( $s ) => $s >= $now_plus ) );
        }

        return $free_slots;
    }

    /**
     * Creates an appointment in Supabase, choosing the first available barber.
     *
     * @param array $data { location, service, date, time, name, email, barber_id }
     * @return array|WP_Error  The created appointment row.
     */
    public function create_appointment( array $data ): array|WP_Error {
        $location     = $data['location'];
        $service      = $data['service'];
        $date         = $data['date'];
        $time         = $data['time'];
        $barber_id    = (int) ( $data['barber_id'] ?? 0 );

        if ( ! isset( self::SERVICES[ $service ] ) ) {
            return new WP_Error( 'invalid_service', __( 'Servicio inválido.', 'six40-booking' ) );
        }

        $slots_needed = self::SERVICES[ $service ]['slots'];

        // Determine barber: if one was explicitly requested, verify it; otherwise auto-assign.
        if ( $barber_id && isset( self::BARBERS[ $barber_id ] )
            && self::BARBERS[ $barber_id ]['location'] === $location ) {
            $assigned_barber = $barber_id;
        } else {
            $assigned_barber = $this->find_free_barber( $location, $date, $time, $slots_needed );
        }

        if ( ! $assigned_barber ) {
            return new WP_Error( 'no_barber', __( 'No hay barberos disponibles en ese horario.', 'six40-booking' ) );
        }

        $duration_mins = self::SERVICES[ $service ]['duration'];
        $end_dt = \DateTime::createFromFormat( 'H:i', $time );
        $end_dt->modify( "+{$duration_mins} minutes" );

        $row = [
            'location'        => $location,
            'service'         => $service,
            'date'            => $date,
            'time'            => $time,
            'end_time'        => $end_dt->format( 'H:i' ),
            'barber_id'       => $assigned_barber,
            'customer_name'   => $data['name'],
            'customer_email'  => $data['email'],
            'status'          => 'confirmed',
            'created_at'      => current_time( 'c' ),
        ];

        $result = $this->supabase_request( 'POST', 'appointments', $row );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Supabase returns an array of inserted rows.
        $appointment = is_array( $result ) && isset( $result[0] ) ? $result[0] : $result;
        $appointment['barber_name'] = self::BARBERS[ $assigned_barber ]['name'] ?? '';
        $appointment['service_label'] = self::SERVICES[ $service ]['label'] ?? $service;

        return $appointment;
    }

    /**
     * Fetches all appointments (with optional filters) for admin use.
     */
    public function get_appointments( array $filters = [] ): array|WP_Error {
        $query = [ 'select' => '*', 'order' => 'date.asc,time.asc' ];

        foreach ( $filters as $col => $val ) {
            $query[ $col ] = 'eq.' . $val;
        }

        return $this->supabase_request( 'GET', 'appointments', [], $query );
    }

    /**
     * Update appointment status (confirmed, cancelled, completed, no_show).
     */
    public function update_appointment_status( int $id, string $status ): array|WP_Error {
        return $this->supabase_request(
            'PATCH',
            'appointments?id=eq.' . $id,
            [ 'status' => $status ]
        );
    }

    /**
     * Returns barber statuses stored in WP options (available / vacation / sick).
     */
    public function get_barber_statuses( string $location = '' ): array {
        $statuses = (array) get_option( 'six40_barber_statuses', [] );

        if ( $location ) {
            $filtered = [];
            foreach ( self::BARBERS as $b ) {
                if ( $b['location'] === $location ) {
                    $filtered[ $b['id'] ] = $statuses[ $b['id'] ] ?? 'available';
                }
            }
            return $filtered;
        }

        $all = [];
        foreach ( self::BARBERS as $b ) {
            $all[ $b['id'] ] = $statuses[ $b['id'] ] ?? 'available';
        }
        return $all;
    }

    /**
     * Updates a barber's status.
     */
    public function update_barber_status( int $barber_id, string $status ): bool {
        $allowed = [ 'available', 'vacation', 'sick' ];
        if ( ! in_array( $status, $allowed, true ) || ! isset( self::BARBERS[ $barber_id ] ) ) {
            return false;
        }
        $statuses = (array) get_option( 'six40_barber_statuses', [] );
        $statuses[ $barber_id ] = $status;
        return update_option( 'six40_barber_statuses', $statuses );
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function generate_day_slots(): array {
        $slots = [];
        $dt    = \DateTime::createFromFormat( 'H:i', sprintf( '%02d:00', self::OPEN_HOUR ) );
        $end   = \DateTime::createFromFormat( 'H:i', sprintf( '%02d:00', self::CLOSE_HOUR ) );
        while ( $dt < $end ) {
            $slots[] = $dt->format( 'H:i' );
            $dt->modify( '+' . self::SLOT_MINS . ' minutes' );
        }
        return $slots;
    }

    private function find_free_barber( string $location, string $date, string $time, int $slots_needed ): int|null {
        $barber_statuses = $this->get_barber_statuses( $location );
        $available = array_filter(
            self::BARBERS,
            fn( $b ) => $b['location'] === $location
                && ( $barber_statuses[ $b['id'] ] ?? 'available' ) === 'available'
        );

        if ( empty( $available ) ) {
            return null;
        }

        // Build blocked slots for the date.
        $appointments = $this->supabase_request( 'GET', 'appointments', [], [
            'location' => 'eq.' . $location,
            'date'     => 'eq.' . $date,
            'status'   => 'neq.cancelled',
            'select'   => 'barber_id,time,service',
        ] );

        if ( is_wp_error( $appointments ) ) {
            return null;
        }

        $occupied = [];
        foreach ( $appointments as $appt ) {
            $bid  = (int) $appt['barber_id'];
            $slot = substr( $appt['time'], 0, 5 );
            $svc  = $appt['service'] ?? 'corte';
            $n    = self::SERVICES[ $svc ]['slots'] ?? 1;
            $dt   = \DateTime::createFromFormat( 'H:i', $slot );
            for ( $i = 0; $i < $n; $i++ ) {
                $occupied[ $bid ][ $dt->format( 'H:i' ) ] = true;
                $dt->modify( '+' . self::SLOT_MINS . ' minutes' );
            }
        }

        // Build consecutive slots starting at $time.
        $slots_to_check = [];
        $dt_check = \DateTime::createFromFormat( 'H:i', $time );
        for ( $s = 0; $s < $slots_needed; $s++ ) {
            $slots_to_check[] = $dt_check->format( 'H:i' );
            $dt_check->modify( '+' . self::SLOT_MINS . ' minutes' );
        }

        foreach ( $available as $barber ) {
            $bid  = $barber['id'];
            $free = true;
            foreach ( $slots_to_check as $check_slot ) {
                if ( isset( $occupied[ $bid ][ $check_slot ] ) ) {
                    $free = false;
                    break;
                }
            }
            if ( $free ) {
                return $bid;
            }
        }

        return null;
    }
}
