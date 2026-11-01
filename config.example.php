<?php
// Lehrgangsheft – Konfiguration
// Kopieren nach config.php und anpassen.

return [
    // Öffentliche Adresse des Hefts (ohne Schrägstrich am Ende)
    'base_url' => 'https://lehrgaenge.example.de',

    // Adresse, unter der Chromium das Heft für den PDF-Export lokal abruft.
    // Muss vom Container selbst erreichbar sein (nginx auf demselben Host).
    'intern_url' => 'http://127.0.0.1',

    // Quelle
    'quelle_liste' => 'https://baden.dlrg.de/lehrgaenge/',
    'quelle_basis' => 'https://baden.dlrg.de',

    // Pause zwischen zwei Abrufen von Detailseiten (Sekunden) – schont den Server des LV
    'abruf_pause' => 1.5,

    // Chromium für den PDF-Export
    'chromium' => '/usr/bin/chromium',

    // Mailversand für Einladungen und „Passwort vergessen“
    //   IONOS:            host smtp.ionos.de, port 587, verschluesselung 'tls'
    //   Google Workspace: host smtp.gmail.com, port 587, 'tls', Passwort = App-Passwort
    'mail' => [
        'host'             => 'smtp.ionos.de',
        'port'             => 587,
        'verschluesselung' => 'tls',          // 'tls' (STARTTLS), 'ssl' (Port 465) oder 'none'
        'benutzer'         => '',             // meist die vollständige E-Mail-Adresse des Postfachs
        'passwort'         => '',
        'absender'         => '',             // z. B. lehrgangsheft@deine-domain.de
        'absender_name'    => 'Lehrgangsheft DLRG LV Baden',
    ],

    // Notfallzugang ohne E-Mail: Benutzername => Passwort-Hash (erzeugen mit: php bin/passwort.php).
    // Die eigentlichen Admins werden im Admin-Bereich unter „Admins“ angelegt.
    'admins' => [
        // 'torsten' => '$2y$12$....',
    ],

    // Reihenfolge der Bereiche im Heft
    'bereiche' => [
        'Grundausbildung',
        'Schwimmen & Rettungsschwimmen',
        'Erste Hilfe · Sanität · RUND',
        'Wasserrettungsdienst',
        'Strömungsrettung & Hochwasser',
        'Bootswesen',
        'Tauchen',
        'Rettungssport',
        'Vereinsmanagement',
        'Tagungen & Gremien',
        'Weitere Lehrgänge',
    ],

    // Vorschlag für den Bereich anhand der Lehrgangsnummer (Ziffern 4–6, z. B. 27-40811 -> "408").
    // Der Admin kann den Bereich je Lehrgang überschreiben.
    'bereich_nach_nummer' => [
        '401' => 'Grundausbildung',
        '402' => 'Erste Hilfe · Sanität · RUND',
        '403' => 'Schwimmen & Rettungsschwimmen',
        '404' => 'Wasserrettungsdienst',
        '408' => 'Strömungsrettung & Hochwasser',
        '410' => 'Vereinsmanagement',
        '412' => 'Rettungssport',
    ],

    // Kontakt auf der Kontaktseite des Hefts
    'kontakt' => [
        'name'    => 'Bildungsteam LV Baden',
        'email'   => 'bildung@baden.dlrg.de',
        'telefon' => '0721 91100-20',
    ],
];
