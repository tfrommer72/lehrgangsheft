# Lehrgangsheft DLRG LV Baden

Blätterbares Seminarheft im DLRG-CD, das sich täglich selbst aus baden.dlrg.de aktualisiert.

- Neue Lehrgänge erscheinen erst nach Freigabe im Admin-Bereich.
- „Jetzt aktualisieren“ für eilige Änderungen.
- Online: „Jetzt anmelden“ je Lehrgang, Sprungliste und anklickbares Inhaltsverzeichnis.
- PDF-Export für das ganze Heft und für einzelne Lehrgänge (Inhaltsverzeichnis mit Sprunglinks).
- Reines PHP ohne Framework und ohne Composer, Datenbank SQLite, PDF über Chromium.

Installation: [docs/INSTALL.md](docs/INSTALL.md)

## Aufbau

```
bin/sync.php          täglicher Abruf (Cron) und „Jetzt aktualisieren“
bin/passwort.php      Passwort-Hash für config.php erzeugen
src/Quelle.php        liest Liste (data-data-JSON) und Detailseiten (dt/dd) von baden.dlrg.de
src/Sync.php          gleicht mit der Datenbank ab; neue Lehrgänge = nicht freigegeben
src/Heft.php          stellt die freigegebenen Lehrgänge für das Heft zusammen
src/Pdf.php           PDF-Export über Chromium, zwischengespeichert je Inhaltsstand
public/index.php      Heft, PDFs, Admin-Bereich
public/assets/heft.js baut die Seiten (Blättern und Druck), QR-Codes im Browser
views/                HTML der Seiten
tests/run.php         Tests mit nachgebauten Seiten von baden.dlrg.de
```
