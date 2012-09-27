<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php
/**
 * @author      Michell Hoduń
 * @copyright   (c) 2010 Michell Hoduń <mhodun@gmail.com>
 * @description Klasa odpowiedzialna za wysyłanie SMS-ów z PlayMobile.pl.
 */
  
  // Załadowanie klasy
  require_once 'PlayMobile.class.php';
  
  // Załadowanie obiektu
  $play = new PlayMobile();
  
  // Zalogowanie się na konto Play
  $play->DoLogin('email@mail.pl', 'haslo');
  
  if($play->SendSMS('508111222', 'tresc'))
  {
    echo 'Wiadomość wysłana poprawnie';
  }
  else
  {
    echo 'Wiadomość nie została wysłana - wystąpił błąd podczas jej wysyłania.';
  }
?>