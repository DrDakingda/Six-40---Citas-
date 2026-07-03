<?php
defined( 'ABSPATH' ) || exit;

/**
 * Six40 Booking API — Handles all appointment logic with Supabase.
 * Supports flexible service combinations and per-barber schedules.
 */
class Six40_Booking_API {

	const SLOT_MINS = 30; // Reservation slot size in minutes

	private function settings() {
		return (array) get_option( 'six40_settings', [] );
	}

	// ── Supabase REST API ──────────────────────────────────────────────────────

	/**
	 * Make a REST request to Supabase.
	 *
	 * @return array|WP_Error
	 */
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

	// ── Public: Services ──────────────────────────────────────────────────────

	/**
	 * Get all active services for a location (grouped selection = "menú libre").
	 * Includes services with location = X and location = NULL (comunes a ambos).
	 *
	 * @param string|null $location 'malaga' | 'torremolinos' | null (todos)
	 * @return array|WP_Error
	 */
	public function get_services( $location = null ) {
		$query = [ 'select' => '*', 'active' => 'eq.true', 'order' => 'display_order.asc' ];
		if ( $location ) {
			// location = X  OR  location IS NULL
			$query['or'] = '(location.eq.' . $location . ',location.is.null)';
		}
		return $this->supabase_request( 'GET', 'services', [], $query );
	}

	/**
	 * Get a single service by ID.
	 *
	 * @return array|WP_Error|null
	 */
	public function get_service( $id ) {
		$result = $this->supabase_request( 'GET', 'services', [], [ 'id' => 'eq.' . intval( $id ) ] );
		if ( is_wp_error( $result ) || empty( $result ) ) {
			return null;
		}
		return isset( $result[0] ) ? $result[0] : $result;
	}

	// ── Public: Barbers ───────────────────────────────────────────────────────

	/**
	 * Get all barbers for a location.
	 *
	 * @return array|WP_Error
	 */
	public function get_barbers( $location = null ) {
		$query = [ 'select' => '*', 'active' => 'eq.true', 'order' => 'id.asc' ];
		if ( $location ) {
			$query['location'] = 'eq.' . $location;
		}
		return $this->supabase_request( 'GET', 'barbers', [], $query );
	}

	/**
	 * Get a barber by ID.
	 *
	 * @return array|WP_Error|null
	 */
	public function get_barber( $id ) {
		$result = $this->supabase_request( 'GET', 'barbers', [], [ 'id' => 'eq.' . intval( $id ) ] );
		if ( is_wp_error( $result ) || empty( $result ) ) {
			return null;
		}
		return isset( $result[0] ) ? $result[0] : $result;
	}

	/**
	 * Get schedule for a barber on a specific day (day_of_week: 0=Mon, 6=Sun).
	 *
	 * @return array Sorted array of [start_time, end_time] pairs
	 */
	public function get_barber_schedule( $barber_id, $day_of_week ) {
		$result = $this->supabase_request( 'GET', 'barber_schedules', [], [
			'barber_id'    => 'eq.' . intval( $barber_id ),
			'day_of_week'  => 'eq.' . intval( $day_of_week ),
			'select'       => 'start_time,end_time',
			'order'        => 'start_time.asc',
		] );

		if ( is_wp_error( $result ) ) {
			return [];
		}

		return is_array( $result ) ? $result : [];
	}

	// ── Public: Availability ──────────────────────────────────────────────────

