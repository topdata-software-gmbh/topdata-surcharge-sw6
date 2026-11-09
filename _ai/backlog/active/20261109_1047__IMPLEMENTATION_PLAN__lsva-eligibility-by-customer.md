# Implementation Plan: LSVA nur an der Kasse für berechtigte Bestandskunden

**Datum:** 2026-11-09  
**Plugin:** `TopdataSurchargeSW6`  
**Status:** Design überarbeitet, Implementierung offen

## Ziel

Die LSVA (Warenkorb-Aufschlag) soll künftig **nur an der Kasse** erscheinen und dort nur für eingeloggte Bestandskunden, deren IPIM-Zusatzfeld `topdata_ipim_lsva` den Wert `yes` hat.

Warenkorb, Offcanvas und Header zeigen keine LSVA. Die LSVA wird dort auch nicht berechnet, damit die angezeigten Summen konsistent sind. Erst auf der Bestellübersicht (Confirm) und bei der Bestellung selbst wird sie dem Cart hinzugefügt. Die Bestellbestätigungsmail zeigt die LSVA ebenfalls.

| Kunde | LSVA an der Kasse |
|---|---|
| Gast, E-Mail unbekannt | nein |
| Gast, E-Mail bekannt | Login erforderlich; danach entscheidet das IPIM-Feld des Hauptkunden |
| Neukunde nach Registrierung | nein |
| Eingeloggt, `topdata_ipim_lsva = no` | nein |
| Eingeloggt, `topdata_ipim_lsva = yes` | ja |

Kontakt-Login erbt das IPIM-Feld des Hauptkunden, weil der Contact-Login den Hauptkunden in den `SalesChannelContext` setzt und dessen `customFields` erhalten bleiben.

## Nicht-Ziele

- Keine LSVA im Warenkorb, Offcanvas oder Header – weder sichtbar noch in den Summen.
- Keine Änderung an der E-Mail-Blockierung im Better-Checkout-Plugin. Die heutige Blockierung bei bekannter Gast-E-Mail bleibt bestehen. Eine echte automatische Redirect-Logik wäre ein separater Eingriff in `topdata-better-checkout-sw6`.
- Keine Änderung an Steuersatz, Rappenrundung oder der LSVA-Höhe.
- Kein Eingriff in das externe IPIM-System. Das Zusatzfeld `topdata_ipim_lsva` wird dort angelegt und gepflegt.
- Keine Änderung an `summary.html.twig`, `cart-widget.html.twig`, `offcanvas-cart-summary.html.twig` oder `line-item.html.twig`. Diese Templates reagieren bereits korrekt auf das Vorhandensein bzw. Fehlen des `surcharge`-LineItems.

## Entscheidungen

1. **Eligibility im PHP-Cart-Prozessor, nicht in Twig.**  
   Twig rendert nur, was im Cart liegt. Eine reine Twig-Ausblendung würde die LSVA in Summen, Steuern und Bestellungen belassen.

2. **Neuer Service `LsvaEligibilityService`.**  
   Kapselt die Kundenprüfung, bleibt testbar und verhindert verstreute Feldzugriffe.

3. **Checkout-Only über eine Route-Allowlist im Prozessor.**  
   Die LSVA wird nur berechnet, wenn die aktuelle Anfrage eine der Checkout-Routen ist:
   - `frontend.checkout.confirm.page` (Bestellübersicht)
   - `frontend.checkout.finish.order` (Bestellung abschicken)

   Auf allen anderen Routen (Warenkorb, Offcanvas, Header, PDP, AJAX-Updates) entsteht kein LSVA-LineItem. Änderungen auf der Confirm-Seite (Menge, Gutschein, Zahlart, Versand) leiten anschließend auf `frontend.checkout.confirm.page` um und berechnen die LSVA dort neu.

4. **Werte-Normalisierung.**  
   `true` (Boolean) gilt als berechtigt; Strings werden getrimmt und kleingeschrieben; nur `yes` gilt als berechtigt. `no`, `null`, leer und unbekannte Werte gelten als nicht berechtigt.

5. **Kein Compounding.**  
   Die Basis bleibt `$toCalculate->getPrice()->getPositionPrice()`. Der Prozessor läuft mit Priorität 4500 nach Produkten und Promotions. Die LSVA wird pro Berechnung frisch hinzugefügt und ist beim Berechnen der Basis noch nicht enthalten.

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
  ├─ Route in Allowlist?
  │    ├─ nein ──> vorhandenes surcharge-LineItem entfernen, return
  │    └─ ja
  ├─ LsvaEligibilityService::isEligible(context)?
  │    ├─ nein ──> vorhandenes surcharge-LineItem entfernen, return
  │    └─ ja ────> LSVA berechnen und hinzufügen
  └─ Twig rendert das LineItem nur, wenn es existiert
```

Wichtig: Auf der Warenkorbseite wird der Cart neu berechnet. Da die Route dort nicht freigegeben ist, wird die LSVA nicht hinzugefügt und verschwindet auch aus einem zuvor berechneten Cart. Auf der Confirm-Seite wird sie neu hinzugefügt.

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

Neuen Service registrieren und `RequestStack` sowie den Eligibility-Service in den Prozessor injizieren:

```xml
<service id="Topdata\TopdataSurchargeSW6\Service\LsvaEligibilityService"/>

