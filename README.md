## Prosty przykład użycia
```php
<?php
require_once 'PlayMobile.class.php';

$login     = '500123123'; // Nasz login na Play.pl
$haslo     = 'ciekaweHaslo'; // Wiadomo - hasło do konta.
$odbiorca  = '500123123'; // Numer na jaki zostanie wysłany SMS
$wiadomosc = 'Witam! Wygląda na to, że działa :)';

/*
 * Konfiguracja DeathByCaptcha
 */
PlayMobile::$dbcUser = 'NazwaUseraDBC'; // Nazwa użytkownika DeathByCaptcha
PlayMobile::$dbcPass = 'takiehaslo'; // Nazwa użytkownika DeathByCaptcha

if(PlayMobile::sendSms($login, $haslo, $odbiorca, $wiadomosc))
{
  echo 'OK';
}
else
{
  echo 'Error...';
}
