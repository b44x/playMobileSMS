<?php
/**
 * @author      Michell Hoduń
 * @copyright   (c) 2010 Michell Hoduń <mhodun@gmail.com>
 * @description Klasa odpowiedzialna za wysyłanie SMS-ów z PlayMobile.pl.
 */

class PlayMobile {

 /**
  * Wspomagacz dla CURL'a - ułatwienie dostępu
  *
  * @param string $url
  * @param array $post
	* @param string $ref
	* @param integer $follow
	* @param integer $header
 */
 public static function curl ($url, $post = NULL, $ref = NULL, $follow = 1, $header = 1, $post_type = NULL)
 {
    $ch = curl_init ($url);
    
    curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; pl; rv:1.9.1.7) Gecko/20091221 Firefox/3.5.7');
    curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
    
    // Zapisywanie ciastek do pliku
    curl_setopt($ch,CURLOPT_COOKIEJAR, dirname(__FILE__).'/cookie.txt');
    curl_setopt($ch,CURLOPT_COOKIEFILE, dirname(__FILE__).'/cookie.txt');
    
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    
    if ( ! empty($post) )
    {
      $postVars='';
      
      foreach ($post as $option => $value)
      {
        $postVars .= $option.'='.urlencode($value).'&';
      }
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postVars);
    }
      
    if ( $ref )
    {
      curl_setopt($ch, CURLOPT_REFERER, $ref);
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch,CURLOPT_HEADER, 1);
    curl_setopt($ch,CURLOPT_FOLLOWLOCATION, $follow);
    
    $r = curl_exec($ch);
    curl_close($ch);
    
    return $r;
  }
  
 /**
  * Funkcja odpowiedzialna za zalogowanie się konto + zalogowanie do bramki.
  *
  * @param string $login
  * @param string $password
  */
  public function DoLogin ($login, $password)
  {
    $BeforePost = array('SAMLRequest' => $this->GetGateway(), 'target'=> 'https://bramka.playmobile.pl');
    
    $content = $this->curl('https://logowanie.playmobile.pl/p4-idp2/SSOrequest.do', $BeforePost, 'https://bramka.playmobile.pl/composer/public/mmsCompose.do');
    
    preg_match('/name="random" value="(.+?)"/', $content, $rand);
    $post = array('step' => 1, 'next' => 'Next', 'random' =>$rand[1], 'login' =>$login, 'password' =>$password);
    
    // Właściwe zalogowanie się
    $LoginDO = $this->curl('https://logowanie.playmobile.pl/p4-idp2/Login.do',$post,'',0);
    $samlLog_Post = array('SAMLResponse' => $this->SAMLResponse($LoginDO), 'target'=>'https://bramka.playmobile.pl');
    $this->curl('https://bramka.playmobile.pl/composer/samlLog?action=sso', $samlLog_Post, 'https://logowanie.playmobile.pl/p4-idp2/SSOrequest.do');
    
    return $this->curl('https://bramka.playmobile.pl/composer/j_security_check', $samlLog_Post, 'https://bramka.playmobile.pl/composer/samlLog?action=sso');
  }

 /**
  * Pobierz "SAMLRequest" - wymagane do zalogowania.
  *
  * @return $SAMLRequest
  */
	public function GetGateway()
	{
    $SAML = $this->curl('https://bramka.playmobile.pl/composer/public/mmsCompose.do', NULL, '', 0, 0);
    
    // Wyszukaj SAMLRequest
    preg_match('/value="(.*)"/msU',$SAML,$w);
    
    $SAMLRequest = $w[1];
    
    // Zwróć
    return $SAMLRequest;
	}
	
 /**
  * Wyciągnięcie SAMLResponse z stringa (treści strony).
  *
  * @param string $content
  */
  public function SAMLResponse ($content)
  {
    preg_match('/value="(.*)"/msU', $content, $w);
    return trim($w[1]);
  }
  
  public function SendSMS ($odbiorca, $tresc)
  {
    $content = $this->curl('https://bramka.playmobile.pl/composer/public/editableSmsCompose.do');
    
    // Wyciągnij kod 'zabezpieczający'
    preg_match('/name="randForm" value="(.+?)"/', $content, $rand);
    
    $SMS = array('recipients' => $odbiorca, 'content_in' => $tresc, 'czas' => 0, 'sendform' => 'on', 'randForm' => $rand[1], 'old_signature' => '', 'old_content' => $tresc,'content_out' => $tresc);
  
    $content2 = $this->curl('https://bramka.playmobile.pl/composer/public/editableSmsCompose.do', $SMS);
    
    $SMS['SMS_SEND_CONFIRMED'] = 'Wyślij';
    $content3 = $this->curl('https://bramka.playmobile.pl/composer/public/editableSmsCompose.do', $SMS);
    
    if (preg_match('/Wiadomo(.*) zosta(.*)a wys(.*)ana/',$content3))
    {
      // Wiadomość została wysłana poprawnie
      return TRUE;
    }
    else
    {
      // Wiadomość nie została wysłana - wystąpił błąd podczas jej wysyłania.
      return FALSE;
    }
  }
}