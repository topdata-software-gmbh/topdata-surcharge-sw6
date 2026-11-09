# Implementation Plan: LSVA nur für berechtigte Bestandskunden

**Datum:** 2026-11-09
**Plugin:** `TopdataSurchargeSW6`
**Status:** Design abgestimmt, Implementierung offen

## Ziel

Die LSVA (Warenkorb-Aufschlag) soll nur noch bei eingeloggten Bestandskunden berechnet und angezeigt werden, deren IPIM-Zusatzfeld `topdata_ipim_lsva` den Wert `yes` hat.

| Kunde | LSVA |
|---|---|
| Gast, E-Mail unbekannt | nein |
| Gast, E-Mail bekannt | Login erforderlich; danach entscheidet das IPIM-Feld des Hauptkunden |
| Neukunde nach Registrierung | nein |
| Eingeloggt, `topdata_ipim_lsva = no` | nein |
| Eingeloggt, `topdata_ipim_lsva = yes` | ja, in Warenkorb, Header, Offcanvas und Kasse |

Kontakt-Login erbt das IPIM-Feld des Hauptkunden, weil der Contact-Login den Hauptkunden in den `SalesChannelContext` setzt und dessen `customFields` erhalten bleiben.

## Nicht-Ziele

- Keine Änderung an der E-Mail-Blockierung im Better-Checkout-Plugin. Die heutige Blockierung bei bekannter Gast-E-Mail bleibt bestehen. Eine echte automatische Redirect-Logik wäre ein separater Eingriff in `topdata-better-checkout-sw6`.
- Keine Änderung an Steuersatz, Rappenrundung oder der LSVA-Höhe.
- Keine Änderung an Bestellungen, Mails oder Order-Detail-Templates, außer sie ergeben sich automatisch aus dem fehlenden LineItem.
- Kein Eingriff in das externe IPIM-System. Das Zusatzfeld `topdata_ipim_lsva` wird dort angelegt und gepflegt.

## Entscheidungen

1. **Eligibility im PHP-Cart-Prozessor, nicht in Twig.**
   Twig rendert nur, was im Cart liegt. Eine reine Twig-Ausblendung würde die LSVA in Summen, Steuern und Bestellungen belassen. Deshalb entscheidet der Prozessor, ob das LineItem entsteht.

2. **Neuer Service `LsvaEligibilityService`.**
   Kapselt die Kundenprüfung, bleibt testbar und verhindert verstreute Feldzugriffe.

3. **Werte-Normalisierung.**
   `true` (Boolean) gilt als berechtigt; Strings werden getrimmt und kleingeschrieben; nur `yes` gilt als berechtigt. `no`, `null`, leer und unbekannte Werte gelten als nicht berechtigt.

4. **Header-Widget zeigt bei `yes`-Kunden den Betrag inklusive LSVA.**
   Die Artikelanzahl bleibt ohne LSVA. Dafür wird die Subtraktion des LSVA-Betrags vom `positionPrice` entfernt.

5. **Kein Compounding.**
   Die Basis bleibt `$toCalculate->getPrice()->getPositionPrice()`. Der Prozessor läuft mit Priorität 4500 nach Produkten und Promotions, aber bevor die LSVA im neuen Cart liegt. Damit wird LSVA nicht auf LSVA berechnet.

## Architektur und Datenfluss

```
SalesChannelContext
  └─ customer
       ├─ guest? ── ja ──> nicht berechtigt
       └─ customFields['topdata_ipim_lsva']
            ├─ 'yes' / true ──> berechtigt
            └─ sonst ─────────> nicht berechtigt

SurchargeCartProcessor
  ├─ Plugin aktiv? Prozentsatz > 0? Produkte im Cart? Steuersatz vorhanden?
  ├─ LsvaEligibilityService::isEligible(context)?
  │    ├─ nein ──> vorhandenes surcharge-LineItem defensiv entfernen, return
  │    └─ ja ────> LSVA wie bisher berechnen und hinzufügen
  └─ Twig rendert das LineItem nur, wenn es existiert
```

## Dateien und Änderungen

### 1. Neu: `src/Service/LsvaEligibilityService.php`

