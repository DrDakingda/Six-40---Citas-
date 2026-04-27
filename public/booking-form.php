<?php
defined( 'ABSPATH' ) || exit;

$services  = [
    'barba'       => [ 'label' => 'Barba',         'desc' => '15 min', 'icon' => '🪒' ],
    'corte'       => [ 'label' => 'Corte',         'desc' => '30 min', 'icon' => '✂️' ],
    'corte_barba' => [ 'label' => 'Corte + Barba', 'desc' => '45 min', 'icon' => '💈' ],
];
$today   = wp_date( 'Y-m-d' );
$max_day = wp_date( 'Y-m-d', strtotime( '+60 days' ) );
?>
<div class="six40-booking-wrap" id="six40-booking">

  <!-- Step indicators -->
  <div class="six40-steps">
    <?php
    $steps = [ 1 => '¿Dónde?', 2 => '¿Cómo?', 3 => '¿Cuándo?', 4 => 'Servicio', 5 => 'Tus datos', 6 => 'Confirmar' ];
    foreach ( $steps as $n => $lbl ) :
      if ( $n > 1 ) echo '<div class="six40-step-connector"></div>';
    ?>
    <div class="six40-step <?= $n === 1 ? 'active' : '' ?>" data-step="<?= $n ?>">
      <span class="six40-step-num"><?= $n ?></span>
      <span class="six40-step-label"><?= $lbl ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <form id="six40-form" novalidate>
    <?php wp_nonce_field( 'six40_booking_nonce', 'six40_nonce' ); ?>

    <!-- PASO 1: DÓNDE -->
    <div class="six40-form-step" data-step="1">
      <h2 class="six40-step-title">¿Dónde quieres tu cita?</h2>
      <div class="six40-location-grid">
        <label class="six40-location-card">
          <input type="radio" name="location" value="malaga" required>
          <span class="six40-location-body">
            <span class="six40-location-icon">📍</span>
            <span class="six40-location-name">Málaga</span>
            <span class="six40-location-barbers">Samuel · Graciela · Adrián · Alejandro</span>
          </span>
        </label>
        <label class="six40-location-card">
          <input type="radio" name="location" value="torremolinos" required>
          <span class="six40-location-body">
            <span class="six40-location-icon">📍</span>
            <span class="six40-location-name">Torremolinos</span>
            <span class="six40-location-barbers">Antonio · Graciela · Juan · Adrián</span>
          </span>
        </label>
      </div>
      <div class="six40-field-error" id="err-location"></div>
      <div class="six40-step-actions">
        <button type="button" class="six40-btn six40-btn-next" data-next="2">Siguiente →</button>
      </div>
    </div>

    <!-- PASO 2: CÓMO + BARBERO -->
    <div class="six40-form-step six40-hidden" data-step="2">
      <h2 class="six40-step-title">¿Cómo prefieres reservar?</h2>
      <div class="six40-mode-grid">
        <label class="six40-mode-card">
          <input type="radio" name="booking_mode" value="auto" required>
          <span class="six40-mode-body">
            <span class="six40-mode-icon">⚡</span>
            <span class="six40-mode-title">Próxima disponible</span>
            <span class="six40-mode-desc">Te asignamos el primer hueco libre</span>
          </span>
        </label>
        <label class="six40-mode-card">
          <input type="radio" name="booking_mode" value="barber" required>
          <span class="six40-mode-body">
            <span class="six40-mode-icon">💈</span>
            <span class="six40-mode-title">Elegir mi barbero</span>
            <span class="six40-mode-desc">Tú decides quién te atiende</span>
          </span>
        </label>
      </div>
      <div class="six40-field-error" id="err-mode"></div>

      <!-- Barbero picker (solo si mode=barber) -->
      <div class="six40-barber-picker six40-hidden" id="six40-barber-picker">
        <h3 class="six40-substep-title">¿Quién te atenderá?</h3>
        <div class="six40-barber-cards" id="six40-barber-cards"></div>
        <div class="six40-field-error" id="err-barber"></div>
      </div>
      <input type="hidden" name="barber_id" id="six40-barber-id" value="0">

      <div class="six40-step-actions">
        <button type="button" class="six40-btn six40-btn-outline six40-btn-prev" data-prev="1">← Atrás</button>
        <button type="button" class="six40-btn six40-btn-next" data-next="3">Siguiente →</button>
      </div>
    </div>

    <!-- PASO 3: CUÁNDO -->
    <div class="six40-form-step six40-hidden" data-step="3">
      <h2 class="six40-step-title">¿Cuándo quieres venir?</h2>
      <div class="six40-date-time-row">
        <div class="six40-field-group">
          <label class="six40-field-label" for="six40-date">📅 Fecha</label>
          <input type="date" id="six40-date" name="date" class="six40-input"
                 min="<?= esc_attr( $today ) ?>" max="<?= esc_attr( $max_day ) ?>" required>
          <div class="six40-field-error" id="err-date"></div>
        </div>
        <div class="six40-field-group">
          <label class="six40-field-label">🕐 Hora disponible</label>
          <div id="six40-slots-container">
            <p class="six40-slots-hint">Selecciona una fecha para ver los horarios.</p>
          </div>
          <input type="hidden" name="time" id="six40-time" required>
          <div class="six40-field-error" id="err-time"></div>
        </div>
      </div>
      <div class="six40-step-actions">
        <button type="button" class="six40-btn six40-btn-outline six40-btn-prev" data-prev="2">← Atrás</button>
        <button type="button" class="six40-btn six40-btn-next" data-next="4">Siguiente →</button>
      </div>
    </div>

    <!-- PASO 4: SERVICIO -->
    <div class="six40-form-step six40-hidden" data-step="4">
      <h2 class="six40-step-title">¿Qué servicio quieres?</h2>
      <div class="six40-cards-row">
        <?php foreach ( $services as $k => $s ) : ?>
        <label class="six40-card-option">
          <input type="radio" name="service" value="<?= esc_attr( $k ) ?>" required>
          <span class="six40-card-body">
            <span class="six40-card-icon"><?= $s['icon'] ?></span>
            <span class="six40-card-title"><?= esc_html( $s['label'] ) ?></span>
            <span class="six40-card-desc"><?= esc_html( $s['desc'] ) ?></span>
          </span>
        </label>
        <?php endforeach; ?>
      </div>
      <div class="six40-field-error" id="err-service"></div>
      <div class="six40-step-actions">
        <button type="button" class="six40-btn six40-btn-outline six40-btn-prev" data-prev="3">← Atrás</button>
        <button type="button" class="six40-btn six40-btn-next" data-next="5">Siguiente →</button>
      </div>
    </div>

    <!-- PASO 5: DATOS -->
    <div class="six40-form-step six40-hidden" data-step="5">
      <h2 class="six40-step-title">Tus datos de contacto</h2>
      <div class="six40-field-group">
        <label class="six40-field-label" for="six40-name">👤 Nombre completo</label>
        <input type="text" id="six40-name" name="name" class="six40-input"
               placeholder="Ej: Carlos García" required maxlength="100" autocomplete="name">
        <div class="six40-field-error" id="err-name"></div>
      </div>
      <div class="six40-field-group">
        <label class="six40-field-label" for="six40-email">📧 Email</label>
        <input type="email" id="six40-email" name="email" class="six40-input"
               placeholder="tucorreo@ejemplo.com" required maxlength="150" autocomplete="email">
        <p class="six40-field-hint">Te enviaremos la confirmación a este email.</p>
        <div class="six40-field-error" id="err-email"></div>
      </div>
      <div class="six40-step-actions">
        <button type="button" class="six40-btn six40-btn-outline six40-btn-prev" data-prev="4">← Atrás</button>
        <button type="button" class="six40-btn six40-btn-next" data-next="6">Revisar cita →</button>
      </div>
    </div>

    <!-- PASO 6: CONFIRMAR -->
    <div class="six40-form-step six40-hidden" data-step="6">
      <h2 class="six40-step-title">Resumen de tu cita</h2>
      <div class="six40-summary-card">
        <div class="six40-summary-row"><span class="six40-summary-label">📍 Local</span>    <span class="six40-summary-value" id="sum-location">—</span></div>
        <div class="six40-summary-row"><span class="six40-summary-label">💈 Barbero/a</span><span class="six40-summary-value" id="sum-barber">—</span></div>
        <div class="six40-summary-row"><span class="six40-summary-label">📅 Fecha</span>    <span class="six40-summary-value" id="sum-date">—</span></div>
        <div class="six40-summary-row"><span class="six40-summary-label">🕐 Hora</span>     <span class="six40-summary-value" id="sum-time">—</span></div>
        <div class="six40-summary-row"><span class="six40-summary-label">✂️ Servicio</span> <span class="six40-summary-value" id="sum-service">—</span></div>
        <div class="six40-summary-row"><span class="six40-summary-label">👤 Nombre</span>   <span class="six40-summary-value" id="sum-name">—</span></div>
        <div class="six40-summary-row"><span class="six40-summary-label">📧 Email</span>    <span class="six40-summary-value" id="sum-email">—</span></div>
      </div>
      <div class="six40-step-actions">
        <button type="button" class="six40-btn six40-btn-outline six40-btn-prev" data-prev="5">← Atrás</button>
        <button type="submit" class="six40-btn six40-btn-submit" id="six40-submit">
          <span class="six40-submit-text">Confirmar cita ✓</span>
          <span class="six40-submit-loading six40-hidden">Enviando…</span>
        </button>
      </div>
    </div>

    <div class="six40-global-error six40-hidden" id="six40-global-error"></div>
  </form>

  <div class="six40-success six40-hidden" id="six40-success">
    <div class="six40-success-icon">✅</div>
    <h2 class="six40-success-title">¡Cita confirmada!</h2>
    <p class="six40-success-body">Revisa tu bandeja de entrada — recibirás un email con todos los detalles.</p>
    <button type="button" class="six40-btn" id="six40-new-booking">Reservar otra cita</button>
  </div>

</div>
