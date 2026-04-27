<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles Supabase communication and slot-availability logic.
 * Compatible with PHP 7.4+
 */
class Six40_Booking_API {

    // Adrián y Graciela trabajan en ambos locales (IDs distintos por local).
    const BARBERS = [
        // Málaga
        1 => [ 'id' => 1, 'name' => 'Samuel Puertas',   'location' => 'malaga' ],
        2 => [ 'id' => 2, 'name' => 'Graciela Arcos',   'location' => 'malaga' ],
        3 => [ 'id' => 3, 'name' => 'Adrián Ortigosa',  'location' => 'malaga' ],
        4 => [ 'id' => 4, 'name' => 'Alejandro Alfonso', 'location' => 'malaga' ],
        // Torremolinos
        5 => [ 'id' => 5, 'name' => 'Antonio Pérez',    'location' => 'torremolinos' ],
        6 => [ 'id' => 6, 'name' => 'Graciela Arcos',   'location' => 'torremolinos' ],
        7 => [ 'id' => 7, 'name' => 'Juan Jose García', 'location' => 'torremolinos' ],
        8 => [ 'id' => 8, 'name' => 'Adrián Ortigosa',  'location' => 'torremolinos' ],
    ];

    const SERVICES = [
        'barba'       => [ 'label' => 'Barba',         'duration' => 15, 'slots' => 1 ],
        'corte'       => [ 'label' => 'Corte',         'duration' => 30, 'slots' => 1 ],
        'corte_barba' => [ 'label' => 'Corte + Barba', 'duration' => 45, 'slots' => 2 ],
    ];

    const OPEN_HOUR  = 9;
    const CLOSE_HOUR = 19;
    const SLOT_MINS  = 30;

    private function settings() {
        return (array) get_option( 'six40_settings', [] );
    }

    // ── Supabase REST ─────────────────────────────────────────────────────────

    private function supabase_request( $method, $endpoint, $body = [], $query = [] ) {
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
            return new WP_Error( 'supabase_error', $data['message'] ?? $data['error'] ?? "HTTP $code" );
        }

