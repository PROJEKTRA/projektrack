# Projektrack mit Docker Compose betreiben

Für Betreiber des Docker-Compose-Profils und verwalteter Instanzen (S-0923, Infrastrukturprofile, Abschnitte 4 und 7). Alle Dateien liegen in `deploy/docker/`.

## Was läuft

| Dienst | Image | Aufgabe |
|---|---|---|
| `proxy` | Caddy | Der einzige Dienst mit veröffentlichten Ports (80, 443). Automatisches TLS, Pflicht-Header, kein Header `Server`, kein Zugriffslog. |
| `app` | Projektrack | Nginx und PHP-FPM als unprivilegierter Benutzer, Root-Dateisystem nur lesbar. |
| `worker` | Projektrack | Abarbeitung der Job-Queue (E-Mails, Benachrichtigungen). |
| `scheduler` | Projektrack | Geplante Aufgaben (Systemprüfung, Prüfung des Prüfprotokolls). |
| `migrate` | Projektrack | Läuft bei jedem Start: Migrationen, Berechtigungskatalog, Datenbankrechte. Beendet sich danach. |
| `backup` | Projektrack | Verschlüsselte Sicherungen alle 6 Stunden, 14 Tage aufbewahrt. |
| `db` | MariaDB 11.4 | Datenvolume, von außen nicht erreichbar. |
| `redis` | Valkey 8 | Cache und Job-Queue, passwortgeschützt, nur intern. |

Zwei Netzwerke: `frontend` (proxy, app, worker, scheduler; der Worker braucht es für den E-Mail-Versand) und das interne `backend` ohne Internetzugang (Datenbank, Cache, Migrationen, Sicherungen). Der Proxy erreicht die Datenbank nicht. Er hat eine feste Adresse, der die Anwendung als ihrem einzigen Proxy vertraut. Andere Adressen vergibt Docker nur aus der oberen Hälfte des Netzwerks, daher kann kein Container sie übernehmen.

Zwei Datenbankkonten: `projektrack_migrator` besitzt das Schema. Die Anwendung läuft als `projektrack`: Dieses Konto liest und schreibt Zeilen, kann das Schema nicht ändern und das Prüfprotokoll nur ergänzen. Das Root-Passwort der Datenbank ist zufällig und wird nie gebraucht.

Noch nicht enthalten: Meilisearch, Gotenberg, MinIO, ClamAV und Reverb. Die Anwendung nutzt dafür ihre eingebauten Alternativen (Infrastrukturprofile, Abschnitt 2).

## Voraussetzungen

Ubuntu 24.04 LTS, Docker Engine 27 oder neuer mit Compose v2, 2 vCPU, 4 GB RAM, 40 GB Festplatte, erreichbare Ports 80 und 443 und ein DNS-Name, der auf den Host zeigt. Härten Sie den Host nach Abschnitt 7 der Infrastrukturprofile (nur SSH-Schlüssel, Firewall, automatische Updates).

## Erste Installation

1. Holen Sie das Image. Prüfen Sie das erste signierte Release (`docs/admin/releases.md`) und verwenden Sie das Image aus dessen `image.txt`. Solange es das nicht gibt, bauen Sie es im Wurzelverzeichnis des Repositorys: `docker build -f deploy/docker/Dockerfile -t projektrack:local .`
2. Schreiben Sie in `deploy/docker/` einmalig die geheimen Werte:

   ```
   ./setup.sh --domain app.example.com --email ops@example.com --image projektrack:local
   ```

   Das schreibt `.env` und `backup-identity.txt` (beide nur für Sie lesbar) und den Ordner `backups/`. Jeder geheime Wert wird erzeugt; das Skript weigert sich, eine vorhandene `.env` zu überschreiben. Ist das Netzwerk `172.31.240.0/24` auf dem Host belegt, ergänzen Sie `--network 10.99.7` (die ersten drei Teile eines freien privaten /24-Netzwerks). Bauen Sie aus einem sauberen Checkout: Alles im Ordner, was nicht ausgeschlossen ist, landet im Image.
3. Tragen Sie den Mailserver in die `MAIL_`-Zeilen der `.env` ein. Vorgabe ist implizites TLS auf Port 465 (`MAIL_SCHEME=smtps`). Behalten Sie es bei, denn STARTTLS auf Port 587 lässt sich unterwegs entfernen. `projektrack:tenant:create` verweigert die Ausführung, solange kein Mailserver eingetragen ist.
4. Verschieben Sie `backup-identity.txt` vom Host in den Passwort-Tresor, zusammen mit einer Kopie von `.env`. Ohne beide lässt sich keine Sicherung wiederherstellen.
5. Starten Sie die Dienste: `docker compose up -d`. Caddy holt das Zertifikat bei der ersten Anfrage.
6. Legen Sie den Mandanten und seinen ersten Administrator an:

   ```
   docker compose exec app php artisan projektrack:tenant:create --slug=acme --name="Acme d.o.o." \
     --domain=app.example.com --admin-email=admin@example.com --admin-name="Ana Admin" \
     --locale=bs --country=BA --currency=BAM --timezone=Europe/Sarajevo
   ```

   Der Administrator erhält per E-Mail einen Einrichtungslink, der 60 Minuten gilt, und setzt Passwort und zweiten Faktor selbst. Der Link wird dem Betreiber nie angezeigt. Läuft er ab, nutzt der Administrator "Passwort vergessen?" auf der Anmeldeseite.
