# Napomene o izdanju 0.9.0 "Founding Edition" (Early Access)

Stanje: 24.11.2026., nije izdato. Planirano za 01.12.2026.

0.9 se isporučuje u fazama (odluka C-06). Early Access uključuje samo ono što je do odluke 30.11.2026. prošlo sve provjere (Definition of Done). Lista ispod pokazuje šta je izrađeno. Poslovni moduli još nisu prošli sve provjere.

## Šta je uključeno

| Oblast | Sadržaj |
|---|---|
| Pristup | Prijava, dvofaktorska autentifikacija po ulozi, uloge i uloge u projektu, dnevnik revizije, API tokeni |
| Jezici | `bs`, `en`, `de`, vlastiti izrazi, vlastiti jezici, regionalni formati |
| Firma | Brend, postavke, obavještenja, pozivnice, početna ploča, svijetli i tamni prikaz |
| Klijenti i projekti | Kontakti, porezni profil, tim, ugovori, radni paketi, upozorenja o budžetu |
| Vrijeme | Sedmični unos, tajmer, odobravanje, korekcije, zaključenje mjeseca |
| Cijene | Prodajne cijene i troškovi sata, radne uloge, kursna lista, marža |
| Servisni izvještaji | Iz odobrenog vremena, kao PDF, DOCX, XLSX i CSV |
| Fakture | Izdavaoci u DE i BA, brojevi bez praznina, storno, djelimični storno, uplate, e-mail, PDF |
| E-fakture | ZUGFeRD i XRechnung (CII, UBL) |
| Licenca | Potpisana licenca, provjera bez interneta; bez licence instalacija radi kao Community |
| Rad sistema | Obični hosting iz ZIP-a, Docker Compose, šifrovani backup i vraćanje, provjera sistema |

## Poznata ograničenja

### Prije izdavanja stvarnih faktura

- **Zakonske napomene mora provjeriti računovođa.** Tekst je prijedlog. Fakture s napomenom su blokirane dok u postavkama ne potvrdite da ih je računovođa provjerio.
- Otvorena pitanja za računovođu: oznaka za izvoz iz BiH, mali poduzetnik s klijentima u EU, čuvanje, mjesto izdavanja i fiskalni broj u BiH, protuvrijednost u KM na EUR fakturama bez PDV-a.
- Fiskalizacija u Federaciji BiH nije potvrđena. Može se upisati oznaka vanjskog fiskalnog sistema.
- **E-fakture nisu provjerene alatima KoSIT i veraPDF.** Provjerite svaku zvaničnim validatorima prije slanja.
- E-fakture za djelimični storno još ne koriste vrstu dokumenta 381. Pogrešan djelimični storno se ne može poništiti.

### Još nije testirano

- **Testovi poslovnih modula na MySQL, MariaDB i PostgreSQL još nisu urađeni.** Isto važi za Docker i Apache. Do tada koristite samo testne podatke.
- Vraćanje backupa s običnog hostinga na MySQL i MariaDB se ne testira automatski. Na PostgreSQL nije podržano.
- Test upotrebljivosti još nije održan.

### Funkcije

- Obični hosting: nema web instalacije ni automatskog ažuriranja.
- Kraj zaboravljenog tajmera se ne može ispraviti.
- Korisnici se ne mogu deaktivirati.
- Oblik i početni broj faktura se ne mogu mijenjati.
- Izdati PDF se još ne arhivira. Nema noćne provjere praznina u brojevima.
- Faktura nastaje iz vremena, ne iz servisnog izvještaja.
- Kurs smije biti star najviše 31 dan. Ta granica nije potvrđena.
- Servisni izvještaji imaju samo ugrađene predloške.

### Ključevi

- Ključ izdanja i ključ licenci još nisu u izdanju. Do tada se izdanja ne mogu provjeriti, a licence se ne mogu instalirati.

## Pomjereno u kasnija izdanja

Tabela rezova navodi ove ciljeve. Gdje ih nema, izdanje nije određeno.

| Pomjereno | Cilj |
|---|---|
| Web instalacija, Docker skripta u jednoj naredbi | 0.9.x |
| Ažuriranje jednim klikom u pregledniku | 0.10 |
| Učitavanje i dodjela predložaka izvještaja | 0.10 |
| Fakture kao Word dokument | 0.10 |
| Čarobnjak za firmu, dobrodošlica, vodiči, matrica prava, više predložaka projekata | nije određeno |
| Portal licenci, aktivacija putem interneta, dnevna prijava | nije određeno |
| Pristupni ključevi (passkeys) | nije određeno |
| PDF zdesna nalijevo, više jezika u pregledu | nije određeno |
| Opći paket za zemlje EU | nije određeno |
| Kvote po ulozi u ugovorima | nije određeno |

Dva reza su izrađena prije plana: uređivanje tekstova po firmi i XRechnung u UBL.

Planirano za novi plan izdanja 0.10: paleta naredbi i pretraga, "prikaži kao", uvoz klijenata, vremenska linija aktivnosti, ponavljajuća knjiženja, dodaci na cijenu, serijski izvještaji, avansne i završne fakture, podsjetnici za obnovu, ponude, odsustva i troškovi.

Nije u 0.9: servisni pult, automatizacija, Gantt, mape puta, wiki, opcionalne AI funkcije, samostalna registracija, ulazne e-fakture.

## Planirane faze

| Izdanje | Sadržaj | Cilj |
|---|---|---|
| 0.9.0 | Early Access, ono što je prošlo sve provjere | 01.12.2026. |
| 0.9.1 | Osnova kompletna | oko 04.12. do 11.12.2026. |
| 0.9.2 | Klijenti, projekti, vrijeme, cijene, servisni izvještaji | oko 22.12.2026. do sredine januara 2027. |
| 0.9.3 | Fakturisanje, porez, licence | januar do februar 2027. |
| 0.9.4 | E-fakture, uvođenje korisnika, test upotrebljivosti | kraj januara do kraja februara 2027. |

Datumi su ciljevi. Datumi za 0.10 i 1.0 se ponovo planiraju. Otvorene odluke: e-fakture kao kasnija faza (P1) i ručni tok prodaje (P2).

## Instalacija

`docs/admin/bs/plain-hosting.md` ili `docs/admin/docker.md` (na engleskom). Prvi koraci: `docs/user/bs/`.
