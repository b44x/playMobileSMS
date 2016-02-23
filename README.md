## Prosty przykład użycia
```php
<?php
// Załadowanie klasy
require_once 'PlayMobile.class.php';

$login     = '500123123'; // Nasz login na Play.pl
$haslo     = 'ciekawehaslo'; // Wiadomo - hasło do konta.
$odbiorca  = '500123123'; // Numer na jaki zostanie wysłany SMS
$wiadomosc = 'Tutaj jakaś ciekawa wiadomość :)';

if(PlayMobile::sendSms('NUMER', 'HASLO', 'NUMER ODBIORCY', 'Treść wiadomości'))
{
  echo 'OK';
}
else
{
  echo 'Error...';
}