7. Prüfen Sie von außen: `docker compose exec app php artisan projektrack:doctor`.

Das Zertifikat wird in Certificate-Transparency-Logs veröffentlicht; wählen Sie einen unauffälligen Hostnamen (Infrastrukturprofile, Abschnitt 7).

Veröffentlichen Sie nur einen A-Eintrag, keinen AAAA-Eintrag. Das Netzwerk der Dienste ist IPv4. Docker würde IPv6-Besucher mit einer gemeinsamen internen Adresse weiterreichen, und die Anmeldelimits würden alle als einen Besucher zählen.

## Zustandsprüfung

`GET /health` antwortet nur den Adressen in `HEALTH_ALLOWED_IPS` mit `OK` (Vorgabe: der Container selbst). Ergänzen Sie die Adresse Ihres Überwachungshosts, durch Komma getrennt, und führen Sie `docker compose up -d` erneut aus. Der Container `app` meldet seinen Zustand selbst an Docker.

## Sicherungen

Der Dienst `backup` schreibt alle 6 Stunden einen Ordner `backups/<UTC time>/` (UTC-Zeit; `BACKUP_INTERVAL_HOURS`) und entfernt Ordner, die älter als 14 Tage sind (`BACKUP_KEEP_DAYS`). Jeder Ordner enthält:

- `database.sql.gz.age`: die Datenbank,
- `files.tar.gz.age`: hochgeladene Dateien und Schlüssel aus dem Speicher-Volume (keine Caches oder Logs),
- `SHA256SUMS`: Prüfsummen von beiden,
- `SHA256SUMS.hmac`: eine Signatur der Prüfsummen mit `BACKUP_HMAC_KEY` aus `.env`.

Beide Dateien werden schon beim Schreiben für den öffentlichen Schlüssel in `.env` verschlüsselt; nichts Unverschlüsseltes gelangt auf die Festplatte. Nur der private Schlüssel im Tresor kann sie entschlüsseln. Der Ordner bleibt daher vertraulich, auch wenn Sie ihn an einen anderen Standort kopieren (zum Beispiel mit `rsync` oder `rclone` vom Host aus). Der öffentliche Schlüssel ist nicht geheim, also könnte jeder eine eigene Sicherung verschlüsseln. Erst die Signatur beweist, dass eine Sicherung von dieser Instanz stammt. Eine Wiederherstellung lehnt jede Sicherung ohne gültige Signatur ab.

Heute bleiben Sicherungen auf dem Host, bis Sie sie selbst an einen anderen Standort kopieren. Richten Sie das für eine verwaltete Instanz vom ersten Tag an ein (zweiter EU-Standort, Infrastrukturprofile, Abschnitt 7). Der Container `backup` wechselt in den Zustand unhealthy, wenn der letzte Erfolg älter ist als das Intervall plus eine Stunde; überwachen Sie diesen Zustand.

Sicherung jetzt: `docker compose exec backup projektrack-backup run`.

## Wiederherstellung üben

Üben Sie monatlich auf einem temporären Host, und stellen Sie nach einem Verlust genauso wieder her. CI führt diese Übung bei jeder Änderung aus (`tests/docker/stack.sh`).

1. Legen Sie auf dem neuen Host die Kopien von `.env` und `backup-identity.txt` aus dem Tresor in `deploy/docker/` ab (`chmod 600` für beide) und die Sicherungsordner in `backups/`. Derselbe `APP_KEY` ist nötig, sonst sind verschlüsselte Werte nicht lesbar.
2. Starten Sie nur Datenbank und Cache: `docker compose up -d db redis`
3. Stellen Sie eine Sicherung in die leere Instanz wieder her:

   ```
   docker compose --profile restore run --rm restore restore 20261123T020000Z
   ```

   Das Skript prüft Signatur und Prüfsummen und entschlüsselt beide Dateien einmal zur Probe, bevor es etwas schreibt. Es weigert sich, in eine Datenbank oder ein Speicher-Volume mit Daten zu schreiben. `BACKUP_HMAC_KEY` in `.env` muss der Schlüssel der Instanz sein, von der die Sicherung stammt.
4. Starten Sie alles: `docker compose up -d`. Der Dienst `migrate` bringt das Schema auf die Version des Images und setzt die Datenbankrechte.
5. Entfernen Sie `backup-identity.txt` wieder vom Host.

Ziele für verwaltete Instanzen: RPO 6 Stunden, RTO 4 Stunden (Geschäftszeiten).

## Logs

