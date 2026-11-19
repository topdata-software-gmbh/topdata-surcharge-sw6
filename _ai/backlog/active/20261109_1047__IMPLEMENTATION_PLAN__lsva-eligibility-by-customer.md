# Implementation Plan: LSVA nur bei LSVA-pflichtigen Kunden, überall sichtbar

**Datum:** 2026-11-10  
**Plugin:** `TopdataSurchargeSW6`  
**Status:** Umgesetzt (2026-11-19)

## Ziel

Die LSVA ist eine **zusätzliche Steuer**, keine Vergünstigung und keine Berechtigung. Sie bleibt wie bisher in **Warenkorb, Offcanvas, Checkout und Bestellbestätigungsmail** sichtbar, wird aber nur noch berechnet, wenn der Kunde LSVA-pflichtig ist.

LSVA-pflichtig ist ein Kunde, wenn im `SalesChannelContext` ein Customer vorhanden ist und dessen Kunden-Zusatzfeld aus dem Feld-Set `topdata_tg` (`topdata_tg_lsva`) den Wert `yes` hat. `yes` bedeutet: **LSVA wird erhoben.** **Gastkunden sind nicht pauschal ausgeschlossen.** Sie werden gespeichert und müssen geprüft werden.

**Änderungsumfang:** ein neuer Prüfservice, dessen Registrierung, ein neues Konfigurationsfeld für den Custom-Field-Namen und ein Gate im bestehenden `SurchargeCartProcessor`. Die bestehende Berechnung, das LineItem, die Steuerlogik, alle Templates, die Mail-Logik, der Header und die Better-Checkout-Logik bleiben unverändert.

| Kunde | LSVA |
|---|---|
| Kein Customer im Context (nicht eingeloggt, kein Gast-Kontext) | nein |
| Gast, aktueller Datensatz `topdata_tg_lsva = yes` | ja |
| Gast, aktueller Datensatz `topdata_tg_lsva = no` | nein |
| Gast, aktueller Datensatz ohne/leer/unbekannt, früherer Gast mit gleicher E-Mail hat Wert | Wert des **neuesten** früheren Gastdatensatzes mit gesetztem Wert |
| Gast, kein früherer Gastdatensatz mit Wert | nein |
| Eingeloggter Bestandskunde, `yes` | ja |
| Eingeloggter Bestandskunde, `no`/leer | nein |
| Neukunde nach Registrierung | nein |
| Kontakt-Login | Wert des Hauptkunden |
| Gast-E-Mail gehört registriertem Kunden/Kontakt | Better-Checkout blockiert weiterhin; nach Login entscheidet der Hauptkunde |

## Nicht-Ziele

- Keine Route-Allowlist und kein `RequestStack`. Die LSVA wird nicht mehr nur an der Kasse berechnet.
- Keine Änderung an `cart-widget.html.twig`, `summary.html.twig`, `offcanvas-cart-summary.html.twig` oder `line-item.html.twig`. Diese Templates reagieren bereits korrekt auf das Vorhandensein bzw. Fehlen des `surcharge`-LineItems.
- **Keine Änderung an der bestehenden Berechnung, am LineItem, an der Steuerlogik, an Steuersatz, Rappenrundung oder LSVA-Höhe.** Die LSVA wird weiterhin genau wie heute berechnet.
- **Keine Änderung an der Better-Checkout-Logik.** Die bestehende E-Mail-Blockierung bei bekannten Nicht-Gast-E-Mails bleibt unverändert.
- Kein Eingriff in das Feld-Set. Das Zusatzfeld `topdata_tg_lsva` im Set `topdata_tg` wird extern angelegt und gepflegt.
- Keine Änderung an Mail-Templates oder Snippets. `MailSurchargeSubscriber` und die DB-Templates funktionieren bereits.

## Entscheidungen

1. **LSVA-Pflichtprüfung im PHP-Cart-Prozessor, nicht in Twig.**  
   Twig rendert nur, was im Cart liegt. Eine reine Twig-Ausblendung würde die LSVA in Summen, Steuern und Bestellungen belassen.

2. **Neuer Service `LsvaTaxApplicabilityService`.**  
   Kapselt die Prüfung, ob die zusätzliche Steuer erhoben werden muss, die Werte-Normalisierung und den Gast-Fallback per E-Mail.

