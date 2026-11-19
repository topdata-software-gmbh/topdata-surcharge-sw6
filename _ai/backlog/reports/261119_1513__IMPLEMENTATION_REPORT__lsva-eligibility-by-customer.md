---
filename: "/topdata/clones/focus/vol/www/custom/plugins/topdata-surcharge-sw6/_ai/backlog/reports/261119_1513__IMPLEMENTATION_REPORT__lsva-eligibility-by-customer.md"
title: "Report: LSVA nur bei LSVA-pflichtigen Kunden, überall sichtbar"
createdAt: 2026-11-19 15:13
updatedAt: 2026-11-19 15:13
repoRoot: "/topdata/clones/focus/vol/www/custom/plugins/topdata-surcharge-sw6"
project: "topdata-surcharge-sw6"
status: complete
filesCreated: 0
filesModified: 2
filesDeleted: 4
tags: [lsva, surcharge, shopware6, custom-fields, cleanup]
documentType: IMPLEMENTATION_REPORT
---

# Report: LSVA nur bei LSVA-pflichtigen Kunden, überall sichtbar

## Summary

Die LSVA-Eligibility-Prüfung war bereits im Cart-Processor verdrahtet; in dieser Session wurde die Implementierung abgeschlossen. Der Default-Custom-Field wurde auf den externen Contract `topdata_tg_lsva` (Feld-Set `topdata_tg`) umgestellt, die vier in Plan §6 markierten Debug-/Defekt-Templates wurden entfernt, und die Repo-Dokumentation wurde auf den tatsächlichen Stand gebracht. PHP-, XML- und JSON-Syntaxprüfungen sind grün; die manuelle 18-Punkte-Testmatrix wurde nicht ausgeführt (kein Test-Setup im Repo).

## Prompt used

> is kundenspez. LSVA fully implemented?

> lets finish implementation .. also note: custom field will be named: ield/set names (`topdata_tg_lsva`, `topdata_tg`) are retained as an external data contract visible to shop administrators and the surcharge plugin, not as an implementation choice.

## Files Changed

### Modified (project source)

- `/topdata/clones/focus/vol/www/custom/plugins/topdata-surcharge-sw6/src/Service/LsvaTaxApplicabilityService.php:12` — `DEFAULT_CUSTOM_FIELD` von `topdata_ipim_lsva` auf `topdata_tg_lsva` geändert.
- `/topdata/clones/focus/vol/www/custom/plugins/topdata-surcharge-sw6/src/Resources/config/config.xml:43` — `lsvaCustomerField`: Default, Labels (DE/EN/FR) und Help-Texte auf `topdata_tg_lsva` / Feld-Set `topdata_tg` umgestellt.

### Deleted (project source)

- `/topdata/clones/focus/vol/www/custom/plugins/topdata-surcharge-sw6/src/Resources/views/storefront/layout/header/header.html.twig` — enthielt nur die Debug-Ausgabe „HURRA, ES KLAPPT!“.
- `/topdata/clones/focus/vol/www/custom/plugins/topdata-surcharge-sw6/src/Resources/views/storefront/page/checkout/cart/index.html.twig` — enthielt nur die orange Testbox.
- `/topdata/clones/focus/vol/www/custom/plugins/topdata-surcharge-sw6/src/Resources/views/storefront/page/checkout/confirm/index.html.twig` — enthielt nur die purple Testbox mit defektem Text.
- `/topdata/clones/focus/vol/www/custom/plugins/topdata-surcharge-sw6/src/Resources/views/storefront/page/checkout/confirm/confirm-checkout.html.twig` — erweiterte das im Core nicht existierende `confirm-checkout-summary.html.twig`.

### Documentation (in-repo, not counted as source)

- `/topdata/clones/focus/vol/www/custom/plugins/topdata-surcharge-sw6/AGENTS.md:25` — LSVA-Gate, sechster Config-Key, Feld-Contract dokumentiert; veraltete Aussage zur Berechnungsbasis (`positionPrice`) korrigiert; Debug-Banner-Warnung entfernt.
- `/topdata/clones/focus/vol/www/custom/plugins/topdata-surcharge-sw6/README.md:38` — Konfigurationspunkt und Abschnitt „LSVA Eligibility“ ergänzt; Berechnungsbasis korrigiert.
- `/topdata/clones/focus/vol/www/custom/plugins/topdata-surcharge-sw6/_ai/backlog/active/20261109_1047__IMPLEMENTATION_PLAN__lsva-eligibility-by-customer.md:5` — Status „Umgesetzt“, Feldnamen auf `topdata_tg_lsva` aktualisiert, Cleanup §6 als erledigt markiert.

## Key Changes

- Externer Feld-Contract ist jetzt durchgängig `topdata_tg_lsva` im Feld-Set `topdata_tg` (Code-Default und Admin-Default).
- Die vier Debug-/Defekt-Overrides wurden gelöscht; die Storefront-Overrides beschränken sich auf tatsächlich benötigte Templates (offcanvas summary, line-item, cart-widget, checkout summary, order-detail).
- Der LSVA-Gate im Processor bleibt unverändert: ohne Customer bzw. ohne truthy Feld wird ein vorhandenes `surcharge`-LineItem entfernt (`/topdata/clones/focus/vol/www/custom/plugins/topdata-surcharge-sw6/src/Core/Checkout/Cart/SurchargeCartProcessor.php:60`).
- Berechnung, Steuerlogik, Rappenrundung, Mail-Subscriber und Anzeige-Templates blieben unangetastet.

