# Pokretanje Projektracka s Docker Composeom

Za operatere profila Docker Compose i upravljanih instanci (S-0923, infrastrukturni profili, odjeljci 4 i 7). Sve datoteke su u `deploy/docker/`.

## Šta se pokreće

| Servis | Image | Zadatak |
|---|---|---|
| `proxy` | Caddy | Jedini servis s objavljenim portovima (80, 443). Automatski TLS, obavezna zaglavlja, bez zaglavlja `Server`, bez dnevnika pristupa. |
| `app` | Projektrack | Nginx i PHP-FPM kao neprivilegovani korisnik, korijenski datotečni sistem samo za čitanje. |
| `worker` | Projektrack | Obrada reda poslova (e-mailovi, obavještenja). |
| `scheduler` | Projektrack | Planirani zadaci (provjera sistema, provjera revizijskog zapisnika). |
| `migrate` | Projektrack | Izvršava se pri svakom pokretanju: migracije, katalog ovlaštenja, prava u bazi. Zatim završava. |
| `backup` | Projektrack | Šifrovani backup svakih 6 sati, čuva se 14 dana. |
| `db` | MariaDB 11.4 | Volumen s podacima, nije dostupan izvana. |
| `redis` | Valkey 8 | Keš i red poslova, zaštićen lozinkom, samo interno. |

Dvije mreže: `frontend` (proxy, app, worker, scheduler; worker je treba za slanje e-maila) i interna `backend` bez pristupa internetu (baza, keš, migracije, backup). Proxy ne može doći do baze. Ima fiksnu adresu kojoj aplikacija vjeruje kao svom jedinom proxyju. Docker dodjeljuje druge adrese samo iz gornje polovine mreže, pa je nijedan kontejner ne može preuzeti.

Dva računa u bazi: `projektrack_migrator` je vlasnik sheme. Aplikacija radi kao `projektrack`: čita i piše redove, ne može mijenjati shemu, a u revizijski zapisnik može samo dodavati. Root lozinka baze je nasumična i nikada nije potrebna.

Još nisu dio ovog skupa servisa: Meilisearch, Gotenberg, MinIO, ClamAV i Reverb. Aplikacija umjesto njih koristi ugrađene zamjene (infrastrukturni profili, odjeljak 2).

## Zahtjevi

Ubuntu 24.04 LTS, Docker Engine 27 ili noviji s Compose v2, 2 vCPU, 4 GB RAM-a, 40 GB diska, dostupni portovi 80 i 443 i DNS ime koje pokazuje na host. Osigurajte host prema odjeljku 7 infrastrukturnih profila (samo SSH ključevi, firewall, automatska ažuriranja).

## Prva instalacija

1. Nabavite image. Provjerite prvo potpisano izdanje (`docs/admin/releases.md`) i koristite image naveden u njegovoj datoteci `image.txt`. Dok ono ne postoji, izgradite image iz korijena repozitorija: `docker build -f deploy/docker/Dockerfile -t projektrack:local .`
2. U `deploy/docker/` jednom upišite tajne vrijednosti:

   ```
   ./setup.sh --domain app.example.com --email ops@example.com --image projektrack:local
   ```

   Time nastaju `.env` i `backup-identity.txt` (oboje čitljivo samo vama) i folder `backups/`. Svaka tajna vrijednost se generiše; skripta odbija prepisati postojeći `.env`. Ako se mreža `172.31.240.0/24` već koristi na hostu, dodajte `--network 10.99.7` (prva tri dijela slobodne privatne /24 mreže). Gradite iz čistog checkouta: sve u folderu što nije isključeno završava u imageu.
3. Upišite mail server u redove `MAIL_` datoteke `.env`. Zadan je implicitni TLS na portu 465 (`MAIL_SCHEME=smtps`). Zadržite ga, jer se STARTTLS na portu 587 može ukloniti usput. `projektrack:tenant:create` odbija rad dok mail server nije postavljen.
4. Premjestite `backup-identity.txt` s hosta u trezor lozinki, zajedno s kopijom datoteke `.env`. Bez oboje se nijedan backup ne može vratiti.
5. Pokrenite servise: `docker compose up -d`. Caddy pribavlja certifikat pri prvom zahtjevu.
6. Kreirajte zakupca i njegovog prvog administratora:

   ```
   docker compose exec app php artisan projektrack:tenant:create --slug=acme --name="Acme d.o.o." \
     --domain=app.example.com --admin-email=admin@example.com --admin-name="Ana Admin" \
     --locale=bs --country=BA --currency=BAM --timezone=Europe/Sarajevo
   ```

   Administrator e-mailom dobija link za postavljanje, koji važi 60 minuta, i sam postavlja lozinku i drugi faktor. Link se nikada ne prikazuje operateru. Ako istekne, administrator koristi "Zaboravili ste lozinku?" na stranici za prijavu.
