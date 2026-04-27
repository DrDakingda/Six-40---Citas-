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

})(jQuery);
