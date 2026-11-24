# Projektrack auf einfachem Hosting installieren

Für Betreiber von geteiltem oder einfachem AMP-Hosting (S-0925): Apache, PHP, MySQL oder MariaDB und ein Cron-Eintrag. Auf dem Server gibt es kein Docker, Redis, Composer oder Node. CI installiert dasselbe ZIP bei jeder Änderung auf diese Weise (`tests/amp/install.sh`).

## Voraussetzungen

- PHP 8.4 oder neuer, für Web und Kommandozeile, mit den Erweiterungen bcmath, ctype, curl, dom, fileinfo, gd, intl, mbstring, openssl, pdo, pdo_mysql, session, sodium, tokenizer, xml, xmlreader, xmlwriter und zip. Die Installation nennt eine fehlende.
- MySQL 8.4 oder MariaDB 11.4 mit leerer Datenbank und einem Konto, das darin Tabellen anlegen darf.
- Apache mit `mod_rewrite` und `AllowOverride`. Die Domain muss auf einen Unterordner (`public/`) zeigen.
- Zugang zur Kommandozeile (SSH) und ein Cron-Eintrag jede Minute. Einen Web-Installer gibt es noch nicht.
- Ein eigener Hostname (kein Unterordner einer Website) mit Zertifikat. Schalten Sie im Hosting-Panel "force HTTPS" ein und prüfen Sie, dass `http://` weiterleitet.
- Ein Konto auf einem Mailserver. Der erste Administrator erhält den Einrichtungslink per E-Mail.

## ZIP holen

Bis es das erste signierte Release gibt, steht ein **Test-ZIP** bereit (nur für Testinstallationen mit synthetischen Daten, nicht signiert): Öffnen Sie in GitHub den letzten erfolgreichen Lauf des Workflows `ci` auf dem Branch `main` (Ereignis push, nie ein Pull Request), Abschnitt Artifacts, `test-zip` (14 Tage aufbewahrt). GitHub verpackt es noch einmal; entpacken Sie zuerst die äußere Datei. Signierte Releases: `docs/admin/releases.md`.

## Installieren

Die Befehle laufen im Ordner mit der Datei `artisan`. Heißt PHP 8.4 beim Hosting anders (zum Beispiel `php8.4`), verwenden Sie überall diesen Namen. Eine Einschränkung nach IP oder Passwort gehört in das Hosting-Panel; `public/.htaccess` öffnet seinen Ordner und wird bei Aktualisierungen ersetzt.

1. Entpacken Sie das ZIP in einen Ordner außerhalb jedes anderen Web-Roots. Richten Sie die Domain auf dessen Ordner `public`, nie auf den Ordner selbst.
2. Erster Lauf. Er schreibt `.env`, nur für Sie lesbar, mit einem erzeugten Schlüssel:

   ```
   php artisan projektrack:install
   ```

3. Bearbeiten Sie `.env`: Ersetzen Sie jedes `CHANGE-ME`. Legen Sie eine Kopie von `.env` in den Passwort-Tresor: Ohne `APP_KEY` sind verschlüsselte Daten verloren.
4. Zweiter Lauf, mit Ihren eigenen Werten:

   ```
   php artisan projektrack:install --slug=acme --name="Acme d.o.o." --admin-email=admin@example.com --admin-name="First Last"
   ```

   Vorgaben: Bosnisch, BA, BAM, Europe/Sarajevo (`--locale`, `--country`, `--currency`, `--timezone`). Fehlt etwas, sagt der Befehl, was, und ändert nichts.
5. Cron, jede Minute. Er führt alle Hintergrundaufgaben aus, ohne ihn wird also keine E-Mail versendet:

   ```
   * * * * * php /path/to/projektrack/artisan schedule:run >/dev/null 2>&1
   ```

6. Öffnen Sie den Link in der E-Mail, setzen Sie das Passwort, melden Sie sich an und schalten Sie die Zwei-Faktor-Anmeldung ein (Pflicht für Administratoren).
7. Öffnen Sie "Systemprüfung" und starten Sie sie.

Prüfen Sie, dass `https://<host>/.env` und `https://<host>/composer.json` mit 403 oder 404 antworten.

## Testrechner ohne HTTPS und Mailserver

Nur für einen Rechner, der aus dem Internet nicht erreichbar ist. In `.env`:

```
APP_ENV=staging
APP_URL=http://test.localhost
MAIL_MAILER=log
LOG_LEVEL=debug
```