3. **Keine Route-Sonderregel.**  
   Sobald der Context einen LSVA-pflichtigen Customer enthält, erscheint die LSVA überall. Ohne Customer entsteht kein LSVA-LineItem.

4. **Werte-Normalisierung.**  
   `true`/`yes`/`ja`/`1` = LSVA wird erhoben.  
   `false`/`no`/`nein`/`0` = keine LSVA.  
   `null`, leer und unbekannte Werte = kein expliziter Wert; bei Gästen wird der E-Mail-Fallback versucht.

5. **Gast-Fallback per E-Mail.**  
   Nur wenn der aktuelle Customer ein Gast ist und **keinen verwertbaren Wert** hat, wird der neueste frühere Gastdatensatz mit derselben E-Mail und einem gesetzten LSVA-Wert gesucht. Der aktuelle Datensatz hat immer Vorrang. Es wird **nur der LSVA-Wert** übernommen; keine Adressen, keine Customer-ID, keine Bestellungen.

6. **Kein Compounding.**  
   Die Basis bleibt `$toCalculate->getPrice()->getPositionPrice()`. Der Prozessor läuft mit Priorität 4500 nach Produkten und Promotions. Die LSVA wird pro Berechnung frisch hinzugefügt und ist beim Berechnen der Basis noch nicht enthalten.

7. **LineItem-Entfernung über die Collection.**  
   `Cart::remove()` nicht verwenden; das LineItem ist `removable=false`. Stattdessen `$cart->getLineItems()->remove($surchargeId)`.

8. **Feldname konfigurierbar.**  
   Der technische Name des Kunden-Custom-Fields wird als Plugin-Konfiguration gepflegt (`TopdataSurchargeSW6.config.lsvaCustomerField`). Standardwert: `topdata_tg_lsva`. Leere oder ungültige Werte fallen auf den Standard zurück.

## Architektur und Datenfluss

```
SalesChannelContext
  └─ customer
       ├─ null ─────────────────────────────> keine LSVA
       └─ customFields[lsvaCustomerField]  (Default: topdata_tg_lsva)
            ├─ yes/ja/true/1 ───────────────> LSVA wird erhoben
            ├─ no/nein/false/0 ─────────────> keine LSVA
            └─ fehlt/leer/unbekannt
                 ├─ kein Gast ──────────────> keine LSVA
                 └─ Gast
                      └─ neuester früherer Gast mit gleicher E-Mail
                           ├─ yes ──────────> LSVA wird erhoben
                           ├─ no ───────────> keine LSVA
                           └─ kein Wert ────> keine LSVA

SurchargeCartProcessor
  ├─ Plugin aktiv? Prozentsatz > 0? Produkte im Cart? Steuersatz vorhanden?
  ├─ LsvaTaxApplicabilityService::isLsvaApplicable(context)?
  │    ├─ nein ──> vorhandenes surcharge-LineItem entfernen, return
  │    └─ ja ────> LSVA berechnen und hinzufügen
  └─ Twig rendert das LineItem nur, wenn es existiert
```

## Dateien und Änderungen

### 1. Neu: `src/Service/LsvaTaxApplicabilityService.php`