```php
<?php declare(strict_types=1);

namespace Topdata\TopdataSurchargeSW6\Service;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LsvaEligibilityService
{
    public const CUSTOM_FIELD = 'topdata_ipim_lsva';

    public function isEligible(SalesChannelContext $context): bool
    {
        $customer = $context->getCustomer();

        if ($customer === null || $customer->getGuest()) {
            return false;
        }

        $value = $customer->getCustomFields()[self::CUSTOM_FIELD] ?? null;

        if ($value === true) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        return strtolower(trim($value)) === 'yes';
    }
}
```

Hinweise:

- `getCustomFields()` kann `null` zurückgeben; `?? null` fängt das ab.
- Falls IPIM künftig `1`/`0` oder `ja`/`nein` liefert, wird nur diese Normalisierung angepasst.
- Der Service hat keine Abhängigkeiten und ist damit trivial testbar.

### 2. `src/Resources/config/services.xml`

Neuen Service registrieren und als Argument in den Prozessor injizieren:

```xml
<service id="Topdata\TopdataSurchargeSW6\Service\LsvaEligibilityService"/>

<service id="Topdata\TopdataSurchargeSW6\Core\Checkout\Cart\SurchargeCartProcessor">
    <argument type="service" id="Shopware\Core\System\SystemConfig\SystemConfigService"/>
    <argument type="service" id="tax.repository"/>
    <argument type="service" id="translator"/>
    <argument type="service" id="Topdata\TopdataSurchargeSW6\Service\LsvaEligibilityService"/>
    <tag name="shopware.cart.processor" priority="4500" />
</service>
```

### 3. `src/Core/Checkout/Cart/SurchargeCartProcessor.php`

- Konstruktor um `private readonly LsvaEligibilityService $lsvaEligibilityService` erweitern.
- Nach den bestehenden Konfigurationsprüfungen und der Produktprüfung:

```php
$surchargeId = '06a3e52f7afc42d69e255068886f1d22';

if (!$this->lsvaEligibilityService->isEligible($context)) {
    if ($toCalculate->has($surchargeId)) {
        $toCalculate->getLineItems()->remove($surchargeId);
    }

    return;
}
```

- Danach die bisherige Logik unverändert lassen.
- `Cart::remove()` nicht verwenden: Das LineItem ist `removable=false`, `Cart::remove()` wirft eine Exception. Die LineItem-Collection entfernt es direkt.
- Reihenfolge beachten: Eligibility prüfen, bevor Steuersatz und Betrag geladen werden, um unnötige Repository-Abfragen zu sparen.

### 4. `src/Resources/views/storefront/layout/header/actions/cart-widget.html.twig`

- `surchargeAmount` wird nicht mehr benötigt.
- `visiblePositionPrice` auf `page.cart.price.positionPrice` setzen, damit die LSVA im Header-Betrag enthalten ist.
- `visibleCount` weiterhin um `surchargeCount` reduzieren.
- Screenreader-Text nutzt automatisch denselben Betrag.

Erwartetes Ergebnis:

```twig
{% set visibleCount = max(0, page.cart.lineItems|length - surchargeCount) %}
{% set visiblePositionPrice = page.cart.price.positionPrice %}
```

### 5. Cleanup vor Go-Live

Diese Reste sind nicht Teil der LSVA-Logik, müssen aber vor dem Release entfernt werden:

- `src/Resources/views/storefront/layout/header/header.html.twig`: Debug-Ausgabe `HURRA, ES KLAPPT!`.
- `src/Resources/views/storefront/page/checkout/cart/index.html.twig`: orange Testbox `TEST: ICH BIN DIE WARENKORB-ZUSAMMENFASSUNG`.
- `src/Resources/views/storefront/page/checkout/confirm/index.html.twig`: purple Testbox und kaputter Text.
- `src/Resources/views/storefront/page/checkout/confirm/confirm-checkout.html.twig`: prüfen, ob die Datei in Shopware 6.7 überhaupt gerendert wird. Sie erweitert `confirm-checkout-summary.html.twig`, das im Core nicht existiert. Entweder löschen oder korrekt anbinden, sonst droht eine doppelte LSVA-Anzeige.