	/**
	 * Get available slots for a location, date, and service combination.
	 *
	 * @param string   $location           'malaga' or 'torremolinos'
	 * @param string   $date               'YYYY-MM-DD'
	 * @param int|array $base_service_id   Base service ID (or array of service IDs for duration calc)
	 * @return array|WP_Error Array of available times ['10:00', '10:30', ...]
	 */
	public function get_available_slots( $location, $date, $base_service_id, $only_barber_id = 0 ) {
		// Validate date
		if ( $date < wp_date( 'Y-m-d' ) ) {
			return [];
		}
		// Domingos y festivos: cerrado.
		if ( $this->is_closed_date( $date ) ) {
			return [];
		}

		// Get service(s) and calculate total duration
		$service_ids = is_array( $base_service_id ) ? $base_service_id : [ $base_service_id ];
		$total_duration = $this->calculate_service_duration( $service_ids );

		if ( $total_duration <= 0 ) {
			return new WP_Error( 'invalid_service', 'Invalid service(s).' );
		}

		$slots_needed = ceil( $total_duration / self::SLOT_MINS );

		// Get barbers for location
		$barbers = $this->get_barbers( $location );
		if ( is_wp_error( $barbers ) || empty( $barbers ) ) {
			return [];
		}

		// Get day of week for the date
		$dt_date = \DateTime::createFromFormat( 'Y-m-d', $date );
		$day_of_week = ( (int) $dt_date->format( 'N' ) ) - 1; // 0=Lun … 6=Dom (coincide con barber_schedules)

		// Get barbers off on this date
		$barbers_off = $this->get_barber_ids_off_on_date( $date );

		// Estados (available / vacation / sick)
		$statuses = $this->get_barber_statuses( $location );

		// Get appointments on this date
		$appointments = $this->supabase_request( 'GET', 'appointments', [], [
			'location' => 'eq.' . $location,
			'date'     => 'eq.' . $date,
			'status'   => 'neq.cancelled',
			'select'   => 'id,barber_id,start_time,end_time',
		] );

		if ( is_wp_error( $appointments ) ) {
			return $appointments;
		}

		$occupied = $this->build_occupied_map( $appointments ?? [] );
		$this->merge_google_busy( $occupied, $barbers, $date );
		$available_slots = [];

		// For each barber, try to find free slots
		foreach ( $barbers as $barber ) {
			// Si se pide un barbero concreto, ignorar los demás.
			if ( $only_barber_id && (int) $barber['id'] !== (int) $only_barber_id ) {
				continue;
			}
			if ( in_array( $barber['id'], $barbers_off, true ) ) {
				continue;
			}
			// Saltar barberos de vacaciones o de baja.
			if ( ( $statuses[ $barber['id'] ] ?? 'available' ) !== 'available' ) {
				continue;
			}

			// Get barber's schedule for this day
			$schedule = $this->get_barber_schedule( $barber['id'], $day_of_week );
			if ( empty( $schedule ) ) {
				continue; // Barber doesn't work this day
			}

			// Generate slots for each time window
			foreach ( $schedule as $window ) {
				$slots = $this->generate_slots_in_window(
					$window['start_time'],
					$window['end_time'],
					$total_duration
				);

				foreach ( $slots as $slot ) {
					// Check if barber is free for all slots
					$free = true;
					$dt_check = \DateTime::createFromFormat( 'H:i', $slot );

					for ( $i = 0; $i < $slots_needed; $i++ ) {
						$check_time = $dt_check->format( 'H:i' );
						if ( isset( $occupied[ $barber['id'] ][ $check_time ] ) ) {
							$free = false;
							break;
						}
						$dt_check->modify( '+' . self::SLOT_MINS . ' minutes' );
					}

					if ( $free ) {
						$available_slots[] = $slot;
					}
				}
			}
		}

		// Remove duplicates and sort
		$available_slots = array_unique( $available_slots );
		sort( $available_slots );

		// Filter out times in the past (for today)
		$today = wp_date( 'Y-m-d' );
		if ( $date === $today ) {
			$cutoff = ( new \DateTime( 'now' ) )->modify( '+30 minutes' )->format( 'H:i' );
			$available_slots = array_values( array_filter( $available_slots, function( $s ) use ( $cutoff ) {
				return $s >= $cutoff;
			} ) );
		}

		return array_values( $available_slots );
	}

	// ── Public: Appointments ──────────────────────────────────────────────────