7. Provjerite izvana: `docker compose exec app php artisan projektrack:doctor`.

Certifikat se objavljuje u dnevnicima Certificate Transparency; izaberite ime hosta koje se ne može lako pogoditi (infrastrukturni profili, odjeljak 7).

Objavite samo A zapis, bez AAAA zapisa. Mreža servisa je IPv4. Docker bi IPv6 posjetioce prosljeđivao s jednom zajedničkom internom adresom, pa bi ih ograničenja prijave sve brojala kao jednog posjetioca.

## Provjera stanja

`GET /health` odgovara s `OK` samo adresama iz `HEALTH_ALLOWED_IPS` (zadano: sam kontejner). Dodajte adresu svog hosta za nadzor, odvojenu zarezom, i ponovo pokrenite `docker compose up -d`. Kontejner `app` sam prijavljuje svoje stanje Dockeru.

## Backup

Servis `backup` svakih 6 sati upisuje folder `backups/<UTC time>/` (UTC vrijeme; `BACKUP_INTERVAL_HOURS`) i uklanja foldere starije od 14 dana (`BACKUP_KEEP_DAYS`). Svaki folder sadrži:

- `database.sql.gz.age`: bazu,
- `files.tar.gz.age`: učitane datoteke i ključeve iz volumena za pohranu (bez keša i dnevnika),
- `SHA256SUMS`: kontrolne sume obje datoteke,
- `SHA256SUMS.hmac`: potpis kontrolnih suma ključem `BACKUP_HMAC_KEY` iz `.env`.

Obje datoteke se šifruju javnim ključem iz `.env` već dok se upisuju; ništa nešifrovano ne dolazi na disk. Samo privatni ključ iz trezora ih može dešifrovati, pa folder ostaje povjerljiv i kada ga kopirate na drugu lokaciju (na primjer s `rsync` ili `rclone` s hosta). Javni ključ nije tajna, pa bi svako mogao šifrovati vlastiti backup. Tek potpis dokazuje da backup dolazi iz ove instance, a vraćanje odbija svaki backup bez važećeg potpisa.

Danas backupi ostaju na hostu dok ih sami ne kopirate na drugu lokaciju. Za upravljanu instancu to postavite od prvog dana (druga lokacija u EU, infrastrukturni profili, odjeljak 7). Kontejner `backup` dobija stanje unhealthy kada je posljednji uspjeh stariji od intervala uvećanog za jedan sat; nadzirite to stanje.

Backup odmah: `docker compose exec backup projektrack-backup run`.

## Vježba vraćanja

Vježbajte svakog mjeseca na privremenom hostu, a nakon gubitka vraćajte na isti način. CI izvršava ovu vježbu pri svakoj izmjeni (`tests/docker/stack.sh`).

1. Na novom hostu stavite kopije datoteka `.env` i `backup-identity.txt` iz trezora u `deploy/docker/` (`chmod 600` za obje), a foldere backupa u `backups/`. Potreban je isti `APP_KEY`, inače se šifrovane vrijednosti ne mogu pročitati.
2. Pokrenite samo bazu i keš: `docker compose up -d db redis`
3. Vratite jedan backup u praznu instancu:

   ```
   docker compose --profile restore run --rm restore restore 20261123T020000Z
   ```

   Skripta provjerava potpis i kontrolne sume i jednom probno dešifruje obje datoteke prije nego što išta upiše. Odbija pisati u bazu ili volumen za pohranu koji sadrži podatke. `BACKUP_HMAC_KEY` u `.env` mora biti onaj iz instance iz koje backup potiče.
4. Pokrenite sve: `docker compose up -d`. Servis `migrate` dovodi shemu na verziju imagea i postavlja prava u bazi.
5. Ponovo uklonite `backup-identity.txt` s hosta.

Ciljevi za upravljane instance: RPO 6 sati, RTO 4 sata (radno vrijeme).

## Dnevnici

