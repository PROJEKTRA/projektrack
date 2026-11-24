# Brzi početak: administrator

Postavite firmu u jednom navratu. Instalacija i ažuriranje: `docs/admin/plain-hosting.md`. Vaša uloga zahtijeva dvofaktorsku autentifikaciju: vidi brzi početak za zaposlenika. Neki ekrani prvo traže lozinku ili kod iz aplikacije za autentifikaciju.

## Korisnici i uloge

1. Otvorite **Korisnici** i izaberite **Pozovi**.
2. Upišite **Ime** i **E-mail**, izaberite jednu ili više **Uloga**, zatim **Pošalji pozivnicu**.
3. Osoba dobija e-mail s linkom za postavljanje lozinke. Link važi 60 minuta. Kasnije: **Zaboravili ste lozinku?** na stranici prijave.
4. Provjerite kolonu **Drugi faktor**. Obavezan je za uloge **Administrator** i **Kontroler**.
5. Kasnije koristite **Promijeni uloge**. Vlastite uloge ne možete mijenjati, a posljednji administrator zadržava ulogu.

| Uloga | Za |
|---|---|
| Administrator | Sve u firmi |
| Kontroler | Klijenti, ugovori, cijene, zaključenje mjeseca, fakture |
| Voditelj projekata | Kreiranje projekata, klijenti (pregled) i njihovi kontakti |
| Zaposlenik | Knjiženje vlastitog vremena |
| Vanjski saradnik | Knjiženje vlastitog vremena |

Odobravanje i servisni izvještaji dolaze s ulogom u projektu, ne s ulogom ovdje.

## Postavke

1. Otvorite **Postavke** u glavnom meniju. Svaka grupa ima svoje dugme **Sačuvaj**.
2. Pod **Opće** postavite **Jezik organizacije**, **Regionalni format** i **Vremensku zonu**.
3. Pod **Praćenje vremena** postavite **Opis obavezan od (minuta)** (0 isključuje pravilo) i **Upozori od (minuta dnevno)**.

## Postavke faktura

1. Pod **Fakture** upišite **Pravni naziv na fakturama**, **Ulicu i broj**, **Poštanski broj** i **Grad**.
2. Upišite **Porezni broj** ili **PDV broj** i izaberite **PDV status**.
3. Upišite **Banku**, **IBAN** i **BIC**.
4. Za e-fakture popunite **Kontakt osoba (e-fakture)**, **Telefon (e-fakture)** i **E-mail (e-fakture)**.
5. Neka računovođa provjeri zakonske napomene. Tek tada uključite **Zakonske napomene potvrdio računovođa**. Bez toga se ne može izdati faktura s napomenom, na primjer prijenos porezne obaveze.
6. Izaberite **Sačuvaj**. Brojevi faktura se dodjeljuju bez praznina po godini; nema ništa za podesiti.

## Jezici i brend

1. Otvorite **Jezici**. Pod radnjama izaberite **Aktiviraj** ili **Deaktiviraj**.
2. Za vlastite izraze izaberite **Uredi tekstove**. Zadržite polja u vitičastim zagradama.
3. Treba vam drugi jezik? Izaberite **Kopiraj jezik**, zatim **Izvezi**, prevedite i **Uvezi**.
4. Otvorite **Brend**. Postavite **Naziv proizvoda**, **Ime pošiljaoca e-maila**, **Logo** i **Primarnu boju**, zatim **Sačuvaj**.

## Licenca

1. Otvorite **Licenca**. Bez licence instalacija radi kao **Community**, bez ograničenja broja korisnika ili vremena.
2. Za licencu pošaljite **ID instance** davaocu licence.
3. Izaberite **Datoteku licence** ili zalijepite **Tekst licence**, zatim **Instaliraj licencu**. Provjerava se bez veze s internetom.

## Provjera sistema

1. Otvorite **Provjera sistema** i izaberite **Provjeri sada**.
2. Nakon minute ponovo učitajte stranicu. Prvo riješite **Kritično**, zatim **Upozorenje**. Uz svaki problem stoji šta uraditi.

**Licenca** i **Provjera sistema** vide se samo administratorima firme koja upravlja instalacijom.
