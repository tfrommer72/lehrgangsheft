# Lehrgangsheft – Installation auf Proxmox

Ziel: ein eigener Container, der jeden Morgen die Lehrgänge von baden.dlrg.de holt,
neue Lehrgänge erst nach Freigabe im Heft zeigt und das Heft samt PDFs öffentlich ausliefert.

Zeitaufwand: etwa 30 Minuten.

---

## 1. Container anlegen

In Proxmox einen neuen LXC-Container anlegen:

| Einstellung | Wert |
|---|---|
| CT-ID | z. B. **108** (105 = Dienstplan, 107 = FCC sind belegt) |
| Hostname | `lehrgangsheft` |
| Vorlage | Debian 12 |
| Unprivilegiert | ja |
| Kerne / RAM | 1 Kern, **1024 MB RAM** (Chromium braucht beim PDF-Export kurz Speicher) |
| Speicher | 6 GB |
| Netz | feste IP im LAN, z. B. 192.168.10.181 |

Container starten, in die Konsole wechseln.

## 2. Pakete installieren

```bash
apt update && apt full-upgrade -y
apt install -y nginx php-fpm php-sqlite3 php-curl php-xml php-mbstring php-intl \
               chromium fonts-dejavu-core unzip cron
```

Prüfen:

```bash
php -v               # PHP 8.2 oder neuer
chromium --version   # Chromium für den PDF-Export
ls /run/php/         # Name der PHP-FPM-Socket-Datei merken (z. B. php8.2-fpm.sock)
```

## 3. Projekt einspielen

Die Zip-Datei auf den Container kopieren (z. B. per `scp` oder über Nextcloud), dann:

```bash
mkdir -p /var/www
cd /var/www
unzip /root/lehrgangsheft.zip        # ergibt /var/www/lehrgangsheft
cd lehrgangsheft
cp config.example.php config.php
chown -R www-data:www-data storage
```

## 4. Konfiguration

`config.php` öffnen (`nano config.php`) und anpassen:

- `base_url`: die öffentliche Adresse, z. B. `https://lehrgaenge.deine-domain.de`
- `intern_url`: bleibt `http://127.0.0.1`
- `chromium`: bleibt `/usr/bin/chromium`
- `admins`: für jede Person, die Lehrgänge freigeben darf, einen Eintrag.
  Den Passwort-Hash erzeugst du so:

  ```bash
  php bin/passwort.php
  ```

  Ausgabe kopieren und eintragen:

  ```php
  'admins' => [
      'torsten' => '$2y$12$....',
      'bildungsteam' => '$2y$12$....',
  ],
  ```

- `mail`: Postfach für Einladungen und „Passwort vergessen“ (siehe Abschnitt „Admins“ unten).
- `kontakt`: steht auf der Kontaktseite im Heft.
- `bereiche` / `bereich_nach_nummer`: Reihenfolge der Kapitel und automatische Zuordnung nach Lehrgangsnummer.
  Jeder Lehrgang kann im Admin-Bereich auch von Hand einem Bereich zugeordnet werden.

## 5. Webserver

```bash
cp docs/nginx-lehrgangsheft.conf /etc/nginx/sites-available/lehrgangsheft
rm -f /etc/nginx/sites-enabled/default
ln -s /etc/nginx/sites-available/lehrgangsheft /etc/nginx/sites-enabled/
```

In der Datei die Zeile `fastcgi_pass` an den Socket aus Schritt 2 anpassen, falls es
`/run/php/php-fpm.sock` nicht gibt (z. B. `unix:/run/php/php8.2-fpm.sock`). Dann:

```bash
nginx -t && systemctl reload nginx
```

Test im Container: `curl -I http://127.0.0.1/` muss `200` liefern.

## 6. Erste Synchronisation

```bash
sudo -u www-data php bin/sync.php
```

Die Ausgabe zeigt, wie viele Lehrgänge gefunden wurden. Alle sind zunächst **nicht freigegeben**.

Am Ende versucht der Lauf, das Heft-PDF zu erzeugen. Steht dort „Heft-PDF aktuell.“, funktioniert auch der PDF-Export.
Steht dort ein PDF-Hinweis, siehe „Fehlersuche“ unten.

> Wer das Heft sofort komplett füllen will: `sudo -u www-data php bin/sync.php --alle-freigeben`
> gibt bei diesem einen Lauf alle gefundenen Lehrgänge frei. Spätere neue Lehrgänge warten trotzdem auf Freigabe.

## 7. Täglicher Abruf

```bash
cp docs/cron-lehrgangsheft /etc/cron.d/lehrgangsheft
chmod 644 /etc/cron.d/lehrgangsheft
```

Der Abruf läuft dann jeden Morgen um 05:17 Uhr. Das Protokoll steht in `storage/sync.log`
und zusammengefasst im Admin-Bereich.