Kontejneri pišu dnevnike u Docker (`docker compose logs`), s rotacijom na 10 x 10 MB po kontejneru. Nijedan dnevnik ne sadrži putanje zahtjeva, jer putanje mogu nositi jednokratne tokene (postavljanje nove lozinke, dijeljeni linkovi): dnevnici pristupa ih izostavljaju, Nginx bilježi samo kritične greške, a Caddyjevi unosi o greškama zahtjeva su isključeni. Za čuvanje od 90 dana na upravljanim instancama prosljeđujte Docker dnevnike u journal hosta.

## Ažuriranja

Preuzmite `SHA256SUMS`, `SHA256SUMS.sig` i `projektrack-X.Y.Z.image.txt` izdanja u prazan folder na hostu, zatim u `deploy/docker/`:

```
./projektrack update /path/to/folder
```

Naredba:

1. provjerava potpis ključevima izdanja iz verzije koja radi (`docs/admin/releases.md`), uzima digest imagea iz provjerene datoteke i odbija verziju koja nije novija od one koja radi;
2. provjerava stanje i prostor na disku, pravi šifrovani backup, uključuje režim održavanja, završava zahtjeve i poslove u toku i zaustavlja pozadinske servise;
3. pravi lokalni snimak baze i datoteka, zatim izvršava migracije nove verzije (posjetioci vide stranicu održavanja);
4. zaustavlja proxy, pokreće novu verziju, provjerava njeno stanje i ponovo je otvara.

Ako neki korak ne uspije, naredba vraća snimak, pokreće prethodnu verziju, provjerava njeno stanje i završava s izlaznim kodom 1. Snimak je potreban jer privatni ključ backupa nije na hostu; nalazi se u vlastitom Docker volumenu samo dok ažuriranje traje.

Ako je nova verzija ispravna, ali se neki servis nakon toga ne pokrene, povratka više nema, jer su korisnici možda već upisali podatke; naredba to kaže.

### Kada je ažuriranje prekinuto

Izlazni kod 2 znači da ni povratak nije uspio: proxy ostaje zaustavljen, a snimak se čuva. Nestanak struje ostavlja datoteku `.update-state` s prethodnim imageom. Naredba odbija dalja ažuriranja dok se to ne riješi:

1. Zaustavite sve što piše: `docker compose stop proxy app worker scheduler backup`, i uklonite ostatke s `docker rm -f projektrack-update-snapshot projektrack-update-migrate`.
2. Ako `.update-state` sadrži `committed=1`, nova verzija je bila ispravna i možda već sadrži nove podatke: zadržite je i pređite na korak 5.
3. Postavite `PROJEKTRACK_IMAGE` u `.env` na vrijednost `previous` iz `.update-state`.
4. Vratite: `docker compose --profile update run --rm snapshot projektrack-snapshot restore`. Ako javi da nema potpunog snimka, baza još nije dirana (bez `committed=1`). Ako javi da je snimak oštećen, vratite šifrovani backup na novom hostu.
5. Pokrenite samo aplikaciju, dok je proxy još zaustavljen: `docker compose up -d --no-deps app`, `docker compose exec app php artisan up`, i provjerite da `docker compose exec app wget -q -O - http://127.0.0.1:8080/health` odgovara s `OK` (nakon pokretanja treba nekoliko sekundi).
6. Tek kada je sve u redu: `docker compose --profile update run --rm snapshot projektrack-snapshot drop`, obrišite `.update-state` i otvorite instancu s `docker compose up -d`.

Snimak sadrži nešifrovane podatke, kao i volumen baze. Isključite volumen `projektrack_snapshot` iz backupa hosta.

Predizdanja kao `0.9.0-rc1` smatraju se starijim od `0.9.0` i mogu se instalirati kao svako potpisano izdanje.
Naredba ne ažurira same datoteke u `deploy/docker/`; uzmite ih iz ZIP-a izdanja kada to kažu njegove napomene, nakon što ga provjerite (`docs/admin/releases.md`).

## Naredbe

| Naredba | Šta radi |
|---|---|
| `./projektrack install --domain ... --email ... --image ...` | Upisuje tajne vrijednosti (`setup.sh`) |
| `./projektrack backup` | Jedan šifrovani backup odmah |
| `./projektrack restore <backup>` | Vraća backup u praznu instancu i pokreće je |
| `./projektrack update <folder>` | Ažuriranje s automatskim povratkom |
