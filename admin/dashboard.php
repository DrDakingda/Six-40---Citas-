<?php
defined( 'ABSPATH' ) || exit;

$page = sanitize_text_field( $_GET['page'] ?? 'six40-dashboard' );
?>
<div class="wrap six40-admin">
  <div class="six40-admin-header">
    <div class="six40-logo">
      <span class="six40-logo-text">SIX40</span>
      <span class="six40-logo-sub">BARBERÍA · Sistema de citas</span>
    </div>
    <nav class="six40-nav">
      <a href="<?= esc_url( admin_url( 'admin.php?page=six40-dashboard' ) ) ?>" class="<?= $page === 'six40-dashboard' ? 'active' : '' ?>">
        <span class="dashicons dashicons-dashboard"></span> Dashboard
      </a>
      <a href="<?= esc_url( admin_url( 'admin.php?page=six40-citas' ) ) ?>" class="<?= $page === 'six40-citas' ? 'active' : '' ?>">
        <span class="dashicons dashicons-calendar-alt"></span> Citas
      </a>
      <a href="<?= esc_url( admin_url( 'admin.php?page=six40-barberos' ) ) ?>" class="<?= $page === 'six40-barberos' ? 'active' : '' ?>">
        <span class="dashicons dashicons-groups"></span> Barberos
      </a>
      <a href="<?= esc_url( admin_url( 'admin.php?page=six40-settings' ) ) ?>" class="<?= $page === 'six40-settings' ? 'active' : '' ?>">
        <span class="dashicons dashicons-admin-settings"></span> Configuración
      </a>
    </nav>
  </div>

  <div class="six40-admin-content">

    <?php
    switch ( $page ) {
        // ─────────────────────────────────────────────────────── DASHBOARD ──
        case 'six40-dashboard':
        default:
            $today_label = date_i18n( 'l, j \d\e F \d\e Y' );
            ?>
            <h1 class="six40-page-title">Dashboard</h1>
            <p class="six40-page-subtitle"><?= esc_html( $today_label ) ?></p>

            <!-- Stats cards -->
            <div class="six40-stats-grid">
              <div class="six40-stat-card">
                <div class="six40-stat-icon six40-icon-malaga">📍</div>
                <div class="six40-stat-body">
                  <div class="six40-stat-number"><?= esc_html( $malaga_count ?? 0 ) ?></div>
                  <div class="six40-stat-label">Citas hoy · Málaga</div>
                </div>
              </div>
              <div class="six40-stat-card">
                <div class="six40-stat-icon six40-icon-torremolinos">📍</div>
                <div class="six40-stat-body">
                  <div class="six40-stat-number"><?= esc_html( $torremolinos_count ?? 0 ) ?></div>
                  <div class="six40-stat-label">Citas hoy · Torremolinos</div>
                </div>
              </div>
              <div class="six40-stat-card">
                <div class="six40-stat-icon">✂️</div>
                <div class="six40-stat-body">
                  <div class="six40-stat-number"><?= esc_html( count( $today_appts ?? [] ) ) ?></div>
                  <div class="six40-stat-label">Total citas hoy</div>
                </div>
              </div>
              <div class="six40-stat-card">
                <div class="six40-stat-icon">💈</div>
                <div class="six40-stat-body">
                  <?php
                  $available_count = isset( $statuses )
                    ? count( array_filter( $statuses, fn( $s ) => $s === 'available' ) )
                    : 0;
                  ?>
                  <div class="six40-stat-number"><?= esc_html( $available_count ) ?>/8</div>
                  <div class="six40-stat-label">Barberos disponibles</div>
                </div>
              </div>
            </div>

            <!-- Today's appointments -->
            <div class="six40-panel">
              <div class="six40-panel-header">
                <h2>Citas de hoy</h2>
                <a href="<?= esc_url( admin_url( 'admin.php?page=six40-citas' ) ) ?>" class="six40-btn six40-btn-sm">Ver todas</a>
              </div>
              <?php if ( empty( $today_appts ) ) : ?>
                <div class="six40-empty">No hay citas para hoy.</div>
              <?php else : ?>
                <table class="six40-table">
                  <thead>
                    <tr>
                      <th>Hora</th>
                      <th>Cliente</th>
                      <th>Servicio</th>
                      <th>Local</th>
                      <th>Barbero</th>
                      <th>Estado</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ( $today_appts as $appt ) :
                        $barbers_map  = Six40_Booking_API::BARBERS;
                        $barber_name  = $barbers_map[ (int) ( $appt['barber_id'] ?? 0 ) ]['name'] ?? '—';
                        $svc_labels   = [ 'barba' => 'Barba', 'corte' => 'Corte', 'corte_barba' => 'Corte + Barba' ];
                        $svc_label    = $svc_labels[ $appt['service'] ?? '' ] ?? ( $appt['service'] ?? '—' );
                        $loc_label    = $appt['location'] === 'malaga' ? 'Málaga' : 'Torremolinos';
                        $status       = $appt['status'] ?? 'confirmed';
                    ?>
                    <tr data-id="<?= esc_attr( $appt['id'] ?? '' ) ?>">
                      <td><strong><?= esc_html( substr( $appt['time'] ?? '', 0, 5 ) ) ?></strong></td>
                      <td>
                        <span class="six40-customer-name"><?= esc_html( $appt['customer_name'] ?? '—' ) ?></span>
                        <small class="six40-customer-email"><?= esc_html( $appt['customer_email'] ?? '' ) ?></small>
                      </td>
                      <td><?= esc_html( $svc_label ) ?></td>
                      <td><?= esc_html( $loc_label ) ?></td>
                      <td><?= esc_html( $barber_name ) ?></td>
                      <td><span class="six40-status six40-status--<?= esc_attr( $status ) ?>"><?= esc_html( ucfirst( $status ) ) ?></span></td>
                      <td class="six40-actions">
                        <select class="six40-status-select" data-id="<?= esc_attr( $appt['id'] ?? '' ) ?>">
                          <option value="confirmed"  <?= selected( $status, 'confirmed',  false ) ?>>Confirmada</option>
                          <option value="completed"  <?= selected( $status, 'completed',  false ) ?>>Completada</option>
                          <option value="cancelled"  <?= selected( $status, 'cancelled',  false ) ?>>Cancelada</option>
                          <option value="no_show"    <?= selected( $status, 'no_show',    false ) ?>>No presentó</option>
                        </select>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>

            <!-- Calendar -->
            <div class="six40-panel">
              <div class="six40-panel-header">
                <h2>Calendario de citas</h2>
                <div class="six40-calendar-filters">
                  <select id="six40-calendar-location">
                    <option value="">Todos los locales</option>
                    <option value="malaga">Málaga</option>
                    <option value="torremolinos">Torremolinos</option>
                  </select>
                </div>
              </div>
              <div id="six40-calendar"></div>
            </div>
            <?php
            break;

        // ────────────────────────────────────────────────────────── CITAS ──
        case 'six40-citas':
            $appointments_list = $appointments ?? [];
            ?>
            <h1 class="six40-page-title">Historial de Citas</h1>

            <!-- Filters -->
            <div class="six40-filters-bar">
              <form method="GET" action="">
                <input type="hidden" name="page" value="six40-citas">
                <select name="location">
                  <option value="">Todos los locales</option>
                  <option value="malaga"       <?= selected( $location ?? '', 'malaga', false ) ?>>Málaga</option>
                  <option value="torremolinos" <?= selected( $location ?? '', 'torremolinos', false ) ?>>Torremolinos</option>
                </select>
                <input type="date" name="date_from" value="<?= esc_attr( $date_from ?? '' ) ?>" placeholder="Desde">
                <select name="status">
                  <option value="">Todos los estados</option>
                  <option value="confirmed"  <?= selected( $status_f ?? '', 'confirmed', false ) ?>>Confirmadas</option>
                  <option value="completed"  <?= selected( $status_f ?? '', 'completed', false ) ?>>Completadas</option>
                  <option value="cancelled"  <?= selected( $status_f ?? '', 'cancelled', false ) ?>>Canceladas</option>
                  <option value="no_show"    <?= selected( $status_f ?? '', 'no_show',   false ) ?>>No presentaron</option>
                </select>
                <button type="submit" class="six40-btn">Filtrar</button>
                <a href="<?= esc_url( admin_url( 'admin.php?page=six40-citas' ) ) ?>" class="six40-btn six40-btn-outline">Limpiar</a>
              </form>
            </div>

            <div class="six40-panel">
              <?php if ( empty( $appointments_list ) ) : ?>
                <div class="six40-empty">No se encontraron citas.</div>
              <?php else : ?>
                <table class="six40-table">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Fecha</th>
                      <th>Hora</th>
                      <th>Cliente</th>
                      <th>Servicio</th>
                      <th>Local</th>
                      <th>Barbero</th>
                      <th>Estado</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ( $appointments_list as $appt ) :
                        $barbers_map = Six40_Booking_API::BARBERS;
                        $barber_name = $barbers_map[ (int) ( $appt['barber_id'] ?? 0 ) ]['name'] ?? '—';
                        $svc_labels  = [ 'barba' => 'Barba', 'corte' => 'Corte', 'corte_barba' => 'Corte + Barba' ];
                        $svc_label   = $svc_labels[ $appt['service'] ?? '' ] ?? ( $appt['service'] ?? '—' );
                        $loc_label   = $appt['location'] === 'malaga' ? 'Málaga' : 'Torremolinos';
                        $status      = $appt['status'] ?? 'confirmed';
                        $date_fmt    = date_i18n( 'd/m/Y', strtotime( $appt['date'] ?? '' ) );
                    ?>
                    <tr>
                      <td><?= esc_html( $appt['id'] ?? '' ) ?></td>
                      <td><?= esc_html( $date_fmt ) ?></td>
                      <td><?= esc_html( substr( $appt['time'] ?? '', 0, 5 ) ) ?></td>
                      <td>
                        <?= esc_html( $appt['customer_name'] ?? '—' ) ?>
                        <small><?= esc_html( $appt['customer_email'] ?? '' ) ?></small>
                      </td>
                      <td><?= esc_html( $svc_label ) ?></td>
                      <td><?= esc_html( $loc_label ) ?></td>
                      <td><?= esc_html( $barber_name ) ?></td>
                      <td><span class="six40-status six40-status--<?= esc_attr( $status ) ?>"><?= esc_html( ucfirst( $status ) ) ?></span></td>
                      <td class="six40-actions">
                        <select class="six40-status-select" data-id="<?= esc_attr( $appt['id'] ?? '' ) ?>">
                          <option value="confirmed"  <?= selected( $status, 'confirmed',  false ) ?>>Confirmada</option>
                          <option value="completed"  <?= selected( $status, 'completed',  false ) ?>>Completada</option>
                          <option value="cancelled"  <?= selected( $status, 'cancelled',  false ) ?>>Cancelada</option>
                          <option value="no_show"    <?= selected( $status, 'no_show',    false ) ?>>No presentó</option>
                        </select>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>
            <?php
            break;

        // ─────────────────────────────────────────────────────── BARBEROS ──
        case 'six40-barberos':
            $barbers_list   = Six40_Booking_API::BARBERS;
            $statuses_list  = $statuses ?? ( new Six40_Booking_API() )->get_barber_statuses();
            $locations_list = [
                'malaga'       => 'Málaga',
                'torremolinos' => 'Torremolinos',
            ];
            ?>
            <h1 class="six40-page-title">Gestión de Barberos</h1>
            <p class="six40-page-subtitle">Actualiza la disponibilidad de cada barbero en tiempo real.</p>

            <?php foreach ( $locations_list as $loc_key => $loc_name ) : ?>
            <div class="six40-panel">
              <div class="six40-panel-header">
                <h2>📍 <?= esc_html( $loc_name ) ?></h2>
              </div>
              <div class="six40-barbers-grid">
                <?php foreach ( $barbers_list as $b ) :
                    if ( $b['location'] !== $loc_key ) continue;
                    $s = $statuses_list[ $b['id'] ] ?? 'available';
                ?>
                <div class="six40-barber-card six40-barber--<?= esc_attr( $s ) ?>" data-id="<?= esc_attr( $b['id'] ) ?>">
                  <div class="six40-barber-avatar">
                    <?= strtoupper( substr( $b['name'], 0, 1 ) ) ?>
                  </div>
                  <div class="six40-barber-info">
                    <strong><?= esc_html( $b['name'] ) ?></strong>
                    <span class="six40-status six40-status--<?= esc_attr( $s ) ?> six40-barber-status-label">
                      <?php
                      $status_labels = [ 'available' => 'Disponible', 'vacation' => 'Vacaciones', 'sick' => 'Baja' ];
                      echo esc_html( $status_labels[ $s ] ?? ucfirst( $s ) );
                      ?>
                    </span>
                  </div>
                  <div class="six40-barber-controls">
                    <button class="six40-barber-btn <?= $s === 'available' ? 'active' : '' ?>"
                            data-id="<?= esc_attr( $b['id'] ) ?>" data-status="available">
                      ✅ Disponible
                    </button>
                    <button class="six40-barber-btn <?= $s === 'vacation' ? 'active' : '' ?>"
                            data-id="<?= esc_attr( $b['id'] ) ?>" data-status="vacation">
                      🏖️ Vacaciones
                    </button>
                    <button class="six40-barber-btn <?= $s === 'sick' ? 'active' : '' ?>"
                            data-id="<?= esc_attr( $b['id'] ) ?>" data-status="sick">
                      🤒 Baja
                    </button>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
            <?php
            break;

        // ──────────────────────────────────────────────────── CONFIGURACIÓN ──
        case 'six40-settings':
            $cfg_data = (array) get_option( 'six40_settings', [] );
            ?>
            <h1 class="six40-page-title">Configuración</h1>

            <?php if ( isset( $_GET['saved'] ) ) : ?>
              <div class="notice notice-success is-dismissible"><p>✅ Configuración guardada correctamente.</p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['oauth_success'] ) ) : ?>
              <div class="notice notice-success is-dismissible"><p>✅ Google Calendar conectado correctamente.</p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['oauth_error'] ) ) : ?>
              <div class="notice notice-error is-dismissible"><p>❌ Error al conectar con Google Calendar. Comprueba las credenciales.</p></div>
            <?php endif; ?>

            <form method="POST" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
              <?php wp_nonce_field( 'six40_save_settings' ); ?>
              <input type="hidden" name="action" value="six40_save_settings">

              <!-- Supabase -->
              <div class="six40-panel">
                <div class="six40-panel-header"><h2>🗄️ Supabase</h2></div>
                <table class="form-table">
                  <tr>
                    <th><label for="supabase_url">Project URL</label></th>
                    <td>
                      <input type="url" id="supabase_url" name="supabase_url" class="regular-text"
                             value="<?= esc_attr( $cfg_data['supabase_url'] ?? '' ) ?>"
                             placeholder="https://xxxx.supabase.co">
                      <p class="description">URL de tu proyecto en Supabase.</p>
                    </td>
                  </tr>
                  <tr>
                    <th><label for="supabase_key">Service Role Key</label></th>
                    <td>
                      <input type="password" id="supabase_key" name="supabase_key" class="regular-text"
                             value="<?= esc_attr( $cfg_data['supabase_key'] ?? '' ) ?>">
                      <p class="description">Clave <em>service_role</em> (Settings → API en Supabase).</p>
                    </td>
                  </tr>
                </table>
              </div>

              <!-- Google Calendar -->
              <div class="six40-panel">
                <div class="six40-panel-header"><h2>📅 Google Calendar</h2></div>
                <table class="form-table">
                  <tr>
                    <th><label for="google_client_id">Client ID</label></th>
                    <td><input type="text" id="google_client_id" name="google_client_id" class="regular-text"
                               value="<?= esc_attr( $cfg_data['google_client_id'] ?? '' ) ?>"></td>
                  </tr>
                  <tr>
                    <th><label for="google_client_secret">Client Secret</label></th>
                    <td><input type="password" id="google_client_secret" name="google_client_secret" class="regular-text"
                               value="<?= esc_attr( $cfg_data['google_client_secret'] ?? '' ) ?>"></td>
                  </tr>
                  <tr>
                    <th><label for="google_calendar_malaga">Calendar ID · Málaga</label></th>
                    <td><input type="text" id="google_calendar_malaga" name="google_calendar_malaga" class="regular-text"
                               value="<?= esc_attr( $cfg_data['google_calendar_malaga'] ?? '' ) ?>"
                               placeholder="xxxx@group.calendar.google.com"></td>
                  </tr>
                  <tr>
                    <th><label for="google_calendar_torremolinos">Calendar ID · Torremolinos</label></th>
                    <td><input type="text" id="google_calendar_torremolinos" name="google_calendar_torremolinos" class="regular-text"
                               value="<?= esc_attr( $cfg_data['google_calendar_torremolinos'] ?? '' ) ?>"
                               placeholder="xxxx@group.calendar.google.com"></td>
                  </tr>
                  <tr>
                    <th>Autorización OAuth2</th>
                    <td>
                      <?php if ( $has_token ?? false ) : ?>
                        <span class="six40-status six40-status--confirmed">✅ Conectado</span>
                        <a href="<?= esc_url( $auth_url ?? '#' ) ?>" class="six40-btn six40-btn-sm six40-btn-outline" style="margin-left:12px;">Reconectar</a>
                      <?php else : ?>
                        <a href="<?= esc_url( $auth_url ?? '#' ) ?>" class="six40-btn six40-btn-sm">Conectar con Google</a>
                        <p class="description">Guarda primero el Client ID y Client Secret, luego conecta.</p>
                      <?php endif; ?>
                    </td>
                  </tr>
                </table>
              </div>

              <!-- Resend Email -->
              <div class="six40-panel">
                <div class="six40-panel-header"><h2>📧 Email (Resend)</h2></div>
                <table class="form-table">
                  <tr>
                    <th><label for="resend_api_key">API Key</label></th>
                    <td><input type="password" id="resend_api_key" name="resend_api_key" class="regular-text"
                               value="<?= esc_attr( $cfg_data['resend_api_key'] ?? '' ) ?>">
                      <p class="description">Vacío = se usará wp_mail como fallback.</p>
                    </td>
                  </tr>
                  <tr>
                    <th><label for="email_from_name">Nombre remitente</label></th>
                    <td><input type="text" id="email_from_name" name="email_from_name" class="regular-text"
                               value="<?= esc_attr( $cfg_data['email_from_name'] ?? 'Six40 Barbería' ) ?>"></td>
                  </tr>
                  <tr>
                    <th><label for="email_from">Email remitente</label></th>
                    <td><input type="email" id="email_from" name="email_from" class="regular-text"
                               value="<?= esc_attr( $cfg_data['email_from'] ?? '' ) ?>"
                               placeholder="noreply@six40.katibu.es"></td>
                  </tr>
                </table>
              </div>

              <?php submit_button( 'Guardar configuración', 'primary', 'submit', true, [ 'class' => 'six40-btn' ] ); ?>
            </form>
            <?php
            break;
    }
    ?>
  </div><!-- .six40-admin-content -->
</div><!-- .six40-admin -->