<service id="Topdata\TopdataSurchargeSW6\Core\Checkout\Cart\SurchargeCartProcessor">
    <argument type="service" id="Shopware\Core\System\SystemConfig\SystemConfigService"/>
    <argument type="service" id="tax.repository"/>
    <argument type="service" id="translator"/>
    <argument type="service" id="request_stack"/>
    <argument type="service" id="Topdata\TopdataSurchargeSW6\Service\LsvaEligibilityService"/>
    <tag name="shopware.cart.processor" priority="4500" />
</service>
```

### 3. `src/Core/Checkout/Cart/SurchargeCartProcessor.php`

- Konstruktor erweitern:
  - `private readonly RequestStack $requestStack`
  - `private readonly LsvaEligibilityService $lsvaEligibilityService`
- Konstante für die erlaubten Routen anlegen.

```php
private const CHECKOUT_ROUTES = [
    'frontend.checkout.confirm.page',
    'frontend.checkout.finish.order',
];
```

- Nach den bestehenden Konfigurations- und Produktprüfungen:

```php
$surchargeId = '06a3e52f7afc42d69e255068886f1d22';
$request = $this->requestStack->getCurrentRequest();
$route = $request?->attributes->get('_route');

if (!in_array($route, self::CHECKOUT_ROUTES, true)) {
    $this->removeSurcharge($toCalculate, $surchargeId);

    return;
}

