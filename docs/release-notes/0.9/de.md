# Versionshinweise 0.9.0 "Founding Edition" (Early Access)

Stand: 24.11.2026, nicht veröffentlicht. Geplant für den 01.12.2026.

0.9 wird in Stufen geliefert (Entscheidung C-06). Early Access schaltet nur ein, was bis zur Freigabe am 30.11.2026 alle Prüfungen (Definition of Done) bestanden hat. Die Liste unten zeigt, was gebaut ist. Die Fachmodule haben noch nicht alle Prüfungen bestanden.

## Was enthalten ist

| Bereich | Inhalt |
|---|---|
| Zugang | Anmeldung, Zwei-Faktor-Anmeldung je Rolle, Rollen und Projektrollen, Prüfprotokoll, API-Token |
| Sprachen | `bs`, `en`, `de`, eigene Formulierungen, eigene Sprachen, regionale Formate |
| Firma | Marke, Einstellungen, Benachrichtigungen, Einladungen, Übersicht, heller und dunkler Modus |
| Kunden und Projekte | Kontakte, Steuerprofil, Team, Verträge, Arbeitspakete, Budgetwarnungen |
| Zeit | Wochenerfassung, Timer, Genehmigung, Korrekturen, Monatsabschluss |
| Sätze | Verkaufs- und Kostensätze, Rollen, Wechselkurse, Deckungsbeitrag |
| Leistungsnachweise | Aus genehmigter Zeit, als PDF, DOCX, XLSX und CSV |
| Rechnungen | Aussteller in DE und BA, lückenlose Nummern, Storno, Rechnungskorrektur, Zahlungen, E-Mail, PDF |
| E-Rechnungen | ZUGFeRD und XRechnung (CII, UBL) |
| Lizenz | Signierte Lizenz, offline geprüft; ohne Lizenz läuft die Installation als Community |
| Betrieb | Einfaches Hosting aus dem ZIP, Docker Compose, verschlüsselte Sicherung und Wiederherstellung, Systemprüfung |

## Bekannte Einschränkungen

### Vor echten Rechnungen

- **Rechtliche Hinweise muss ein Steuerberater prüfen.** Der Wortlaut ist ein Vorschlag. Rechnungen mit Hinweis sind gesperrt, bis Sie in den Einstellungen die Prüfung bestätigen.
- Offene Fragen an den Steuerberater: Code für Exporte aus BiH, Kleinunternehmer mit EU-Kunden, Aufbewahrung, Ausstellungsort und Fiskalnummer in BiH, KM-Gegenwert auf steuerfreien EUR-Rechnungen.
- Die Fiskalisierung in der Föderation BiH ist nicht geklärt. Eine externe Fiskalreferenz lässt sich erfassen.
- **E-Rechnungen sind nicht mit KoSIT oder veraPDF geprüft.** Prüfen Sie jede mit den offiziellen Validatoren, bevor Sie sie senden.
- E-Rechnungen zu Rechnungskorrekturen nutzen den Dokumenttyp 381 noch nicht. Eine falsche Korrektur lässt sich nicht zurücknehmen.

### Noch nicht getestet

- **Die Läufe der Fachmodule auf MySQL, MariaDB und PostgreSQL stehen noch aus.** Ebenso die mit Docker und auf Apache. Nutzen Sie bis dahin nur Testdaten.
- Die Wiederherstellung einer Sicherung vom einfachen Hosting wird auf MySQL und MariaDB nicht automatisch getestet. PostgreSQL wird dafür nicht unterstützt.
- Ein Usability-Test fand noch nicht statt.

### Funktionen

- Einfaches Hosting: kein Web-Installer und keine automatische Aktualisierung.
- Das Ende eines vergessenen Timers lässt sich nicht korrigieren.
- Benutzer lassen sich nicht deaktivieren.
- Format und Startwert der Rechnungsnummern lassen sich nicht ändern.
- Das ausgestellte PDF wird noch nicht archiviert. Eine nächtliche Prüfung auf Lücken in den Nummern fehlt.
- Eine Rechnung entsteht aus Zeiten, nicht aus einem Leistungsnachweis.
- Wechselkurse dürfen höchstens 31 Tage alt sein. Diese Grenze ist nicht bestätigt.
- Leistungsnachweise haben nur eingebaute Vorlagen.

### Schlüssel

- Release-Schlüssel und Lizenzschlüssel sind noch nicht enthalten. Bis dahin lassen sich Releases nicht prüfen und Lizenzen nicht installieren.

## Auf spätere Releases verschoben

Die Tabelle der Kürzungen nennt diese Ziele. Wo sie keines nennt, steht das Release nicht fest.

| Verschoben | Ziel |
|---|---|
| Web-Installer, Docker-Skript mit einem Befehl | 0.9.x |
| Aktualisierung per Klick im Browser | 0.10 |
| Hochladen und Zuordnen von Berichtsvorlagen | 0.10 |
| Rechnungen als Word-Dokument | 0.10 |
| Firmenassistent, Begrüßung, Touren, Rechtematrix, weitere Projektvorlagen | offen |
| Lizenzportal, Online-Aktivierung, täglicher Abgleich | offen |
| Passkeys | offen |
| PDF von rechts nach links, weitere Vorschausprachen | offen |
| Allgemeines EU-Länderpaket | offen |
| Rollenkontingente in Verträgen | offen |

Zwei Kürzungen wurden vorzeitig gebaut: Texte je Firma bearbeiten und XRechnung in UBL.

Geplant für das neu geplante 0.10: Befehlspalette und Suche, "Ansehen als", Kundenimport, Aktivitätsverlauf, wiederkehrende Buchungen, Zuschläge, Sammelberichte, Abschlags- und Schlussrechnungen, Verlängerungserinnerungen, Angebote, Abwesenheiten und Spesen.

Nicht in 0.9: Service Desk, Automatisierung, Gantt, Roadmaps, Wiki, optionale KI-Funktionen, Selbstregistrierung, eingehende E-Rechnungen.

## Geplante Stufen

| Release | Inhalt | Ziel |
|---|---|---|
| 0.9.0 | Early Access, was alle Prüfungen bestanden hat | 01.12.2026 |
| 0.9.1 | Grundlage vollständig | etwa 04.12. bis 11.12.2026 |
| 0.9.2 | Kunden, Projekte, Zeit, Sätze, Leistungsnachweise | etwa 22.12.2026 bis Mitte Januar 2027 |
| 0.9.3 | Rechnungen, Steuer, Lizenzen | Januar bis Februar 2027 |
| 0.9.4 | E-Rechnungen, Einführung, Usability-Test | Ende Januar bis Ende Februar 2027 |

Die Termine sind Ziele. Die Termine von 0.10 und 1.0 werden neu geplant. Offene Entscheidungen: E-Rechnungen als spätere Phase (P1) und manueller Verkaufsablauf (P2).

## Installation

`docs/admin/de/plain-hosting.md` oder `docs/admin/docker.md` (Englisch). Erste Schritte: `docs/user/de/`.
