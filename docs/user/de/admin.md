# Schnellstart: Administration

Richten Sie Ihre Firma in einem Zug ein. Installation und Aktualisierung: `docs/admin/plain-hosting.md`. Ihre Rolle verlangt die Zwei-Faktor-Anmeldung: siehe Schnellstart für Mitarbeiter. Manche Seiten fragen zuerst nach Passwort oder Authenticator-Code.

Die Oberfläche gibt es auf Bosnisch und Englisch. Die deutschen Bezeichnungen hier sind Übersetzungen.

## Benutzer und Rollen

1. Öffnen Sie **Benutzer** und wählen Sie **Einladen**.
2. Geben Sie **Name** und **E-Mail** ein, wählen Sie eine oder mehrere **Rollen**, dann **Einladung senden**.
3. Die Person erhält eine E-Mail mit einem Link für das Passwort. Er gilt 60 Minuten. Später: **Passwort vergessen?** auf der Anmeldeseite.
4. Prüfen Sie die Spalte **Zwei-Faktor**. Pflicht ist sie für **Administrator** und **Controller**.
5. Ändern Sie Rollen später mit **Rollen ändern**. Die eigenen können Sie nicht ändern, und der letzte Administrator behält die Rolle.

| Rolle | Für |
|---|---|
| Administrator | Alles in der Firma |
| Controller | Kunden, Verträge, Sätze, Monatsabschluss, Rechnungen |
| Projektleiter | Projekte anlegen, Kunden (Ansicht) und deren Kontakte |
| Mitarbeiter | Eigene Zeit buchen |
| Extern | Eigene Zeit buchen |

Genehmigung und Leistungsnachweise kommen mit der Projektrolle, nicht mit der Rolle hier.

## Einstellungen

1. Öffnen Sie **Einstellungen** im Hauptmenü. Jede Gruppe hat ihr eigenes **Speichern**.
2. Legen Sie unter **Allgemein** **Sprache der Organisation**, **Regionales Format** und **Zeitzone** fest.
3. Legen Sie unter **Zeiterfassung** **Beschreibung nötig ab (Minuten)** (0 schaltet aus) und **Warnen ab (Minuten pro Tag)** fest.

## Rechnungseinstellungen

1. Erfassen Sie unter **Rechnungen** **Rechtlicher Name auf Rechnungen**, **Straße und Nummer**, **Postleitzahl** und **Ort**.
2. Geben Sie **Steuernummer** oder **USt-IdNr.** ein und wählen Sie den **USt-Status**.
3. Geben Sie **Bank**, **IBAN** und **BIC** ein.
4. Füllen Sie für E-Rechnungen **Ansprechpartner (E-Rechnungen)**, **Telefon (E-Rechnungen)** und **E-Mail (E-Rechnungen)** aus.
5. Lassen Sie die rechtlichen Hinweise steuerlich prüfen. Schalten Sie erst dann **Rechtliche Hinweise vom Steuerberater bestätigt** ein. Ohne das lässt sich keine Rechnung mit Hinweis ausstellen, zum Beispiel bei Steuerschuldumkehr.
6. Wählen Sie **Speichern**. Rechnungsnummern laufen je Jahr lückenlos; einzurichten ist nichts.

## Sprachen und Marke

1. Öffnen Sie **Sprachen**. Wählen Sie bei den Aktionen **Aktivieren** oder **Deaktivieren**.
2. Wählen Sie **Texte bearbeiten** für eigene Formulierungen. Behalten Sie die Platzhalter in Klammern.
3. Weitere Sprache nötig? Wählen Sie **Sprache kopieren**, dann **Exportieren**, übersetzen und **Importieren**.
4. Öffnen Sie **Marke**. Legen Sie **Produktname**, **Absendername für E-Mails**, **Logo** und **Primärfarbe** fest, dann **Speichern**.

## Lizenz

1. Öffnen Sie **Lizenz**. Ohne Lizenz läuft die Installation als **Community**, ohne Grenze für Benutzer oder Zeit.
2. Senden Sie für eine Lizenz die **Instanz-ID** an den Lizenzgeber.
3. Wählen Sie die **Lizenzdatei** oder fügen Sie den **Lizenztext** ein, dann **Lizenz installieren**. Geprüft wird ohne Internetverbindung.

## Systemprüfung

1. Öffnen Sie **Systemprüfung** und wählen Sie **Jetzt prüfen**.
2. Laden Sie die Seite nach einer Minute neu. Lösen Sie erst **Kritisch**, dann **Warnung**. Jedes Problem nennt die Abhilfe.

**Lizenz** und **Systemprüfung** sehen nur Administratoren der Firma, die die Installation betreibt.
