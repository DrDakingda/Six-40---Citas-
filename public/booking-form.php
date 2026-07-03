<?php
defined( 'ABSPATH' ) || exit;

$today   = wp_date( 'Y-m-d' );
$max_day = wp_date( 'Y-m-d', strtotime( '+60 days' ) );
?>
<div class="tf-wrap" id="six40-booking" data-today="<?= esc_attr( $today ) ?>" data-max="<?= esc_attr( $max_day ) ?>">

  <!-- Barra de progreso -->
  <div class="tf-progress"><div class="tf-progress-bar" id="tf-progress-bar"></div></div>

  <form id="six40-form" novalidate>
    <?php wp_nonce_field( 'six40_booking_nonce', 'six40_nonce' ); ?>

    <!-- PASO 1: LOCAL -->
    <div class="tf-step active" data-step="1">
      <div class="tf-step-inner">
        <p class="tf-step-num">01 <span class="tf-arrow">→</span></p>
        <h2 class="tf-question">¿DÓNDE QUIERES TU CITA?</h2>
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
        <h2 class="tf-question">¿CÓMO PREFIERES RESERVAR?</h2>
        <div class="tf-cards tf-cards--2">
          <button type="button" class="tf-card" data-field="booking_mode" data-value="auto">
            <span class="tf-card-letter">A</span>
            <span class="tf-card-content">
              <strong>⚡ Primera cita disponible</strong>
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
        <input type="hidden" name="booking_mode" id="tf-booking_mode">
        <p class="tf-error" id="err-booking_mode"></p>
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

    <!-- PASO 4: SERVICIOS (menú libre) -->
    <div class="tf-step" data-step="4">
      <div class="tf-step-inner tf-step-inner--wide">
        <p class="tf-step-num">04 <span class="tf-arrow">→</span></p>
        <h2 class="tf-question">¿Qué te vas a hacer?</h2>
        <p class="tf-sub">Elige uno o varios servicios.</p>
        <div id="tf-services-container">
          <p class="tf-muted">Cargando servicios…</p>
        </div>
        <p class="tf-error" id="err-services"></p>
        <div class="tf-actions">
          <div class="tf-estimate" id="tf-estimate">
            <span class="tf-estimate-label">Precio orientativo</span>
            <span class="tf-estimate-value" id="tf-estimate-value">—</span>
            <span class="tf-estimate-note">Se paga en el local</span>
          </div>
          <button type="button" class="tf-btn-ok" id="tf-services-ok">
            Continuar <span class="tf-check">→</span>
          </button>
        </div>
      </div>
    </div>

    <!-- PASO 5: FECHA + HORA -->
    <div class="tf-step" data-step="5">
      <div class="tf-step-inner tf-step-inner--wide">
        <p class="tf-step-num">05 <span class="tf-arrow">→</span></p>
        <h2 class="tf-question">Elige fecha y hora</h2>
        <div class="tf-cal-layout">
          <div class="tf-cal" id="tf-cal">
            <div class="tf-cal-head">
              <button type="button" class="tf-cal-nav" id="tf-cal-prev" aria-label="Mes anterior">‹</button>
              <span class="tf-cal-title" id="tf-cal-title"></span>
              <button type="button" class="tf-cal-nav" id="tf-cal-next" aria-label="Mes siguiente">›</button>
            </div>
            <div class="tf-cal-weekdays">
              <span>lun</span><span>mar</span><span>mié</span><span>jue</span><span>vie</span><span>sáb</span><span>dom</span>
            </div>
            <div class="tf-cal-grid" id="tf-cal-grid"></div>
          </div>
          <div class="tf-slots-panel">
            <p class="tf-slots-day" id="tf-slots-day">Selecciona un día</p>
            <div id="tf-slots-container">
              <p class="tf-muted">Elige primero un día en el calendario.</p>
            </div>
          </div>
        </div>
        <input type="hidden" name="date" id="tf-date">
        <input type="hidden" name="start_time" id="tf-start-time">
        <p class="tf-error" id="err-start_time"></p>
      </div>
    </div>

    <!-- PASO 6: DATOS + CONFIRMAR -->
    <div class="tf-step" data-step="6">
      <div class="tf-step-inner tf-step-inner--wide">
        <p class="tf-step-num">06 <span class="tf-arrow">→</span></p>
        <h2 class="tf-question">Tus datos</h2>

        <div class="tf-form-grid">
          <div class="tf-field">
            <label class="tf-label" for="tf-customer-name">Nombre *</label>
            <input type="text" id="tf-customer-name" name="customer_name" class="tf-text-input"
                   placeholder="Tu nombre completo" required maxlength="100" autocomplete="name">
            <p class="tf-error" id="err-customer_name"></p>
          </div>
          <div class="tf-field">
            <label class="tf-label" for="tf-customer-email">Email *</label>
            <input type="email" id="tf-customer-email" name="customer_email" class="tf-text-input"
                   placeholder="tucorreo@ejemplo.com" required maxlength="150" autocomplete="email">
            <p class="tf-error" id="err-customer_email"></p>
          </div>
          <div class="tf-field">
            <label class="tf-label" for="tf-customer-phone">Teléfono *</label>
            <input type="tel" id="tf-customer-phone" name="customer_phone" class="tf-text-input"
                   placeholder="Ej: 600 123 456" required maxlength="20" autocomplete="tel">
            <p class="tf-error" id="err-customer_phone"></p>
          </div>
        </div>

        <div class="tf-summary-box">
          <h3 class="tf-summary-title">Resumen de tu cita</h3>
          <div class="tf-summary-grid">
            <div class="tf-summary-item"><span class="tf-sum-label">📍 Local</span>      <span class="tf-sum-value" id="sum-location">—</span></div>
            <div class="tf-summary-item"><span class="tf-sum-label">💈 Barbero/a</span>  <span class="tf-sum-value" id="sum-barber">—</span></div>
            <div class="tf-summary-item"><span class="tf-sum-label">✂️ Servicios</span>  <span class="tf-sum-value" id="sum-services">—</span></div>
            <div class="tf-summary-item"><span class="tf-sum-label">⏱️ Duración</span>   <span class="tf-sum-value" id="sum-duration">—</span></div>
            <div class="tf-summary-item"><span class="tf-sum-label">📅 Fecha</span>      <span class="tf-sum-value" id="sum-date">—</span></div>
            <div class="tf-summary-item"><span class="tf-sum-label">🕐 Hora</span>       <span class="tf-sum-value" id="sum-time">—</span></div>
            <div class="tf-summary-item tf-summary-total"><span class="tf-sum-label">💶 Precio orientativo</span> <span class="tf-sum-value" id="sum-price">—</span></div>
          </div>
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
