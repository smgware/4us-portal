# Portal Sigma

Symfony projekt MySQL adatbázissal és `username` mezővel.

## Beállítás Windows alatt

1. Telepítsd a csomagokat:

```cmd
composer install
```

2. Ellenőrizd a kapcsolati adatokat a `config/config.yaml` fájlban. Az adatbázis- és SMTP-kapcsolati adatok már nem a `.env` fájlból töltődnek be.

3. Hozd létre az adatbázist és futtasd a migrációt:

```cmd
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:sync-metadata-storage
php bin/console doctrine:migrations:migrate
```

4. Admin létrehozás böngészőből:

```text
http://localhost/install-admin
```

Belépési adatok:

```text
username: admin
password: Admin1234!
```

## Alternatíva migráció nélkül

Ha a Doctrine migrációval gond van, importáld a `database.sql` fájlt MySQL-be/phpMyAdminba.

## Dinamikus login és jelszó-visszaállítás

A belépő oldal a `/login/index` címen érhető el. A login résznézet AJAX-szal töltődik be a `/login/login` végpontról. Az „Elfelejtett jelszó” gomb a `templates/login/passwordrecovery.html.twig` sablont tölti be, majd a backend ellenőrzi az email címet, tokent ír a `user.PasswordRecoveryToken` mezőbe, és PHPMailerrel küld jelszó-visszaállító linket.

Telepítés/frissítés után futtasd:

```bash
composer install
php bin/console doctrine:migrations:migrate
```

A PHPMailer SMTP beállításai a `config/config.yaml` fájlban találhatók `app.mail.*` paraméterként.

Az Everlink eventlog nézet itt érhető el:

```text
/everlink/eventlog
```