	/**
	 * Create a new appointment.
	 *
	 * @param array $data {
	 *   'location'             => 'malaga' or 'torremolinos',
	 *   'date'                 => 'YYYY-MM-DD',
	 *   'start_time'           => 'HH:MM',
	 *   'base_service_id'      => int,
	 *   'additional_service_ids' => array of ints (optional),
	 *   'barber_id'            => int (optional, auto-assigned if not provided),
	 *   'customer_name'        => string,
	 *   'customer_email'       => string,
	 *   'customer_phone'       => string (optional),
	 * }
	 * @return array|WP_Error The created appointment with services
	 */
	public function create_appointment( $data ) {
		$location = $data['location'] ?? '';
		$date = $data['date'] ?? '';
		$start_time = $data['start_time'] ?? '';
		$barber_id = (int) ( $data['barber_id'] ?? 0 );

		// Servicios (menú libre): admite 'service_ids' o base+additional (compat).
		if ( ! empty( $data['service_ids'] ) ) {
			$service_ids = array_map( 'intval', (array) $data['service_ids'] );
		} else {
			$base = (int) ( $data['base_service_id'] ?? 0 );
			$add  = array_map( 'intval', (array) ( $data['additional_service_ids'] ?? [] ) );
			$service_ids = array_merge( [ $base ], $add );
		}
		$service_ids = array_values( array_unique( array_filter( $service_ids ) ) );

		// Validate location
		if ( ! in_array( $location, [ 'malaga', 'torremolinos' ], true ) ) {
			return new WP_Error( 'invalid_location', 'Invalid location.' );
		}

		// Domingos y festivos: cerrado.
		if ( $this->is_closed_date( $date ) ) {
			return new WP_Error( 'closed_date', 'Ese día no está disponible para reservas.' );
		}

		// Validate: al menos un servicio existente
		if ( empty( $service_ids ) ) {
			return new WP_Error( 'invalid_service', 'Selecciona al menos un servicio.' );
		}
		$valid_services = [];
		foreach ( $service_ids as $sid ) {
			$svc = $this->get_service( $sid );
			if ( $svc ) {
				$valid_services[] = $svc;
			}
		}
		if ( empty( $valid_services ) ) {
			return new WP_Error( 'invalid_service', 'Servicios no válidos.' );
		}
		// Solo IDs realmente válidos
		$service_ids = array_map( function ( $s ) { return (int) $s['id']; }, $valid_services );

		// Calculate total duration
		$total_duration = $this->calculate_service_duration( $service_ids );
		$end_time = $this->calculate_end_time( $start_time, $total_duration );

		// Auto-assign barber if not provided
		if ( ! $barber_id ) {
			$barber_id = $this->find_free_barber( $location, $date, $start_time, $service_ids );
			if ( ! $barber_id ) {
				return new WP_Error( 'no_barber', 'No barbers available at this time.' );
			}
		}

		// Create appointment
		$appt_data = [
			'location'        => $location,
			'date'            => $date,
			'start_time'      => $start_time,
			'end_time'        => $end_time,
			'barber_id'       => $barber_id,
			'customer_name'   => $data['customer_name'] ?? '',
			'customer_email'  => $data['customer_email'] ?? '',
			'customer_phone'  => $data['customer_phone'] ?? '',
			'status'          => 'confirmed',
		];

		$result = $this->supabase_request( 'POST', 'appointments', $appt_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$appointment = isset( $result[0] ) ? $result[0] : $result;
		$appt_id = $appointment['id'] ?? null;

		if ( ! $appt_id ) {
			return new WP_Error( 'no_id', 'Failed to create appointment.' );
		}

		// Insert appointment services
		foreach ( $service_ids as $idx => $svc_id ) {
			$svc_row = [
				'appointment_id' => $appt_id,
				'service_id'     => $svc_id,
				'sequence'       => $idx,
			];
			$this->supabase_request( 'POST', 'appointment_services', $svc_row );
		}

		// Enrich response with barber and service info
		$barber = $this->get_barber( $barber_id );
		$appointment['barber'] = $barber ?: [ 'name' => 'Unknown' ];

		$appointment['services'] = $valid_services;
		$appointment['duration'] = $total_duration;

		return $appointment;
	}

	/**
	 * Get appointments with optional filters.
	 *
	 * @param array $filters e.g., ['location' => 'malaga', 'date' => '2026-06-15']
	 * @return array|WP_Error
	 */
	public function get_appointments( $filters = [] ) {
		// Traemos los servicios embebidos (tabla puente appointment_services → services).
		$query = [
			'select' => '*,appointment_services(sequence,services(name))',
			'order'  => 'date.asc,start_time.asc',
		];
		foreach ( $filters as $col => $val ) {
			$query[ $col ] = 'eq.' . $val;
		}
		$rows = $this->supabase_request( 'GET', 'appointments', [], $query );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		foreach ( $rows as &$row ) {
			$row['services_label'] = $this->build_services_label( $row );
		}
		unset( $row );
		return $rows;
	}

	/**
	 * Construye "Servicio1 + Servicio2" a partir de los servicios embebidos.
	 *
	 * @param array $appointment
	 * @return string
	 */
	private function build_services_label( $appointment ) {
		$items = $appointment['appointment_services'] ?? [];
		if ( ! is_array( $items ) || empty( $items ) ) {
			return '';
		}
		usort( $items, function ( $a, $b ) {
			return ( (int) ( $a['sequence'] ?? 0 ) ) - ( (int) ( $b['sequence'] ?? 0 ) );
		} );
		$names = [];
		foreach ( $items as $it ) {
			$name = $it['services']['name'] ?? '';
			if ( $name ) {
				$names[] = $name;
			}
		}
		return implode( ' + ', $names );
	}

	/**
	 * Update appointment status.
	 *
	 * @return array|WP_Error
	 */
	public function update_appointment_status( $id, $status ) {
		$allowed = [ 'confirmed', 'completed', 'cancelled', 'no_show' ];
		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_Error( 'invalid_status', 'Invalid status.' );
		}
		return $this->supabase_request( 'PATCH', 'appointments?id=eq.' . intval( $id ), [ 'status' => $status ] );
	}

