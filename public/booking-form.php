<?php
defined( 'ABSPATH' ) || exit;

// Fetch services from Supabase
$api = new Six40_Booking_API();
$all_services = $api->get_services();

if ( is_wp_error( $all_services ) ) {
    $all_services = [];
}

// Separate base and additional services
$base_services = [];
$additional_services = [];

foreach ( $all_services as $svc ) {
    if ( $svc['type'] === 'base' ) {
        $base_services[] = $svc;
    } elseif ( $svc['type'] === 'additional' ) {
        $additional_services[] = $svc;
    }
}

$today = wp_date( 'Y-m-d' );
$max_day = wp_date( 'Y-m-d', strtotime( '+60 days' ) );
?>
<div class="tf-wrap" id="six40-booking">

  <!-- Barra de progreso -->
  <div class="tf-progress"><div class="tf-progress-bar" id="tf-progress-bar"></div></div>

  <form id="six40-form" novalidate>
    <?php wp_nonce_field( 'six40_booking_nonce', 'six40_nonce' ); ?>

    <!-- PASO 1: DÓNDE -->
    <div class="tf-step active" data-step="1">
      <div class="tf-step-inner">
        <p class="tf-step-num">01 <span class="tf-arrow">→</span></p>
        <h2 class="tf-question">¿Dónde quieres tu cita?</h2>
        <div class="tf-cards tf-cards--2">
          <button type="button" class="tf-card" data-field="location" data-value="malaga">
            <span class="tf-card-letter">A</span>
            <span class="tf-card-content">
              <strong>Málaga</strong>
              <small>Samuel · Graciela · Adrián · Alejandro</small>
            </span>
          </button>
          <button type="button" class="tf-card" data-field="location" data-value="torremolinos">
            <span class="tf-card-letter">B</span>
            <span class="tf-card-content">
              <strong>Torremolinos</strong>
              <small>Antonio · Juan · Graciela · Adrián</small>
            </span>
          </button>
        </div>
        <input type="hidden" name="location" id="tf-location">
        <p class="tf-error" id="err-location"></p>
      </div>
    </div>

    <!-- PASO 2: CÓMO -->
    <div class="tf-step" data-step="2">
      <div class="tf-step-inner">
        <p class="tf-step-num">02 <span class="tf-arrow">→</span></p>
        <h2 class="tf-question">¿Cómo prefieres reservar?</h2>
        <div class="tf-cards tf-cards--2">
          <button type="button" class="tf-card" data-field="booking_mode" data-value="auto">
            <span class="tf-card-letter">A</span>
            <span class="tf-card-content">
              <strong>⚡ Próxima disponible</strong>
              <small>Te asignamos el primer hueco libre</small>
            </span>
          </button>
          <button type="button" class="tf-card" data-field="booking_mode" data-value="barber">
            <span class="tf-card-letter">B</span>
            <span class="tf-card-content">
              <strong>💈 Elegir mi barbero</strong>
              <small>Tú decides quién te atiende</small>
            </span>
          </button>
        </div>
        <input type="hidden" name="booking_mode" id="tf-mode">
        <p class="tf-error" id="err-mode"></p>
      </div>
    </div>

    <!-- PASO 3: BARBERO (solo si mode=barber) -->
    <div class="tf-step" data-step="3">
      <div class="tf-step-inner">
        <p class="tf-step-num">03 <span class="tf-arrow">→</span></p>
        <h2 class="tf-question">¿Quién te atenderá?</h2>
        <div class="tf-barber-cards" id="tf-barber-cards"></div>
        <input type="hidden" name="barber_id" id="tf-barber-id" value="0">
        <p class="tf-error" id="err-barber"></p>
      </div>
    </div>

    <!-- PASO 4: FECHA -->
    <div class="tf-step" data-step="4">
      <div class="tf-step-inner">
        <p class="tf-step-num">04 <span class="tf-arrow">→</span></p>
        <h2 class="tf-question">¿Qué día?</h2>
        <input type="date" id="tf-date" name="date" class="tf-date-input"
               min="<?= esc_attr( $today ) ?>" max="<?= esc_attr( $max_day ) ?>" required>
        <p class="tf-error" id="err-date"></p>
        <div class="tf-actions">
          <button type="button" class="tf-btn-ok" id="tf-date-ok">
            Ok <span class="tf-check">✓</span>
          </button>
          <span class="tf-hint">pulsa <kbd>Enter ↵</kbd></span>
        </div>
      </div>
    </div>

    <!-- PASO 5: HORA -->
    <div class="tf-step" data-step="5">
      <div class="tf-step-inner">
        <p class="tf-step-num">05 <span class="tf-arrow">→</span></p>
        <h2 class="tf-question">Elige tu hora</h2>
        <div id="tf-slots-container">
          <p class="tf-muted">Cargando horarios disponibles…</p>
        </div>
        <input type="hidden" name="start_time" id="tf-start-time" required>
        <p class="tf-error" id="err-start_time"></p>
      </div>
    </div>

    <!-- PASO 6: SERVICIOS (base + adicionales) -->
    <div class="tf-step" data-step="6">
      <div class="tf-step-inner">
        <p class="tf-step-num">06 <span class="tf-arrow">→</span></p>
        <h2 class="tf-question">¿Qué servicio quieres?</h2>

        <!-- Servicio base (required) -->
        <div class="tf-service-section">
          <h3 class="tf-service-title">Servicio principal</h3>
          <div class="tf-cards tf-cards--3">
            <?php $letters = ['A','B','C','D','E','F']; $i = 0;
            foreach ( $base_services as $svc ) : ?>
            <button type="button" class="tf-card tf-card-base-service"
                    data-field="base_service_id"
                    data-value="<?= esc_attr( $svc['id'] ) ?>"
                    data-duration="<?= esc_attr( $svc['duration'] ) ?>">
              <span class="tf-card-letter"><?= $letters[ $i++ ] ?></span>
              <span class="tf-card-content">
                <strong><?= esc_html( $svc['name'] ) ?></strong>
                <small><?= esc_html( $svc['duration'] ) ?> min</small>
              </span>
            </button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Servicios adicionales (optional) -->
        <?php if ( ! empty( $additional_services ) ) : ?>
        <div class="tf-service-section">
          <h3 class="tf-service-title">Servicios adicionales (+20 min cada uno)</h3>
          <p class="tf-service-hint">Selecciona los que quieras. Se suman al servicio principal.</p>
          <div class="tf-checkboxes">
            <?php foreach ( $additional_services as $svc ) : ?>
            <label class="tf-checkbox-label">
              <input type="checkbox" class="tf-checkbox-input tf-additional-service"
                     name="additional_service_ids[]"
                     value="<?= esc_attr( $svc['id'] ) ?>"
                     data-duration="<?= esc_attr( $svc['duration'] ) ?>">
              <span class="tf-checkbox-custom"></span>
              <span class="tf-checkbox-text"><?= esc_html( $svc['name'] ) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <input type="hidden" name="base_service_id" id="tf-base-service-id" required>
        <p class="tf-error" id="err-base_service_id"></p>
        <p class="tf-service-summary" id="tf-service-summary"></p>
      </div>
    </div>

    <!-- PASO 7: NOMBRE -->
    <div class="tf-step" data-step="7">
      <div class="tf-step-inner">
        <p class="tf-step-num">07 <span class="tf-arrow">→</span></p>
        <h2 class="tf-question">¿Cómo te llamas?</h2>
        <input type="text" id="tf-customer-name" name="customer_name" class="tf-text-input"
               placeholder="Tu nombre completo" required maxlength="100" autocomplete="name">
        <p class="tf-error" id="err-customer_name"></p>
        <div class="tf-actions">
          <button type="button" class="tf-btn-ok" id="tf-name-ok">Ok <span class="tf-check">✓</span></button>
          <span class="tf-hint">pulsa <kbd>Enter ↵</kbd></span>
        </div>
      </div>
    </div>

    <!-- PASO 8: EMAIL -->
    <div class="tf-step" data-step="8">
      <div class="tf-step-inner">
        <p class="tf-step-num">08 <span class="tf-arrow">→</span></p>
        <h2 class="tf-question">¿Tu email?</h2>
        <p class="tf-sub">Te enviaremos la confirmación aquí</p>
        <input type="email" id="tf-customer-email" name="customer_email" class="tf-text-input"
               placeholder="tucorreo@ejemplo.com" required maxlength="150" autocomplete="email">
        <p class="tf-error" id="err-customer_email"></p>
        <div class="tf-actions">
          <button type="button" class="tf-btn-ok" id="tf-email-ok">Ok <span class="tf-check">✓</span></button>
          <span class="tf-hint">pulsa <kbd>Enter ↵</kbd></span>
        </div>
      </div>
    </div>

    <!-- PASO 9: TELÉFONO (opcional) -->
    <div class="tf-step" data-step="9">
      <div class="tf-step-inner">
        <p class="tf-step-num">09 <span class="tf-arrow">→</span></p>
        <h2 class="tf-question">¿Tu teléfono? (opcional)</h2>
        <input type="tel" id="tf-customer-phone" name="customer_phone" class="tf-text-input"
               placeholder="Ej: 600 123 456" maxlength="20" autocomplete="tel">
        <p class="tf-error" id="err-customer_phone"></p>
        <div class="tf-actions">
          <button type="button" class="tf-btn-ok" id="tf-phone-ok">Ok <span class="tf-check">✓</span></button>
          <span class="tf-hint">pulsa <kbd>Enter ↵</kbd> para saltar</span>
        </div>
      </div>
    </div>

    <!-- PASO 10: RESUMEN -->
    <div class="tf-step" data-step="10">
      <div class="tf-step-inner tf-summary">
        <p class="tf-step-num">10 <span class="tf-arrow">→</span></p>
        <h2 class="tf-question">Confirma tu cita</h2>
        <div class="tf-summary-grid">
          <div class="tf-summary-item"><span class="tf-sum-label">📍 Local</span>           <span class="tf-sum-value" id="sum-location">—</span></div>
          <div class="tf-summary-item"><span class="tf-sum-label">💈 Barbero/a</span>     <span class="tf-sum-value" id="sum-barber">—</span></div>
          <div class="tf-summary-item"><span class="tf-sum-label">📅 Fecha</span>           <span class="tf-sum-value" id="sum-date">—</span></div>
          <div class="tf-summary-item"><span class="tf-sum-label">🕐 Hora</span>            <span class="tf-sum-value" id="sum-time">—</span></div>
          <div class="tf-summary-item"><span class="tf-sum-label">✂️ Servicios</span>       <span class="tf-sum-value" id="sum-services">—</span></div>
          <div class="tf-summary-item"><span class="tf-sum-label">⏱️ Duración</span>        <span class="tf-sum-value" id="sum-duration">—</span></div>
          <div class="tf-summary-item"><span class="tf-sum-label">👤 Nombre</span>          <span class="tf-sum-value" id="sum-name">—</span></div>
          <div class="tf-summary-item"><span class="tf-sum-label">📧 Email</span>           <span class="tf-sum-value" id="sum-email">—</span></div>
          <div class="tf-summary-item"><span class="tf-sum-label">📱 Teléfono</span>       <span class="tf-sum-value" id="sum-phone">—</span></div>
        </div>
        <button type="submit" class="tf-btn-submit" id="six40-submit">
          <span class="tf-submit-text">Confirmar cita</span>
          <span class="tf-submit-loading tf-hidden">Enviando…</span>
        </button>
        <p class="tf-error tf-error--global tf-hidden" id="six40-global-error"></p>
      </div>
    </div>

  </form>

  <!-- Navegación prev/next -->
  <div class="tf-nav" id="tf-nav">
    <button type="button" class="tf-nav-btn" id="tf-prev" aria-label="Anterior">▲</button>
    <button type="button" class="tf-nav-btn" id="tf-next" aria-label="Siguiente">▼</button>
  </div>

  <!-- Pantalla de éxito -->
  <div class="tf-success tf-hidden" id="six40-success">
    <div class="tf-success-inner">
      <div class="tf-success-check">✓</div>
      <h2>¡Cita confirmada!</h2>
      <p>Revisa tu bandeja de entrada — te enviamos todos los detalles.</p>
      <button type="button" class="tf-btn-ok" id="six40-new-booking">Reservar otra cita</button>
    </div>
  </div>

</div>
