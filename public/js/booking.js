/* global six40Ajax, jQuery */
(function ($) {
  'use strict';

  var form         = document.getElementById('six40-form');
  var successPanel = document.getElementById('six40-success');
  if (!form) return;

  var currentStep = 1;

  // ── Service & location labels ─────────────────────────────────────────────
  var serviceLabels = {
    barba:       'Barba (15 min)',
    corte:       'Corte (30 min)',
    corte_barba: 'Corte + Barba (45 min)'
  };

  var locationLabels = {
    malaga:       'Málaga',
    torremolinos: 'Torremolinos'
  };

  // ── Navigation ────────────────────────────────────────────────────────────
  function goToStep(step) {
    var $form = $(form);
    $form.find('.six40-form-step').addClass('six40-hidden');
    $form.find('.six40-form-step[data-step="' + step + '"]').removeClass('six40-hidden');
    currentStep = step;
    updateStepIndicators(step);

    // If going to summary, populate it.
    if (step === 4) populateSummary();

    // Scroll to form top.
    $('html, body').animate({ scrollTop: $('#six40-booking').offset().top - 60 }, 300);
  }

  function updateStepIndicators(active) {
    $('.six40-step').each(function () {
      var s = parseInt($(this).data('step'), 10);
      $(this).removeClass('active done');
      if (s === active) $(this).addClass('active');
      else if (s < active) $(this).addClass('done');
    });
    // Connectors.
    $('.six40-step-connector').each(function (i) {
      $(this).toggleClass('done', i + 1 < active);
    });
  }

  // ── Validation ────────────────────────────────────────────────────────────
  function clearErrors() {
    $('.six40-field-error').text('');
    $('.six40-input').removeClass('invalid');
  }

  function showError(fieldId, msg) {
    $('#err-' + fieldId).text(msg);
    if (fieldId !== 'location' && fieldId !== 'service' && fieldId !== 'time') {
      $('#six40-' + fieldId).addClass('invalid');
    }
  }

  function validateStep(step) {
    clearErrors();
    var valid = true;

    if (step === 1) {
      if (!$('input[name="location"]:checked').val()) {
        showError('location', 'Por favor, selecciona un local.');
        valid = false;
      }
      if (!$('input[name="service"]:checked').val()) {
        showError('service', 'Por favor, selecciona un servicio.');
        valid = false;
      }
    }

    if (step === 2) {
      var date = $('#six40-date').val();
      if (!date) {
        showError('date', 'Por favor, selecciona una fecha.');
        valid = false;
      }
      var time = $('#six40-time').val();
      if (!time) {
        showError('time', 'Por favor, selecciona una hora.');
        valid = false;
      }
    }

    if (step === 3) {
      var name = $('#six40-name').val().trim();
      if (!name || name.length < 2) {
        showError('name', 'Por favor, introduce tu nombre completo.');
        valid = false;
      }
      var email = $('#six40-email').val().trim();
      if (!email || !isValidEmail(email)) {
        showError('email', 'Por favor, introduce un email válido.');
        valid = false;
      }
    }

    return valid;
  }

  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  // ── Next / Prev ───────────────────────────────────────────────────────────
  $(document).on('click', '.six40-btn-next', function () {
    var next = parseInt($(this).data('next'), 10);
    if (validateStep(currentStep)) {
      goToStep(next);
    }
  });

  $(document).on('click', '.six40-btn-prev', function () {
    var prev = parseInt($(this).data('prev'), 10);
    clearErrors();
    goToStep(prev);
  });

  // ── Date change → load slots ──────────────────────────────────────────────
  $('#six40-date').on('change', function () {
    var date     = $(this).val();
    var location = $('input[name="location"]:checked').val();
    var service  = $('input[name="service"]:checked').val();

    // Reset time selection.
    $('#six40-time').val('');
    $('#six40-barber-id').val('0');
    $('#err-time').text('');

    if (!date || !location || !service) {
      $('#six40-slots-container').html(
        '<p class="six40-slots-hint">' + (six40Ajax.strings.selectDate) + '</p>'
      );
      return;
    }

    loadSlots(location, date, service);
  });

  // Also reload slots if location/service changes while on step 2.
  $('input[name="location"], input[name="service"]').on('change', function () {
    var date = $('#six40-date').val();
    if (currentStep === 2 && date) {
      var location = $('input[name="location"]:checked').val();
      var service  = $('input[name="service"]:checked').val();
      if (location && service) loadSlots(location, date, service);
    }
  });

  function loadSlots(location, date, service) {
    var $container = $('#six40-slots-container');
    $container.html(
      '<p class="six40-slots-loading"><span class="six40-spinner"></span>' +
      six40Ajax.strings.loading + '</p>'
    );

    $.post(six40Ajax.ajaxUrl, {
      action:   'six40_get_slots',
      nonce:    six40Ajax.nonce,
      location: location,
      date:     date,
      service:  service
    }, function (res) {
      if (!res.success || !res.data || !res.data.slots) {
        $container.html('<div class="six40-no-slots">' + six40Ajax.strings.error + '</div>');
        return;
      }
      renderSlots(res.data.slots);
    }).fail(function () {
      $container.html('<div class="six40-no-slots">' + six40Ajax.strings.error + '</div>');
    });
  }

  function renderSlots(slots) {
    var $container = $('#six40-slots-container');
    if (!slots || slots.length === 0) {
      $container.html('<div class="six40-no-slots">' + six40Ajax.strings.noSlots + '</div>');
      return;
    }
    var html = '<div class="six40-slots-grid">';
    slots.forEach(function (slot) {
      html += '<button type="button" class="six40-slot-btn" data-time="' + slot + '">' + slot + '</button>';
    });
    html += '</div>';
    $container.html(html);
  }

  $(document).on('click', '.six40-slot-btn', function () {
    var $btn = $(this);
    var time = $btn.data('time');
    $('.six40-slot-btn').removeClass('selected');
    $btn.addClass('selected');
    $('#six40-time').val(time);
    $('#err-time').text('');
  });

  // ── Summary ───────────────────────────────────────────────────────────────
  function populateSummary() {
    var location = $('input[name="location"]:checked').val() || '';
    var service  = $('input[name="service"]:checked').val() || '';
    var date     = $('#six40-date').val() || '';
    var time     = $('#six40-time').val() || '';
    var name     = $('#six40-name').val().trim() || '';
    var email    = $('#six40-email').val().trim() || '';

    $('#sum-location').text(locationLabels[location] || location);
    $('#sum-service').text(serviceLabels[service] || service);
    $('#sum-date').text(formatDate(date));
    $('#sum-time').text(time);
    $('#sum-name').text(name);
    $('#sum-email').text(email);
  }

  function formatDate(dateStr) {
    if (!dateStr) return '—';
    var parts = dateStr.split('-');
    if (parts.length !== 3) return dateStr;
    var months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    var d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
    var days = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
    return days[d.getDay()] + ', ' + d.getDate() + ' de ' + months[d.getMonth()] + ' de ' + d.getFullYear();
  }

  // ── Form submit ───────────────────────────────────────────────────────────
  $(form).on('submit', function (e) {
    e.preventDefault();
    if (!validateStep(4)) return;

    var $btn = $('#six40-submit');
    $btn.prop('disabled', true);
    $btn.find('.six40-submit-text').addClass('six40-hidden');
    $btn.find('.six40-submit-loading').removeClass('six40-hidden');
    $('#six40-global-error').addClass('six40-hidden').text('');

    var data = {
      action:    'six40_submit_booking',
      nonce:     six40Ajax.nonce,
      location:  $('input[name="location"]:checked').val(),
      service:   $('input[name="service"]:checked').val(),
      date:      $('#six40-date').val(),
      time:      $('#six40-time').val(),
      name:      $('#six40-name').val().trim(),
      email:     $('#six40-email').val().trim(),
      barber_id: $('#six40-barber-id').val() || 0
    };

    $.post(six40Ajax.ajaxUrl, data, function (res) {
      $btn.prop('disabled', false);
      $btn.find('.six40-submit-text').removeClass('six40-hidden');
      $btn.find('.six40-submit-loading').addClass('six40-hidden');

      if (res.success) {
        $(form).addClass('six40-hidden');
        $('.six40-steps').addClass('six40-hidden');
        $(successPanel).removeClass('six40-hidden');
      } else {
        var msg = (res.data && res.data.message) ? res.data.message : six40Ajax.strings.error;
        $('#six40-global-error').removeClass('six40-hidden').text(msg);
        $('html, body').animate({ scrollTop: $('#six40-global-error').offset().top - 80 }, 300);
      }
    }).fail(function () {
      $btn.prop('disabled', false);
      $btn.find('.six40-submit-text').removeClass('six40-hidden');
      $btn.find('.six40-submit-loading').addClass('six40-hidden');
      $('#six40-global-error').removeClass('six40-hidden').text(six40Ajax.strings.error);
    });
  });

  // ── New booking reset ─────────────────────────────────────────────────────
  $('#six40-new-booking').on('click', function () {
    form.reset();
    $('#six40-time').val('');
    $('#six40-barber-id').val('0');
    $('#six40-slots-container').html(
      '<p class="six40-slots-hint">' + six40Ajax.strings.selectDate + '</p>'
    );
    clearErrors();
    $(successPanel).addClass('six40-hidden');
    $(form).removeClass('six40-hidden');
    $('.six40-steps').removeClass('six40-hidden');
    goToStep(1);
  });

  // ── Init ──────────────────────────────────────────────────────────────────
  goToStep(1);

})(jQuery);
