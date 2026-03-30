/**
 * campaign-persist.js
 * Sichert UTM-Parameter, Click-IDs und Landing Page in localStorage.
 * Wird auf ALLEN Seiten geladen, damit Kampagnen-Daten beim ersten
 * Seitenaufruf erfasst werden — unabhängig davon, ob das Buchungstool
 * auf der Seite eingebunden ist.
 */
(function() {
    var params = new URLSearchParams(window.location.search);
    var keys = ['utm_source','utm_medium','utm_campaign','utm_content','utm_term','gclid','fbclid'];

    var hasAny = keys.some(function(k) { return params.get(k); });
    if (!hasAny) return;

    keys.forEach(function(k) {
        var val = params.get(k);
        if (val) localStorage.setItem('glattt_' + k, val);
    });

    localStorage.setItem('glattt_landing_page', window.location.href);
    if (document.referrer) {
        localStorage.setItem('glattt_referrer', document.referrer);
    }
})();
