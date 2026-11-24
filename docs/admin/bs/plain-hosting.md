# Instalacija Projektracka na običnom hostingu

Za operatere dijeljenog ili običnog AMP hostinga (S-0925): Apache, PHP, MySQL ili MariaDB i jedan cron unos. Na serveru nema Dockera, Redisa, Composera ni Nodea. CI pri svakoj izmjeni instalira isti ZIP na ovaj način (`tests/amp/install.sh`).

## Zahtjevi

- PHP 8.4 ili noviji, za web i komandnu liniju, s ekstenzijama bcmath, ctype, curl, dom, fileinfo, gd, intl, mbstring, openssl, pdo, pdo_mysql, session, sodium, tokenizer, xml, xmlreader, xmlwriter i zip. Instalacija navodi koja nedostaje.
- MySQL 8.4 ili MariaDB 11.4 s praznom bazom i računom koji u njoj smije kreirati tabele.
- Apache s `mod_rewrite` i `AllowOverride`. Domena mora pokazivati na podfolder (`public/`).
- Pristup komandnoj liniji (SSH) i cron unos svake minute. Web instalacija još ne postoji.
- Vlastito ime hosta (ne podfolder neke stranice) s certifikatom. U panelu hostinga uključite "force HTTPS" i provjerite da `http://` preusmjerava.
- Račun na mail serveru. Prvi administrator dobija link za postavljanje e-mailom.

## Preuzmite ZIP

Dok ne postoji prvo potpisano izdanje, postoji **testni ZIP** (samo za testne instalacije sa sintetičkim podacima, nije potpisan): u GitHubu otvorite posljednje uspješno izvršavanje workflowa `ci` na grani `main` (događaj push, nikada pull request), dio Artifacts, `test-zip` (čuva se 14 dana). GitHub ga pakuje još jednom; prvo raspakujte vanjsku datoteku. Potpisana izdanja: `docs/admin/releases.md`.

## Instalirajte

Naredbe se izvršavaju u folderu s datotekom `artisan`. Ako hosting PHP 8.4 zove drugačije (na primjer `php8.4`), svuda koristite to ime. Ograničenje po IP adresi ili lozinci pripada u panel hostinga; `public/.htaccess` otvara svoj folder, a ažuriranja ga zamjenjuju.

1. Raspakujte ZIP u folder izvan svakog drugog web korijena i usmjerite domenu na njegov folder `public`, nikada na sam folder.
2. Prvo pokretanje. Upisuje `.env` s generisanim ključem, čitljiv samo vama:

   ```
   php artisan projektrack:install
   ```

3. Uredite `.env`: zamijenite svaki `CHANGE-ME`. Kopiju datoteke `.env` stavite u trezor lozinki: bez `APP_KEY` su šifrovani podaci izgubljeni.
4. Drugo pokretanje, s vašim vrijednostima:

   ```
   php artisan projektrack:install --slug=acme --name="Acme d.o.o." --admin-email=admin@example.com --admin-name="First Last"
   ```

   Zadano: bosanski, BA, BAM, Europe/Sarajevo (`--locale`, `--country`, `--currency`, `--timezone`). Ako nešto nedostaje, naredba to kaže i ništa ne mijenja.
5. Cron, svake minute. Pokreće sve pozadinske poslove, pa se bez njega ne šalje nijedan e-mail:

   ```
   * * * * * php /path/to/projektrack/artisan schedule:run >/dev/null 2>&1
   ```

6. Otvorite link iz e-maila, postavite lozinku, prijavite se i uključite dvofaktorsku autentifikaciju (obavezna za administratore).
7. Otvorite "Provjera sistema" i pokrenite je.

Provjerite da `https://<host>/.env` i `https://<host>/composer.json` vraćaju 403 ili 404.

## Testni računar bez HTTPS-a i mail servera

Samo za računar koji nije dostupan s interneta. U `.env`:

