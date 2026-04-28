/* global six40Ajax, jQuery */
(function ($) {
  'use strict';

  var wrap    = document.getElementById('six40-booking');
  var form    = document.getElementById('six40-form');
  var success = document.getElementById('six40-success');
  if (!form) return;

  // ── Datos ─────────────────────────────────────────────────────────────────
  var TOTAL_STEPS = 9;
  var currentStep = 1;
  var skipBarberStep = false; // true si mode=auto

  var barbersByLocation = {
    malaga:       { 1:'Samuel Puertas', 2:'Graciela Arcos', 3:'Adrián Ortigosa', 4:'Alejandro Alfonso' },
    torremolinos: { 5:'Antonio Pérez',  6:'Graciela Arcos', 7:'Juan Jose García', 8:'Adrián Ortigosa'  }
  };

  var svcLabels  = { barba:'Barba (15 min)', corte:'Corte (30 min)', corte_barba:'Corte + Barba (45 min)' };
  var locLabels  = { malaga:'Málaga', torremolinos:'Torremolinos' };

  // ── Progreso ──────────────────────────────────────────────────────────────
  function updateProgress(step) {
    var pct = Math.round(((step - 1) / (TOTAL_STEPS - 1)) * 100);
    $('#tf-progress-bar').css('width', pct + '%');
    $('#tf-prev').prop('disabled', step <= 1);
    $('#tf-next').prop('disabled', step >= TOTAL_STEPS);
  }

  // ── Navegación ────────────────────────────────────────────────────────────
  function goTo(step, direction) {
    if (step < 1 || step > TOTAL_STEPS) return;

    // Saltar paso 3 (barbero) si modo=auto
    if (step === 3 && skipBarberStep) {
      step = direction === 'next' ? 4 : 2;
    }

    var $current = $('.tf-step.active');
    var $next    = $('.tf-step[data-step="' + step + '"]');

    if (!$next.length) return;

    // Animar salida
    $current.addClass(direction === 'next' ? 'exit-up' : 'exit-down').removeClass('active');
    setTimeout(function () { $current.removeClass('exit-up exit-down'); }, 420);

    // Animar entrada
    $next.addClass(direction === 'next' ? 'enter-down' : 'enter-up');
    setTimeout(function () {
      $next.removeClass('enter-down enter-up').addClass('active');
      // Focus en primer input del paso
      $next.find('.tf-text-input, .tf-date-input').first().trigger('focus');
    }, 30);

    currentStep = step;
    updateProgress(step);

    // Si llega al paso resumen, rellenarlo
    if (step === 9) populateSummary();

    // Si llega al paso de slots (5), cargar
    if (step === 5) loadSlots();
  }

  function nextStep() {
    if (!validateStep(currentStep)) return;
    goTo(currentStep + 1, 'next');
  }

  function prevStep() {
    goTo(currentStep - 1, 'prev');
  }

  // ── Botones nav ──────────────────────────────────────────────────────────
  $('#tf-prev').on('click', prevStep);
  $('#tf-next').on('click', function () { nextStep(); });

  // ── Tecla Enter / Escape ──────────────────────────────────────────────────
  $(document).on('keydown', function (e) {
    if (!$(wrap).length) return;
    // Solo si el foco está dentro del formulario
    if (e.key === 'Enter' && !$(e.target).is('textarea')) {
      e.preventDefault();
      nextStep();
    }
    if (e.key === 'ArrowDown') { e.preventDefault(); nextStep(); }
    if (e.key === 'ArrowUp')   { e.preventDefault(); prevStep(); }
  });

  // ── Cards de opción (auto-avance) ─────────────────────────────────────────
  $(document).on('click', '.tf-card', function () {
    var $btn   = $(this);
    var field  = $btn.data('field');
    var value  = $btn.data('value');

    // Deseleccionar hermanos
    $btn.closest('.tf-cards').find('.tf-card').removeClass('selected');
    $btn.addClass('selected');

    // Guardar valor en hidden input
    $('#tf-' + field).val(value);

    // Lógica especial: si elige modo
    if (field === 'booking_mode') {
      skipBarberStep = (value === 'auto');
      if (value === 'barber') renderBarberCards($('#tf-location').val());
    }

    // Auto-avanzar tras breve pausa
    setTimeout(nextStep, 380);
  });

  // ── Barberos ──────────────────────────────────────────────────────────────
  function renderBarberCards(location) {
    var barbers = barbersByLocation[location] || {};
    var html = '';
    $.each(barbers, function (id, name) {
      html += '<button type="button" class="tf-barber-card" data-id="' + id + '">' +
              '<div class="tf-barber-avatar">' + name.charAt(0) + '</div>' +
              '<div class="tf-barber-name">' + name + '</div>' +
              '</button>';
    });
    $('#tf-barber-cards').html(html);
  }

  $(document).on('click', '.tf-barber-card', function () {
    $('.tf-barber-card').removeClass('selected');
    $(this).addClass('selected');
    $('#tf-barber-id').val($(this).data('id'));
    setTimeout(nextStep, 380);
  });

  // ── Botones Ok y fecha ────────────────────────────────────────────────────
  $('#tf-date-ok').on('click', nextStep);
  $('#tf-name-ok').on('click', nextStep);
  $('#tf-email-ok').on('click', nextStep);

  $('#tf-date').on('change', function () {
    // Limpiar hora cuando cambia la fecha
    $('#tf-time').val('');
    $('.tf-slot').removeClass('selected');
  });

  // ── Carga de slots ────────────────────────────────────────────────────────
  function loadSlots() {
    var location = $('#tf-location').val();
    var date     = $('#tf-date').val();
    var barberId = $('#tf-barber-id').val() || '0';

    if (!date || !location) {
      $('#tf-slots-container').html('<p class="tf-muted">Selecciona primero la fecha.</p>');
      return;
    }

    $('#tf-slots-container').html(
      '<div class="tf-slots-loading"><div class="tf-spinner"></div><span>' + six40Ajax.strings.loading + '</span></div>'
    );

    $.post(six40Ajax.ajaxUrl, {
      action:    'six40_get_slots',
      nonce:     six40Ajax.nonce,
      location:  location,
      date:      date,
      service:   'corte',
      barber_id: barberId
    }, function (res) {
      if (!res.success || !res.data || !res.data.slots) {
        $('#tf-slots-container').html('<div class="tf-no-slots">' + six40Ajax.strings.error + '</div>');
        return;
      }
      renderSlots(res.data.slots);
    }).fail(function () {
      $('#tf-slots-container').html('<div class="tf-no-slots">' + six40Ajax.strings.error + '</div>');
    });
  }

  function renderSlots(slots) {
    if (!slots || !slots.length) {
      $('#tf-slots-container').html('<div class="tf-no-slots">' + six40Ajax.strings.noSlots + '</div>');
      return;
    }

    var currentTime = $('#tf-time').val();
    var html = '<div class="tf-slots-grid">';
    slots.forEach(function (s) {
      var sel = (s === currentTime) ? ' selected' : '';
      html += '<button type="button" class="tf-slot' + sel + '" data-time="' + s + '">' + s + '</button>';
    });
    html += '</div>';
    $('#tf-slots-container').html(html);
  }

  $(document).on('click', '.tf-slot', function () {
    $('.tf-slot').removeClass('selected');
    $(this).addClass('selected');
    $('#tf-time').val($(this).data('time'));
    $('#err-time').text('');
    // Auto-avanzar tras elegir hora
    setTimeout(nextStep, 380);
  });

  // ── Validación ────────────────────────────────────────────────────────────
  function clearErrors() { $('.tf-error').text(''); }

  function validateStep(step) {
    clearErrors();
    if (step === 1 && !$('#tf-location').val()) {
      $('#err-location').text('Por favor, selecciona un local.'); return false;
    }
    if (step === 2 && !$('#tf-mode').val()) {
      $('#err-mode').text('Por favor, elige cómo quieres reservar.'); return false;
    }
    if (step === 3 && !skipBarberStep && (!$('#tf-barber-id').val() || $('#tf-barber-id').val() === '0')) {
      $('#err-barber').text('Por favor, selecciona un barbero.'); return false;
    }
    if (step === 4 && !$('#tf-date').val()) {
      $('#err-date').text('Por favor, selecciona una fecha.'); return false;
    }
    if (step === 5 && !$('#tf-time').val()) {
      $('#err-time').text('Por favor, selecciona una hora.'); return false;
    }
    if (step === 6 && !$('#tf-service').val()) {
      $('#err-service').text('Por favor, selecciona un servicio.'); return false;
    }
    if (step === 7) {
      var name = $('#tf-name').val().trim();
      if (!name || name.length < 2) { $('#err-name').text('Introduce tu nombre completo.'); return false; }
    }
    if (step === 8) {
      var email = $('#tf-email').val().trim();
      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        $('#err-email').text('Introduce un email válido.'); return false;
      }
    }
    return true;
  }

  // ── Resumen ───────────────────────────────────────────────────────────────
  function populateSummary() {
    var location = $('#tf-location').val() || '';
    var barberId = $('#tf-barber-id').val() || '0';
    var mode     = $('#tf-mode').val() || '';
    var service  = $('#tf-service').val() || '';

    var barberName = 'Primer disponible';
    if (mode === 'barber' && barberId !== '0' && barbersByLocation[location]) {
      barberName = barbersByLocation[location][barberId] || 'Primer disponible';
    }

    $('#sum-location').text(locLabels[location] || location);
    $('#sum-barber').text(barberName);
    $('#sum-date').text(formatDate($('#tf-date').val()));
    $('#sum-time').text($('#tf-time').val());
    $('#sum-service').text(svcLabels[service] || service);
    $('#sum-name').text($('#tf-name').val().trim());
    $('#sum-email').text($('#tf-email').val().trim());
  }

  function formatDate(d) {
    if (!d) return '—';
    var p = d.split('-');
    var months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    var days   = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
    var dt = new Date(parseInt(p[0]), parseInt(p[1])-1, parseInt(p[2]));
    return days[dt.getDay()] + ', ' + dt.getDate() + ' de ' + months[dt.getMonth()] + ' de ' + dt.getFullYear();
  }

  // ── Submit ────────────────────────────────────────────────────────────────
  $(form).on('submit', function (e) {
    e.preventDefault();
    var $btn = $('#six40-submit');
    $btn.prop('disabled',true).find('.tf-submit-text').addClass('tf-hidden');
    $btn.find('.tf-submit-loading').removeClass('tf-hidden');
    $('#six40-global-error').addClass('tf-hidden').text('');

    $.post(six40Ajax.ajaxUrl, {
      action:    'six40_submit_booking',
      nonce:     six40Ajax.nonce,
      location:  $('#tf-location').val(),
      service:   $('#tf-service').val(),
      date:      $('#tf-date').val(),
      time:      $('#tf-time').val(),
      name:      $('#tf-name').val().trim(),
      email:     $('#tf-email').val().trim(),
      barber_id: $('#tf-barber-id').val() || 0
    }, function (res) {
      $btn.prop('disabled',false).find('.tf-submit-text').removeClass('tf-hidden');
      $btn.find('.tf-submit-loading').addClass('tf-hidden');
      if (res.success) {
        $(form).addClass('tf-hidden');
        $('#tf-nav').addClass('tf-hidden');
        $('.tf-progress').addClass('tf-hidden');
        $(success).removeClass('tf-hidden');
      } else {
        var msg = (res.data && res.data.message) ? res.data.message : six40Ajax.strings.error;
        $('#six40-global-error').removeClass('tf-hidden').text(msg);
      }
    }).fail(function () {
      $btn.prop('disabled',false).find('.tf-submit-text').removeClass('tf-hidden');
      $btn.find('.tf-submit-loading').addClass('tf-hidden');
      $('#six40-global-error').removeClass('tf-hidden').text(six40Ajax.strings.error);
    });
  });

  // ── Nueva reserva ─────────────────────────────────────────────────────────
  $('#six40-new-booking').on('click', function () {
    form.reset();
    $('#tf-location, #tf-mode, #tf-service, #tf-time').val('');
    $('#tf-barber-id').val('0');
    $('.tf-card, .tf-barber-card, .tf-slot').removeClass('selected');
    skipBarberStep = false;
    clearErrors();
    $(success).addClass('tf-hidden');
    $(form).removeClass('tf-hidden');
    $('#tf-nav').removeClass('tf-hidden');
    $('.tf-progress').removeClass('tf-hidden');
    $('.tf-step').removeClass('active exit-up exit-down enter-down enter-up');
    goTo(1, 'next');
  });

  // ── Init ──────────────────────────────────────────────────────────────────
  updateProgress(1);

})(jQuery);
