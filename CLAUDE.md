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

Gearbeitet und released wird auf **`test_v21`** (liegt weit vor `master`; die Nutzer
installieren über den Module Store bzw. diesen Branch). `master` nicht als Basis nehmen.

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

## Besonderheiten

- Die DND-Zeiten (`dnd_starttime`/`dnd_endtime`) sind Unix-Timestamps mit
  DateTime-Presentation „nur Uhrzeit" (`VariablePresentations::timeOnly()`, DATE=0/TIME=2).
- `GetValueFormatted()` wird an mehreren Stellen zur Anzeige verwendet — Presentations
  der betroffenen Variablen nicht entfernen, ohne diese Stellen zu prüfen.
- Timeout beim UDP-Senden ist bewusst 5 s (`load_multi_map` braucht länger als 2 s).
