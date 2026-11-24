# Brzi početak: kontroler

Od klijenta do plaćene fakture. Vaša uloga zahtijeva dvofaktorsku autentifikaciju: vidi brzi početak za zaposlenika.

## Klijent i ugovor

1. Otvorite **Klijenti**, izaberite **Novi klijent** i upišite **Naziv** i **Adresu za fakturisanje**.
2. Pod **Porez** izaberite **Porezni status**. Upišite **PDV broj** ili **Porezni broj**.
3. Pod **Fakturisanje** postavite **Jezik dokumenata**, **Valutu**, **Rok plaćanja (dana)** i **Format fakture**.
4. Dodajte kontakt s e-mailom i uključite **Prima fakture**. Izaberite **Sačuvaj**.
5. Otvorite projekat pod **Projekti** i izaberite **Novi ugovor**.
6. Upišite **Broj**, **Vrstu ugovora**, **Budžet (sati)** i **Zaokruži na (minuta)**. Izaberite **Sačuvaj**.

## Cijene

1. Otvorite **Cijene sata** i izaberite **Nova cijena**.
2. Izaberite **Važi za**, zatim **Cijenu po satu**, **Valutu** i **Od**. Izaberite **Sačuvaj**.
3. Važi najuža cijena. Odredite cijene prije odobravanja vremena.
4. Cijenu zatvarate s **Završi**. Odobreno vrijeme koje ima vrijednost je zadržava.

Cijena u drugoj valuti nego projekat? Pod **Kursna lista** izaberite **Novi kurs**. Kurs smije biti najviše 31 dan stariji od radnog dana. BAM i EUR su fiksni.

## Zaključenje mjeseca i korekcije

1. Otvorite **Zaključenje mjeseca** i idite na mjesec. Provjerite koliko unosa nije odobreno.
2. Odobreno vrijeme nije tačno? Izaberite **Ispravi**, upišite **Tačno vrijeme** i **Razlog**, zatim **Sačuvaj**. Vlastito vrijeme ne možete ispraviti.
3. Izaberite **Zaključaj mjesec**. Više ne prima knjiženja, izmjene ni korekcije.
4. Nešto treba izmijeniti? Izaberite **Otključaj mjesec** i navedite **Razlog**.

## Faktura iz vremena

1. Otvorite **Fakture** i izaberite **Faktura iz vremena**.
2. Izaberite **Projekat**, **Usluga od** i **Usluga do**, zatim **Napravi nacrt**.
3. Provjerite stavke. S **Uredi** dodajete vlastite.
4. Riješite sve pod **Prije izdavanja fakture:**.
5. Izaberite **Izdaj fakturu**. Potvrdite lozinkom ili kodom iz aplikacije; vraćate se na nacrt.
6. Ponovo izaberite **Izdaj fakturu** i potvrdite. Faktura dobija broj i više se ne mijenja.

Bez vremena s projekta? Izaberite **Slobodna faktura**. Nepotreban nacrt: **Odbaci nacrt**.

## Slanje, e-faktura, uplata

1. Preuzmite **PDF**. Kod formata **ZUGFeRD** XML je u njemu. **XRechnung** dodaje dugme **XML**.
2. Izaberite **Pošalji e-mailom**, provjerite kontakte i potvrdite. **Poslano e-mailom** pokazuje stanje. Kod XRechnung se šalje i XML.
3. Izaberite **Evidentiraj uplatu** i upišite **Iznos** i **Datum**. Moguće su djelimične uplate.
4. **Otvorene stavke** pokazuju šta nije plaćeno i dane kašnjenja.

XRechnung zahtijeva **Leitweg-ID** i kontakt klijenta s e-mailom te vaše kontakt podatke i **IBAN** u postavkama. E-fakture još nisu provjerene zvaničnim validatorima: provjerite ih prije upotrebe.

## Storno

1. Otvorite izdatu fakturu i izaberite **Storniraj fakturu**. Potvrdite lozinkom.
2. Izdaje se storno s vlastitim brojem. Vrijeme se može ponovo fakturisati.
3. Evidentirana je uplata? Prvo je poništite negativnom uplatom. **Evidentiraj uplatu** se vidi samo dok postoji otvoren iznos.
