<?php

/**
 * Captcha Resolvers. Based on code from https://github.com/jumper423/yii2-captcha
 *
 * @author      luzik
 */

class Captcha
{
    public $domain = "rucaptcha.com";
    public $pathTmp = 'captcha';
    private $apiKey;
    public $isVerbose = true;
    public $requestTimeout = 5;
    public $maxTimeout = 120;
    public $isPhrase = 0;
    public $isRegSense = 0;
    public $isNumeric = 0;
    public $minLen = 0;
    public $maxLen = 0;
    public $language = 0;
    private $error = null;
    private $result = null;

    private $errors = [
        'ERROR_NO_SLOT_AVAILABLE' => 'The current bid is higher than the maximum bid set for your account.',
        'ERROR_ZERO_CAPTCHA_FILESIZE' => 'CAPTCHA size is less than 100 bites',
        'ERROR_TOO_BIG_CAPTCHA_FILESIZE' => 'CAPTCHA size is more than 100 Kbites',
        'ERROR_ZERO_BALANCE' => 'You don’t have money on your account',
        'ERROR_IP_NOT_ALLOWED' => 'The request has sent from the IP that is not on the list of your IPs. Check the list of your IPs in the system.',
        'ERROR_CAPTCHA_UNSOLVABLE' => 'Captcha could not solve three different employee. Funds for this captcha not',
        'ERROR_BAD_DUPLICATES' => 'ERROR_BAD_DUPLICATES',
        'ERROR_NO_SUCH_METHOD' => 'ERROR_NO_SUCH_METHOD',
        'ERROR_IMAGE_TYPE_NOT_SUPPORTED' => 'The server cannot recognize the CAPTCHA file type',
        'ERROR_KEY_DOES_NOT_EXIST' => 'The "key" do not exist',
        'ERROR_WRONG_USER_KEY' => 'Wrong "key" parameter format, it should contain 32 symbols',
        'ERROR_WRONG_ID_FORMAT' => 'ERROR_WRONG_ID_FORMAT',
        'ERROR_WRONG_FILE_EXTENSION' => 'The CAPTCHA has a wrong extension. Possible extensions are: jpg,jpeg,gif,png',
    ];

    public function setApiKey($apiKey)
    {
        if (is_callable($apiKey)){
            $this->apiKey = $apiKey();
        } else {
            $this->apiKey = $apiKey;
        }
    }

    public function run($filename)
    {
        $this->result = null;
        $this->error = null;
        try {
            $postData = [
                'method' => 'post',
                'key' => $this->apiKey,
                'file' => (version_compare(PHP_VERSION, '5.5.0') >= 0) ? new \CURLFile($filename):  '@' . $filename,
                'phrase' => $this->isPhrase,
                'regsense' => $this->isRegSense,
                'numeric' => $this->isNumeric,
                'min_len' => $this->minLen,
                'max_len' => $this->maxLen,
                'language' => $this->language,
                'soft_id' => 882,
            ];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://{$this->domain}/in.php");
            if (version_compare(PHP_VERSION, '5.5.0') >= 0 && version_compare(PHP_VERSION, '7.0') < 0) {
                curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception("CURL returned error: " . curl_error($ch));
            }
            curl_close($ch);
            $this->setError($result);
            list(, $captcha_id) = explode("|", $result);
            $waitTime = 0;
            sleep($this->requestTimeout);
            while (true) {
                $result = file_get_contents("http://{$this->domain}/res.php?key={$this->apiKey}&action=get&id={$captcha_id}");
                $this->setError($result);
                if ($result == "CAPCHA_NOT_READY") {
                    $waitTime += $this->requestTimeout;
                    if ($waitTime > $this->maxTimeout) {
                        break;
                    }
                    sleep($this->requestTimeout);
                } else {
                    $ex = explode('|', $result);
                    if (trim($ex[0]) == 'OK') {
                        $this->result = trim($ex[1]);
                        return true;
                    }
                }
            }
            throw new Exception('Timeout');
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function result()
    {
        return $this->result;
    }

    public function error()
    {
        return $this->error;
    }

    private function setError($error)
    {
        if (strpos($error, 'ERROR') !== false) {
            if (array_key_exists($error, $this->errors)) {
                throw new Exception($this->errors[$error]);
            } else {
                throw new Exception($error);
            }
        }
    }
}