## Deviations from Plan

- **Feldname:** Der Plan spezifizierte `topdata_ipim_lsva`; laut Nutzervorgabe ist der externe Contract `topdata_tg_lsva` (Feld-Set `topdata_tg`). Default und Admin-Texte wurden entsprechend geändert.
- **Cleanup §6:** Für `confirm-checkout.html.twig` erlaubte der Plan „löschen oder korrekt anbinden“. Gewählt wurde Löschen, da die Datei auf ein nicht existierendes Core-Template zeigte, von keiner anderen Datei referenziert wurde und die Anzeige bereits über `summary.html.twig` erfolgt.
- **Keine Abweichung an der Logik:** Gate, Gast-Fallback, Normalisierung und LineItem-Verhalten waren bereits implementiert und wurden nicht verändert.

## Technical Decisions

- Der Default `topdata_tg_lsva` liegt zusätzlich als Code-Fallback in `getFieldName()`; damit funktioniert der Gate auch, wenn der neue System-Config-Key noch nicht in der DB synchronisiert wurde.
- Es wurde kein Alias/Fallback auf `topdata_ipim_lsva` eingebaut: Der Nutzer hat `topdata_tg_lsva` als verbindlichen Contract bestätigt.
- Die defekte Confirm-Override wurde entfernt statt neu verdrahtet, um doppelte LSVA-Zeilen in der Bestellübersicht zu vermeiden.

## Testing Notes

Ausgeführt in dieser Session:

```bash
/usr/bin/php -l /topdata/clones/focus/vol/www/custom/plugins/topdata-surcharge-sw6/src/Service/LsvaTaxApplicabilityService.php
/usr/bin/php -l /topdata/clones/focus/vol/www/custom/plugins/topdata-surcharge-sw6/src/Core/Checkout/Cart/SurchargeCartProcessor.php
/usr/bin/php -l /topdata/clones/focus/vol/www/custom/plugins/topdata-surcharge-sw6/src/Subscriber/MailSurchargeSubscriber.php
/usr/bin/xmllint --noout /topdata/clones/focus/vol/www/custom/plugins/topdata-surcharge-sw6/src/Resources/config/config.xml
/usr/bin/xmllint --noout /topdata/clones/focus/vol/www/custom/plugins/topdata-surcharge-sw6/src/Resources/config/services.xml
/usr/bin/python3 -m json.tool /topdata/clones/focus/vol/www/custom/plugins/topdata-surcharge-sw6/src/Resources/snippet/storefront.de-DE.json
/usr/bin/rg -n "topdata_ipim_lsva|HURRA|TEST: ICH BIN|confirm-checkout-summary" /topdata/clones/focus/vol/www/custom/plugins/topdata-surcharge-sw6/src
```

Ergebnis: keine Syntaxfehler, XML/JSON valide, keine Reste in `src/`.

Nicht ausgeführt (kein Test-Suite und kein Shop-Zugriff im Repo-Scope):

- Manuelle 18-Punkte-Testmatrix aus Plan §„Testmatrix“ (anonymer Besucher, `yes`/`no`-Kunden, Gast-Fallback, Login/Logout, Rappenrundung, Kontakt-Login).
- Storefront-/Checkout-Smoke-Test und Mail-Rendering.

## Usage Examples

Admin-Konfiguration:

```text
Einstellungen → System → Plugins → Topdata Cart Surcharge
  Kundenprüfung → Custom-Field für LSVA-Pflicht = topdata_tg_lsva
  (Feld-Set: topdata_tg, extern angelegt/befüllt)
```

Rollout im Host-Shop (nicht in dieser Session ausgeführt):

```bash
docker exec focus-www sh -lc 'cd /www && bin/console cache:clear'
docker exec focus-www sh -lc 'cd /www && bin/console cache:warmup --env=prod'
```

## Documentation Updates

- `AGENTS.md`: LSVA-Gate und Feld-Contract dokumentiert, veraltete Berechnungsbasis korrigiert, Debug-Warnung entfernt.
- `README.md`: LSVA-Konfiguration und Eligibility-Verhalten ergänzt.
- Implementation-Plan: Status und Feldnamen aktualisiert, Cleanup als erledigt markiert.

## Next Steps

- Manuelle Testmatrix aus dem Plan abarbeiten, insbesondere Gast-Fallback (gleiche E-Mail) und Login/Logout im laufenden Cart.
- Nach Deploy `cache:clear`/`cache:warmup` ausführen.
- Falls in einer bestehenden Installation bereits `topdata_ipim_lsva` als System-Config-Wert gespeichert war, im Admin auf `topdata_tg_lsva` ändern (der Code-Fallback greift nur bei leerem/ungültigem Wert).
- Vor einem Release prüfen, ob ein Versionsbump in `composer.json` gewünscht ist (keine Migration in dieser Änderung).