        return $data ?? [];
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * @return array|WP_Error
     */
    public function get_available_slots( $location, $date, $service ) {
        if ( ! isset( self::SERVICES[ $service ] ) ) {
            return new WP_Error( 'invalid_service', __( 'Servicio inválido.', 'six40-booking' ) );
        }

        if ( $date < wp_date( 'Y-m-d' ) ) {
            return [];
        }

        $slots_needed       = self::SERVICES[ $service ]['slots'];
        $barber_statuses    = $this->get_barber_statuses( $location );
        $barbers_on_day_off = $this->get_barber_ids_off_on_date( $date );

        $available_barbers = array_filter(
            self::BARBERS,
            function( $b ) use ( $location, $barber_statuses, $barbers_on_day_off ) {
                return $b['location'] === $location
                    && ( $barber_statuses[ $b['id'] ] ?? 'available' ) === 'available'
                    && ! in_array( $b['id'], $barbers_on_day_off, true );
            }
        );

        if ( empty( $available_barbers ) ) {
            return [];
        }

        $appointments = $this->supabase_request( 'GET', 'appointments', [], [
            'location' => 'eq.' . $location,
            'date'     => 'eq.' . $date,
            'status'   => 'neq.cancelled',
            'select'   => 'barber_id,time,service',
        ] );

        if ( is_wp_error( $appointments ) ) {
            return $appointments;
        }

        $occupied  = $this->build_occupied_map( $appointments );
        $all_slots = $this->generate_day_slots();
        $free_slots = [];
        $total      = count( $all_slots );

        foreach ( $all_slots as $idx => $slot ) {
            if ( $idx + $slots_needed > $total ) break;

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

        $today = wp_date( 'Y-m-d' );
        if ( $date === $today ) {
            $cutoff = ( new \DateTime( 'now' ) )->modify( '+30 minutes' )->format( 'H:i' );
            $free_slots = array_values( array_filter( $free_slots, function( $s ) use ( $cutoff ) {
                return $s >= $cutoff;
            } ) );
        }

        return $free_slots;
    }

    /**
     * @return array|WP_Error
     */
    public function create_appointment( $data ) {
        $location  = $data['location'];
        $service   = $data['service'];
        $date      = $data['date'];
        $time      = $data['time'];
        $barber_id = (int) ( $data['barber_id'] ?? 0 );

        if ( ! isset( self::SERVICES[ $service ] ) ) {
            return new WP_Error( 'invalid_service', __( 'Servicio inválido.', 'six40-booking' ) );
        }

        $slots_needed = self::SERVICES[ $service ]['slots'];

        if ( $barber_id && isset( self::BARBERS[ $barber_id ] )
            && self::BARBERS[ $barber_id ]['location'] === $location ) {
            $assigned = $barber_id;
        } else {
            $assigned = $this->find_free_barber( $location, $date, $time, $slots_needed );
        }

        if ( ! $assigned ) {
            return new WP_Error( 'no_barber', __( 'No hay barberos disponibles en ese horario.', 'six40-booking' ) );
        }

        $duration = self::SERVICES[ $service ]['duration'];
        $end_dt   = \DateTime::createFromFormat( 'H:i', $time );
        $end_dt->modify( "+{$duration} minutes" );

        $row = [
            'location'       => $location,
            'service'        => $service,
            'date'           => $date,
            'time'           => $time,
            'end_time'       => $end_dt->format( 'H:i' ),
            'barber_id'      => $assigned,
            'customer_name'  => $data['name'],
            'customer_email' => $data['email'],
            'status'         => 'confirmed',
            'created_at'     => current_time( 'c' ),
        ];

        $result = $this->supabase_request( 'POST', 'appointments', $row );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $appointment = is_array( $result ) && isset( $result[0] ) ? $result[0] : $result;
        $appointment['barber_name']   = self::BARBERS[ $assigned ]['name'] ?? '';
        $appointment['service_label'] = self::SERVICES[ $service ]['label'] ?? $service;

        return $appointment;
    }

    /**
     * @return array|WP_Error
     */
    public function get_appointments( $filters = [] ) {
        $query = [ 'select' => '*', 'order' => 'date.asc,time.asc' ];
        foreach ( $filters as $col => $val ) {
            $query[ $col ] = 'eq.' . $val;
        }
        return $this->supabase_request( 'GET', 'appointments', [], $query );
    }

    /**
     * @return array|WP_Error
     */
    public function update_appointment_status( $id, $status ) {
        return $this->supabase_request( 'PATCH', 'appointments?id=eq.' . $id, [ 'status' => $status ] );
    }

    public function get_barber_days_off( $barber_id, $year_month ) {
        $from   = $year_month . '-01';
        $to     = $year_month . '-' . date( 't', strtotime( $from ) );
        $result = $this->supabase_request( 'GET',
            'barber_days_off?barber_id=eq.' . intval( $barber_id ) .
            '&date=gte.' . $from . '&date=lte.' . $to . '&select=date,note'
        );
        if ( is_wp_error( $result ) || ! is_array( $result ) ) return [];
        return $result;
    }

    public function toggle_barber_day_off( $barber_id, $date, $note = '' ) {
        $existing = $this->supabase_request( 'GET',
            'barber_days_off?barber_id=eq.' . intval( $barber_id ) . '&date=eq.' . $date . '&select=id'
        );
        if ( is_wp_error( $existing ) ) return $existing;

        if ( ! empty( $existing ) ) {
            return $this->supabase_request( 'DELETE',
                'barber_days_off?barber_id=eq.' . intval( $barber_id ) . '&date=eq.' . $date
            );
        }

        return $this->supabase_request( 'POST', 'barber_days_off', [
            'barber_id' => intval( $barber_id ),
            'date'      => $date,
            'note'      => $note,
        ] );
    }

    public function get_barber_statuses( $location = '' ) {
        $statuses = (array) get_option( 'six40_barber_statuses', [] );
        $result   = [];

        foreach ( self::BARBERS as $b ) {
            if ( $location && $b['location'] !== $location ) continue;
            $result[ $b['id'] ] = $statuses[ $b['id'] ] ?? 'available';
        }

        return $result;
    }

    public function update_barber_status( $barber_id, $status ) {
        $allowed = [ 'available', 'vacation', 'sick' ];
        if ( ! in_array( $status, $allowed, true ) || ! isset( self::BARBERS[ $barber_id ] ) ) {
            return false;
        }
        $statuses = (array) get_option( 'six40_barber_statuses', [] );
        $statuses[ $barber_id ] = $status;
        return (bool) update_option( 'six40_barber_statuses', $statuses );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function get_barber_ids_off_on_date( $date ) {
        $result = $this->supabase_request( 'GET',
            'barber_days_off?date=eq.' . $date . '&select=barber_id'
        );
        if ( is_wp_error( $result ) || ! is_array( $result ) ) return [];
        return array_map( 'intval', array_column( $result, 'barber_id' ) );
    }

    private function generate_day_slots() {
        $slots = [];
        $dt    = \DateTime::createFromFormat( 'H:i', sprintf( '%02d:00', self::OPEN_HOUR ) );
        $end   = \DateTime::createFromFormat( 'H:i', sprintf( '%02d:00', self::CLOSE_HOUR ) );
        while ( $dt < $end ) {
            $slots[] = $dt->format( 'H:i' );
            $dt->modify( '+' . self::SLOT_MINS . ' minutes' );
        }
        return $slots;
    }

    private function build_occupied_map( $appointments ) {
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
        return $occupied;
    }

    private function find_free_barber( $location, $date, $time, $slots_needed ) {
        $barber_statuses    = $this->get_barber_statuses( $location );
        $barbers_on_day_off = $this->get_barber_ids_off_on_date( $date );
        $available = array_filter(
            self::BARBERS,
            function( $b ) use ( $location, $barber_statuses, $barbers_on_day_off ) {
                return $b['location'] === $location
                    && ( $barber_statuses[ $b['id'] ] ?? 'available' ) === 'available'
                    && ! in_array( $b['id'], $barbers_on_day_off, true );
            }
        );

        if ( empty( $available ) ) return null;

        $appointments = $this->supabase_request( 'GET', 'appointments', [], [
            'location' => 'eq.' . $location,
            'date'     => 'eq.' . $date,
            'status'   => 'neq.cancelled',
            'select'   => 'barber_id,time,service',
        ] );

        if ( is_wp_error( $appointments ) ) return null;

        $occupied       = $this->build_occupied_map( $appointments );
        $slots_to_check = [];
        $dt             = \DateTime::createFromFormat( 'H:i', $time );

        for ( $s = 0; $s < $slots_needed; $s++ ) {
            $slots_to_check[] = $dt->format( 'H:i' );
            $dt->modify( '+' . self::SLOT_MINS . ' minutes' );
        }

        foreach ( $available as $barber ) {
            $bid  = $barber['id'];
            $free = true;
            foreach ( $slots_to_check as $check ) {
                if ( isset( $occupied[ $bid ][ $check ] ) ) {
                    $free = false;
                    break;
                }
            }
            if ( $free ) return $bid;
        }

        return null;
    }
}
