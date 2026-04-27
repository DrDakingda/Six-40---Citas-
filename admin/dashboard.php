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
      <a href="<?= esc_url( admin_url('admin.php?page=six40-dashboard') ) ?>" class="<?= $page==='six40-dashboard'?'active':'' ?>">
        <span class="dashicons dashicons-dashboard"></span> Dashboard
      </a>
      <a href="<?= esc_url( admin_url('admin.php?page=six40-citas') ) ?>" class="<?= $page==='six40-citas'?'active':'' ?>">
        <span class="dashicons dashicons-calendar-alt"></span> Citas
      </a>
      <a href="<?= esc_url( admin_url('admin.php?page=six40-barberos') ) ?>" class="<?= $page==='six40-barberos'?'active':'' ?>">
        <span class="dashicons dashicons-groups"></span> Barberos
      </a>
      <a href="<?= esc_url( admin_url('admin.php?page=six40-settings') ) ?>" class="<?= $page==='six40-settings'?'active':'' ?>">
        <span class="dashicons dashicons-admin-settings"></span> Configuración
      </a>
    </nav>
  </div>

  <div class="six40-admin-content">
  <?php switch ( $page ) :

    // ── DASHBOARD ────────────────────────────────────────────────────────────
    case 'six40-dashboard': default: ?>
    <h1 class="six40-page-title">Dashboard</h1>
    <p class="six40-page-subtitle"><?= esc_html( date_i18n('l, j \d\e F \d\e Y') ) ?></p>

    <div class="six40-stats-grid">
      <div class="six40-stat-card">
        <div class="six40-stat-icon">📍</div>
        <div class="six40-stat-body">
          <div class="six40-stat-number"><?= esc_html( $malaga_count ?? 0 ) ?></div>
          <div class="six40-stat-label">Citas hoy · Málaga</div>
        </div>
      </div>
      <div class="six40-stat-card">
        <div class="six40-stat-icon">📍</div>
        <div class="six40-stat-body">
          <div class="six40-stat-number"><?= esc_html( $torremolinos_count ?? 0 ) ?></div>
          <div class="six40-stat-label">Citas hoy · Torremolinos</div>
        </div>
      </div>
      <div class="six40-stat-card">
        <div class="six40-stat-icon">✂️</div>
        <div class="six40-stat-body">
          <div class="six40-stat-number"><?= esc_html( count($today_appts ?? []) ) ?></div>
          <div class="six40-stat-label">Total citas hoy</div>
        </div>
      </div>
      <div class="six40-stat-card">
        <div class="six40-stat-icon">💈</div>
        <div class="six40-stat-body">
          <div class="six40-stat-number"><?= esc_html( count(array_filter($statuses ?? [], fn($s) => $s==='available')) ) ?>/8</div>
          <div class="six40-stat-label">Barberos disponibles</div>
        </div>
      </div>
    </div>

    <div class="six40-panel">
      <div class="six40-panel-header">
        <h2>Citas de hoy</h2>
        <a href="<?= esc_url(admin_url('admin.php?page=six40-citas')) ?>" class="six40-btn six40-btn-sm">Ver todas</a>
      </div>
      <?php if ( empty($today_appts) ) : ?>
        <div class="six40-empty">No hay citas para hoy.</div>
      <?php else :
        $svc_labels = ['barba'=>'Barba','corte'=>'Corte','corte_barba'=>'Corte + Barba'];
        $barbers_map = Six40_Booking_API::BARBERS;
        ?>
        <table class="six40-table">
          <thead><tr><th>Hora</th><th>Cliente</th><th>Servicio</th><th>Local</th><th>Barbero/a</th><th>Estado</th><th>Acción</th></tr></thead>
          <tbody>
          <?php foreach ( $today_appts as $appt ) :
            $status = $appt['status'] ?? 'confirmed';
            $statusLabels = ['confirmed'=>'Confirmada','completed'=>'Completada','cancelled'=>'Cancelada','no_show'=>'No presentó'];
          ?>
          <tr>
            <td><strong><?= esc_html(substr($appt['time']??'',0,5)) ?></strong></td>
            <td>
              <span class="six40-customer-name"><?= esc_html($appt['customer_name']??'—') ?></span>
              <small class="six40-customer-email"><?= esc_html($appt['customer_email']??'') ?></small>
            </td>
            <td><?= esc_html($svc_labels[$appt['service']??'']??($appt['service']??'—')) ?></td>
            <td><?= $appt['location']==='malaga'?'Málaga':'Torremolinos' ?></td>
            <td><?= esc_html($barbers_map[(int)($appt['barber_id']??0)]['name']??'—') ?></td>
            <td><span class="six40-status six40-status--<?= esc_attr($status) ?>"><?= esc_html($statusLabels[$status]??ucfirst($status)) ?></span></td>
            <td>
              <select class="six40-status-select" data-id="<?= esc_attr($appt['id']??'') ?>">
                <?php foreach ($statusLabels as $k=>$v): ?>
                <option value="<?= $k ?>" <?= selected($status,$k,false) ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="six40-panel">
      <div class="six40-panel-header">
        <h2>Calendario de citas</h2>
        <select id="six40-calendar-location">
          <option value="">Todos los locales</option>
          <option value="malaga">Málaga</option>
          <option value="torremolinos">Torremolinos</option>
        </select>
      </div>
      <div id="six40-calendar"></div>
    </div>
    <?php break;

    // ── CITAS ────────────────────────────────────────────────────────────────
    case 'six40-citas':
      $appointments_list = $appointments ?? [];
      $svc_labels = ['barba'=>'Barba','corte'=>'Corte','corte_barba'=>'Corte + Barba'];
      $barbers_map = Six40_Booking_API::BARBERS;
      $statusLabels = ['confirmed'=>'Confirmada','completed'=>'Completada','cancelled'=>'Cancelada','no_show'=>'No presentó'];
    ?>
    <h1 class="six40-page-title">Historial de Citas</h1>
    <div class="six40-filters-bar">
      <form method="GET">
        <input type="hidden" name="page" value="six40-citas">
        <select name="location">
          <option value="">Todos los locales</option>
          <option value="malaga"       <?= selected($location??'','malaga',false) ?>>Málaga</option>
          <option value="torremolinos" <?= selected($location??'','torremolinos',false) ?>>Torremolinos</option>
        </select>
        <input type="date" name="date_from" value="<?= esc_attr($date_from??'') ?>">
        <select name="status">
          <option value="">Todos los estados</option>
          <?php foreach ($statusLabels as $k=>$v): ?>
          <option value="<?= $k ?>" <?= selected($status_f??'',$k,false) ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="six40-btn">Filtrar</button>
        <a href="<?= esc_url(admin_url('admin.php?page=six40-citas')) ?>" class="six40-btn six40-btn-outline">Limpiar</a>
      </form>
    </div>
    <div class="six40-panel">
      <?php if ( empty($appointments_list) ) : ?>
        <div class="six40-empty">No se encontraron citas.</div>
      <?php else : ?>
        <table class="six40-table">
          <thead><tr><th>#</th><th>Fecha</th><th>Hora</th><th>Cliente</th><th>Servicio</th><th>Local</th><th>Barbero/a</th><th>Estado</th><th>Acción</th></tr></thead>
          <tbody>
          <?php foreach ( $appointments_list as $appt ) :
            $status = $appt['status'] ?? 'confirmed';
          ?>
          <tr>
            <td><?= esc_html($appt['id']??'') ?></td>
            <td><?= esc_html(date_i18n('d/m/Y',strtotime($appt['date']??''))) ?></td>
            <td><?= esc_html(substr($appt['time']??'',0,5)) ?></td>
            <td>
              <?= esc_html($appt['customer_name']??'—') ?>
              <small><?= esc_html($appt['customer_email']??'') ?></small>
            </td>
            <td><?= esc_html($svc_labels[$appt['service']??'']??($appt['service']??'—')) ?></td>
            <td><?= $appt['location']==='malaga'?'Málaga':'Torremolinos' ?></td>
            <td><?= esc_html($barbers_map[(int)($appt['barber_id']??0)]['name']??'—') ?></td>
            <td><span class="six40-status six40-status--<?= esc_attr($status) ?>"><?= esc_html($statusLabels[$status]??ucfirst($status)) ?></span></td>
            <td>
              <select class="six40-status-select" data-id="<?= esc_attr($appt['id']??'') ?>">
                <?php foreach ($statusLabels as $k=>$v): ?>
                <option value="<?= $k ?>" <?= selected($status,$k,false) ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <?php break;

    // ── BARBEROS ─────────────────────────────────────────────────────────────
    case 'six40-barberos':
      $statuses_all = $statuses ?? (new Six40_Booking_API())->get_barber_statuses();
      $barbers_list = Six40_Booking_API::BARBERS;
      $status_labels = ['available'=>'Disponible','vacation'=>'Vacaciones','sick'=>'Baja'];
      $btn_labels    = ['available'=>'✅ Disponible','vacation'=>'🏖️ Vacaciones','sick'=>'🤒 Baja'];
      $locations     = ['malaga'=>'Málaga','torremolinos'=>'Torremolinos'];
    ?>
    <h1 class="six40-page-title">Gestión de Barberos</h1>
    <p class="six40-page-subtitle">Cambios se aplican en tiempo real — los clientes no podrán reservar con barberos en Vacaciones o Baja.</p>
    <?php foreach ( $locations as $loc_key => $loc_name ) : ?>
    <div class="six40-panel">
      <div class="six40-panel-header"><h2>📍 <?= esc_html($loc_name) ?></h2></div>
      <div class="six40-barbers-grid">
        <?php foreach ( $barbers_list as $b ) :
          if ( $b['location'] !== $loc_key ) continue;
          $s = $statuses_all[$b['id']] ?? 'available';
        ?>
        <div class="six40-barber-card six40-barber--<?= esc_attr($s) ?>">
          <div class="six40-barber-avatar"><?= strtoupper(substr($b['name'],0,1)) ?></div>
          <div class="six40-barber-info">
            <strong><?= esc_html($b['name']) ?></strong>
            <span class="six40-status six40-status--<?= esc_attr($s) ?> six40-barber-status-label">
              <?= esc_html($status_labels[$s]??ucfirst($s)) ?>
            </span>
          </div>
          <div class="six40-barber-controls">
            <?php foreach ($btn_labels as $k=>$v) : ?>
            <button class="six40-barber-btn <?= $s===$k?'active':'' ?>"
                    data-id="<?= esc_attr($b['id']) ?>" data-status="<?= $k ?>">
              <?= $v ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; break;

    // ── CONFIGURACIÓN ────────────────────────────────────────────────────────
    case 'six40-settings':
      $cfg_data = (array) get_option('six40_settings',[]);
    ?>
    <h1 class="six40-page-title">Configuración</h1>
    <?php if (isset($_GET['saved'])): ?><div class="notice notice-success is-dismissible"><p>✅ Configuración guardada.</p></div><?php endif; ?>
    <?php if (isset($_GET['oauth_success'])): ?><div class="notice notice-success is-dismissible"><p>✅ Google Calendar conectado.</p></div><?php endif; ?>
    <?php if (isset($_GET['oauth_error'])): ?><div class="notice notice-error is-dismissible"><p>❌ Error al conectar Google Calendar. Revisa las credenciales.</p></div><?php endif; ?>

    <form method="POST" action="<?= esc_url(admin_url('admin-post.php')) ?>">
      <?php wp_nonce_field('six40_save_settings'); ?>
      <input type="hidden" name="action" value="six40_save_settings">

      <div class="six40-panel">
        <div class="six40-panel-header"><h2>🗄️ Supabase</h2></div>
        <table class="form-table">
          <tr><th><label for="supabase_url">Project URL</label></th>
            <td><input type="url" id="supabase_url" name="supabase_url" class="regular-text"
                value="<?= esc_attr($cfg_data['supabase_url']??'') ?>" placeholder="https://xxxx.supabase.co">
              <p class="description">URL de tu proyecto Supabase (Settings → API).</p></td></tr>
          <tr><th><label for="supabase_key">Service Role Key</label></th>
            <td><input type="password" id="supabase_key" name="supabase_key" class="regular-text"
                value="<?= esc_attr($cfg_data['supabase_key']??'') ?>">
              <p class="description">Clave <em>service_role</em> — nunca la clave <em>anon</em>.</p></td></tr>
        </table>
      </div>

      <div class="six40-panel">
        <div class="six40-panel-header"><h2>📅 Google Calendar</h2></div>
        <table class="form-table">
          <tr><th><label for="google_client_id">Client ID</label></th>
            <td><input type="text" id="google_client_id" name="google_client_id" class="regular-text"
                value="<?= esc_attr($cfg_data['google_client_id']??'') ?>"></td></tr>
          <tr><th><label for="google_client_secret">Client Secret</label></th>
            <td><input type="password" id="google_client_secret" name="google_client_secret" class="regular-text"
                value="<?= esc_attr($cfg_data['google_client_secret']??'') ?>"></td></tr>
          <tr><th><label for="google_calendar_malaga">Calendar ID · Málaga</label></th>
            <td><input type="text" id="google_calendar_malaga" name="google_calendar_malaga" class="regular-text"
                value="<?= esc_attr($cfg_data['google_calendar_malaga']??'') ?>" placeholder="xxxx@group.calendar.google.com"></td></tr>
          <tr><th><label for="google_calendar_torremolinos">Calendar ID · Torremolinos</label></th>
            <td><input type="text" id="google_calendar_torremolinos" name="google_calendar_torremolinos" class="regular-text"
                value="<?= esc_attr($cfg_data['google_calendar_torremolinos']??'') ?>" placeholder="xxxx@group.calendar.google.com"></td></tr>
          <tr><th>Autorización OAuth2</th>
            <td>
              <?php if ($has_token??false): ?>
                <span class="six40-status six40-status--confirmed">✅ Conectado</span>
                <a href="<?= esc_url($auth_url??'#') ?>" class="six40-btn six40-btn-sm six40-btn-outline" style="margin-left:12px;">Reconectar</a>
              <?php else: ?>
                <a href="<?= esc_url($auth_url??'#') ?>" class="six40-btn six40-btn-sm">Conectar con Google</a>
                <p class="description">Guarda primero Client ID y Secret, luego conecta.</p>
              <?php endif; ?>
            </td></tr>
        </table>
      </div>

      <div class="six40-panel">
        <div class="six40-panel-header"><h2>📧 Email (Resend)</h2></div>
        <table class="form-table">
          <tr><th><label for="resend_api_key">API Key</label></th>
            <td><input type="password" id="resend_api_key" name="resend_api_key" class="regular-text"
                value="<?= esc_attr($cfg_data['resend_api_key']??'') ?>">
              <p class="description">Si está vacío se usa wp_mail como fallback.</p></td></tr>
          <tr><th><label for="email_from_name">Nombre remitente</label></th>
            <td><input type="text" id="email_from_name" name="email_from_name" class="regular-text"
                value="<?= esc_attr($cfg_data['email_from_name']??'Six40 Barbería') ?>"></td></tr>
          <tr><th><label for="email_from">Email remitente</label></th>
            <td><input type="email" id="email_from" name="email_from" class="regular-text"
                value="<?= esc_attr($cfg_data['email_from']??'') ?>" placeholder="noreply@six40.katibu.es"></td></tr>
        </table>
      </div>

      <?php submit_button('Guardar configuración','primary large'); ?>
    </form>
    <?php break; endswitch; ?>
  </div>
</div>