Verbleibende Werte `CHANGE-ME` des Mailservers sind dann unwichtig. Nachdem Cron einmal lief (`php artisan schedule:run`), steht der Einrichtungslink in der neuesten Datei von `storage/logs/`. Dieses Log enthält Anmeldelinks: Verwenden Sie diese Werte nie auf einem erreichbaren Server.

## Beispieldaten

Auf einer Testinstallation (`APP_ENV=staging`), für eine leere Firma:

```
php artisan projektrack:demo <slug>
```

Alles darin ist synthetisch; niemand kann sich als Beispielperson anmelden, und keine Rechnung wird ausgestellt.

## Was sich heute testen lässt

| Bereich | Stand |
|---|---|
| Passwort setzen, Anmeldung, Zwei-Faktor, Sitzungen, Profil | fertig |
| Sprachen und eigene Formulierungen (bs, en, de), von rechts nach links | fertig |
| Marke, Einstellungen, heller und dunkler Modus | fertig |
| Lizenzseite (läuft ohne Lizenz als Community) | fertig |
| Systemprüfung, Prüfprotokoll, Benachrichtigungen, API-Token | fertig |
| Benutzer und Rollen, Übersicht | fertig |
| Kunden, Projekte, Verträge, Arbeitspakete | fertig |
| Wochenerfassung, Genehmigung, Korrekturen, Monatsabschluss | fertig |
| Stundensätze, Leistungsnachweise (PDF, CSV) | fertig |
| Rechnungen für Aussteller in DE und BA, Storno, Zahlungen (PDF) | fertig; rechtliche Hinweise muss ein Steuerberater bestätigen |
| Timer, XLSX, DOCX, Rechnungen per E-Mail, ZUGFeRD und XRechnung (CII, UBL) | fertig; noch nicht mit den offiziellen Validatoren (KoSIT, veraPDF) geprüft |
| Angebote, Spesen, Abwesenheiten, Abschlagszahlungen | noch nicht gebaut |
| Verschlüsselte Sicherung und Wiederherstellung auf einfachem Hosting | fertig |
| Web-Installer, Nginx-Beispiel | noch nicht gebaut |

## Sicherung

1. `php artisan projektrack:backup:key`. Tragen Sie die Zeile `BACKUP_PUBLIC_KEY` in `.env` ein und legen Sie den geheimen Schlüssel in den Passwort-Tresor, nie auf den Server.
2. Ab dann schreibt Cron jede Nacht eine verschlüsselte Sicherung nach `storage/backups` (14 Tage aufbewahrt). `php artisan projektrack:backup` schreibt sofort eine.
3. Kopieren Sie die Sicherungen regelmäßig vom Server, zusammen mit einer Kopie von `.env`: Eine Sicherung enthält sie nicht, und ohne `APP_KEY` sind verschlüsselte Werte verloren.

Wiederherstellen, in eine leere Installation derselben Version (`.env` der alten, `php artisan migrate --force`, nicht `projektrack:install`): `php artisan projektrack:restore <file> --key-file=<file with the secret key>` (Sicherungsdatei und Datei mit dem geheimen Schlüssel), dann löschen Sie die Schlüsseldatei. Nicht auf PostgreSQL. Probieren Sie eine Wiederherstellung einmal aus, bevor Sie sich darauf verlassen.

## Neueres ZIP

Auf einfachem Hosting gibt es noch keine automatische Aktualisierung.

1. Prüfen Sie ein signiertes Release aus dem alten Ordner (`docs/admin/releases.md`), dann `php artisan projektrack:backup`, und kopieren Sie die Datei vom Server.
2. `php artisan down`
3. Entpacken Sie das neue ZIP in einen neuen Ordner. Kopieren Sie `.env` (`cp -p`, damit sie nur für Sie lesbar bleibt) und `storage/app` aus dem alten.
4. Im neuen Ordner: `php artisan projektrack:install`. Der Befehl migriert und behält die Daten. Für eine Firma, die vor den Modulen bestand: `php artisan projektrack:modules:enable <slug> service_reports` und dasselbe mit `invoices` (beide bringen mit, was sie brauchen).
5. Richten Sie Domain und Cron auf den neuen Ordner.

Scheitert Schritt 4: Stellen Sie die Datenbank wieder her, führen Sie `php artisan up` im alten Ordner aus und lassen Sie die Domain darauf.