```php
<?php declare(strict_types=1);

namespace Topdata\TopdataSurchargeSW6\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class LsvaTaxApplicabilityService
{
    public const DEFAULT_CUSTOM_FIELD = 'topdata_tg_lsva';
    public const CONFIG_KEY = 'TopdataSurchargeSW6.config.lsvaCustomerField';

    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function isLsvaApplicable(SalesChannelContext $context): bool
    {
        $customer = $context->getCustomer();

        if ($customer === null) {
            return false;
        }

        $fieldName = $this->getFieldName($context);
        $value = $this->normalize($customer->getCustomFields()[$fieldName] ?? null);

        if ($value !== null) {
            return $value;
        }

        if (!$customer->getGuest()) {
            return false;
        }

        return $this->findPreviousGuestValue($customer, $fieldName) ?? false;
    }

    private function getFieldName(SalesChannelContext $context): string
    {
        $fieldName = trim($this->systemConfigService->getString(
            self::CONFIG_KEY,
            $context->getSalesChannelId()
        ));

        if ($fieldName === '' || preg_match('/^[a-zA-Z0-9_]+$/', $fieldName) !== 1) {
            return self::DEFAULT_CUSTOM_FIELD;
        }

        return $fieldName;
    }

    private function findPreviousGuestValue(CustomerEntity $customer, string $fieldName): ?bool
    {
        $path = '$.'.$fieldName;

        $rows = $this->connection->fetchAllAssociative(
            'SELECT custom_fields
             FROM customer
             WHERE email = :email
               AND guest = 1
               AND LOWER(HEX(id)) != :currentId
               AND JSON_EXTRACT(custom_fields, :path) IS NOT NULL
             ORDER BY created_at DESC
             LIMIT 25',
            [
                'email' => $customer->getEmail(),
                'currentId' => $customer->getId(),
                'path' => $path,
            ]
        );

        foreach ($rows as $row) {
            $fields = json_decode((string) ($row['custom_fields'] ?? '[]'), true);
            $value = $this->normalize($fields[$fieldName] ?? null);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function normalize(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return match ($value) {
                1 => true,
                0 => false,
                default => null,
            };
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        if (in_array($normalized, ['yes', 'ja', 'true', '1'], true)) {
            return true;
        }

        if (in_array($normalized, ['no', 'nein', 'false', '0'], true)) {
            return false;
        }

        return null;
    }
}
```

Hinweise:

- `JSON_EXTRACT(custom_fields, :path)` liest den Wert aus dem JSON-Feld, ohne die gesamte Customer-Tabelle zu laden.
- Der Feldname kommt aus der Plugin-Konfiguration. Nur `[a-zA-Z0-9_]+` ist erlaubt; sonst greift der Standardwert. Dadurch bleibt der JSON-Pfad sicher.
- Der aktuelle Customer wird per Hex-ID ausgeschlossen.
- Falls die JSON-Path-Bindung mit Doctrine nicht funktioniert, den Pfad als Literal in den SQL-String schreiben.
- `LIMIT 25` reicht für die Praxis; der erste Datensatz mit verwertbarem Wert gewinnt.
- Kein Caching im Service: eine Query pro Gast-Cart-Berechnung ohne Wert ist vertretbar.

### 2. `src/Resources/config/config.xml` und `services.xml`

Neues Konfigurationsfeld für den Custom-Field-Namen:

```xml
<input-field type="text">
    <name>lsvaCustomerField</name>
    <label>Custom-Field für LSVA-Pflicht</label>
    <label lang="en-GB">Custom field for LSVA eligibility</label>
    <label lang="fr-FR">Custom field pour l'assujettissement LSVA</label>
    <defaultValue>topdata_tg_lsva</defaultValue>
    <helpText>Technischer Name des Kunden-Custom-Fields aus dem Feld-Set topdata_tg. Standard: topdata_tg_lsva.</helpText>
    <helpText lang="en-GB">Technical name of the customer custom field from the field set topdata_tg. Default: topdata_tg_lsva.</helpText>
    <helpText lang="fr-FR">Nom technique du custom field client du field set topdata_tg. Valeur par défaut : topdata_tg_lsva.</helpText>
</input-field>
```

Neuen Service registrieren und in den Prozessor injizieren:

```xml
<service id="Topdata\TopdataSurchargeSW6\Service\LsvaTaxApplicabilityService">
    <argument type="service" id="Doctrine\DBAL\Connection"/>
    <argument type="service" id="Shopware\Core\System\SystemConfig\SystemConfigService"/>
</service>

<service id="Topdata\TopdataSurchargeSW6\Core\Checkout\Cart\SurchargeCartProcessor">
    <argument type="service" id="Shopware\Core\System\SystemConfig\SystemConfigService"/>
    <argument type="service" id="tax.repository"/>
    <argument type="service" id="translator"/>
    <argument type="service" id="Topdata\TopdataSurchargeSW6\Service\LsvaTaxApplicabilityService"/>
    <tag name="shopware.cart.processor" priority="4500" />
</service>
```

`request_stack` wird **nicht** mehr benötigt.

### 3. `src/Core/Checkout/Cart/SurchargeCartProcessor.php`

- Konstruktor um `LsvaTaxApplicabilityService` erweitern.
- Nach den bestehenden Konfigurationsprüfungen und vor der Steuersatz-Abfrage:

