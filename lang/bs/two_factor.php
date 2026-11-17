<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

return [
    'required' => 'Vaša uloga zahtijeva dvofaktorsku autentifikaciju. Postavite je da biste nastavili.',
    'cannot_disable' => 'Vaša uloga zahtijeva dvofaktorsku autentifikaciju, pa se ona ne može isključiti.',
    'reset_done' => 'Dvofaktorska autentifikacija je poništena. Korisnik je odjavljen i obaviješten e-mailom.',
    'recovery_codes_shown_once' => 'Sačuvajte ove kodove sada. Radi vaše sigurnosti prikazuju se samo ovaj put.',
    'recovery_codes_hidden' => 'Vaši kodovi za oporavak prikazani su prilikom kreiranja. Imate :count neiskorištenih kodova. Ako ste ih izgubili, kreirajte nove; stari tada prestaju važiti.',
    'reset_mail' => [
        'subject' => 'Vaša dvofaktorska autentifikacija za :brand je poništena',
        'greeting' => 'Zdravo :name,',
        'body' => 'Administrator je poništio dvofaktorsku autentifikaciju za vaš račun. Odjavljeni ste na svim uređajima. Pri sljedećoj prijavi možda ćete morati ponovo postaviti dvofaktorsku autentifikaciju.',
        'not_you' => 'Ako to niste tražili, odmah kontaktirajte svog administratora.',
        'salutation' => 'Vaš :brand tim',
    ],
];