	// ── Public: Barber Days Off ───────────────────────────────────────────────

	/**
	 * Get days off for a barber in a given month.
	 *
	 * @param int    $barber_id Barber ID
	 * @param string $year_month 'YYYY-MM'
	 * @return array|WP_Error
	 */
	public function get_barber_days_off( $barber_id, $year_month ) {
		$from = $year_month . '-01';
		$to = $year_month . '-' . date( 't', strtotime( $from ) );
		return $this->supabase_request( 'GET', 'barber_days_off', [], [
			'barber_id' => 'eq.' . intval( $barber_id ),
			'date' => 'gte.' . $from,
			'date' => 'lte.' . $to,
			'select' => 'id,date,note',
			'order' => 'date.asc',
		] );
	}

	/**
	 * Toggle a day off for a barber (create or delete).
	 *
	 * @return array|WP_Error
	 */
	public function toggle_barber_day_off( $barber_id, $date, $note = '' ) {
		$existing = $this->supabase_request( 'GET', 'barber_days_off', [], [
			'barber_id' => 'eq.' . intval( $barber_id ),
			'date'      => 'eq.' . $date,
			'select'    => 'id',
		] );

		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		if ( ! empty( $existing ) ) {
			// Delete
			return $this->supabase_request( 'DELETE', 'barber_days_off', [], [
				'barber_id' => 'eq.' . intval( $barber_id ),
				'date'      => 'eq.' . $date,
			] );
		}

		// Create
		return $this->supabase_request( 'POST', 'barber_days_off', [
			'barber_id' => intval( $barber_id ),
			'date'      => $date,
			'note'      => $note,
		] );
	}

	// ── Public: Barber Status ─────────────────────────────────────────────────

	/**
	 * Get barber status (available, vacation, sick).
	 *
	 * @return array Key: barber_id, Value: status
	 */
	public function get_barber_statuses( $location = '' ) {
		$statuses = (array) get_option( 'six40_barber_statuses', [] );
		$barbers = $this->get_barbers( $location );

		if ( is_wp_error( $barbers ) ) {
			return $statuses;
		}

		$result = [];
		foreach ( $barbers as $b ) {
			$result[ $b['id'] ] = $statuses[ $b['id'] ] ?? 'available';
		}

		return $result;
	}

	/**
	 * Update barber status.
	 *
	 * @param int    $barber_id
	 * @param string $status 'available', 'vacation', or 'sick'
	 * @return bool
	 */
	public function update_barber_status( $barber_id, $status ) {
		$allowed = [ 'available', 'vacation', 'sick' ];
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		$statuses = (array) get_option( 'six40_barber_statuses', [] );
		$statuses[ $barber_id ] = $status;

		return (bool) update_option( 'six40_barber_statuses', $statuses );
	}

	// ── Private: Helpers ──────────────────────────────────────────────────────

