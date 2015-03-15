<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php
/**
* @author      Michell Hoduń
* @copyright   (c) 2010 Michell Hoduń <mhodun@gmail.com>
* @description Klasa odpowiedzialna za wysyłanie SMS-ów z PlayMobile.pl.
 
* ---- UPDATE: Adding support for captcha ----
* @author      Piotr Siekierzyński
* @copyright   15.03.2015 Piotr Siekierzyński <piotr.siekierzynski@gmail.com>
* @description Dodanie obsługi kodów captcha
*/

// Załadowanie klasy
require_once 'PlayMobile.class.php';

// Załadowanie obiektu
$play = new PlayMobile();

// Zalogowanie się na konto Play
$play->DoLogin('numer', 'haslo');

$odbiorca = '000000000';
$tresc = 'tresc';

if(isset($_POST[0])){
	if(isset($_POST['odbiorca'])){
		$odbiorca = $_POST['odbiorca'];
	}
	if(isset($_POST['tresc'])){
		$tresc = $_POST['tresc'];
	}
}


	if(isset($_POST['captcha'])){
		$res = $play->SendSMS($odbiorca, $tresc, $_POST['captcha']);
	}
	else
	{
		$res = $play->SendSMS($odbiorca, $tresc);
	}
 
	if($res === TRUE)
	{
	echo 'Wiadomość wysłana poprawnie';
	}
	else if($res === FALSE)
	{
	echo 'Wiadomość nie została wysłana - wystąpił błąd podczas jej wysyłania';
	}
	else if($res[0] === 'CAPTCHA')
	{
		$img = $play->GetCaptcha();
		echo '<img src="'.$img.'"><br />';
		echo '<form method="post">';
		echo '<input type="text" placeholder="Kod z obrazka" name="captcha" style="margin-top: 3px"><br /><input type="submit" name="wyslij" value="Wyślij"> <input type="submit" name="reload" value="Przeładuj">';
		echo '<input type="hidden" name="odbiorca" value="'.addslashes($res['odbiorca']).'"><input type="hidden" name="tresc" value="'.addslashes($res['tresc']).'">';
		echo'</form>';
	}
?>
