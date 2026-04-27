<?php
defined( 'ABSPATH' ) || exit;

$services  = [
    'barba'       => [ 'label' => 'Barba',         'desc' => '15 min', 'icon' => '🪒' ],
    'corte'       => [ 'label' => 'Corte',         'desc' => '30 min', 'icon' => '✂️' ],
    'corte_barba' => [ 'label' => 'Corte + Barba', 'desc' => '45 min', 'icon' => '💈' ],
];
$locations = [ 'malaga' => 'Málaga', 'torremolinos' => 'Torremolinos' ];
$today     = wp_date( 'Y-m-d' );
$max_day   = wp_date( 'Y-m-d', strtotime( '+60 days' ) );
?>
<div class="six40-booking-wrap" id="six40-booking">

  <div class="six40-steps">
    <?php foreach ( [1=>'Local &amp; Servicio',2=>'Fecha &amp; Hora',3=>'Tus datos',4=>'Confirmar'] as $n=>$lbl ) : ?>
    <?php if ($n>1): ?><div class="six40-step-connector"></div><?php endif; ?>
    <div class="six40-step <?= $n===1?'active':'' ?>" data-step="<?= $n ?>">
      <span class="six40-step-num"><?= $n ?></span>
      <span class="six40-step-label"><?= $lbl ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <form id="six40-form" novalidate>
    <?php wp_nonce_field( 'six40_booking_nonce', 'six40_nonce' ); ?>

    <!-- Step 1 -->
    <div class="six40-form-step" data-step="1">
      <h2 class="six40-step-title">Elige tu local y servicio</h2>
      <div class="six40-field-group">
        <label class="six40-field-label">📍 Local</label>
        <div class="six40-cards-row">
          <?php foreach ( $locations as $k=>$v ) : ?>
          <label class="six40-card-option">
            <input type="radio" name="location" value="<?= esc_attr($k) ?>" required>
            <span class="six40-card-body"><span class="six40-card-title"><?= esc_html($v) ?></span></span>
          </label>
          <?php endforeach; ?>
        </div>
        <div class="six40-field-error" id="err-location"></div>
      </div>
      <div class="six40-field-group">
        <label class="six40-field-label">✂️ Servicio</label>
        <div class="six40-cards-row">
          <?php foreach ( $services as $k=>$s ) : ?>
          <label class="six40-card-option">
            <input type="radio" name="service" value="<?= esc_attr($k) ?>" required>
            <span class="six40-card-body">
              <span class="six40-card-icon"><?= $s['icon'] ?></span>
              <span class="six40-card-title"><?= esc_html($s['label']) ?></span>
              <span class="six40-card-desc"><?= esc_html($s['desc']) ?></span>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
        <div class="six40-field-error" id="err-service"></div>
      </div>
      <div class="six40-step-actions">
        <button type="button" class="six40-btn six40-btn-next" data-next="2">Siguiente →</button>
      </div>
    </div>

    <!-- Step 2 -->
    <div class="six40-form-step six40-hidden" data-step="2">
      <h2 class="six40-step-title">Elige fecha y hora</h2>
      <div class="six40-date-time-row">
        <div class="six40-field-group">
          <label class="six40-field-label" for="six40-date">📅 Fecha</label>
          <input type="date" id="six40-date" name="date" class="six40-input"
                 min="<?= esc_attr($today) ?>" max="<?= esc_attr($max_day) ?>" required>
          <div class="six40-field-error" id="err-date"></div>
        </div>
        <div class="six40-field-group">
          <label class="six40-field-label">🕐 Hora disponible</label>
          <div id="six40-slots-container">
            <p class="six40-slots-hint">Selecciona una fecha para ver los horarios.</p>
          </div>
          <input type="hidden" name="time" id="six40-time" required>
          <input type="hidden" name="barber_id" id="six40-barber-id" value="0">
          <div class="six40-field-error" id="err-time"></div>
        </div>
      </div>
      <div class="six40-step-actions">
        <button type="button" class="six40-btn six40-btn-outline six40-btn-prev" data-prev="1">← Atrás</button>
        <button type="button" class="six40-btn six40-btn-next" data-next="3">Siguiente →</button>
      </div>
    </div>

    <!-- Step 3 -->
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
        <p class="six40-field-hint">Te enviaremos la confirmación a este email.</p>
        <div class="six40-field-error" id="err-email"></div>
      </div>
      <div class="six40-step-actions">
        <button type="button" class="six40-btn six40-btn-outline six40-btn-prev" data-prev="2">← Atrás</button>
        <button type="button" class="six40-btn six40-btn-next" data-next="4">Revisar cita →</button>
      </div>
    </div>

    <!-- Step 4 -->
    <div class="six40-form-step six40-hidden" data-step="4">
      <h2 class="six40-step-title">Resumen de tu cita</h2>
      <div class="six40-summary-card">
        <div class="six40-summary-row"><span class="six40-summary-label">📍 Local</span>    <span class="six40-summary-value" id="sum-location">—</span></div>
        <div class="six40-summary-row"><span class="six40-summary-label">✂️ Servicio</span> <span class="six40-summary-value" id="sum-service">—</span></div>
        <div class="six40-summary-row"><span class="six40-summary-label">📅 Fecha</span>    <span class="six40-summary-value" id="sum-date">—</span></div>
        <div class="six40-summary-row"><span class="six40-summary-label">🕐 Hora</span>     <span class="six40-summary-value" id="sum-time">—</span></div>
        <div class="six40-summary-row"><span class="six40-summary-label">👤 Nombre</span>   <span class="six40-summary-value" id="sum-name">—</span></div>
        <div class="six40-summary-row"><span class="six40-summary-label">📧 Email</span>    <span class="six40-summary-value" id="sum-email">—</span></div>
      </div>
      <div class="six40-step-actions">
        <button type="button" class="six40-btn six40-btn-outline six40-btn-prev" data-prev="3">← Atrás</button>
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
