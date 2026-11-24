# Schnellstart: Controlling

Vom Kunden zur bezahlten Rechnung. Ihre Rolle verlangt die Zwei-Faktor-Anmeldung: siehe Schnellstart für Mitarbeiter.

Die Oberfläche gibt es auf Bosnisch und Englisch. Die deutschen Bezeichnungen hier sind Übersetzungen.

## Kunde und Vertrag

1. Öffnen Sie **Kunden**, wählen Sie **Neuer Kunde** und erfassen Sie **Name** und **Rechnungsadresse**.
2. Wählen Sie unter **Steuer** den **Steuerstatus**. Geben Sie **USt-IdNr.** oder **Steuernummer** ein.
3. Legen Sie unter **Rechnungsstellung** **Dokumentsprache**, **Währung**, **Zahlungsziel (Tage)** und **Rechnungsformat** fest. Wählen Sie Bosnisch oder Englisch als Dokumentsprache.
4. Ergänzen Sie einen Kontakt mit E-Mail und schalten Sie **Erhält Rechnungen** ein. Wählen Sie **Speichern**.
5. Öffnen Sie das Projekt unter **Projekte** und wählen Sie **Neuer Vertrag**.
6. Erfassen Sie **Nummer**, **Vertragsart**, **Budget (Stunden)** und **Aufrunden auf (Minuten)**. Wählen Sie **Speichern**.

## Stundensätze

1. Öffnen Sie **Stundensätze** und wählen Sie **Neuer Satz**.
2. Wählen Sie **Gilt für**, dann **Satz pro Stunde**, **Währung** und **Ab**. Wählen Sie **Speichern**.
3. Der genaueste Satz gilt. Legen Sie Sätze fest, bevor Zeit genehmigt wird.
4. Mit **Beenden** schließen Sie einen Satz. Bereits bewertete Zeit behält ihren Wert.

Satz in anderer Währung als das Projekt? Wählen Sie unter **Wechselkurse** **Neuer Wechselkurs**. Ein Kurs darf höchstens 31 Tage älter als der Arbeitstag sein. BAM und EUR sind fest.

## Monatsabschluss und Korrekturen

1. Öffnen Sie **Monatsabschluss** und gehen Sie zum Monat. Prüfen Sie, wie viele Einträge nicht genehmigt sind.
2. Genehmigte Zeit falsch? Wählen Sie **Korrigieren**, geben Sie **Richtige Zeit** und **Grund** ein, dann **Speichern**. Eigene Zeit können Sie nicht korrigieren.
3. Wählen Sie **Monat sperren**. Er nimmt keine Buchungen, Änderungen oder Korrekturen mehr an.
4. Doch etwas zu ändern? Wählen Sie **Monat wieder öffnen** und nennen Sie einen **Grund**.

## Rechnung aus Zeit

1. Öffnen Sie **Rechnungen** und wählen Sie **Rechnung aus Zeit**.
2. Wählen Sie **Projekt**, **Leistung von** und **Leistung bis**, dann **Entwurf erstellen**.
3. Prüfen Sie die Positionen. Mit **Bearbeiten** fügen Sie eigene hinzu.
4. Erledigen Sie alles unter **Bevor die Rechnung ausgestellt werden kann:**.
5. Wählen Sie **Rechnung ausstellen**. Bestätigen Sie mit Passwort oder Authenticator-Code; Sie kommen zum Entwurf zurück.
6. Wählen Sie erneut **Rechnung ausstellen** und bestätigen Sie. Die Rechnung erhält ihre Nummer und ändert sich nie mehr.

Keine Projektzeit? Wählen Sie **Freie Rechnung**. Entwurf nicht nötig: **Entwurf verwerfen**.

## Versand, E-Rechnung, Zahlung

1. Laden Sie das **PDF** herunter. Bei **ZUGFeRD** steckt das XML darin. **XRechnung** ergänzt die Schaltfläche **XML**.
2. Wählen Sie **Per E-Mail senden**, prüfen Sie die Kontakte und bestätigen Sie. **Per E-Mail gesendet** zeigt den Stand. Bei XRechnung wird auch das XML angehängt.
3. Wählen Sie **Zahlung erfassen** und geben Sie **Betrag** und **Datum** ein. Teilzahlungen sind möglich.
4. **Offene Posten** zeigt, was unbezahlt ist, mit Tagen Verzug.

XRechnung braucht **Leitweg-ID** und E-Mail-Kontakt des Kunden sowie Ihre Kontaktdaten und **IBAN** in den Einstellungen. E-Rechnungen sind noch nicht mit den offiziellen Validatoren geprüft: Prüfen Sie sie vor der Verwendung.

## Stornieren

1. Öffnen Sie die ausgestellte Rechnung und wählen Sie **Rechnung stornieren**. Bestätigen Sie mit Ihrem Passwort.
2. Ein Storno mit eigener Nummer wird ausgestellt. Die Zeit ist wieder abrechenbar.
3. Zahlung erfasst? Gleichen Sie sie zuerst mit einer negativen Zahlung aus. **Zahlung erfassen** erscheint nur, solange ein Betrag offen ist.