## 8. Von außen erreichbar machen (Nginx Proxy Manager)

Im Nginx Proxy Manager (CT 102) einen neuen Proxy Host anlegen:

- Domain: die Adresse aus `base_url`
- Scheme `http`, Forward Hostname = IP des neuen Containers, Port `80`
- SSL: Let's-Encrypt-Zertifikat anfordern, „Force SSL“ an
- Beim DNS-Anbieter einen A-Eintrag für die Subdomain auf deine öffentliche IP setzen.

Unter **Advanced** eintragen, damit der PDF-Export nicht abbricht:

```
proxy_read_timeout 120s;
```

## 9. Fertig – so wird es benutzt

| Adresse | Wofür |
|---|---|
| `/` | Das Heft zum Blättern, mit „Jetzt anmelden“ je Lehrgang |
| `/heft.pdf` | Das ganze Heft als PDF (Inhaltsverzeichnis mit Sprunglinks) |
| `/kurs/<ID>.pdf` | Ein einzelner Lehrgang als PDF (Link steht auch im Heft beim Lehrgang) |
| `/admin` | Verwaltung: Freigaben, Bereiche, „Jetzt aktualisieren“ |

Im Admin-Bereich:

1. Gelb hinterlegte Zeilen mit „neu“ sind seit dem letzten Speichern hinzugekommen.
2. Haken bei „Im Heft“ setzen oder entfernen, bei Bedarf den Bereich ändern.
3. **Auswahl speichern** – das Heft und die PDFs zeigen die Änderung sofort.
4. **Jetzt aktualisieren** holt Liste und Detailseiten sofort neu, z. B. wenn der LV einen Schreibfehler
   in einer Ausschreibung korrigiert hat. Das dauert ein bis zwei Minuten.

Lehrgänge, die von baden.dlrg.de verschwinden, werden automatisch aus dem Heft genommen.
Kurse mit erreichtem Meldeschluss stehen im Anhang statt mit eigener Doppelseite.

## Admins

Admins melden sich mit **E-Mail-Adresse und Passwort** an. Unter **Admin-Bereich → Admins**:

- **Einladen:** Name und E-Mail eintragen. Die Person bekommt einen Link (48 Stunden gültig) und legt ihr Passwort selbst fest.
- **Passwort vergessen?** auf der Anmeldeseite schickt einen Link, der 1 Stunde gilt und nur einmal funktioniert.
- **Entfernen:** zweimal auf „Entfernen“ klicken. Sich selbst kann man nicht entfernen.

Der Eintrag unter `admins` in `config.php` bleibt ein **Notfallzugang** (Benutzername statt E-Mail), falls der Mailversand ausfällt.

Damit Mails rausgehen, in `config.php` den Abschnitt `mail` ausfüllen:

| Anbieter | host | port | verschluesselung | passwort |
|---|---|---|---|---|
| IONOS | smtp.ionos.de | 587 | tls | Passwort des Postfachs |
| Google Workspace | smtp.gmail.com | 587 | tls | App-Passwort des Kontos |

Testmail an dich selbst:

```bash
php -r 'require "src/bootstrap.php"; Mailer::senden("deine@adresse.de", "Test Lehrgangsheft", "Mailversand funktioniert."); echo "ok\n";'
```

---

## Fehlersuche

**Heft-PDF wird nicht erzeugt**

```bash
sudo -u www-data php -r 'require "src/bootstrap.php"; echo Pdf::datei(), "\n";'
cat storage/chromium/chromium.log
```

Typische Ursachen: zu wenig RAM (Container auf 1024 MB stellen), Chromium nicht installiert,
oder `curl -I http://127.0.0.1/druck/heft` im Container liefert nicht `200` (nginx-Konfiguration prüfen).

**Synchronisation meldet „Lehrgangstabelle nicht gefunden“**

Der LV hat den Aufbau seiner Webseite geändert. Das Heft bleibt dann unverändert auf dem letzten Stand.
Die Stelle zum Anpassen ist `src/Quelle.php`.

**Tests ohne Internet**

```bash
php tests/run.php
```

## Updates einspielen

Neue Version entpacken und `config.php` sowie den Ordner `storage/` behalten:

```bash
cd /var/www
cp lehrgangsheft/config.php /root/config.php.bak
unzip -o /root/lehrgangsheft-neu.zip
chown -R www-data:www-data lehrgangsheft/storage
```

## Hinweis zur Schrift

Für Überschriften ist die DLRG-Hausschrift „DLRG Univers Bold“ als Webschrift eingebunden
(`public/assets/univers-bold.woff2`). Als Webschrift lässt sie sich von der öffentlichen Seite
herunterladen. Bitte mit den Nutzungsbedingungen der DLRG für die Hausschrift abgleichen. Wenn das nicht gewünscht
ist: Datei löschen; die Überschriften erscheinen dann in Arial.