if (!$this->lsvaEligibilityService->isEligible($context)) {
    $this->removeSurcharge($toCalculate, $surchargeId);

    return;
}
```

- Hilfsmethode:

```php
private function removeSurcharge(Cart $cart, string $surchargeId): void
{
    if ($cart->has($surchargeId)) {
        $cart->getLineItems()->remove($surchargeId);
    }
}
```

- `Cart::remove()` nicht verwenden: Das LineItem ist `removable=false`, `Cart::remove()` wirft eine Exception. Die LineItem-Collection entfernt es direkt.
- Reihenfolge beachten: Route und Eligibility prüfen, bevor Steuersatz und Betrag geladen werden, um unnötige Repository-Abfragen zu sparen.
- Ohne HTTP-Request (CLI, Admin-Kontexte) liefert `getCurrentRequest()` `null`; die LSVA wird dann nicht hinzugefügt.

### 4. Keine Twig-Änderungen für die Anzeige

Diese Dateien bleiben unverändert:

- `src/Resources/views/storefront/page/checkout/summary.html.twig` – zeigt die LSVA-Zeile nur, wenn ein `surcharge`-LineItem vorhanden ist; auf der Warenkorbseite ist keins vorhanden.
- `src/Resources/views/storefront/component/checkout/offcanvas-cart-summary.html.twig` – findet kein `surcharge`-LineItem und zeigt keine LSVA.
- `src/Resources/views/storefront/layout/header/actions/cart-widget.html.twig` – findet kein `surcharge`-LineItem; Betrag und Anzahl bleiben unverändert.
- `src/Resources/views/storefront/component/line-item/line-item.html.twig` – blendet das `surcharge`-LineItem in der Positionsliste aus; auf der Confirm-Seite erscheint die LSVA dadurch nur in der Summary.

### 5. Bestellbestätigungsmail

Die Bestellbestätigungsmail zeigt die LSVA bereits, ohne dass Templates geändert werden müssen:

- `src/Subscriber/MailSurchargeSubscriber.php` reagiert auf `MailBeforeValidateEvent`, sucht das `surcharge`-OrderLineItem, entfernt es aus `order.lineItems` und stellt `surchargeLabel` sowie `surchargeTotal` als Template-Daten bereit.
- Die Datenbank-Mail-Templates für `order_confirmation_mail` enthalten bereits einen Block `{% if surchargeLabel %}` und rendern `surchargeLabel` + `surchargeTotal`.
- Ist keine LSVA in der Bestellung (Gast, Neukunde, `no`), bleiben beide Variablen `null`; der Block wird nicht gerendert.
- Es sind **keine Änderungen** an Mail-Templates, Snippets oder am `MailSurchargeSubscriber` nötig.

### 6. Cleanup vor Go-Live

Diese Reste sind nicht Teil der LSVA-Logik, müssen aber vor dem Release entfernt werden:

- `src/Resources/views/storefront/layout/header/header.html.twig`: Debug-Ausgabe `HURRA, ES KLAPPT!`.
- `src/Resources/views/storefront/page/checkout/cart/index.html.twig`: orange Testbox `TEST: ICH BIN DIE WARENKORB-ZUSAMMENFASSUNG`.
- `src/Resources/views/storefront/page/checkout/confirm/index.html.twig`: purple Testbox und kaputter Text.
- `src/Resources/views/storefront/page/checkout/confirm/confirm-checkout.html.twig`: prüfen, ob die Datei in Shopware 6.7 überhaupt gerendert wird. Sie erweitert `confirm-checkout-summary.html.twig`, das im Core nicht existiert. Entweder löschen oder korrekt anbinden, sonst droht eine doppelte LSVA-Anzeige.

## Anzeige-Verhalten nach der Änderung

- **Warenkorb (`frontend.checkout.cart.page`):** Kein `surcharge`-LineItem. Keine LSVA in Summary, Header oder Offcanvas. Alle Summen ohne LSVA.
- **Offcanvas (`frontend.cart.offcanvas`):** Kein `surcharge`-LineItem. Keine LSVA.
- **PDP/Listing:** Kein `surcharge`-LineItem. Header und Offcanvas zeigen keine LSVA.
- **Confirm (`frontend.checkout.confirm.page`):** `surcharge`-LineItem wird berechnet. Summary zeigt die LSVA-Zeile; Gesamtsumme enthält sie. Die Positionsliste blendet sie weiterhin aus.
- **Bestellung (`frontend.checkout.finish.order`):** LSVA wird erneut berechnet und ist in der Bestellung enthalten.
- **Finish-Seite:** Order-Summary und Order-Detail zeigen die LSVA, weil sie im Order-Objekt enthalten ist.
- **Bestellbestätigungsmail:** Zeigt die LSVA-Zeile über `surchargeLabel`/`surchargeTotal`, sofern die Bestellung eine LSVA enthält.
- **Zurück zum Warenkorb:** Beim Neuberechnen wird die LSVA nicht mehr hinzugefügt und verschwindet.
- **Login/Logout:** Beim nächsten Confirm-Aufruf entscheidet der aktuelle Kundenstatus; alte LineItems werden nicht fortgeschrieben.

## Testmatrix (manuell)

Voraussetzung: Testkunden mit `topdata_ipim_lsva = yes` und `no` über Admin oder DB setzen. Das Feld wird nicht vom Plugin angelegt.

1. Eingeloggter `yes`-Kunde, Warenkorb: keine LSVA, Summe ohne LSVA.
2. Derselbe Kunde, Confirm: LSVA-Zeile sichtbar, Gesamtsumme mit LSVA.
3. Zurück zum Warenkorb: LSVA verschwindet wieder.
4. Bestellung abschicken: LSVA ist in der Bestellung, im Order-Detail und in der Bestellbestätigungsmail enthalten (deutsch und französisch prüfen).
5. Eingeloggter `no`-Kunde, Confirm: keine LSVA, Summe ohne LSVA.
6. Neukunde nach Registrierung, Confirm: keine LSVA.
7. Gast, unbekannte E-Mail, Checkout: keine LSVA in Bestellung oder Mail.
8. Gast, bekannte E-Mail: bestehende Blockierung/Login-Aufforderung; danach entscheidet das IPIM-Feld.
9. Kontakt-Login eines `yes`-Hauptkunden: LSVA; Kontakt eines `no`-Hauptkunden: keine LSVA.
10. Mengenänderung oder Gutschein auf der Confirm-Seite: LSVA-Betrag wird neu berechnet, keine Doppelberechnung.
11. Offcanvas und Header auf PDP, Warenkorb und Confirm: keine LSVA.
12. Steueranzeige und Rappenrundung auf der Confirm-Seite unverändert.

## Rollout

```bash
docker exec focus-www sh -lc 'cd /www && bin/console cache:clear'
docker exec focus-www sh -lc 'cd /www && bin/console cache:warmup --env=prod'
```

- `theme:compile` ist nur nötig, wenn SCSS oder `theme.json` geändert werden. Dieser Plan ändert nur PHP und Services.
- `assets:install` ist nicht nötig.
- Keine Migration und keine neuen System-Config-Keys.

## Risiken und offene Punkte

- **Route-Allowlist ist wartungsbedürftig.** Wird künftig eine weitere Route benötigt, die eine Bestellung erzeugt oder die Confirm-Summary rendert, muss sie ergänzt werden. Insbesondere `frontend.checkout.finish.order` darf nicht entfernt werden.
- **Drittanbieter-Checkout-Routen:** Plugins, die eine Bestellung außerhalb von `frontend.checkout.finish.order` erzeugen, würden die LSVA nicht berechnen. Vor dem Rollout prüfen, ob der Shop solche Plugins einsetzt.
- **Feldname und Werteformat** sind hart kodiert (`topdata_ipim_lsva` / `yes`). Ändert IPIM das Schema, muss `LsvaEligibilityService` angepasst werden.
- **Login-Umleitung:** Das Better-Checkout-Plugin blockiert den Gast-Checkout heute per Fehlermeldung, nicht per automatischem Redirect. Das entspricht dem abgestimmten Verhalten „Login erforderlich“. Ein echter Redirect ist separat umzusetzen.
- **AGENTS.md im Surcharge-Plugin ist teilweise veraltet:** Dort steht, die Basis sei die Summe der Produkt-Nettopreise. Der aktuelle Code nutzt `positionPrice`. Bei der Umsetzung die Doku aktualisieren, nicht den Code an die alte Doku anpassen.
- **Keine automatisierten Tests im Plugin.** Die Testmatrix ist manuell abzuarbeiten.
