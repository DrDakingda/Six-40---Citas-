<?php
defined( 'ABSPATH' ) || exit;

// Services and locations – must match class-booking-api constants.
$services = [
    'barba'       => [ 'label' => 'Barba', 'desc' => '15 min', 'icon' => '🪒' ],
    'corte'       => [ 'label' => 'Corte', 'desc' => '30 min', 'icon' => '✂️' ],
    'corte_barba' => [ 'label' => 'Corte + Barba', 'desc' => '45 min', 'icon' => '💈' ],
];

$locations = [
    'malaga'       => 'Málaga',
    'torremolinos' => 'Torremolinos',
];

// Min date: today. Max: 60 days from now.
$today   = wp_date( 'Y-m-d' );
$max_day = wp_date( 'Y-m-d', strtotime( '+60 days' ) );
?>
<div class="six40-booking-wrap" id="six40-booking">

  <!-- Step indicators -->
  <div class="six40-steps" aria-label="Pasos del proceso">
    <div class="six40-step active" data-step="1">
      <span class="six40-step-num">1</span>
      <span class="six40-step-label">Local &amp; Servicio</span>
    </div>
    <div class="six40-step-connector"></div>
    <div class="six40-step" data-step="2">
      <span class="six40-step-num">2</span>
      <span class="six40-step-label">Fecha &amp; Hora</span>
    </div>
    <div class="six40-step-connector"></div>
    <div class="six40-step" data-step="3">
      <span class="six40-step-num">3</span>
      <span class="six40-step-label">Tus datos</span>
    </div>
    <div class="six40-step-connector"></div>
    <div class="six40-step" data-step="4">
      <span class="six40-step-num">4</span>
      <span class="six40-step-label">Confirmar</span>
    </div>
  </div>

  <form id="six40-form" novalidate>
    <?php wp_nonce_field( 'six40_booking_nonce', 'six40_nonce', true, true ); ?>

    <!-- ── Step 1: Location & Service ──────────────────────────────────────── -->
    <div class="six40-form-step" data-step="1">
      <h2 class="six40-step-title">Elige tu local y servicio</h2>

      <!-- Location -->
      <div class="six40-field-group">
        <label class="six40-field-label">📍 Local</label>
        <div class="six40-cards-row">
          <?php foreach ( $locations as $key => $name ) : ?>
          <label class="six40-card-option">
            <input type="radio" name="location" value="<?= esc_attr( $key ) ?>" required>
            <span class="six40-card-body">
              <span class="six40-card-title"><?= esc_html( $name ) ?></span>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
        <div class="six40-field-error" id="err-location"></div>
      </div>

      <!-- Service -->
      <div class="six40-field-group">
        <label class="six40-field-label">✂️ Servicio</label>
        <div class="six40-cards-row">
          <?php foreach ( $services as $key => $svc ) : ?>
          <label class="six40-card-option">
            <input type="radio" name="service" value="<?= esc_attr( $key ) ?>" required>
            <span class="six40-card-body">
              <span class="six40-card-icon"><?= $svc['icon'] ?></span>
              <span class="six40-card-title"><?= esc_html( $svc['label'] ) ?></span>
              <span class="six40-card-desc"><?= esc_html( $svc['desc'] ) ?></span>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
        <div class="six40-field-error" id="err-service"></div>
      </div>

      <div class="six40-step-actions">
        <button type="button" class="six40-btn six40-btn-next" data-next="2">
          Siguiente <span class="six40-btn-arrow">→</span>
        </button>
      </div>
    </div>

    <!-- ── Step 2: Date & Time ─────────────────────────────────────────────── -->
    <div class="six40-form-step six40-hidden" data-step="2">
      <h2 class="six40-step-title">Elige fecha y hora</h2>

      <div class="six40-date-time-row">
        <!-- Date picker -->
        <div class="six40-field-group">
          <label class="six40-field-label" for="six40-date">📅 Fecha</label>
          <input type="date" id="six40-date" name="date"
                 min="<?= esc_attr( $today ) ?>"
                 max="<?= esc_attr( $max_day ) ?>"
                 class="six40-input"
                 required>
          <div class="six40-field-error" id="err-date"></div>
        </div>

        <!-- Time slots -->
        <div class="six40-field-group" id="six40-time-group">
          <label class="six40-field-label" for="six40-time">🕐 Hora disponible</label>
          <div id="six40-slots-container">
            <p class="six40-slots-hint">Selecciona una fecha para ver los horarios disponibles.</p>
          </div>
          <input type="hidden" name="time" id="six40-time" required>
          <input type="hidden" name="barber_id" id="six40-barber-id" value="0">
          <div class="six40-field-error" id="err-time"></div>
        </div>
      </div>

      <div class="six40-step-actions">
        <button type="button" class="six40-btn six40-btn-outline six40-btn-prev" data-prev="1">← Atrás</button>
        <button type="button" class="six40-btn six40-btn-next" data-next="3">
          Siguiente <span class="six40-btn-arrow">→</span>
        </button>
      </div>
    </div>

    <!-- ── Step 3: Personal data ───────────────────────────────────────────── -->
    <div class="six40-form-step six40-hidden" data-step="3">
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
        <p class="six40-field-hint">Te enviaremos la confirmación de tu cita a este email.</p>
        <div class="six40-field-error" id="err-email"></div>
      </div>

      <div class="six40-step-actions">
        <button type="button" class="six40-btn six40-btn-outline six40-btn-prev" data-prev="2">← Atrás</button>
        <button type="button" class="six40-btn six40-btn-next" data-next="4">
          Revisar cita <span class="six40-btn-arrow">→</span>
        </button>
      </div>
    </div>

    <!-- ── Step 4: Summary ────────────────────────────────────────────────── -->
    <div class="six40-form-step six40-hidden" data-step="4">
      <h2 class="six40-step-title">Resumen de tu cita</h2>

      <div class="six40-summary-card">
        <div class="six40-summary-row">
          <span class="six40-summary-label">📍 Local</span>
          <span class="six40-summary-value" id="sum-location">—</span>
        </div>
        <div class="six40-summary-row">
          <span class="six40-summary-label">✂️ Servicio</span>
          <span class="six40-summary-value" id="sum-service">—</span>
        </div>
        <div class="six40-summary-row">
          <span class="six40-summary-label">📅 Fecha</span>
          <span class="six40-summary-value" id="sum-date">—</span>
        </div>
        <div class="six40-summary-row">
          <span class="six40-summary-label">🕐 Hora</span>
          <span class="six40-summary-value" id="sum-time">—</span>
        </div>
        <div class="six40-summary-row">
          <span class="six40-summary-label">👤 Nombre</span>
          <span class="six40-summary-value" id="sum-name">—</span>
        </div>
        <div class="six40-summary-row">
          <span class="six40-summary-label">📧 Email</span>
          <span class="six40-summary-value" id="sum-email">—</span>
        </div>
      </div>

      <div class="six40-step-actions">
        <button type="button" class="six40-btn six40-btn-outline six40-btn-prev" data-prev="3">← Atrás</button>
        <button type="submit" class="six40-btn six40-btn-submit" id="six40-submit">
          <span class="six40-submit-text">Confirmar cita ✓</span>
          <span class="six40-submit-loading six40-hidden">Enviando…</span>
        </button>
      </div>
    </div>

    <!-- Global error -->
    <div class="six40-global-error six40-hidden" id="six40-global-error"></div>

  </form>

  <!-- Success screen -->
  <div class="six40-success six40-hidden" id="six40-success">
    <div class="six40-success-icon">✅</div>
    <h2 class="six40-success-title">¡Cita confirmada!</h2>
    <p class="six40-success-body">
      Revisa tu bandeja de entrada — recibirás un email con todos los detalles.
    </p>
    <button type="button" class="six40-btn" id="six40-new-booking">Reservar otra cita</button>
  </div>

</div><!-- .six40-booking-wrap -->