Container loggen nach Docker (`docker compose logs`), rotiert bei 10 x 10 MB pro Container. Kein Log enthält Anfragepfade, denn Pfade können Einmal-Token enthalten (Passwort zurücksetzen, Freigabelinks): Zugriffslogs lassen sie weg, Nginx loggt nur kritische Fehler, und die Einträge von Caddy zu Anfragefehlern sind abgeschaltet. Für 90 Tage Aufbewahrung auf verwalteten Instanzen leiten Sie die Docker-Logs in das Journal des Hosts.

## Aktualisierungen

Laden Sie `SHA256SUMS`, `SHA256SUMS.sig` und `projektrack-X.Y.Z.image.txt` des Releases in einen leeren Ordner auf dem Host, dann in `deploy/docker/`:

```
./projektrack update /path/to/folder
```

Der Befehl:

1. prüft die Signatur mit den Release-Schlüsseln der laufenden Version (`docs/admin/releases.md`), nimmt den Image-Digest aus der geprüften Datei und lehnt eine Version ab, die nicht neuer als die laufende ist;
2. prüft Zustand und Speicherplatz, erstellt eine verschlüsselte Sicherung, schaltet den Wartungsmodus ein, beendet laufende Anfragen und Jobs und stoppt die Hintergrunddienste;
3. erstellt einen lokalen Snapshot von Datenbank und Dateien und führt dann die Migrationen der neuen Version aus (Besucher sehen die Wartungsseite);
4. stoppt den Proxy, startet die neue Version, prüft ihren Zustand und öffnet sie wieder.

Scheitert ein Schritt, stellt der Befehl den Snapshot wieder her, startet die vorherige Version, prüft ihren Zustand und endet mit Exit-Code 1. Der Snapshot ist nötig, weil der private Schlüssel der Sicherungen nicht auf dem Host liegt; er liegt nur während einer Aktualisierung in einem eigenen Docker-Volume.

Ist die neue Version in Ordnung, aber ein Dienst startet danach nicht, gibt es kein Rollback mehr, weil Benutzer bereits Daten geschrieben haben können; der Befehl sagt das.

### Wenn eine Aktualisierung unterbrochen wurde

Exit-Code 2 bedeutet, dass auch das Rollback gescheitert ist: Der Proxy bleibt gestoppt, und der Snapshot bleibt erhalten. Ein Stromausfall hinterlässt die Datei `.update-state` mit dem vorherigen Image. Der Befehl lehnt weitere Aktualisierungen ab, bis das behoben ist:

1. Stoppen Sie alles, was schreibt: `docker compose stop proxy app worker scheduler backup`, und entfernen Sie Reste mit `docker rm -f projektrack-update-snapshot projektrack-update-migrate`.
2. Enthält `.update-state` den Eintrag `committed=1`, war die neue Version in Ordnung und enthält möglicherweise schon neue Daten: Behalten Sie sie und springen Sie zu Schritt 5.
3. Setzen Sie `PROJEKTRACK_IMAGE` in `.env` auf `previous` aus `.update-state`.
4. Stellen Sie wieder her: `docker compose --profile update run --rm snapshot projektrack-snapshot restore`. Meldet der Befehl, dass es keinen vollständigen Snapshot gibt, wurde die Datenbank noch nicht angetastet (ohne `committed=1`). Meldet er, dass der Snapshot beschädigt ist, stellen Sie die verschlüsselte Sicherung auf einem neuen Host wieder her.
5. Starten Sie nur die Anwendung, der Proxy bleibt noch gestoppt: `docker compose up -d --no-deps app`, `docker compose exec app php artisan up`, und prüfen Sie, dass `docker compose exec app wget -q -O - http://127.0.0.1:8080/health` mit `OK` antwortet (nach dem Start dauert es einige Sekunden).
6. Erst wenn alles in Ordnung ist: `docker compose --profile update run --rm snapshot projektrack-snapshot drop`, löschen Sie `.update-state` und öffnen Sie die Instanz mit `docker compose up -d`.

Der Snapshot enthält unverschlüsselte Daten, wie das Datenbank-Volume. Schließen Sie das Volume `projektrack_snapshot` von Host-Sicherungen aus.

Vorabversionen wie `0.9.0-rc1` gelten als älter als `0.9.0` und lassen sich wie jedes signierte Release installieren.
Der Befehl aktualisiert die Dateien in `deploy/docker/` nicht selbst; nehmen Sie diese aus dem Release-ZIP, wenn dessen Hinweise es verlangen, nachdem Sie es geprüft haben (`docs/admin/releases.md`).

## Befehle

| Befehl | Wirkung |
|---|---|
| `./projektrack install --domain ... --email ... --image ...` | Schreibt die geheimen Werte (`setup.sh`) |
| `./projektrack backup` | Eine verschlüsselte Sicherung jetzt |
| `./projektrack restore <backup>` | Stellt eine Sicherung in eine leere Instanz wieder her und startet sie |
| `./projektrack update <folder>` | Aktualisierung mit automatischem Rollback |
