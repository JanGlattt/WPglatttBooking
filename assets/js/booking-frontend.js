jQuery(function($){
    console.log('🔔 booking-frontend.js geladen');
    let branches = [], currentIndex = 0,
        weekStart, weekEnd, earliestWeek,
        selectedService = '',
        serviceDurations = {};

    const urlParams     = new URLSearchParams(window.location.search);
    const defaultBranch = urlParams.get('id');

    function init() {
        $('#glattt-booking-widget').css({ position: 'relative', overflow: 'hidden' });
        setCurrentWeek();
        earliestWeek = weekStart;
        fetchBranches();
        bindEvents();
    }

    function setCurrentWeek() {
        const today = new Date(), dow = today.getDay() || 7;
        const mono = new Date(today); mono.setDate(today.getDate() - dow + 1);
        const sat = new Date(mono);   sat.setDate(mono.getDate() + 5);
        weekStart = mono.getTime();
        weekEnd   = sat.getTime();
        renderWeekRange();
    }

    function bindEvents() {
        $(document).on('click', '.institute-prev, .institute-next', onChangeInstitute);
        $(document).on('change', '#glattt-service', function(){
            selectedService = this.value;
            loadAvailability();
        });
        $(document).on('click', '.prev-week', () => changeWeek(-7));
        $(document).on('click', '.next-week', () => changeWeek(7));
        $(document).on('click', '.go-back', fadeBackToStep1);

        $(document).on('submit', '#glattt-booking-form', function(e){
            console.log('🟢 glattt-booking-form submit fired');
            e.preventDefault();
            bookAppointment();
        });
        $(document).on('click', '#glattt-booking-form button[type="submit"]', function(){
            console.log('🟢 Jetzt-buchen-Button geklickt');
        });
    }

    function onChangeInstitute(){
        fadeBackToStep1();
        if ($(this).hasClass('institute-prev')) showPreviousInstitute();
        else showNextInstitute();
    }

    function fetchBranches() {
        showSpinner();
        $.post(glatttFrontend.ajax_url, {
            action: 'glattt_get_branches',
            nonce:  glatttFrontend.nonce_get
        }, resp => {
            hideSpinner();
            if (!resp.success) return;
            branches = resp.data;
            if (defaultBranch) {
                const idx = branches.findIndex(b=>b.branchId===defaultBranch);
                if (idx>=0) currentIndex = idx;
            }
            renderInstituteSelection();
            renderServices();
        });
    }

    function renderInstituteSelection() {
        const b = branches[currentIndex];
        $('#glattt-branch').val(b.branchId);
        $('.institute-selector').css('background-image',
            `linear-gradient(to right, rgb(225,181,32) 10%, rgba(225,181,32,0) 100%), url(${b.imageUrl||''})`
        );
        $('.institute-name').text(b.name);
        $('.institute-address').html(`${b.streetAddress1}<br>${b.city}`);
    }

    function showPreviousInstitute(){
        currentIndex = (currentIndex - 1 + branches.length) % branches.length;
        renderInstituteSelection();
        renderServices();
    }

    function showNextInstitute(){
        currentIndex = (currentIndex + 1) % branches.length;
        renderInstituteSelection();
        renderServices();
    }

    function renderWeekRange() {
        const fmt = { day:'2-digit', month:'2-digit' };
        const st  = new Date(weekStart).toLocaleDateString(undefined, fmt);
        const ed  = new Date(weekEnd).toLocaleDateString(undefined, fmt);
        $('.week-range').text(`${st} – ${ed}`);
        $('.prev-week').prop('disabled', weekStart <= earliestWeek);
    }

    function changeWeek(days) {
        const delta = days * 86400000;
        if (days<0 && weekStart+delta < earliestWeek) return;
        weekStart += delta;
        weekEnd   += delta;
        renderWeekRange();
        if (selectedService) loadAvailability();
    }

    function renderServices() {
        showSpinner();
        const branchId = branches[currentIndex].branchId;
        const $sel = $('#glattt-service').prop('disabled', true).empty();
        $.post(glatttFrontend.ajax_url, {
            action: 'glattt_get_services',
            nonce:  glatttFrontend.nonce_get,
            branch: branchId
        }, resp => {
            hideSpinner();
            if (!resp.success) return;
            serviceDurations = {};
            resp.data.forEach(s=> serviceDurations[s.serviceId] = parseInt(s.duration,10) || 0);
            if (resp.data.length === 1) {
                const s   = resp.data[0],
                      lbl = s.friendly_name || s.name;
                $sel.append(`<option value="${s.serviceId}">${lbl}</option>`)
                    .val(s.serviceId).prop('disabled',false).trigger('change');
            } else {
                $sel.append('<option value="" disabled selected></option>');
                resp.data.forEach(s=>{
                    const lbl = s.friendly_name||s.name;
                    $sel.append(`<option value="${s.serviceId}">${lbl}</option>`);
                });
                $sel.prop('disabled',false);
            }
        });
    }

    function loadAvailability() {
        showSpinner();
        const branchId = branches[currentIndex].branchId;
        $('.timeslots').empty();
        $.post(glatttFrontend.ajax_url, {
            action:   'glattt_get_availability',
            nonce:    glatttFrontend.nonce_get,
            branch:   branchId,
            service:  selectedService,
            monday:   weekStart,
            sunday:   weekEnd
        }, resp => {
            hideSpinner();
            if (resp.success) renderGridTimeslots(resp.data);
        });
    }

    function renderGridTimeslots(slots) {
        const $cont = $('<div class="timetable-container"></div>'),
              $hdr  = $('<div class="weekdays-row"></div>');
        for (let d=0; d<6; d++){
            const dt   = new Date(weekStart + d*86400000),
                  day2 = dt.toLocaleDateString(undefined,{weekday:'short'}).slice(0,2),
                  date = dt.toLocaleDateString(undefined,{day:'2-digit',month:'2-digit'});
            $hdr.append(`<div class="day-header">${day2} ${date}</div>`);
        }
        $cont.append($hdr);
        const $grid = $('<div class="block-grid"></div>');
        for (let d=0; d<6; d++){
            const dt = new Date(weekStart + d*86400000);
            const col = slots.filter(s=>{
                const m=new Date(s.startTime);
                return m.getFullYear()===dt.getFullYear() &&
                       m.getMonth()===dt.getMonth() &&
                       m.getDate()===dt.getDate();
            }).sort((a,b)=>new Date(a.startTime)-new Date(b.startTime));
            const $col = $('<div class="day-block"></div>');
            col.forEach(slot=>{
                const t    = new Date(slot.startTime)
                               .toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}),
                      $btn = $(`<button class="timeslot">${t}</button>`);
                $btn.on('click', ()=> openBookingStep2(slot));
                $col.append($btn);
            });
            $grid.append($col);
        }
        $cont.append($grid);
        $('.timeslots').append($cont);
    }

    function openBookingStep2(slot) {
        const $w  = $('#glattt-booking-widget'),
              $s1 = $w.find('.step-1'),
              $s2 = $w.find('.step-2');
        fillStep2(slot);
        // Mes­sure heights
        $s2.removeClass('hidden');
        const h1 = $s1.outerHeight(),
              h2 = $s2.outerHeight();
        $w.css({ height: h1, overflow: 'hidden' });
        $s2.css({ position:'absolute', top:0, left:0, width:'100%', opacity:0, zIndex:1 }).removeClass('hidden');
        $s1.css({ position:'absolute', top:0, left:0, width:'100%', opacity:1, zIndex:2 });
        // Animate fade
        $w.animate({ height: h2 }, 300);
        $s1.animate({ opacity:0 }, 300);
        $s2.animate({ opacity:1 }, 300, function(){
            $s1.addClass('hidden').attr('style','');
            $s2.attr('style','');
            $w.css({ height:'', overflow:'' });
        });
    }

    function fadeBackToStep1() {
        const $w  = $('#glattt-booking-widget'),
              $s1 = $w.find('.step-1'),
              $s2 = $w.find('.step-2');
        const h2 = $s2.outerHeight(),
              h1 = $s1.outerHeight();
        $w.css({ height: h2, overflow:'hidden' });
        $s1.removeClass('hidden').css({ position:'absolute', top:0, left:0, width:'100%', opacity:0, zIndex:2 });
        $s2.css({ position:'absolute', top:0, left:0, width:'100%', opacity:1, zIndex:1 });
        $w.animate({ height: h1 }, 300);
        $s2.animate({ opacity:0 }, 300);
        $s1.animate({ opacity:1 }, 300, function(){
            $s2.addClass('hidden').attr('style','');
            $s1.attr('style','');
            $w.css({ height:'', overflow:'' });
        });
    }

    function fillStep2(slot) {
        const startMs = new Date(slot.startTime).getTime(),
              dur     = serviceDurations[selectedService]||0,
              endMs   = startMs + dur*60000,
              svcName = $('#glattt-service option:selected').text(),
              start   = new Date(startMs),
              end     = new Date(endMs);

        $('input[name=branch]').val(branches[currentIndex].branchId);
        $('input[name=service]').val(selectedService);
        $('input[name=start]').val(startMs);
        $('input[name=end]').val(endMs);
        $('input[name=staff]').val(
          slot.clientSchedules?.[0]?.serviceSchedules?.[0]?.staffId || ''
        );

        $('.modal-service, .selected-service-name').text(svcName);
        $('.sum-date').text(start.toLocaleDateString(undefined,{day:'2-digit',month:'2-digit',year:'numeric'}));
        $('.sum-time').text(
          `${start.toLocaleTimeString(undefined,{hour:'2-digit',minute:'2-digit'})}`+
          ` – ${end.toLocaleTimeString(undefined,{hour:'2-digit',minute:'2-digit'})}`
        );
        $(`#glattt-booking-widget .step-2 .sum-institute`).text(branches[currentIndex].name);
    }

    function bookAppointment() {
  console.log('bookAppointment() wird ausgeführt, sende Daten:', $('#glattt-booking-form').serializeArray());
  const p = { action:'glattt_book_appointment', nonce:glatttFrontend.nonce_book };
  $('#glattt-booking-form').serializeArray().forEach(f => p[f.name] = f.value);

  $.post(glatttFrontend.ajax_url, p, resp => {
    if ( resp.success && resp.data.redirect ) {
      window.location.href = resp.data.redirect;
    } else {
      // Nutzerfreundliche Fehlermeldung anzeigen
      const msg = 'Entschuldigung, deine Buchung konnte leider nicht abgeschlossen werden. Bitte versuche es später noch einmal.';
      // Hier per Alert oder als Inline-Message ins Formular
      alert(msg);
      // alternativ: 
      // if (!$('.booking-error').length) {
      //   $('#glattt-booking-form').prepend('<div class="booking-error" style="color:red;margin-bottom:1rem;">'+msg+'</div>');
      // }
    }
  });
}

    function showSpinner(){ $('#glattt-booking-widget').addClass('loading'); }
    function hideSpinner(){ $('#glattt-booking-widget').removeClass('loading'); }

    init();
});