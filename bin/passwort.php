<?php
// Erzeugt einen Passwort-Hash für config.php ('admins' => ['name' => 'HASH']).
//   php bin/passwort.php
echo 'Neues Passwort: ';
system('stty -echo 2>/dev/null');
$pw = trim((string)fgets(STDIN));
system('stty echo 2>/dev/null');
echo "\n";
if (strlen($pw) < 10) {
    fwrite(STDERR, "Bitte mindestens 10 Zeichen verwenden.\n");
    exit(1);
}
echo password_hash($pw, PASSWORD_DEFAULT), "\n";
