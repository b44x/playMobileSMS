<?php
/**
 * Klasa odpowiedzialna za wysyłanie SMS-ów z play.pl.
 * W najnowszej wersji dodano obsługę captcha przez - (DeathByCaptcha.com)
 *
 * @author      Michell `b4x` Hoduń
 * @copyright   (c) 2010-2016 Michell Hoduń <mhodun@gmail.com>
 */

class PlayMobile
{
  const USERAGENT    = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.116 Safari/537.36';
  const loginPage    = 'https://logowanie.play.pl/opensso/logowanie';
  const smsPage      = 'https://bramka.play.pl/composer/public/editableSmsCompose.do';
  const smsGate      = 'https://bramka.play.pl/';
  const ssoPage      = 'https://logowanie.play.pl/p4-idp2/SSOrequest.do';
  const securityPage = 'https://bramka.play.pl/composer/j_security_check';
  const samlLog      = 'https://bramka.play.pl/composer/samlLog?action=sso';

  private static $captchaRequired = FALSE;
  public static $captchaService = NULL; // Serwis captcha deathbycaptcha.com|rucaptcha.com|2captcha.com|pixodrom.com|captcha24.com|socialink.ru|anti-captcha.com
  public static $captchaApiKey = NULL; // APIKey to captcha service
  public static $dbcUser    = NULL; // Login DeathByCaptcha
  public static $dbcPass    = NULL; // Hasło DeathByCaptcha

 /**
  * Wspomagacz dla CURL'a - ułatwienie dostępu
  *
  * @param string $url
  * @param array $post
  * @param string $ref
  * @param integer $follow
  * @param integer $header
 */
 public static function curl($url, $post = null, $ref = null, $follow = 1, $header = 1, $post_type = null)
 {
    $ch = curl_init($url);

    curl_setopt_array($ch, array(
      CURLOPT_USERAGENT      => PlayMobile::USERAGENT,
      CURLOPT_AUTOREFERER    => TRUE,
      CURLOPT_SSL_VERIFYPEER => FALSE,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_COOKIEJAR      => dirname(__FILE__).'/cookie.txt',
      CURLOPT_COOKIEFILE     => dirname(__FILE__).'/cookie.txt',
      CURLOPT_HEADER         => FALSE,
      CURLOPT_FOLLOWLOCATION => TRUE
    ));

    if ( ! empty($post))
    {
      $postVars ='';

      foreach ($post as $option => $value)
        $postVars .= $option.'='.urlencode($value).'&';

      curl_setopt($ch, CURLOPT_POST, TRUE);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $postVars);
    }

