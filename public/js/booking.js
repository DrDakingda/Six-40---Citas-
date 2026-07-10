/* global six40Ajax, jQuery */
(function ($) {
  'use strict';

  var wrap    = document.getElementById('six40-booking');
  var form    = document.getElementById('six40-form');
  var success = document.getElementById('six40-success');
  if (!form) return;

  // ── Estado ──────────────────────────────────────────────────────────────────
  var TOTAL_STEPS    = 6;
  var currentStep    = 1;
  var skipBarberStep = false; // true si mode=auto

  var locLabels = { malaga: 'Málaga', torremolinos: 'Torremolinos' };

  var barbersByLocation = {
    malaga:       { 1:'Samuel Puertas', 2:'Graciela Arcos', 3:'Adrián Ortigosa', 4:'Alejandro Alfonso' },
    torremolinos: { 5:'Antonio Pérez',  6:'Graciela Arcos', 7:'Juan Jose García', 8:'Adrián Ortigosa'  }
  };

  var servicesCache   = {};   // { location: [categories] }
  var servicesById    = {};   // { id: {name,duration,price,price_from} }
  var loadedServicesFor = ''; // local para el que están pintados los servicios

  var TODAY = wrap.getAttribute('data-today') || null;
  var MAXD  = wrap.getAttribute('data-max')   || null;

  // ── Utilidades ──────────────────────────────────────────────────────────────
  function fmtPrice(v, from) {
    if (v === null || v === undefined || isNaN(v)) return '';
    var n = Number(v);
    var s = (n % 1 === 0) ? String(n) : n.toFixed(2).replace('.', ',');
    return (from ? 'desde ' : '') + s + ' €';
  }

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function ymd(y, m, d) { return y + '-' + pad(m + 1) + '-' + pad(d); }

  // ── Progreso ──────────────────────────────────────────────────────────────
  function updateProgress(step) {
    var pct = Math.round(((step - 1) / (TOTAL_STEPS - 1)) * 100);
    $('#tf-progress-bar').css('width', pct + '%');
    $('#tf-prev').prop('disabled', step <= 1);
    $('#tf-next').prop('disabled', step >= TOTAL_STEPS);
  }

  // ── Navegación ────────────────────────────────────────────────────────────
  // push: si es distinto de false, añade una entrada al historial del navegador
  // para que el botón "atrás" vuelva al paso anterior (y no salga a la home).
  function goTo(step, direction, push) {
    if (step < 1 || step > TOTAL_STEPS) return;

    // Saltar paso 3 (barbero) si modo=auto
    if (step === 3 && skipBarberStep) {
      step = direction === 'next' ? 4 : 2;
    }

    var $current = $('.tf-step.active');
    var $next    = $('.tf-step[data-step="' + step + '"]');
    if (!$next.length) return;

    $current.addClass(direction === 'next' ? 'exit-up' : 'exit-down').removeClass('active');
    setTimeout(function () { $current.removeClass('exit-up exit-down'); }, 420);

    $next.addClass(direction === 'next' ? 'enter-down' : 'enter-up');
    setTimeout(function () {
      $next.removeClass('enter-down enter-up').addClass('active');
      $next.find('.tf-text-input').first().trigger('focus');
    }, 30);

    currentStep = step;
    updateProgress(step);

    if (push !== false) {
      try { history.pushState({ six40Step: step }, ''); } catch (e) {}
    }

    if (step === 4) loadServices();
    if (step === 5) buildCalendar();
    if (step === 6) populateSummary();
  }

  function nextStep() {
    if (!validateStep(currentStep)) return;
    goTo(currentStep + 1, 'next');
  }
  function prevStep() {
    if (currentStep <= 1) return;
    history.back(); // dispara popstate → anima al paso anterior
  }

  $('#tf-prev').on('click', prevStep);
  $('#tf-next').on('click', function () { nextStep(); });

  // ── Historial: "atrás" del navegador = paso anterior, no salir de la página ──
  try { history.replaceState({ six40Step: 1 }, '' ); } catch (e) {}
  window.addEventListener('popstate', function (e) {
    var target = (e.state && e.state.six40Step) ? e.state.six40Step : 1;
    if (target === currentStep) return;
    goTo(target, target < currentStep ? 'prev' : 'next', false);
  });

  // ── Teclas ──────────────────────────────────────────────────────────────
  $(document).on('keydown', function (e) {
    if (!$(wrap).length) return;
    // En pasos con formularios/varias opciones no forzamos avance con flechas.
    if (e.key === 'Enter' && $(e.target).is('.tf-text-input')) {
      e.preventDefault();
      nextStep();
    }
  });

  // ── Cards de opción (local, modo) ──────────────────────────────────────────
  $(document).on('click', '.tf-card', function () {
    var $btn  = $(this);
    var field = $btn.data('field');
    var value = $btn.data('value');

    $btn.closest('.tf-cards').find('.tf-card').removeClass('selected');
    $btn.addClass('selected');
    $('#tf-' + field).val(value);

    if (field === 'location') {
      // Cambiar de local invalida barbero/servicios/fecha/hora
      resetDownstreamFromLocation(value);
    }
    if (field === 'booking_mode') {
      skipBarberStep = (value === 'auto');
      if (value === 'barber') renderBarberCards($('#tf-location').val());
      if (value === 'auto') { $('#tf-barber-id').val('0'); }
    }

    setTimeout(nextStep, 320);
  });

  function resetDownstreamFromLocation(loc) {
    $('#tf-barber-id').val('0');
    $('#tf-date').val('');
    $('#tf-start-time').val('');
    // Forzar recarga de servicios para el nuevo local
    if (loadedServicesFor && loadedServicesFor !== loc) {
      loadedServicesFor = '';
      $('#tf-services-container').html('<p class="tf-muted">Cargando servicios…</p>');
    }
  }

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
    setTimeout(nextStep, 320);
  });

  // ── Servicios (menú libre) ──────────────────────────────────────────────────
  function loadServices() {
    var location = $('#tf-location').val();
    if (!location) return;
    if (loadedServicesFor === location) return; // ya pintados

    if (servicesCache[location]) {
      renderServices(servicesCache[location]);
      loadedServicesFor = location;
      return;
    }

    $('#tf-services-container').html('<p class="tf-muted">' + six40Ajax.strings.loading + '</p>');

    $.post(six40Ajax.ajaxUrl, {
      action:   'six40_get_services',
      nonce:    six40Ajax.nonce,
      location: location
    }, function (res) {
      if (!res.success || !res.data || !res.data.categories) {
        $('#tf-services-container').html('<p class="tf-no-slots">' +
          (res.data && res.data.message ? res.data.message : six40Ajax.strings.error) + '</p>');
        return;
      }
      servicesCache[location] = res.data.categories;
      renderServices(res.data.categories);
      loadedServicesFor = location;
    }).fail(function () {
      $('#tf-services-container').html('<p class="tf-no-slots">' + six40Ajax.strings.error + '</p>');
    });
  }

  function renderServices(categories) {
    servicesById = {};
    var html = '';
    categories.forEach(function (cat) {
      html += '<div class="tf-svc-cat">';
      html += '<h3 class="tf-svc-cat-title">' + cat.label + '</h3>';
      html += '<div class="tf-svc-list">';
      cat.items.forEach(function (svc) {
        servicesById[svc.id] = svc;
        var price = fmtPrice(svc.price, svc.price_from);
        html += '<label class="tf-svc-item">' +
                  '<input type="checkbox" class="tf-service-input" value="' + svc.id + '" ' +
                    'data-duration="' + svc.duration + '" data-price="' + (svc.price || 0) + '" ' +
                    'data-from="' + (svc.price_from ? '1' : '0') + '">' +
                  '<span class="tf-svc-check"></span>' +
                  '<span class="tf-svc-info">' +
                    '<span class="tf-svc-name">' + svc.name + '</span>' +
                    '<span class="tf-svc-meta">' + svc.duration + ' min</span>' +
                  '</span>' +
                  '<span class="tf-svc-price">' + price + '</span>' +
                '</label>';
      });
      html += '</div></div>';
    });
    $('#tf-services-container').html(html);
    updateEstimate();
  }

  function selectedServiceIds() {
    var ids = [];
    $('.tf-service-input:checked').each(function () { ids.push(parseInt($(this).val(), 10)); });
    return ids;
  }

  // Duración total según reglas por combinación (espejo del backend).
  function computeDuration(list) {
    var hasCorte = false, corteTime = 0, beards = 0, big = false, sumAll = 0;
    list.forEach(function (s) {
      var cat = s.category || '', name = (s.name || '').toLowerCase(), dur = parseInt(s.duration, 10) || 0;
      sumAll += dur;
      if (cat === 'corte') { hasCorte = true; corteTime += dur; }
      else if (cat === 'barba') { beards++; }
      else if (cat === 'tratamiento') { if (name.indexOf('fantas') > -1 || name.indexOf('mecha') > -1) big = true; }
    });
    if (!hasCorte) return sumAll;
    var block = corteTime;
    if (beards > 0) block = 40;
    if (big) block = Math.max(block, 60);
    return block;
  }

  function serviceTotals() {
    var ids = selectedServiceIds();
    var totalPrice = 0, hasFrom = false, list = [];
    ids.forEach(function (id) {
      var s = servicesById[id];
      if (!s) return;
      totalPrice += parseFloat(s.price) || 0;
      if (s.price_from) hasFrom = true;
      list.push(s);
    });
    return { price: totalPrice, duration: computeDuration(list), from: hasFrom, count: ids.length };
  }

  function updateEstimate() {
    var t = serviceTotals();
    if (t.count === 0) {
      $('#tf-estimate-value').text('—');
    } else {
      $('#tf-estimate-value').text(fmtPrice(t.price, t.from) + ' · ' + t.duration + ' min');
    }
  }

  $(document).on('change', '.tf-service-input', function () {
    $('#err-services').text('');
    // Cambiar servicios cambia la duración → invalidar la hora ya elegida.
    $('#tf-start-time').val('');
    updateEstimate();
  });

  $('#tf-services-ok').on('click', nextStep);

  // ── Calendario (estilo Calendly) ────────────────────────────────────────────
  var calYear, calMonth; // mes mostrado

  function parseYmd(s) {
    var p = (s || '').split('-');
    return p.length === 3 ? { y: +p[0], m: +p[1] - 1, d: +p[2] } : null;
  }

  // ¿La fecha 'YYYY-MM-DD' cae dentro de algún rango de vacaciones?
  function inVacation(ds, ranges) {
    for (var i = 0; i < ranges.length; i++) {
      var r = ranges[i];
      if (r && r.start && r.end && ds >= r.start && ds <= r.end) { return true; }
    }
    return false;
  }

  function buildCalendar() {
    // Iniciar en el mes del día ya elegido, o en el mes de hoy.
    if (calYear === undefined) {
      var base = parseYmd($('#tf-date').val()) || parseYmd(TODAY) || null;
      if (base) { calYear = base.y; calMonth = base.m; }
      else { var n = new Date(); calYear = n.getFullYear(); calMonth = n.getMonth(); }
    }
    renderCalendar();
    // Si ya hay un día elegido, recargar sus horas (reflejan los servicios actuales).
    var d = $('#tf-date').val();
    if (d) {
      $('#tf-slots-day').text(formatDate(d));
      loadSlots();
    }
  }

  function renderCalendar() {
    var monthNames = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $('#tf-cal-title').text(monthNames[calMonth] + ' ' + calYear);

    var first = new Date(calYear, calMonth, 1);
    var startOffset = (first.getDay() + 6) % 7; // lunes primero
    var daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
    var selected = $('#tf-date').val();

    var closedWeekdays = (six40Ajax && six40Ajax.closedWeekdays) || [];
    var holidays       = (six40Ajax && six40Ajax.holidays) || [];

    // Vacaciones del barbero elegido (solo si se ha elegido barbero concreto).
    var vacRanges = [];
    if (!skipBarberStep) {
      var selBarber = parseInt($('#tf-barber-id').val(), 10) || 0;
      var allVac = (six40Ajax && six40Ajax.barberVacations) || {};
      if (selBarber && allVac[selBarber]) { vacRanges = allVac[selBarber]; }
    }

    var html = '';
    for (var i = 0; i < startOffset; i++) { html += '<span class="tf-cal-cell tf-cal-empty"></span>'; }
    for (var d = 1; d <= daysInMonth; d++) {
      var ds  = ymd(calYear, calMonth, d);
      var dow = new Date(calYear, calMonth, d).getDay(); // 0=domingo
      var closed = (closedWeekdays.indexOf(dow) !== -1) || (holidays.indexOf(ds) !== -1) || inVacation(ds, vacRanges);
      var disabled = (TODAY && ds < TODAY) || (MAXD && ds > MAXD) || closed;
      var cls = 'tf-cal-cell tf-cal-day'
              + (disabled ? ' tf-cal-disabled' : '')
              + (closed ? ' tf-cal-closed' : '')
              + (ds === selected ? ' tf-cal-selected' : '');
      html += '<button type="button" class="' + cls + '" data-date="' + ds + '"' + (disabled ? ' disabled' : '') + '>' + d + '</button>';
    }
    $('#tf-cal-grid').html(html);

    // Navegación: no ir antes de hoy ni después del máximo
    var prevDisabled = false, nextDisabled = false;
    if (TODAY) {
      var t = parseYmd(TODAY);
      prevDisabled = (calYear < t.y) || (calYear === t.y && calMonth <= t.m);
    }
    if (MAXD) {
      var mx = parseYmd(MAXD);
      nextDisabled = (calYear > mx.y) || (calYear === mx.y && calMonth >= mx.m);
    }
    $('#tf-cal-prev').prop('disabled', prevDisabled);
    $('#tf-cal-next').prop('disabled', nextDisabled);
  }

  $('#tf-cal-prev').on('click', function () {
    calMonth--; if (calMonth < 0) { calMonth = 11; calYear--; }
    renderCalendar();
  });
  $('#tf-cal-next').on('click', function () {
    calMonth++; if (calMonth > 11) { calMonth = 0; calYear++; }
    renderCalendar();
  });

  $(document).on('click', '.tf-cal-day:not(.tf-cal-disabled)', function () {
    var ds = $(this).data('date');
    $('.tf-cal-day').removeClass('tf-cal-selected');
    $(this).addClass('tf-cal-selected');
    $('#tf-date').val(ds);
    $('#tf-start-time').val('');
    $('#tf-slots-day').text(formatDate(ds));
    loadSlots();
  });

  // ── Carga de slots ────────────────────────────────────────────────────────
  function loadSlots() {
    var location = $('#tf-location').val();
    var date     = $('#tf-date').val();
    var ids      = selectedServiceIds();
    var barberId = skipBarberStep ? 0 : (parseInt($('#tf-barber-id').val(), 10) || 0);

    if (!date || !location || !ids.length) {
      $('#tf-slots-container').html('<p class="tf-muted">Completa los pasos anteriores primero.</p>');
      return;
    }

    $('#tf-slots-container').html(
      '<div class="tf-slots-loading"><div class="tf-spinner"></div><span>' + six40Ajax.strings.loading + '</span></div>'
    );

    $.post(six40Ajax.ajaxUrl, {
      action:      'six40_get_slots',
      nonce:       six40Ajax.nonce,
      location:    location,
      date:        date,
      barber_id:   barberId,
      service_ids: ids
    }, function (res) {
      if (!res.success || !res.data || !res.data.slots) {
        $('#tf-slots-container').html('<div class="tf-no-slots">' +
          (res.data && res.data.message ? res.data.message : six40Ajax.strings.error) + '</div>');
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
    var current = $('#tf-start-time').val();
    var html = '<div class="tf-slots-grid">';
    slots.forEach(function (s) {
      var sel = (s === current) ? ' selected' : '';
      html += '<button type="button" class="tf-slot' + sel + '" data-time="' + s + '">' + s + '</button>';
    });
    html += '</div>';
    $('#tf-slots-container').html(html);
  }

  $(document).on('click', '.tf-slot', function () {
    $('.tf-slot').removeClass('selected');
    $(this).addClass('selected');
    $('#tf-start-time').val($(this).data('time'));
    $('#err-start_time').text('');
    setTimeout(nextStep, 320);
  });

  // ── Validación ────────────────────────────────────────────────────────────
  function clearErrors() { $('.tf-error').text(''); }

  function validateStep(step) {
    clearErrors();
    if (step === 1 && !$('#tf-location').val()) {
      $('#err-location').text('Por favor, selecciona un local.'); return false;
    }
    if (step === 2 && !$('#tf-booking_mode').val()) {
      $('#err-booking_mode').text('Por favor, elige cómo quieres reservar.'); return false;
    }
    if (step === 3 && !skipBarberStep && (!$('#tf-barber-id').val() || $('#tf-barber-id').val() === '0')) {
      $('#err-barber').text('Por favor, selecciona un barbero.'); return false;
    }
    if (step === 4 && selectedServiceIds().length === 0) {
      $('#err-services').text('Elige al menos un servicio.'); return false;
    }
    if (step === 5 && (!$('#tf-date').val() || !$('#tf-start-time').val())) {
      $('#err-start_time').text('Selecciona un día y una hora.'); return false;
    }
    if (step === 6) {
      var name = $('#tf-customer-name').val().trim();
      if (!name || name.length < 2) { $('#err-customer_name').text('Introduce tu nombre completo.'); return false; }
      var email = $('#tf-customer-email').val().trim();
      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        $('#err-customer_email').text('Introduce un email válido.'); return false;
      }
      var phone = $('#tf-customer-phone').val().trim();
      var digits = phone.replace(/\D/g, '');
      if (!phone || digits.length < 9) {
        $('#err-customer_phone').text('Introduce un teléfono válido.'); return false;
      }
    }
    return true;
  }

  // ── Resumen ───────────────────────────────────────────────────────────────
  function populateSummary() {
    var location = $('#tf-location').val() || '';
    var barberId = $('#tf-barber-id').val() || '0';

    var barberName = 'Primer disponible';
    if (!skipBarberStep && barberId !== '0' && barbersByLocation[location]) {
      barberName = barbersByLocation[location][barberId] || barberName;
    }

    var ids = selectedServiceIds();
    var names = ids.map(function (id) { return servicesById[id] ? servicesById[id].name : ''; }).filter(Boolean);
    var t = serviceTotals();

    $('#sum-location').text(locLabels[location] || location);
    $('#sum-barber').text(barberName);
    $('#sum-services').text(names.join(' + ') || '—');
    $('#sum-duration').text(t.duration > 0 ? t.duration + ' min' : '—');
    $('#sum-date').text(formatDate($('#tf-date').val()));
    $('#sum-time').text($('#tf-start-time').val() || '—');
    $('#sum-price').text(t.count ? fmtPrice(t.price, t.from) : '—');
  }

  function formatDate(d) {
    if (!d) return '—';
    var p = d.split('-');
    var months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    var days   = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
    var dt = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
    return days[dt.getDay()] + ', ' + dt.getDate() + ' de ' + months[dt.getMonth()] + ' de ' + dt.getFullYear();
  }

  // ── Submit ────────────────────────────────────────────────────────────────
  $(form).on('submit', function (e) {
    e.preventDefault();
    if (!validateStep(6)) return;

    var $btn = $('#six40-submit');
    $btn.prop('disabled', true).find('.tf-submit-text').addClass('tf-hidden');
    $btn.find('.tf-submit-loading').removeClass('tf-hidden');
    $('#six40-global-error').addClass('tf-hidden').text('');

    $.post(six40Ajax.ajaxUrl, {
      action:         'six40_submit_booking',
      nonce:          six40Ajax.nonce,
      location:       $('#tf-location').val(),
      service_ids:    selectedServiceIds(),
      date:           $('#tf-date').val(),
      start_time:     $('#tf-start-time').val(),
      customer_name:  $('#tf-customer-name').val().trim(),
      customer_email: $('#tf-customer-email').val().trim(),
      customer_phone: $('#tf-customer-phone').val().trim(),
      barber_id:      skipBarberStep ? 0 : ($('#tf-barber-id').val() || 0)
    }, function (res) {
      $btn.prop('disabled', false).find('.tf-submit-text').removeClass('tf-hidden');
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
      $btn.prop('disabled', false).find('.tf-submit-text').removeClass('tf-hidden');
      $btn.find('.tf-submit-loading').addClass('tf-hidden');
      $('#six40-global-error').removeClass('tf-hidden').text(six40Ajax.strings.error);
    });
  });

  // ── Nueva reserva ─────────────────────────────────────────────────────────
  $('#six40-new-booking').on('click', function () {
    form.reset();
    $('#tf-location, #tf-booking_mode, #tf-date, #tf-start-time').val('');
    $('#tf-barber-id').val('0');
    $('.tf-card, .tf-barber-card, .tf-slot').removeClass('selected');
    $('.tf-service-input').prop('checked', false);
    skipBarberStep = false;
    loadedServicesFor = '';
    calYear = undefined; calMonth = undefined;
    clearErrors();
    $('#tf-services-container').html('<p class="tf-muted">Cargando servicios…</p>');
    $('#tf-slots-container').html('<p class="tf-muted">Elige primero un día en el calendario.</p>');
    $('#tf-slots-day').text('Selecciona un día');
    $(success).addClass('tf-hidden');
    $(form).removeClass('tf-hidden');
    $('#tf-nav').removeClass('tf-hidden');
    $('.tf-progress').removeClass('tf-hidden');
    $('.tf-step').removeClass('active exit-up exit-down enter-down enter-up');
    $('.tf-step[data-step="1"]').addClass('active');
    currentStep = 1;
    updateProgress(1);
    try { history.replaceState({ six40Step: 1 }, '' ); } catch (e) {}
  });

  // ── Init ──────────────────────────────────────────────────────────────────
  updateProgress(1);

})(jQuery);
