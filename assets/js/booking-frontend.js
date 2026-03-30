jQuery(function($){
    // Google Ads conversion tracking
    function gtag_report_conversion(url) {
      // Prüfen ob gtag verfügbar ist
      if (typeof gtag === 'undefined') {
        console.log('⚠️ gtag nicht verfügbar - Google Ads Tracking übersprungen');
        return false;
      }
      var callback = function () {
        if (typeof(url) != 'undefined') {
          window.location = url;
        }
      };
      gtag('event', 'conversion', {
          'send_to': 'AW-11073201712/k7OACIa71tkZELDMjqAp',
          'event_callback': callback
      });
      return false;
    }

    // Meta Pixel conversion tracking
    function fbq_report_conversion() {
      // Prüfen ob fbq verfügbar ist
      if (typeof fbq === 'undefined') {
        console.log('⚠️ fbq nicht verfügbar - Meta Pixel Tracking übersprungen');
        return false;
      }
      fbq('track', 'InitiateCheckout');
      console.log('✅ Meta Pixel: InitiateCheckout Event getrackt');
      return true;
    }
    console.log('🔔 booking-frontend.js geladen');

    // UTM-Persistierung läuft global über campaign-persist.js (auf allen Seiten)

    let branches = [], currentIndex = 0,
        weekStart, weekEnd, earliestWeek,
        selectedService = '',
        serviceDurations = {},
        autoSkipWeeksCount = 0,       // Zähler für automatische Wochenwechsel
        maxAutoSkipWeeks = 12;        // Maximal 12 Wochen voraus suchen

const urlParams     = new URLSearchParams(window.location.search);
const widget        = $('#glattt-booking-widget');
const defaultBranch = widget.attr('data-default-branch') || urlParams.get('id');

/**
 * Sammelt Browser- und Kampagnen-Daten für das Booking-Tracking.
 * Wird beim Buchungsabschluss an das WP-Backend mitgeschickt.
 */
function collectTrackingData() {
    const ua = navigator.userAgent || '';
    const params = new URLSearchParams(window.location.search);

    // Device-Type Erkennung
    let deviceType = 'desktop';
    if (/Mobi|Android/i.test(ua)) {
        deviceType = (Math.min(screen.width, screen.height) > 600) ? 'tablet' : 'mobile';
    }

    // Browser-Erkennung
    let browserName = '', browserVersion = '';
    if (/Edg\//i.test(ua)) {
        browserName = 'Edge';
        browserVersion = (ua.match(/Edg\/([\d.]+)/) || [])[1] || '';
    } else if (/Chrome\//i.test(ua) && !/Chromium/i.test(ua)) {
        browserName = 'Chrome';
        browserVersion = (ua.match(/Chrome\/([\d.]+)/) || [])[1] || '';
    } else if (/Safari\//i.test(ua) && !/Chrome/i.test(ua)) {
        browserName = 'Safari';
        browserVersion = (ua.match(/Version\/([\d.]+)/) || [])[1] || '';
    } else if (/Firefox\//i.test(ua)) {
        browserName = 'Firefox';
        browserVersion = (ua.match(/Firefox\/([\d.]+)/) || [])[1] || '';
    }

    // OS-Erkennung
    let osName = '', osVersion = '';
    if (/Windows/i.test(ua)) {
        osName = 'Windows';
        osVersion = (ua.match(/Windows NT ([\d.]+)/) || [])[1] || '';
    } else if (/Mac OS X/i.test(ua)) {
        osName = 'macOS';
        osVersion = (ua.match(/Mac OS X ([\d_]+)/) || [])[1]?.replace(/_/g, '.') || '';
    } else if (/Android/i.test(ua)) {
        osName = 'Android';
        osVersion = (ua.match(/Android ([\d.]+)/) || [])[1] || '';
    } else if (/iPhone|iPad/i.test(ua)) {
        osName = 'iOS';
        osVersion = (ua.match(/OS ([\d_]+)/) || [])[1]?.replace(/_/g, '.') || '';
    } else if (/Linux/i.test(ua)) {
        osName = 'Linux';
    }

    // Verweildauer berechnen
    let bookingDuration = null;
    const startTime = sessionStorage.getItem('glatttBookingStartTime');
    if (startTime) {
        bookingDuration = Math.round((Date.now() - parseInt(startTime, 10)) / 1000);
    }

    // Kampagnen-Daten: localStorage hat Vorrang (vom Kampagnen-Einstieg),
    // Fallback auf aktuelle URL-Parameter
    function getParam(key) {
        return localStorage.getItem('glattt_' + key) || params.get(key) || null;
    }

    return {
        user_agent: ua,
        browser_name: browserName,
        browser_version: browserVersion,
        os_name: osName,
        os_version: osVersion,
        device_type: deviceType,
        screen_width: screen.width || null,
        screen_height: screen.height || null,
        viewport_width: window.innerWidth || null,
        viewport_height: window.innerHeight || null,
        language: navigator.language || null,
        referrer: localStorage.getItem('glattt_referrer') || document.referrer || null,
        landing_page: localStorage.getItem('glattt_landing_page') || window.location.href || null,
        utm_source: getParam('utm_source'),
        utm_medium: getParam('utm_medium'),
        utm_campaign: getParam('utm_campaign'),
        utm_content: getParam('utm_content'),
        utm_term: getParam('utm_term'),
        gclid: getParam('gclid'),
        fbclid: getParam('fbclid'),
        booking_duration_seconds: bookingDuration,
        cookie_consent: (typeof window.Borlabs !== 'undefined' && window.Borlabs.Cookie)
            ? 'borlabs' : null
    };
}

    function init() {
        // erst mal Overlay anzeigen, Schritte blockieren
$('#glattt-start-booking').on('click', function() {
  sessionStorage.setItem('glatttBookingStartTime', Date.now());
  gtag_report_conversion();
  fbq_report_conversion();
  $('.initial-overlay').fadeOut(300, function() {
    $(this).remove();
  });

  if (window._paq) {
    _paq.push(['trackEvent', 'Buchung', 'Start', window.location.pathname]);
  }
});
        $('#glattt-booking-widget').css({ position: 'relative', overflow: 'hidden' });
        setCurrentWeek();
        earliestWeek = weekStart;
        initFloatingLabels();
        fetchBranches();
        bindEvents();
    }

    function setCurrentWeek() {
        const today = new Date();
        // Berechne Wochentag (Montag=1 ... Sonntag=7)
        const dow = today.getDay() || 7;
        // Montag dieser Woche
        const mono = new Date(today);
        mono.setDate(today.getDate() - dow + 1);
        mono.setHours(0, 0, 0, 0);
        // Sonntag dieser Woche (6 Tage nach Montag)
        const sun = new Date(mono);
        sun.setDate(mono.getDate() + 6);
        sun.setHours(23, 59, 59, 999);

        weekStart = mono.getTime();
        weekEnd   = sun.getTime();
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

        // Gender-Switch: Klick auf eine Option
        $(document).on('click', '.gender-option', function(){
            $('.gender-option').removeClass('active');
            $(this).addClass('active');
            $('#gender').val($(this).data('value'));
        });

        // PLZ: nur Ziffern erlauben
        $(document).on('input', '#zip', function(){
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        $(document).on('click', '#glattt-booking-form button[type="submit"]', function(){
            console.log('🟢 Jetzt-buchen-Button geklickt');
            // Matomo-Tracking: Klick auf Jetzt buchen
            if (window._paq) {
              _paq.push(['trackEvent', 'Buchung', 'Klick auf Jetzt buchen', window.location.pathname]);
            }
            // Zeitmessung seit Buchungsstart
            const startTime = sessionStorage.getItem('glatttBookingStartTime');
            if (startTime && window._paq) {
              const durationMs = Date.now() - parseInt(startTime, 10);
              const durationMin = (durationMs / 60000).toFixed(2);
              _paq.push(['trackEvent', 'Buchung', 'Zeit bis Jetzt-buchen-Klick', durationMin + ' Minuten']);
            }
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

    function changeWeek(days, isAutoSkip = false) {
        // Statt Millisekunden zu addieren, arbeite mit Date-Objekten
        const currentMonday = new Date(weekStart);
        currentMonday.setDate(currentMonday.getDate() + days);
        
        // Sicherstellen, dass es wirklich ein Montag ist
        const dow = currentMonday.getDay() || 7; // Sonntag=7, Montag=1
        if (dow !== 1) {
            // Falls nicht Montag, korrigiere auf den Montag dieser Woche
            currentMonday.setDate(currentMonday.getDate() - dow + 1);
        }
        currentMonday.setHours(0, 0, 0, 0);
        
        const newWeekStart = currentMonday.getTime();
        
        // Prüfe, ob wir nicht vor die früheste Woche gehen
        if (days < 0 && newWeekStart < earliestWeek) {
            return;
        }
        
        // Sonntag berechnen (6 Tage nach Montag)
        const currentSunday = new Date(currentMonday);
        currentSunday.setDate(currentMonday.getDate() + 6);
        currentSunday.setHours(23, 59, 59, 999);
        
        weekStart = newWeekStart;
        weekEnd = currentSunday.getTime();
        
        renderWeekRange();
        if (selectedService) loadAvailability(isAutoSkip);
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

    function loadAvailability(isAutoSkip = false) {
        showSpinner();
        const branchId = branches[currentIndex].branchId;
        $('.timeslots').empty();
        
        // Reset Auto-Skip-Zähler bei manuellem Laden
        if (!isAutoSkip) {
            autoSkipWeeksCount = 0;
        }
        
        console.log(`🔍 loadAvailability aufgerufen - isAutoSkip: ${isAutoSkip}, autoSkipWeeksCount: ${autoSkipWeeksCount}`);
        console.log(`🔍 Anfrage für Woche: ${new Date(weekStart).toLocaleDateString()} - ${new Date(weekEnd).toLocaleDateString()}`);
        
        $.post(glatttFrontend.ajax_url, {
            action:   'glattt_get_availability',
            nonce:    glatttFrontend.nonce_get,
            branch:   branchId,
            service:  selectedService,
            monday:   weekStart,
            sunday:   weekEnd
        }, resp => {
            hideSpinner();
            console.log('📥 API Response:', resp);
            console.log('📥 resp.success:', resp.success);
            console.log('📥 resp.data:', resp.data);
            console.log('📥 Anzahl Slots:', resp.data ? resp.data.length : 'keine data');
            
            if (resp.success) {
                const slots = resp.data;
                const now = new Date().getTime();
                
                // Filtere nur zukünftige Slots (mindestens 30 Minuten in der Zukunft)
                const futureSlots = slots.filter(s => {
                    const slotTime = new Date(s.startTime).getTime();
                    return slotTime > (now + 30 * 60 * 1000); // 30 Min Puffer
                });
                
                console.log(`📅 Woche ${new Date(weekStart).toLocaleDateString()}: ${slots.length} Slots total, ${futureSlots.length} in der Zukunft`);
                
                // Prüfen ob ZUKÜNFTIGE Slots in dieser Woche verfügbar sind
                if (futureSlots.length === 0 && autoSkipWeeksCount < maxAutoSkipWeeks) {
                    // Keine zukünftigen Termine in dieser Woche - automatisch zur nächsten wechseln
                    autoSkipWeeksCount++;
                    console.log(`⏭️ Keine zukünftigen Termine in dieser Woche, wechsle zu Woche ${autoSkipWeeksCount}/${maxAutoSkipWeeks}`);
                    changeWeek(7, true); // true = Auto-Skip
                } else {
                    // Zukünftige Slots gefunden oder Max erreicht - anzeigen
                    if (autoSkipWeeksCount > 0) {
                        console.log(`✅ Termine gefunden nach ${autoSkipWeeksCount} Woche(n) Vorsprung`);
                    }
                    renderGridTimeslots(futureSlots);
                }
            } else {
                console.log('❌ API Response nicht erfolgreich:', resp);
            }
        }).fail(function(xhr, status, error) {
            console.log('❌ AJAX Fehler:', status, error);
            hideSpinner();
        });
    }

    function renderGridTimeslots(slots) {
        const $cont = $('<div class="timetable-container"></div>'),
              $hdr  = $('<div class="weekdays-row"></div>');
        // Schleife für 6 Tage (Montag bis Samstag)
        for (let d=0; d<6; d++){
            const dt   = new Date(weekStart + d*86400000),
                  day2 = dt.toLocaleDateString(undefined,{weekday:'short'}).slice(0,2),
                  date = dt.toLocaleDateString(undefined,{day:'2-digit',month:'2-digit'});
            $hdr.append(`<div class="day-header">${day2} ${date}</div>`);
        }
        $cont.append($hdr);
        const $grid = $('<div class="block-grid"></div>');
        let firstSlotDayIndex = -1; // Track ersten Tag mit Slots
        // Schleife für 6 Tage (Montag bis Samstag)
        for (let d=0; d<6; d++){
            const dt = new Date(weekStart + d*86400000);
            const col = slots.filter(s=>{
                const m=new Date(s.startTime);
                return m.getFullYear()===dt.getFullYear() &&
                       m.getMonth()===dt.getMonth() &&
                       m.getDate()===dt.getDate();
            }).sort((a,b)=>new Date(a.startTime)-new Date(b.startTime));
            const $col = $('<div class="day-block"></div>');
            // Merke ersten Tag mit verfügbaren Slots
            if (col.length > 0 && firstSlotDayIndex === -1) {
                firstSlotDayIndex = d;
            }
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
        
        // Auto-Scroll zum ersten Tag mit verfügbaren Slots
        if (firstSlotDayIndex > 0) {
            scrollToFirstAvailableSlot($cont, firstSlotDayIndex);
        }
    }
    
    /**
     * Scrollt den Timetable-Container horizontal, sodass der erste Tag
     * mit verfügbaren Slots oben links sichtbar ist
     */
    function scrollToFirstAvailableSlot($container, dayIndex) {
        // Warte kurz, bis das DOM gerendert ist
        setTimeout(() => {
            const columnWidth = 120; // Breite pro Tag (aus CSS: grid-auto-columns: 120px)
            const gap = 8; // Gap zwischen Spalten (0.5rem ≈ 8px)
            const scrollPosition = dayIndex * (columnWidth + gap);
            
            // Smooth scroll zum ersten verfügbaren Tag
            $container[0].scrollTo({
                left: scrollPosition,
                behavior: 'smooth'
            });
        }, 100);
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
        // Matomo-Tracking: Schritt 2 erreicht
        if (window._paq) {
          _paq.push(['trackEvent', 'Buchung', 'Schritt 2 erreicht', window.location.pathname]);
        }
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

  // Geschlecht-Pflichtfeld prüfen
  if (!$('#gender').val()) {
    alert('Bitte wähle Dein Geschlecht aus.');
    return;
  }

  // PLZ validieren (5 Ziffern)
  const zipVal = $('#zip').val().trim();
  if (!/^[0-9]{5}$/.test(zipVal)) {
    alert('Bitte gib eine gültige 5-stellige Postleitzahl ein.');
    $('#zip').focus();
    return;
  }

  const p = { action:'glattt_book_appointment', nonce:glatttFrontend.nonce_book };
  $('#glattt-booking-form').serializeArray().forEach(f => p[f.name] = f.value);

  // Tracking-Daten sammeln und als JSON mitsenden
  const tracking = collectTrackingData();
  p.tracking_data = JSON.stringify(tracking);

  // Buchungs-Overlay anzeigen
  showBookingOverlay();

  $.post(glatttFrontend.ajax_url, p, resp => {
    if ( resp.success && resp.data.redirect ) {
      const startTime = sessionStorage.getItem('glatttBookingStartTime');
      if (startTime && window._paq) {
        const durationMs = Date.now() - parseInt(startTime, 10);
        const durationMin = (durationMs / 60000).toFixed(2);
        _paq.push(['trackEvent', 'Buchung', 'Dauer bis Abschluss', durationMin + ' Minuten']);
        _paq.push(['trackGoal', 1]);
        sessionStorage.removeItem('glatttBookingStartTime');
      }
      window.location.href = resp.data.redirect;
    } else {
      hideBookingOverlay();
      // Fehlermeldung aus der API oder Fallback
      const errorMsg = resp.data && resp.data.message 
        ? resp.data.message 
        : 'Es gab einen Fehler bei der Buchung. Bitte versuche es erneut oder kontaktiere uns.';
      
      alert(errorMsg);
      console.error('Buchungsfehler:', resp);
    }
  });
}

    function showSpinner(){ $('#glattt-booking-widget').addClass('loading'); }
    function hideSpinner(){ $('#glattt-booking-widget').removeClass('loading'); }

    function showBookingOverlay() {
      if ($('#booking-overlay').length) return;
      $('#glattt-booking-widget').append(
        '<div id="booking-overlay">' +
          '<div class="booking-overlay-content">' +
            '<div class="booking-overlay-spinner"></div>' +
            '<p>Deine Buchung wird durchgeführt&hellip;</p>' +
          '</div>' +
        '</div>'
      );
    }
    function hideBookingOverlay() {
      $('#booking-overlay').remove();
    }
    
    /**
     * Floating Labels: Erkennt ob Felder bereits gefüllt sind (z.B. durch Autofill)
     */
    function initFloatingLabels() {
        // Event-Listener für Input-Änderungen
        $(document).on('input change', '.form-field input', function() {
            updateFloatingLabel($(this));
        });
        
        // Autofill-Erkennung: Prüfe nach kurzer Verzögerung
        setTimeout(() => {
            $('.form-field input').each(function() {
                updateFloatingLabel($(this));
            });
        }, 100);
        
        // Nochmal nach längerer Zeit (für langsames Autofill)
        setTimeout(() => {
            $('.form-field input').each(function() {
                updateFloatingLabel($(this));
            });
        }, 1000);
    }
    
    function updateFloatingLabel($input) {
        if ($input.val() && $input.val().length > 0) {
            $input.addClass('has-value');
        } else {
            $input.removeClass('has-value');
        }
    }

    init();
});