## Anzeige-Verhalten nach der Änderung

- **Gast / `no` / Neukunde:** Kein `surcharge`-LineItem. Warenkorb, Offcanvas, Kasse, Bestellung und Mail zeigen keine LSVA. Header-Betrag und Anzahl bleiben unverändert.
- **`yes`-Kunde:** `surcharge`-LineItem wird berechnet. Summary, Offcanvas, Kasse, Bestellung und Mail zeigen die LSVA. Header-Betrag enthält die LSVA, Anzahl nicht.
- **Logout:** Beim nächsten Recalculate entsteht kein neues LineItem. Das alte wird nicht in den neuen Cart kopiert und verschwindet. Defensives Entfernen im Prozessor deckt Sonderfälle ab.
- **Login:** Beim nächsten Recalculate wird die LSVA bei `yes` ergänzt.
- **Kontakt-Login:** Verwendet die `customFields` des Hauptkunden; keine Sonderlogik nötig.

## Testmatrix (manuell)

Voraussetzung: Testkunden mit `topdata_ipim_lsva = yes` und `no` über Admin oder DB setzen. Das Feld wird nicht vom Plugin angelegt.

1. Gast, unbekannte E-Mail: Warenkorb, Offcanvas, Kasse und Bestellung ohne LSVA.
2. Gast, bekannte E-Mail: bestehende Blockierung/Login-Aufforderung; Login als `yes`-Kunde → LSVA überall inkl. Header; Login als `no`-Kunde → keine LSVA.
3. Neukunde nach Registrierung: keine LSVA.
4. Eingeloggter `no`-Kunde: keine LSVA.
5. Eingeloggter `yes`-Kunde: LSVA in Warenkorb, Header, Offcanvas, Kasse, Bestellung, Order-Detail und Mail.
6. Kontakt-Login eines `yes`-Hauptkunden: LSVA; Kontakt eines `no`-Hauptkunden: keine LSVA.
7. Logout eines `yes`-Kunden: LSVA verschwindet; erneuter Login: LSVA kommt zurück.
8. Gutschein oder Mengenänderung: LSVA-Betrag wird neu berechnet, keine Doppelberechnung.
9. Gast-Checkout mit unbekannter E-Mail: Gastkunde wird angelegt, bleibt ohne LSVA.

Zusätzlich prüfen:

- Header-Betrag enthält bei `yes` die LSVA, Badge-Anzahl nicht.
- Zwischensumme in Summary/Offcanvas bleibt Produktwert ohne LSVA; LSVA steht als eigene Zeile; Gesamtsumme enthält sie.
- Steueranzeige und Rappenrundung unverändert.
- Mail-Template enthält keine doppelte LSVA.

## Rollout

```bash
docker exec focus-www sh -lc 'cd /www && bin/console cache:clear'
docker exec focus-www sh -lc 'cd /www && bin/console cache:warmup --env=prod'
```

- `theme:compile` ist nur nötig, wenn SCSS oder `theme.json` geändert werden. Dieser Plan ändert nur PHP und Twig.
- `assets:install` ist nicht nötig.
- Keine Migration und keine neuen System-Config-Keys.

## Risiken und offene Punkte

- **Feldname und Werteformat** sind hart kodiert (`topdata_ipim_lsva` / `yes`). Ändert IPIM das Schema, muss `LsvaEligibilityService` angepasst werden.
- **Login-Umleitung:** Das Better-Checkout-Plugin blockiert den Gast-Checkout heute per Fehlermeldung, nicht per automatischem Redirect. Das entspricht dem abgestimmten Verhalten „Login erforderlich“. Ein echter Redirect ist separat umzusetzen.
- **AGENTS.md im Surcharge-Plugin ist teilweise veraltet:** Dort steht, die Basis sei die Summe der Produkt-Nettopreise. Der aktuelle Code nutzt `positionPrice`. Bei der Umsetzung die Doku aktualisieren, nicht den Code an die alte Doku anpassen.
- **Keine automatisierten Tests im Plugin.** Die Testmatrix ist manuell abzuarbeiten.