```php
$surchargeId = '06a3e52f7afc42d69e255068886f1d22';

if (!$this->lsvaTaxApplicabilityService->isLsvaApplicable($context)) {
    if ($toCalculate->has($surchargeId)) {
        $toCalculate->getLineItems()->remove($surchargeId);
    }

    return;
}

if ($toCalculate->has($surchargeId)) {
    return;
}
```

- Die bestehende Berechnung und das Hinzufügen des LineItems bleiben unverändert.
- Der bisherige frühe `return` bei leerem Produkt-Cart bleibt bestehen; die LSVA-Pflichtprüfung kann davor oder danach erfolgen. Empfehlung: nach der Produktprüfung, damit ein leerer Cart keine zusätzliche Customer-Query auslöst.
- `Cart::remove()` nicht verwenden.

### 4. Keine Twig-Änderungen für die Anzeige

Diese Dateien bleiben unverändert:

- `src/Resources/views/storefront/page/checkout/summary.html.twig`
- `src/Resources/views/storefront/component/checkout/offcanvas-cart-summary.html.twig`
- `src/Resources/views/storefront/layout/header/actions/cart-widget.html.twig`
- `src/Resources/views/storefront/component/line-item/line-item.html.twig`

Das `surcharge`-LineItem wird in der Positionsliste weiterhin ausgeblendet und in den Summen angezeigt.

### 5. Bestellbestätigungsmail

Keine Änderung nötig:

- `src/Subscriber/MailSurchargeSubscriber.php` entfernt das `surcharge`-OrderLineItem aus `order.lineItems` und stellt `surchargeLabel` sowie `surchargeTotal` bereit.
- Die DB-Templates für `order_confirmation_mail` rendern bereits `{% if surchargeLabel %}`.
- Ohne LSVA bleiben beide Variablen `null`; der Block wird nicht gerendert.
- Labels: DE `LSVA`, FR `RPLP`.

### 6. Cleanup vor Go-Live (erledigt)

Die folgenden Debug-/Defekt-Reste wurden entfernt:

- `src/Resources/views/storefront/layout/header/header.html.twig`: Debug-Ausgabe `HURRA, ES KLAPPT!` — Datei gelöscht.
- `src/Resources/views/storefront/page/checkout/cart/index.html.twig`: orange Testbox `TEST: ICH BIN DIE WARENKORB-ZUSAMMENFASSUNG` — Datei gelöscht.
- `src/Resources/views/storefront/page/checkout/confirm/index.html.twig`: purple Testbox und kaputter Text — Datei gelöscht.
- `src/Resources/views/storefront/page/checkout/confirm/confirm-checkout.html.twig`: erweiterte das im Core nicht existierende `confirm-checkout-summary.html.twig` — Datei gelöscht; die Anzeige im Checkout erfolgt über `summary.html.twig`.

## Anzeige-Verhalten nach der Änderung

- **Anonymer Besucher ohne Customer:** Kein `surcharge`-LineItem. Keine LSVA in Warenkorb, Offcanvas, Header oder Checkout.
- **Eingeloggter `yes`-Kunde:** LSVA in Warenkorb, Offcanvas, Checkout und Mail.
- **Eingeloggter `no`-Kunde:** Keine LSVA.
- **Gast mit `yes` im aktuellen Datensatz:** LSVA überall, wo der Gast-Customer im Context ist.
- **Gast ohne Wert, früherer Gast mit `yes`:** LSVA nach dem Gast-Checkout, sobald der neue Gast-Customer im Context ist.
- **Gast ohne Wert, früherer Gast mit `no`:** Keine LSVA.
- **Neukunde:** Keine LSVA, bis das Feld gesetzt wird.
- **Login/Logout:** Beim nächsten Cart-Recalculate wird das LineItem hinzugefügt oder entfernt.
- **Header:** Bleibt unverändert; kein Eingriff in die Anzeige.

## Testmatrix (manuell)

Voraussetzung: Testkunden mit `topdata_tg_lsva = yes` und `no` über Admin oder DB setzen. Das Feld wird extern im Feld-Set `topdata_tg` angelegt, nicht vom Plugin.

