/* global six40Ajax, jQuery */
(function ($) {
  'use strict';

  var form = document.getElementById('six40-form');
  var successPanel = document.getElementById('six40-success');
  if (!form) return;

  var currentStep = 1;
  var serviceLabels  = { barba:'Barba (15 min)', corte:'Corte (30 min)', corte_barba:'Corte + Barba (45 min)' };
  var locationLabels = { malaga:'Málaga', torremolinos:'Torremolinos' };

  function goToStep(step) {
    $(form).find('.six40-form-step').addClass('six40-hidden');
    $(form).find('.six40-form-step[data-step="'+step+'"]').removeClass('six40-hidden');
    currentStep = step;
    updateIndicators(step);
    if (step === 4) populateSummary();
    $('html,body').animate({ scrollTop: $('#six40-booking').offset().top - 60 }, 300);
  }

  function updateIndicators(active) {
    $('.six40-step').each(function(){
      var s = parseInt($(this).data('step'), 10);
      $(this).removeClass('active done');
      if (s === active) $(this).addClass('active');
      else if (s < active) $(this).addClass('done');
    });
    $('.six40-step-connector').each(function(i){ $(this).toggleClass('done', i+1 < active); });
  }

  function clearErrors() {
    $('.six40-field-error').text('');
    $('.six40-input').removeClass('invalid');
  }

  function showError(id, msg) {
    $('#err-'+id).text(msg);
    if (!['location','service','time'].includes(id)) $('#six40-'+id).addClass('invalid');
  }

  function validateStep(step) {
    clearErrors(); var valid = true;
    if (step === 1) {
      if (!$('input[name="location"]:checked').val()) { showError('location','Por favor, selecciona un local.'); valid=false; }
      if (!$('input[name="service"]:checked').val())  { showError('service','Por favor, selecciona un servicio.'); valid=false; }
    }
    if (step === 2) {
      if (!$('#six40-date').val()) { showError('date','Por favor, selecciona una fecha.'); valid=false; }
      if (!$('#six40-time').val()) { showError('time','Por favor, selecciona una hora.'); valid=false; }
    }
    if (step === 3) {
      var name  = $('#six40-name').val().trim();
      var email = $('#six40-email').val().trim();
      if (!name||name.length<2)  { showError('name','Por favor, introduce tu nombre completo.'); valid=false; }
      if (!email||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showError('email','Por favor, introduce un email válido.'); valid=false; }
    }
    return valid;
  }

  $(document).on('click','.six40-btn-next', function(){
    var next = parseInt($(this).data('next'),10);
    if (validateStep(currentStep)) goToStep(next);
  });

  $(document).on('click','.six40-btn-prev', function(){
    clearErrors(); goToStep(parseInt($(this).data('prev'),10));
  });

  $('#six40-date').on('change', function(){
    var date=$(this).val(), loc=$('input[name="location"]:checked').val(), svc=$('input[name="service"]:checked').val();
    $('#six40-time').val(''); $('#six40-barber-id').val('0'); $('#err-time').text('');
    if (!date||!loc||!svc) { $('#six40-slots-container').html('<p class="six40-slots-hint">'+six40Ajax.strings.selectDate+'</p>'); return; }
    loadSlots(loc,date,svc);
  });

  $('input[name="location"],input[name="service"]').on('change', function(){
    var date=$('#six40-date').val();
    if (currentStep===2&&date) {
      var loc=$('input[name="location"]:checked').val(), svc=$('input[name="service"]:checked').val();
      if (loc&&svc) loadSlots(loc,date,svc);
    }
  });

  function loadSlots(loc, date, svc) {
    $('#six40-slots-container').html('<p class="six40-slots-loading"><span class="six40-spinner"></span>'+six40Ajax.strings.loading+'</p>');
    $.post(six40Ajax.ajaxUrl, { action:'six40_get_slots', nonce:six40Ajax.nonce, location:loc, date:date, service:svc }, function(res){
      if (!res.success||!res.data) { $('#six40-slots-container').html('<div class="six40-no-slots">'+six40Ajax.strings.error+'</div>'); return; }
      renderSlots(res.data.slots);
    }).fail(function(){ $('#six40-slots-container').html('<div class="six40-no-slots">'+six40Ajax.strings.error+'</div>'); });
  }

  function renderSlots(slots) {
    if (!slots||!slots.length) { $('#six40-slots-container').html('<div class="six40-no-slots">'+six40Ajax.strings.noSlots+'</div>'); return; }
    var html='<div class="six40-slots-grid">';
    slots.forEach(function(s){ html+='<button type="button" class="six40-slot-btn" data-time="'+s+'">'+s+'</button>'; });
    html+='</div>';
    $('#six40-slots-container').html(html);
  }

  $(document).on('click','.six40-slot-btn', function(){
    $('.six40-slot-btn').removeClass('selected');
    $(this).addClass('selected');
    $('#six40-time').val($(this).data('time'));
    $('#err-time').text('');
  });

  function populateSummary() {
    var loc=$('input[name="location"]:checked').val()||'', svc=$('input[name="service"]:checked').val()||'',
        date=$('#six40-date').val()||'', time=$('#six40-time').val()||'',
        name=$('#six40-name').val().trim(), email=$('#six40-email').val().trim();
    $('#sum-location').text(locationLabels[loc]||loc);
    $('#sum-service').text(serviceLabels[svc]||svc);
    $('#sum-date').text(formatDate(date)); $('#sum-time').text(time);
    $('#sum-name').text(name); $('#sum-email').text(email);
  }

  function formatDate(d) {
    if(!d) return '—';
    var p=d.split('-'), months=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'],
        days=['domingo','lunes','martes','miércoles','jueves','viernes','sábado'],
        dt=new Date(parseInt(p[0]),parseInt(p[1])-1,parseInt(p[2]));
    return days[dt.getDay()]+', '+dt.getDate()+' de '+months[dt.getMonth()]+' de '+dt.getFullYear();
  }

  $(form).on('submit', function(e){
    e.preventDefault();
    if (!validateStep(4)) return;
    var $btn=$('#six40-submit');
    $btn.prop('disabled',true).find('.six40-submit-text').addClass('six40-hidden');
    $btn.find('.six40-submit-loading').removeClass('six40-hidden');
    $('#six40-global-error').addClass('six40-hidden').text('');

    $.post(six40Ajax.ajaxUrl, {
      action:'six40_submit_booking', nonce:six40Ajax.nonce,
      location:$('input[name="location"]:checked').val(), service:$('input[name="service"]:checked').val(),
      date:$('#six40-date').val(), time:$('#six40-time').val(),
      name:$('#six40-name').val().trim(), email:$('#six40-email').val().trim(),
      barber_id:$('#six40-barber-id').val()||0
    }, function(res){
      $btn.prop('disabled',false).find('.six40-submit-text').removeClass('six40-hidden');
      $btn.find('.six40-submit-loading').addClass('six40-hidden');
      if (res.success) {
        $(form).addClass('six40-hidden'); $('.six40-steps').addClass('six40-hidden'); $(successPanel).removeClass('six40-hidden');
      } else {
        var msg=(res.data&&res.data.message)?res.data.message:six40Ajax.strings.error;
        $('#six40-global-error').removeClass('six40-hidden').text(msg);
        $('html,body').animate({ scrollTop:$('#six40-global-error').offset().top-80 },300);
      }
    }).fail(function(){
      $btn.prop('disabled',false).find('.six40-submit-text').removeClass('six40-hidden');
      $btn.find('.six40-submit-loading').addClass('six40-hidden');
      $('#six40-global-error').removeClass('six40-hidden').text(six40Ajax.strings.error);
    });
  });

  $('#six40-new-booking').on('click', function(){
    form.reset(); $('#six40-time').val(''); $('#six40-barber-id').val('0');
    $('#six40-slots-container').html('<p class="six40-slots-hint">'+six40Ajax.strings.selectDate+'</p>');
    clearErrors(); $(successPanel).addClass('six40-hidden'); $(form).removeClass('six40-hidden'); $('.six40-steps').removeClass('six40-hidden');
    goToStep(1);
  });

  goToStep(1);

})(jQuery);
