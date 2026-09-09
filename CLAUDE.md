# Roborock — Projekt-Hinweise

IP-Symcon-Modulbibliothek zur Steuerung von Roborock-/Xiaomi-Saugrobotern
(`IPSModuleStrict`, zwei Module).

## Struktur

- `Roborock IO/module.php` — I/O-Instanz: UDP-Kommunikation (miIO-Protokoll, Port 54321),
  Cloud-Login (Xiaomi-Konto inkl. 2FA-Verifizierung), Warteschlange, Webhook
- `Roborock Robot/module.php` — Geräteinstanz: Variablen, Timer, Karte, Formular
  (das Konfigurationsformular wird **im Code** aufgebaut — `FormElements()`/`FormActions()`,
  keine form.json)
- `Roborock Robot/roborock_vacuum.php` — Gerätedefinitionen (Modelle, Verbrauchsmaterial,
  Fan-Power-Stufen je Modell)
- `Roborock Robot/RRMapFileParser.php` / `RRMapDraw.php` — Kartendaten parsen/zeichnen
- `libs/VariablePresentations.php` — Presentation-Helfer (statt Legacy-Profilen)
- `libs/picture.php`, `libs/joystick.html` — Karten-Media und Fernbedienungs-HTML
- `library.json` (Repo-Wurzel) — Version, Build, Datum (Build-Konvention siehe globale CLAUDE.md)

## Branches

Gearbeitet und released wird auf **`master`**; darauf zeigen auch die Store-Releases.
Den früher hier genannten Branch `test_v21` gibt es nicht mehr — er ist in `master`
aufgegangen (geprüft 09.09.2026: weder lokal noch auf origin vorhanden, die Builds 86–88
liegen sämtlich in der master-Historie). Die übrigen Zweige sind Altstände: `Beta` (2020),
`Old_Version` (2019), `master_v11` (2022).

## Übersetzungen

Englische Texte sind die Übersetzungsschlüssel; deutsche Übersetzungen in
`Roborock Robot/locale.json` bzw. `Roborock IO/locale.json`. Da das Formular im Code
aufgebaut wird, gelten auch die `'caption'/'label'/'suffix'`-Literale in module.php als
Schlüssel. Vollständigkeit prüfen mit:

```
php tests/check_locale.php
```

(läuft auch in der CI; viele de-Schlüssel werden nur dynamisch genutzt — z. B.
Wochentage, Statusnamen — und erscheinen deshalb als „verwaist", das ist kein Fehler).

## Kartenzeichnung: Bildpunkte sind festgehalten

```
php tests/check-map-rendering.php
```

Zeichnet eine synthetische Karte über alle Zeichenwege von `RRMapDraw` und vergleicht die
Bildpunkte je Maßstab (1,0 / 1,5 / 2,0) gegen festgehaltene Prüfsummen. Der Test ist
entstanden, um `declare(strict_types=1)` abzusichern: Der Kartenpfad rechnet durchweg mit
`float` — `getImage(float $scale)` reicht den Maßstab bis in jede Zeichenmethode — und
übergibt die Ergebnisse an GD-Funktionen, die `int` erwarten.

**Ein (int)-Cast gehört genau dorthin, wo PHP ihn ohne `strict_types` implizit gemacht
hätte: an den GD-Aufruf, nicht schon an die Berechnung der Koordinate.** Ein Versuch,
bereits die Zuweisung zu casten, verschob das Bild bei den Maßstäben 1,5 und 2,0 um einen
Bildpunkt, weil `drawObstacles` mit `round($x - imagesx(...) / 2)` weiterrechnet.

Gehasht werden die Bildpunkte, nicht die PNG-Datei — deren Kodierung hängt an der zlib- und
libgd-Fassung und wäre auf dem Bauserver eine andere. Ändert sich der Zeichencode
absichtlich, die Karte ansehen und die Sollwerte mit `MAP_HASH_ERMITTELN=1` neu ermitteln.
Lokal braucht der Lauf GD: `php -d extension=gd tests/check-map-rendering.php`.

## Besonderheiten

- Die DND-Zeiten (`dnd_starttime`/`dnd_endtime`) sind Unix-Timestamps mit
  DateTime-Presentation „nur Uhrzeit" (`VariablePresentations::timeOnly()`, DATE=0/TIME=2).
- `GetValueFormatted()` wird an mehreren Stellen zur Anzeige verwendet — Presentations
  der betroffenen Variablen nicht entfernen, ohne diese Stellen zu prüfen.
- Timeout beim UDP-Senden ist bewusst 5 s (`load_multi_map` braucht länger als 2 s).
