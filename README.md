# WPglatttBooking

WordPress-Plugin für die Integration der Phorest-Buchungs-API bei glattt.

## Features

- **Terminbuchung** über Phorest API
- **Mehrere Standorte** (Institute) mit individuellen Einstellungen
- **Service-Verwaltung** mit Friendly Names und Beschreibungen
- **E-Mail-Templates** für Buchungsbestätigungen
- **Buchungs-Übersicht** im Admin mit Status-Sync
- **Responsive Frontend** mit modernem Design

## Shortcode

```
[glattt_booking]
[glattt_booking branch-id="BRANCH_ID"]
```

## Changelog

### 0.6.0 (31.01.2026)
- **Neu:** Automatischer Wochenwechsel wenn keine Termine in aktueller Woche verfügbar
- **Neu:** Auto-Scroll zum ersten verfügbaren Tag innerhalb einer Woche
- **Neu:** Coupon-Code Feld im Buchungsformular
- **Neu:** Modernes Floating-Label Design für Formularfelder
- **Neu:** Gradient-Sweep Hover-Effekt auf Buchungs-Button
- **Fix:** Leere API-Antworten werden korrekt als "keine Termine" behandelt
- **Fix:** Theme-Styles werden korrekt überschrieben

### 0.5.1
- Diverse Bugfixes und Verbesserungen

### 0.5.0
- E-Mail-Templates mit Platzhaltern
- Buchungs-Übersicht mit Stornierung
