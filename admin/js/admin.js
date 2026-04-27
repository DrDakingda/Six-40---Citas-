/* global six40Admin, FullCalendar */
(function ($) {
  'use strict';

  function toast(msg, type) {
    var $t = $('<div class="six40-toast ' + (type||'success') + '">' + msg + '</div>');
    $('body').append($t);
    setTimeout(function(){ $t.addClass('show'); }, 10);
    setTimeout(function(){ $t.removeClass('show'); setTimeout(function(){ $t.remove(); },300); }, 3000);
  }

  // Status select: citas
  $(document).on('focus', '.six40-status-select', function(){ $(this).data('previous', $(this).val()); });
  $(document).on('change', '.six40-status-select', function(){
    var $sel = $(this), id = $sel.data('id'), status = $sel.val(), $row = $sel.closest('tr');
    $.post(six40Admin.ajaxUrl, { action:'six40_update_appt_status', nonce:six40Admin.nonce, id:id, status:status }, function(res){
      if (res.success) {
        var labels = {confirmed:'Confirmada',completed:'Completada',cancelled:'Cancelada',no_show:'No presentó'};
        $row.find('.six40-status').attr('class','six40-status six40-status--'+status).text(labels[status]||status);
        toast('Estado actualizado.','success');
      } else {
        toast('Error: '+(res.data||'No se pudo actualizar.'),'error');
        $sel.val($sel.data('previous')||'confirmed');
      }
    }).fail(function(){ toast('Error de conexión.','error'); });
  });

  // Barber status buttons
  $(document).on('click', '.six40-barber-btn', function(){
    var $btn = $(this), bid = $btn.data('id'), status = $btn.data('status'), $card = $btn.closest('.six40-barber-card');
    $btn.prop('disabled',true).text('…');
    $.post(six40Admin.ajaxUrl, { action:'six40_update_barber_status', nonce:six40Admin.nonce, barber_id:bid, status:status }, function(res){
      if (res.success) {
        var labels    = {available:'Disponible',vacation:'Vacaciones',sick:'Baja'};
        var btnLabels = {available:'✅ Disponible',vacation:'🏖️ Vacaciones',sick:'🤒 Baja'};
        $card.attr('class','six40-barber-card six40-barber--'+status);
        $card.find('.six40-barber-status-label').attr('class','six40-status six40-status--'+status+' six40-barber-status-label').text(labels[status]);
        $card.find('.six40-barber-btn').each(function(){
          var $b=$(this); $b.prop('disabled',false).text(btnLabels[$b.data('status')]).toggleClass('active',$b.data('status')===status);
        });
        toast('Barbero actualizado.','success');
      } else {
        toast('Error: '+(res.data||'No se pudo actualizar.'),'error');
        $btn.prop('disabled',false);
      }
    }).fail(function(){ toast('Error de conexión.','error'); $btn.prop('disabled',false); });
  });

  // FullCalendar
  var calEl = document.getElementById('six40-calendar');
  if (!calEl) return;

  var calInstance = null;

  function loadCalendar() {
    if (typeof FullCalendar !== 'undefined') { initCalendar(); return; }
    var link = document.createElement('link');
    link.rel = 'stylesheet'; link.href = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css';
    document.head.appendChild(link);
    var script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js';
    script.onload = initCalendar; document.head.appendChild(script);
  }

  function initCalendar() {
    calInstance = new FullCalendar.Calendar(calEl, {
      initialView: 'dayGridMonth', locale: 'es',
      headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek,timeGridDay' },
      buttonText: { today:'Hoy', month:'Mes', week:'Semana', day:'Día' },
      height: 'auto', events: fetchEvents,
      eventClick: function(info) {
        var p = info.event.extendedProps;
        alert('Cliente: '+info.event.title+'\nLocal: '+p.location+'\nEstado: '+p.status+'\nEmail: '+p.email);
      }
    });
    calInstance.render();
  }

  function fetchEvents(info, ok, fail) {
    var loc = $('#six40-calendar-location').val()||'';
    $.get(six40Admin.ajaxUrl, { action:'six40_get_appointments_json', nonce:six40Admin.nonce, location:loc }, function(res){
      res.success ? ok(res.data) : fail(res.data);
    }).fail(fail);
  }

  $(document).on('change','#six40-calendar-location', function(){ if(calInstance) calInstance.refetchEvents(); });

  loadCalendar();

  // ── Days-off mini-calendar ─────────────────────────────────────────────────
  var daysOffState = {}; // { barberId: { yearMonth, offDays: Set } }

  var MONTH_NAMES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

  function todayStr() {
    var d = new Date();
    return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate());
  }

  function pad(n) { return String(n).padStart(2,'0'); }

  function renderMiniCal(barberId) {
    var state = daysOffState[barberId];
    var ym    = state.yearMonth;
    var parts = ym.split('-');
    var year  = parseInt(parts[0]);
    var month = parseInt(parts[1]) - 1;
    var first = new Date(year, month, 1);
    var last  = new Date(year, month + 1, 0);
    var today = todayStr();

    var startDow = first.getDay();
    startDow = (startDow === 0) ? 6 : startDow - 1;

    var html = '<div class="six40-mini-cal">';
    html += '<div class="six40-mini-cal-header">';
    html += '<button class="six40-cal-nav" data-dir="-1" data-id="' + barberId + '">&#8249;</button>';
    html += '<span>' + MONTH_NAMES[month] + ' ' + year + '</span>';
    html += '<button class="six40-cal-nav" data-dir="1" data-id="' + barberId + '">&#8250;</button>';
    html += '</div>';
    html += '<table class="six40-mini-cal-table"><thead><tr>';
    ['L','M','X','J','V','S','D'].forEach(function(d){ html += '<th>' + d + '</th>'; });
    html += '</tr></thead><tbody><tr>';

    for (var i = 0; i < startDow; i++) html += '<td></td>';
    var cells = startDow;

    for (var day = 1; day <= last.getDate(); day++) {
      var dateStr = ym + '-' + pad(day);
      var isOff  = state.offDays.has(dateStr);
      var isPast = dateStr < today;
      var cls = 'six40-cal-day' + (isOff ? ' is-off' : '') + (isPast ? ' is-past' : '');
      html += '<td><button class="' + cls + '" data-id="' + barberId + '" data-date="' + dateStr + '"' + (isPast ? ' disabled' : '') + '>' + day + '</button></td>';
      cells++;
      if (cells % 7 === 0 && day < last.getDate()) html += '</tr><tr>';
    }

    var rem = cells % 7;
    if (rem > 0) { for (var j = rem; j < 7; j++) html += '<td></td>'; }
    html += '</tr></tbody></table>';
    html += '<div class="six40-cal-legend">';
    html += '<span><span class="dot" style="background:var(--six40-red)"></span> Día libre</span>';
    html += '</div></div>';
    return html;
  }

  function loadDaysOff(barberId, yearMonth, callback) {
    if (!daysOffState[barberId]) daysOffState[barberId] = { yearMonth: yearMonth, offDays: new Set() };
    daysOffState[barberId].yearMonth = yearMonth;
    $.get(six40Admin.ajaxUrl, {
      action: 'six40_get_days_off', nonce: six40Admin.nonce,
      barber_id: barberId, year_month: yearMonth
    }, function(res) {
      if (res.success) daysOffState[barberId].offDays = new Set(res.data);
      if (callback) callback();
    }).fail(function() { if (callback) callback(); });
  }

  $(document).on('click', '.six40-days-off-btn', function() {
    var bid  = $(this).data('id');
    var $panel = $('#days-off-' + bid);
    if ($panel.is(':visible')) { $panel.slideUp(200); return; }
    var ym = (new Date()).getFullYear() + '-' + pad((new Date()).getMonth()+1);
    if (!daysOffState[bid]) daysOffState[bid] = { yearMonth: ym, offDays: new Set() };
    daysOffState[bid].yearMonth = ym;
    $panel.slideDown(200);
    $('#cal-wrap-' + bid).html('<div class="six40-cal-loading">Cargando…</div>');
    loadDaysOff(bid, ym, function() {
      $('#cal-wrap-' + bid).html(renderMiniCal(bid));
    });
  });

  $(document).on('click', '.six40-days-off-close', function() {
    var bid = $(this).data('id');
    $('#days-off-' + bid).slideUp(200);
  });

  $(document).on('click', '.six40-cal-nav', function() {
    var bid = $(this).data('id');
    var dir = parseInt($(this).data('dir'));
    var state = daysOffState[bid];
    if (!state) return;
    var p = state.yearMonth.split('-');
    var d = new Date(parseInt(p[0]), parseInt(p[1]) - 1 + dir, 1);
    var newYM = d.getFullYear() + '-' + pad(d.getMonth()+1);
    state.yearMonth = newYM;
    $('#cal-wrap-' + bid).html('<div class="six40-cal-loading">Cargando…</div>');
    loadDaysOff(bid, newYM, function() {
      $('#cal-wrap-' + bid).html(renderMiniCal(bid));
    });
  });

  $(document).on('click', '.six40-cal-day:not(.is-past)', function() {
    var $btn = $(this);
    var bid  = $btn.data('id');
    var date = $btn.data('date');
    if ($btn.prop('disabled')) return;
    $btn.prop('disabled', true);
    $.post(six40Admin.ajaxUrl, {
      action: 'six40_toggle_day_off', nonce: six40Admin.nonce,
      barber_id: bid, date: date
    }, function(res) {
      if (res.success) {
        var state = daysOffState[bid];
        if (state.offDays.has(date)) {
          state.offDays.delete(date);
          toast('Día restaurado: ' + date, 'success');
        } else {
          state.offDays.add(date);
          toast('Día libre: ' + date, 'success');
        }
        $('#cal-wrap-' + bid).html(renderMiniCal(bid));
      } else {
        toast('Error: ' + (res.data || 'No se pudo actualizar.'), 'error');
        $btn.prop('disabled', false);
      }
    }).fail(function() {
      toast('Error de conexión.', 'error');
      $btn.prop('disabled', false);
    });
  });

})(jQuery);
