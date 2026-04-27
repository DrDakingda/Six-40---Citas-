/* global six40Ajax, jQuery */
(function ($) {
  'use strict';

  var form         = document.getElementById('six40-form');
  var successPanel = document.getElementById('six40-success');
  if (!form) return;

  var currentStep = 1;

  var barbersByLocation = {
    malaga: { 1:'Samuel Puertas', 2:'Graciela Arcos', 3:'Adrián Ortigosa', 4:'Alejandro Alfonso' },
    torremolinos: { 5:'Antonio Pérez', 6:'Graciela Arcos', 7:'Juan Jose García', 8:'Adrián Ortigosa' }
  };
  var serviceLabels  = { barba:'Barba (15 min)', corte:'Corte (30 min)', corte_barba:'Corte + Barba (45 min)' };
  var locationLabels = { malaga:'Málaga', torremolinos:'Torremolinos' };

  // ── Navegación ────────────────────────────────────────────────────────────
  function goToStep(step) {
    $(form).find('.six40-form-step').addClass('six40-hidden');
    $(form).find('.six40-form-step[data-step="' + step + '"]').removeClass('six40-hidden');
    currentStep = step;
    updateIndicators(step);
    if (step === 6) populateSummary();
    $('html,body').animate({ scrollTop: $('#six40-booking').offset().top - 60 }, 250);
  }

  function updateIndicators(active) {
    $('.six40-step').each(function () {
      var s = parseInt($(this).data('step'), 10);
      $(this).removeClass('active done');
      if (s === active) $(this).addClass('active');
      else if (s < active) $(this).addClass('done');
    });
    $('.six40-step-connector').each(function (i) {
      $(this).toggleClass('done', i + 1 < active);
    });
  }

  // ── Validación ────────────────────────────────────────────────────────────
  function clearErrors() {
    $('.six40-field-error').text('');
    $('.six40-input').removeClass('invalid');
  }

  function showError(id, msg) {
    $('#err-' + id).text(msg);
    if (['name','email','date'].indexOf(id) !== -1) $('#six40-' + id).addClass('invalid');
  }

  function validateStep(step) {
    clearErrors();
    var valid = true;
    if (step === 1) {
      if (!$('input[name="location"]:checked').val()) { showError('location','Por favor, selecciona un local.'); valid=false; }
    }
    if (step === 2) {
      var mode = $('input[name="booking_mode"]:checked').val();
      if (!mode) { showError('mode','Por favor, elige cómo quieres reservar.'); valid=false; }
      if (mode === 'barber' && (!$('#six40-barber-id').val() || $('#six40-barber-id').val() === '0')) {
        showError('barber','Por favor, selecciona un barbero.'); valid=false;
      }
    }
    if (step === 3) {
      if (!$('#six40-date').val()) { showError('date','Por favor, selecciona una fecha.'); valid=false; }
      if (!$('#six40-time').val()) { showError('time','Por favor, selecciona una hora.'); valid=false; }
    }
    if (step === 4) {
      if (!$('input[name="service"]:checked').val()) { showError('service','Por favor, selecciona un servicio.'); valid=false; }
    }
    if (step === 5) {
      var name  = $('#six40-name').val().trim();
      var email = $('#six40-email').val().trim();
      if (!name || name.length < 2) { showError('name','Por favor, introduce tu nombre completo.'); valid=false; }
      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showError('email','Por favor, introduce un email válido.'); valid=false; }
    }
    return valid;
  }

  $(document).on('click', '.six40-btn-next', function () {
    if (validateStep(currentStep)) goToStep(parseInt($(this).data('next'), 10));
  });
  $(document).on('click', '.six40-btn-prev', function () {
    clearErrors(); goToStep(parseInt($(this).data('prev'), 10));
  });

  // ── Modo reserva ──────────────────────────────────────────────────────────
  $(document).on('change', 'input[name="booking_mode"]', function () {
    var mode     = $(this).val();
    var location = $('input[name="location"]:checked').val();
    if (mode === 'barber') {
      renderBarberCards(location);
      $('#six40-barber-picker').removeClass('six40-hidden');
    } else {
      $('#six40-barber-picker').addClass('six40-hidden');
      $('#six40-barber-id').val('0');
    }
    resetSlots();
  });

  function renderBarberCards(location) {
    var barbers = barbersByLocation[location] || {};
    var html = '';
    $.each(barbers, function (id, name) {
      html += '<div class="six40-barber-pick-card" data-id="' + id + '">' +
              '<div class="six40-barber-pick-avatar">' + name.charAt(0) + '</div>' +
              '<div class="six40-barber-pick-name">' + name + '</div>' +
              '</div>';
    });
    $('#six40-barber-cards').html(html);
  }

  $(document).on('click', '.six40-barber-pick-card', function () {
    $('.six40-barber-pick-card').removeClass('selected');
    $(this).addClass('selected');
    $('#six40-barber-id').val($(this).data('id'));
    $('#err-barber').text('');
    resetSlots();
  });

  // ── Slots ─────────────────────────────────────────────────────────────────
  $('#six40-date').on('change', function () {
    var date     = $(this).val();
    var location = $('input[name="location"]:checked').val();
    var bid      = $('#six40-barber-id').val() || '0';
    $('#six40-time').val(''); $('#err-time').text('');
    if (!date || !location) { resetSlots(); return; }
    loadSlots(location, date, bid);
  });

  function loadSlots(location, date, barberId) {
    $('#six40-slots-container').html('<p class="six40-slots-loading"><span class="six40-spinner"></span>' + six40Ajax.strings.loading + '</p>');
    $.post(six40Ajax.ajaxUrl, {
      action:'six40_get_slots', nonce:six40Ajax.nonce,
      location:location, date:date, service:'corte', barber_id:barberId
    }, function (res) {
      if (!res.success || !res.data) { $('#six40-slots-container').html('<div class="six40-no-slots">' + six40Ajax.strings.error + '</div>'); return; }
      renderSlots(res.data.slots);
    }).fail(function () { $('#six40-slots-container').html('<div class="six40-no-slots">' + six40Ajax.strings.error + '</div>'); });
  }

  function renderSlots(slots) {
    if (!slots || !slots.length) { $('#six40-slots-container').html('<div class="six40-no-slots">' + six40Ajax.strings.noSlots + '</div>'); return; }
    var html = '<div class="six40-slots-grid">';
    slots.forEach(function (s) { html += '<button type="button" class="six40-slot-btn" data-time="' + s + '">' + s + '</button>'; });
    html += '</div>';
    $('#six40-slots-container').html(html);
  }

  function resetSlots() {
    $('#six40-time').val('');
    $('#six40-date').val('');
    $('#six40-slots-container').html('<p class="six40-slots-hint">' + six40Ajax.strings.selectDate + '</p>');
  }

  $(document).on('click', '.six40-slot-btn', function () {
    $('.six40-slot-btn').removeClass('selected');
    $(this).addClass('selected');
    $('#six40-time').val($(this).data('time'));
    $('#err-time').text('');
  });

  // ── Resumen ───────────────────────────────────────────────────────────────
  function populateSummary() {
    var location = $('input[name="location"]:checked').val() || '';
    var barberId = $('#six40-barber-id').val() || '0';
    var mode     = $('input[name="booking_mode"]:checked').val() || '';
    var service  = $('input[name="service"]:checked').val() || '';
    var barberName = (mode === 'barber' && barberId !== '0' && barbersByLocation[location])
      ? (barbersByLocation[location][barberId] || 'Primer disponible')
      : 'Primer disponible';

    $('#sum-location').text(locationLabels[location] || location);
    $('#sum-barber').text(barberName);
    $('#sum-date').text(formatDate($('#six40-date').val()));
    $('#sum-time').text($('#six40-time').val());
    $('#sum-service').text(serviceLabels[service] || service);
    $('#sum-name').text($('#six40-name').val().trim());
    $('#sum-email').text($('#six40-email').val().trim());
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
    $btn.prop('disabled',true).find('.six40-submit-text').addClass('six40-hidden');
    $btn.find('.six40-submit-loading').removeClass('six40-hidden');
    $('#six40-global-error').addClass('six40-hidden').text('');

    $.post(six40Ajax.ajaxUrl, {
      action:'six40_submit_booking', nonce:six40Ajax.nonce,
      location:$('input[name="location"]:checked').val(),
      service:$('input[name="service"]:checked').val(),
      date:$('#six40-date').val(), time:$('#six40-time').val(),
      name:$('#six40-name').val().trim(), email:$('#six40-email').val().trim(),
      barber_id:$('#six40-barber-id').val() || 0
    }, function (res) {
      $btn.prop('disabled',false).find('.six40-submit-text').removeClass('six40-hidden');
      $btn.find('.six40-submit-loading').addClass('six40-hidden');
      if (res.success) {
        $(form).addClass('six40-hidden'); $('.six40-steps').addClass('six40-hidden'); $(successPanel).removeClass('six40-hidden');
      } else {
        var msg = (res.data && res.data.message) ? res.data.message : six40Ajax.strings.error;
        $('#six40-global-error').removeClass('six40-hidden').text(msg);
        $('html,body').animate({ scrollTop:$('#six40-global-error').offset().top - 80 }, 250);
      }
    }).fail(function () {
      $btn.prop('disabled',false).find('.six40-submit-text').removeClass('six40-hidden');
      $btn.find('.six40-submit-loading').addClass('six40-hidden');
      $('#six40-global-error').removeClass('six40-hidden').text(six40Ajax.strings.error);
    });
  });

  $('#six40-new-booking').on('click', function () {
    form.reset();
    $('#six40-barber-id').val('0');
    $('#six40-barber-picker').addClass('six40-hidden');
    $('#six40-barber-cards').html('');
    resetSlots(); clearErrors();
    $(successPanel).addClass('six40-hidden'); $(form).removeClass('six40-hidden'); $('.six40-steps').removeClass('six40-hidden');
    goToStep(1);
  });

  goToStep(1);

})(jQuery);