```
APP_ENV=staging
APP_URL=http://test.localhost
MAIL_MAILER=log
LOG_LEVEL=debug
```

Preostale vrijednosti `CHANGE-ME` za mail server tada nisu bitne. Nakon što se cron jednom izvrši (`php artisan schedule:run`), link za postavljanje je u najnovijoj datoteci u `storage/logs/`. Taj dnevnik sadrži linkove za prijavu: ove vrijednosti nikada ne koristite na dostupnom serveru.

## Primjeri podataka

Na testnoj instalaciji (`APP_ENV=staging`), za praznu firmu:

```
php artisan projektrack:demo <slug>
```

Sve u njoj je sintetičko; niko se ne može prijaviti kao osoba iz primjera, a nijedna faktura se ne izdaje.

## Šta se danas može testirati

| Oblast | Stanje |
|---|---|
| Postavljanje lozinke, prijava, dvofaktorska autentifikacija, sesije, profil | spremno |
| Jezici i vlastiti izrazi (bs, en, de), zdesna nalijevo | spremno |
| Brend, postavke, svijetli i tamni prikaz | spremno |
| Ekran licence (bez licence radi kao Community) | spremno |
| Provjera sistema, dnevnik revizije, obavještenja, API tokeni | spremno |
| Korisnici i uloge, početna ploča | spremno |
| Klijenti, projekti, ugovori, radni paketi | spremno |
| Sedmični unos vremena, odobravanje, korekcije, zaključenje mjeseca | spremno |
| Cijene sata, servisni izvještaji (PDF, CSV) | spremno |
| Fakture za izdavaoce u DE i BA, storno, uplate (PDF) | spremno; zakonske napomene mora potvrditi računovođa |
| Tajmer, XLSX, DOCX, fakture e-mailom, ZUGFeRD i XRechnung (CII, UBL) | spremno; još nije provjereno zvaničnim validatorima (KoSIT, veraPDF) |
| Ponude, troškovi, odsustva, avansi | još nije izrađeno |
| Šifrovani backup i vraćanje na običnom hostingu | spremno |
| Web instalacija, primjer za Nginx | još nije izrađeno |

## Backup

1. `php artisan projektrack:backup:key`. Red `BACKUP_PUBLIC_KEY` stavite u `.env`, a tajni ključ u trezor lozinki, nikada na server.
2. Od tada cron svake noći upisuje šifrovani backup u `storage/backups` (čuva se 14 dana). `php artisan projektrack:backup` ga upisuje odmah.
3. Redovno kopirajte backupe van servera, zajedno s jednom kopijom datoteke `.env`: backup je ne sadrži, a bez `APP_KEY` su šifrovane vrijednosti izgubljene.

Vraćanje, u praznu instalaciju iste verzije (`.env` stare, `php artisan migrate --force`, ne `projektrack:install`): `php artisan projektrack:restore <file> --key-file=<file with the secret key>` (datoteka backupa i datoteka s tajnim ključem), zatim obrišite datoteku s ključem. Ne na PostgreSQL. Isprobajte vraćanje jednom prije nego što se oslonite na njega.

## Noviji ZIP

Na običnom hostingu još nema automatskog ažuriranja.

1. Provjerite potpisano izdanje iz starog foldera (`docs/admin/releases.md`), zatim `php artisan projektrack:backup` i kopirajte datoteku van servera.
2. `php artisan down`
3. Raspakujte novi ZIP u novi folder. Iz starog kopirajte `.env` (`cp -p`, da ostane čitljiv samo vama) i `storage/app`.
4. U novom folderu: `php artisan projektrack:install`. Migrira bazu i čuva podatke. Za firmu koja je postojala prije modula: `php artisan projektrack:modules:enable <slug> service_reports` i isto s `invoices` (oba donose ono što im treba).
5. Usmjerite domenu i cron na novi folder.

Ako korak 4 ne uspije: vratite bazu, pokrenite `php artisan up` u starom folderu i zadržite domenu na njemu.