1. Anonymer Besucher, Warenkorb/Offcanvas: keine LSVA.
2. Eingeloggter `yes`-Kunde, Warenkorb: LSVA sichtbar, Summe enthält LSVA.
3. Derselbe Kunde, Offcanvas: LSVA sichtbar.
4. Derselbe Kunde, Checkout: LSVA sichtbar, keine Doppelberechnung.
5. Bestellung abschicken: LSVA in Bestellung, Order-Detail und Mail (DE/FR).
6. Eingeloggter `no`-Kunde: keine LSVA.
7. Neukunde nach Registrierung: keine LSVA.
8. Gast, aktueller Datensatz `yes`: LSVA im Checkout und in der Mail.
9. Gast, aktueller Datensatz `no`: keine LSVA.
10. Gast, aktueller Datensatz ohne Wert, früherer Gast mit `yes`: LSVA per E-Mail-Fallback.
11. Gast, aktueller Datensatz ohne Wert, früherer Gast mit `no`: keine LSVA.
12. Mehrere frühere Gäste: neuester Datensatz mit gesetztem Wert gewinnt.
13. Wiederholte Gastbestellung mit gleicher E-Mail: neuer Gastdatensatz, Fallback greift.
14. Bekannte Nicht-Gast-E-Mail: Better-Checkout blockiert weiterhin; nach Login entscheidet der Hauptkunde.
15. Kontakt-Login: LSVA des Hauptkunden.
16. Login/Logout im laufenden Cart: LSVA wird korrekt hinzugefügt/entfernt.
17. Steueranzeige und Rappenrundung unverändert.
18. Konfigurierter Feldname: anderes gültiges Feld wird gelesen; leerer/ungültiger Wert fällt auf `topdata_tg_lsva` zurück.

## Rollout

```bash
docker exec focus-www sh -lc 'cd /www && bin/console cache:clear'
docker exec focus-www sh -lc 'cd /www && bin/console cache:warmup --env=prod'
```

- `plugin:refresh` ist für eine reine `config.xml`-Änderung **nicht nötig**. Es aktualisiert die Plugin-Registry/Metadaten und ist nur relevant, wenn Plugins hinzugefügt/entfernt oder Plugin-Metadaten geändert wurden.
- Falls das neue Feld im Admin wider Erwarten nicht auftaucht, `bin/console plugin:refresh` zusätzlich ausführen.
- `theme:compile` ist nur nötig, wenn SCSS oder `theme.json` geändert werden. Dieser Plan ändert nur PHP, Services und `config.xml`.
- `assets:install` ist nicht nötig.
- Keine DB-Migration; aber ein neuer System-Config-Key (`TopdataSurchargeSW6.config.lsvaCustomerField`).

## Risiken und offene Punkte

- **Unveränderlicher Rahmen:** Berechnung, LineItem, Steuerlogik, Templates, Mail und Better-Checkout bleiben unangetastet. Einzige fachliche Änderung ist die Prüfung, ob die bestehende LSVA-Berechnung ausgeführt wird.
- **Feldname** ist konfigurierbar (`TopdataSurchargeSW6.config.lsvaCustomerField`), Standard `topdata_tg_lsva`. Wird der Name geändert, werden alte Werte unter dem bisherigen JSON-Key nicht mehr gefunden; das neue Feld muss extern befüllt werden oder es braucht eine Migration. Das Werteformat ist weiterhin im Service normalisiert.
- **Feld noch nicht in der DB:** Solange `topdata_tg_lsva` nicht angelegt und befüllt ist, wird keine LSVA erhoben. Das ist bei der Inbetriebnahme zu berücksichtigen.
- **JSON-Path-Bindung:** Falls `JSON_EXTRACT(custom_fields, :path)` mit Doctrine/MariaDB nicht wie erwartet funktioniert, den Pfad als Literal in den SQL-String schreiben.
- **Gast-Fallback:** Existiert kein früherer Gastdatensatz mit gesetztem Wert, bleibt es bei „keine LSVA“. Das ist die abgestimmte Regel.
- **Keine automatisierten Tests im Plugin.** Die Testmatrix ist manuell abzuarbeiten.
- **AGENTS.md im Surcharge-Plugin ist teilweise veraltet:** Dort steht, die Basis sei die Summe der Produkt-Nettopreise. Der aktuelle Code nutzt `positionPrice`. Die Doku ist zu aktualisieren; der Code bleibt unverändert.