	/**
	 * ¿Está cerrado ese día? (domingo o festivo configurado en el admin).
	 *
	 * @param string $date 'YYYY-MM-DD'
	 * @return bool
	 */
	private function is_closed_date( $date ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
			return true;
		}
		$dt = \DateTime::createFromFormat( 'Y-m-d', $date );
		if ( ! $dt ) {
			return true;
		}
		// Domingo cerrado (N: 1=lun … 7=dom).
		if ( (int) $dt->format( 'N' ) === 7 ) {
			return true;
		}
		$holidays = (array) get_option( 'six40_holidays', [] );
		return in_array( $date, $holidays, true );
	}

	/**
	 * Calculate total duration for a set of services.
	 *
	 * @param array $service_ids Service IDs (first = base, rest = additional)
	 * @return int Duration in minutes
	 */
	private function calculate_service_duration( $service_ids ) {
		if ( empty( $service_ids ) ) {
			return 0;
		}

		$total = 0;
		foreach ( $service_ids as $svc_id ) {
			$service = $this->get_service( $svc_id );
			if ( $service ) {
				$total += (int) $service['duration'];
			}
		}

		return $total;
	}

	/**
	 * Calculate end time given start time and duration.
	 *
	 * @param string $start_time 'HH:MM'
	 * @param int    $duration_mins Minutes
	 * @return string End time as 'HH:MM'
	 */
	private function calculate_end_time( $start_time, $duration_mins ) {
		$dt = \DateTime::createFromFormat( 'H:i', $start_time );
		if ( ! $dt ) {
			return $start_time;
		}
		$dt->modify( "+{$duration_mins} minutes" );
		return $dt->format( 'H:i' );
	}

	/**
	 * Get barber IDs that are off on a specific date.
	 *
	 * @param string $date 'YYYY-MM-DD'
	 * @return array Barber IDs
	 */
	private function get_barber_ids_off_on_date( $date ) {
		$result = $this->supabase_request( 'GET', 'barber_days_off', [], [
			'date'   => 'eq.' . $date,
			'select' => 'barber_id',
		] );

		if ( is_wp_error( $result ) ) {
			return [];
		}

		return array_map( 'intval', array_column( (array) $result, 'barber_id' ) );
	}

	/**
	 * Generate all 30-min slots within a time window that fit a duration.
	 *
	 * @param string $start_time 'HH:MM'
	 * @param string $end_time   'HH:MM'
	 * @param int    $duration_mins Service duration
	 * @return array Available start times
	 */
	private function generate_slots_in_window( $start_time, $end_time, $duration_mins ) {
		$slots = [];
		// Las horas de Supabase pueden venir como 'HH:MM' o 'HH:MM:SS' → normalizar.
		$dt = \DateTime::createFromFormat( 'H:i', substr( $start_time, 0, 5 ) );
		$end_dt = \DateTime::createFromFormat( 'H:i', substr( $end_time, 0, 5 ) );
		if ( ! $dt || ! $end_dt ) {
			return [];
		}
		$slots_needed = ceil( $duration_mins / self::SLOT_MINS );

		while ( $dt < $end_dt ) {
			$check_dt = clone $dt;
			$fits = true;

			for ( $i = 0; $i < $slots_needed; $i++ ) {
				if ( $check_dt >= $end_dt ) {
					$fits = false;
					break;
				}
				$check_dt->modify( '+' . self::SLOT_MINS . ' minutes' );
			}

			if ( $fits ) {
				$slots[] = $dt->format( 'H:i' );
			}

			$dt->modify( '+' . self::SLOT_MINS . ' minutes' );
		}

		return $slots;
	}

	/**
	 * Build an occupied map: [barber_id][time] => true.
	 *
	 * @param array $appointments
	 * @return array
	 */
	private function build_occupied_map( $appointments ) {
		$occupied = [];

		foreach ( $appointments as $appt ) {
			$bid = (int) $appt['barber_id'];
			$start = $appt['start_time'];
			$end = $appt['end_time'];

			$dt = \DateTime::createFromFormat( 'H:i', substr( $start, 0, 5 ) );
			$end_dt = \DateTime::createFromFormat( 'H:i', substr( $end, 0, 5 ) );
			if ( ! $dt || ! $end_dt ) {
				continue;
			}

			while ( $dt <= $end_dt ) {
				$occupied[ $bid ][ $dt->format( 'H:i' ) ] = true;
				$dt->modify( '+' . self::SLOT_MINS . ' minutes' );
				if ( $dt > $end_dt ) break;
			}
		}

		return $occupied;
	}

	/**
	 * Fusiona en el mapa de ocupación los eventos "busy" del Google Calendar de
	 * cada barbero (si están configurados y hay conexión con Google). Fail-safe:
	 * si no hay calendarios o Google no responde, no cambia nada.
	 *
	 * @param array  &$occupied  Mapa [barber_id][H:i] => true (por referencia)
	 * @param array   $barbers   Barberos considerados
	 * @param string  $date      'YYYY-MM-DD'
	 */
	private function merge_google_busy( &$occupied, $barbers, $date ) {
		$map = (array) get_option( 'six40_barber_calendars', [] );
		if ( empty( $map ) ) {
			return;
		}

		$calendar_ids  = [];
		$cal_to_barber = [];
		foreach ( (array) $barbers as $b ) {
			$bid = (int) ( $b['id'] ?? 0 );
			$cid = trim( (string) ( $map[ $bid ] ?? '' ) );
			if ( $bid && $cid !== '' ) {
				$calendar_ids[]        = $cid;
				$cal_to_barber[ $cid ] = $bid;
			}
		}
		if ( empty( $calendar_ids ) ) {
			return;
		}

		$busy = ( new Six40_Google_Calendar() )->get_busy( $calendar_ids, $date );
		if ( ! is_array( $busy ) || empty( $busy ) ) {
			return;
		}

		foreach ( $busy as $cid => $intervals ) {
			$bid = $cal_to_barber[ $cid ] ?? 0;
			if ( ! $bid ) {
				continue;
			}
			foreach ( (array) $intervals as $iv ) {
				$this->mark_busy_slots( $occupied, $bid, $iv[0] ?? '', $iv[1] ?? '' );
			}
		}
	}

	/**
	 * Marca como ocupados los slots de 30 min que se solapan con un intervalo.
	 */
	private function mark_busy_slots( &$occupied, $bid, $start, $end ) {
		$dt     = \DateTime::createFromFormat( 'H:i', substr( (string) $start, 0, 5 ) );
		$end_dt = \DateTime::createFromFormat( 'H:i', substr( (string) $end, 0, 5 ) );
		if ( ! $dt || ! $end_dt || $dt >= $end_dt ) {
			return;
		}
		// Alinear el inicio al slot de 30 min inferior.
		$mins = (int) $dt->format( 'i' ) % self::SLOT_MINS;
		if ( $mins ) {
			$dt->modify( '-' . $mins . ' minutes' );
		}
		while ( $dt < $end_dt ) {
			$occupied[ $bid ][ $dt->format( 'H:i' ) ] = true;
			$dt->modify( '+' . self::SLOT_MINS . ' minutes' );
		}
	}

	/**
	 * Find an available barber for a time slot.
	 *
	 * @param string $location
	 * @param string $date 'YYYY-MM-DD'
	 * @param string $start_time 'HH:MM'
	 * @param array  $service_ids Service IDs to calculate duration
	 * @return int|null Barber ID or null
	 */
	private function find_free_barber( $location, $date, $start_time, $service_ids ) {
		$total_duration = $this->calculate_service_duration( $service_ids );
		$slots_needed = ceil( $total_duration / self::SLOT_MINS );

		// Get barbers
		$barbers = $this->get_barbers( $location );
		if ( is_wp_error( $barbers ) || empty( $barbers ) ) {
			return null;
		}

		// Get day of week
		$dt_date = \DateTime::createFromFormat( 'Y-m-d', $date );
		$day_of_week = ( (int) $dt_date->format( 'N' ) ) - 1; // 0=Lun … 6=Dom (coincide con barber_schedules)

		// Get barbers off
		$barbers_off = $this->get_barber_ids_off_on_date( $date );

		// Get statuses
		$statuses = $this->get_barber_statuses( $location );

		// Get appointments
		$appointments = $this->supabase_request( 'GET', 'appointments', [], [
			'location' => 'eq.' . $location,
			'date'     => 'eq.' . $date,
			'status'   => 'neq.cancelled',
			'select'   => 'id,barber_id,start_time,end_time',
		] );

		if ( is_wp_error( $appointments ) ) {
			return null;
		}

		$occupied = $this->build_occupied_map( $appointments ?? [] );
		$this->merge_google_busy( $occupied, $barbers, $date );

		// Try each barber
		foreach ( $barbers as $barber ) {
			if ( in_array( $barber['id'], $barbers_off, true ) ) {
				continue;
			}

			if ( ( $statuses[ $barber['id'] ] ?? 'available' ) !== 'available' ) {
				continue;
			}

			// Check schedule
			$schedule = $this->get_barber_schedule( $barber['id'], $day_of_week );
			if ( empty( $schedule ) ) {
				continue;
			}

			// Check if free
			$bid = $barber['id'];
			$free = true;
			$dt = \DateTime::createFromFormat( 'H:i', $start_time );

			for ( $i = 0; $i < $slots_needed; $i++ ) {
				$check = $dt->format( 'H:i' );
				if ( isset( $occupied[ $bid ][ $check ] ) ) {
					$free = false;
					break;
				}
				$dt->modify( '+' . self::SLOT_MINS . ' minutes' );
			}

			if ( $free ) {
				return $barber['id'];
			}
		}

		return null;
	}
}