    if($ref)
      curl_setopt($ch, CURLOPT_REFERER, $ref);

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
  public static function sendSms($login, $password, $recipent, $message)
  {

    if( ! $randomId = PlayMobile::getFormRandomId())
    {
      // Robimy puste wywołanie (dla pobrania ciasteczek)
      PlayMobile::curl(PlayMobile::loginPage);

      // Ustawiamy niezbędne parametry do przesłania
      $formParams = array(
        'IDToken1'             => $login,
        'IDToken2'             => $password,
        'Login.Submit'         => 'Zaloguj',
        'goto'                 => '',
        'gotoOnFail'           => '',
        'SunQueryParamsString' => '',
        'encoded'              => 'false',
        'gx_charset'           => 'UTF-8'
      );

      // Logujemy się na konto
      $loginRequest = PlayMobile::curl(PlayMobile::loginPage, $formParams);

      // Pobieramy "ukryte" dane (potrzebne do wysłania SMS-a)
      $smsGateResult = PlayMobile::curl(PlayMobile::smsGate);

      // Poszukujemy niezbędnych danych
      preg_match('/name="SAMLRequest"\s+value="([+=\w\d\n\r]+)"/i', $smsGateResult, $res1);
      preg_match('/name="target"\s+value="([:\/\.\w]+)"\s+\/>/i', $smsGateResult, $res2);

      $formParams = array(
        'SAMLRequest' => $res1[1],
        'target'      => $res2[1]
      );

      $ssoPageResult = PlayMobile::curl(PlayMobile::ssoPage, $formParams);

      // Nie są już nam potrzebne
      unset($res1, $res2);

      preg_match('/name="SAMLResponse"\s+value="([+=\w\d\n\r]+)"/i', $ssoPageResult, $res3);
      preg_match('/NAME="target"\s+VALUE="([:\/\.\w]+)">/i', $ssoPageResult, $res4);

      $formParams = array(
        'SAMLResponse' => $res3[1],
        'target'      => $res4[1]
      );

      $samlPageResult = PlayMobile::curl(PlayMobile::samlLog, $formParams);

      // Wyciągamy SAMLResponse
      preg_match('/name="SAMLResponse"\s+value="([+=\w\d\n\r]+)/i', $ssoPageResult, $res5);

      $formParams = array(
        'SAMLResponse' => $res5[1]
      );

      $securityPageResult = PlayMobile::curl(PlayMobile::securityPage, $formParams);
      unset($res3);

      $randomId = PlayMobile::getFormRandomId();
    }

    $formParams = array(
      'recipients'      => $recipent,
      'content_in'      => $message,
      'czas'            => '0',
      'content_out'     => $message,
      'templateId'      => '',
      'sendform'        => 'on',
      'composedMsg'     => '',
      'randForm'        => $randomId,
      'old_signature'   => '',
      'old_content'     => $message,
      'MessageJustSent' => 'false'
    );

    // Ustawiamy captchę
    if(PlayMobile::$captchaRequired) {
      if (PlayMobile::$captchaService and (PlayMobile::$dbcUser or PlayMobile::$captchaApiKey)) {
        $formParams['inputCaptcha'] = PlayMobile::fixCaptcha(PlayMobile::$captchaRequired);
      }
      else {
        print "Captch dbc required, but not enabled\n";
        return false;
      }
    }

    PlayMobile::curl(PlayMobile::smsPage, $formParams);
    $formParams['SMS_SEND_CONFIRMED'] = 'Wyślij';
    $sendSmsResult = PlayMobile::curl(PlayMobile::smsPage, $formParams);
    return preg_match('/Wiadomość została przyjęta do realizacji/i', $sendSmsResult);
  }

  public static function getFormRandomId()
  {
    $smsPageResult = PlayMobile::curl(PlayMobile::smsPage);

    preg_match('/<input\s+type="hidden"\s+name="randForm"\s+value="(\d+)">/i', $smsPageResult, $res6);

    // Sprawdzamy ... być może dostaniemy captcha do rozwiązania.
    if(preg_match('/<img class="captchaPosition" id="imgCaptcha" alt="" width="200" height="50"\s+src=(.+?)>/i', $smsPageResult, $cCheck))
      PlayMobile::$captchaRequired = 'https://bramka.play.pl'.$cCheck[1];
    else
      PlayMobile::$captchaRequired = NULL;

    if(isset($res6[1]))
      return $res6[1];

    return FALSE;
  }

  /**
   * Obsługa DBC - rozwiązywanie captcha
   * @param string $captchaUrl
   */
  public static function fixCaptcha($captchaUrl)
  {
    $captchaFile = dirname(__FILE__) . '/captcha/' . uniqid('captcha') . '.png';
    file_put_contents( $captchaFile, PlayMobile::curl($captchaUrl));

    if (PlayMobile::$captchaService == 'deathbycaptcha.com') {
      require_once 'vendor/deathbycaptcha.php';
      $client  = new DeathByCaptcha_SocketClient(PlayMobile::$dbcUser, PlayMobile::$dbcPass);
      $captcha = $client->decode($captchaFile, DeathByCaptcha_Client::DEFAULT_TIMEOUT);
      $captcha = $captcha['text'];
    }
    else {
      require_once 'vendor/Captcha.php';
      $client = new Captcha();
      $client->domain = PlayMobile::$captchaService;
      $client->setApiKey(PlayMobile::$captchaApiKey);
      if ($client->run($captchaFile)){
        $captcha = $client->result();
      }
      else {
        $captcha = NULL;
      }
    }
    if ($captcha)
    {
      @unlink($captchaFile);
      print "Captcha: $captcha\n";
      return $captcha;
    }
    else
    {
      @unlink($captchaFile);
      return FALSE;
    }
  }

}
