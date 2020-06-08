<?php

namespace Bca;

use Carbon\Carbon;
use Unirest\Request;
use Unirest\Request\Body;

/**
 * BCA REST API Library.
 *
 * @author     Pribumi Technology
 * @license    MIT
 * @copyright  (c) 2017, Pribumi Technology
 *
 * Modified and tested for use with GetPlus (Global Poin Indonesia, PT)
 * @modifiedby Firman Syamsudin
 */
class BcaHttp
{
    public static $VERSION = '2.3.1';

    /**
     * Default Timezone.
     *
     * @var string
     */
    private static $timezone = 'Asia/Jakarta';

    /**
     * Default BCA Port.
     *
     * @var int
     */
    private static $port = 443;

    /**
     * Default BCA Host.
     *
     * @var string
     */
    private static $hostName = 'sandbox.bca.co.id';

    /**
     * Default BCA Host.
     *
     * @var string
     */
    private static $scheme = 'https';

    /**
     * Timeout curl.
     *
     * @var int
     */
    private static $timeOut = 60;

    /**
     * Default Curl Options.
     *
     * @var int
     */
    private static $curlOptions = array(
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSLVERSION => 6,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 60,
    );

    /**
     * Default BCA Settings.
     *
     * @var array
     */
    protected $settings = array(
        'corp_id' => '',
        'client_id' => '',
        'client_secret' => '',
        'api_key' => '',
        'secret_key' => '',
        'curl_options' => array(),
        // Backward compatible
        'host' => 'sandbox.bca.co.id',
        'scheme' => 'https',
        'timeout' => 60,
        'port' => 443,
        'timezone' => 'Asia/Jakarta',
        // New Options
        'options' => array(
            'host' => 'sandbox.bca.co.id',
            'scheme' => 'https',
            'timeout' => 60,
            'port' => 443,
            'timezone' => 'Asia/Jakarta',
        ),
    );

    /**
     * Default Constructor.
     *
     * @param string $corp_id nilai corp id
     * @param string $client_id nilai client key
     * @param string $client_secret nilai client secret
     * @param string $api_key niali oauth key
     * @param string $secret_key nilai oauth secret
     * @param array $options opsi ke server bca
     */
    public function __construct($corp_id, $client_id, $client_secret, $api_key, $secret_key, array $options = [])
    {
        // Required parameters.
        $this->settings['corp_id'] = $corp_id;
        $this->settings['client_id'] = $client_id;
        $this->settings['client_secret'] = $client_secret;
        $this->settings['api_key'] = $api_key;
        $this->settings['secret_key'] = $secret_key;
        $this->settings['host'] =
            preg_replace('/http[s]?\:\/\//', '', $this->settings['host'], 1);

        foreach ($options as $key => $value) {
            if (isset($this->settings[$key])) {
                $this->settings[$key] = $value;
            }
        }

        // Setup optional scheme, if scheme is empty
        if (isset($options['scheme'])) {
            $this->settings['scheme'] = $options['scheme'];
            $this->settings['options']['scheme'] = $options['scheme'];
        } else {
            $this->settings['scheme'] = self::getScheme();
            $this->settings['options']['scheme'] = self::getScheme();
        }

        // Setup optional host, if host is empty
        if (isset($options['host'])) {
            $this->settings['host'] = $options['host'];
            $this->settings['options']['host'] = $options['host'];
        } else {
            $this->settings['host'] = self::getHostName();
            $this->settings['options']['host'] = self::getHostName();
        }

        // Setup optional port, if port is empty
        if (isset($options['port'])) {
            $this->settings['port'] = $options['port'];
            $this->settings['options']['port'] = $options['port'];
        } else {
            $this->settings['port'] = self::getPort();
            $this->settings['options']['port'] = self::getPort();
        }

        // Setup optional timezone, if timezone is empty
        if (isset($options['timezone'])) {
            $this->settings['timezone'] = $options['timezone'];
            $this->settings['options']['timezone'] = $options['timezone'];
        } else {
            $this->settings['timezone'] = self::getHostName();
            $this->settings['options']['timezone'] = self::getHostName();
        }

        // Setup optional timeout, if timeout is empty
        if (isset($options['timeout'])) {
            $this->settings['timeout'] = $options['timeout'];
            $this->settings['options']['timeout'] = $options['timeout'];
        } else {
            $this->settings['timeout'] = self::getTimeOut();
            $this->settings['options']['timeout'] = self::getTimeOut();
        }

        // Set Default Curl Options.
        Request::curlOpts(self::$curlOptions);

        // Set custom curl options
        if (!empty($this->settings['curl_options'])) {
            $data = self::mergeCurlOptions(self::$curlOptions, $this->settings['curl_options']);
            Request::curlOpts($data);
        }
    }

    /**
     * Ambil Nilai settings.
     *
     * @return array
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * Build the ddn domain.
     * output = 'https://sandbox.bca.co.id:443'
     * scheme = http(s)
     * host = sandbox.bca.co.id
     * port = 80 ? 443
     *
     * @return string
     */
    private function ddnDomain()
    {
        return $this->settings['scheme'] . '://' . $this->settings['host'] . ':' . $this->settings['port'] . '/';
    }

    /**
     * Generate authentifikasi ke server berupa OAUTH.
     *
     * @return \Unirest\Response
     */
    public function httpAuth()
    {
        $client_id = $this->settings['client_id'];
        $client_secret = $this->settings['client_secret'];

        $headerToken = base64_encode("$client_id:$client_secret");

        $headers = array('Accept' => 'application/json', 'Authorization' => "Basic $headerToken");

        $request_path = "api/oauth/token";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $data = array('grant_type' => 'client_credentials');
        $body = Body::form($data);
        $response = Request::post($full_url, $headers, $body);

        return $response;
    }

    /**
     * Ambil informasi saldo berdasarkan nomor akun BCA.
     *
     * @param string $oauth_token nilai token yang telah didapatkan setelah login
     * @param array $sourceAccountId nomor akun yang akan dicek
     *
     * @throws BcaHttpException error
     * @return \Unirest\Response
     */
    public function getBalanceInfo($oauth_token, $sourceAccountId = [])
    {
        $corp_id = $this->settings['corp_id'];

        $this->validateArray($sourceAccountId);

        ksort($sourceAccountId);
        $arraySplit = implode(",", $sourceAccountId);
        $arraySplit = urlencode($arraySplit);

        $uriSign = "GET:/banking/v3/corporates/$corp_id/accounts/$arraySplit";
        $isoTime = self::generateIsoTime();
        $authSignature = self::generateSign($uriSign, $oauth_token, $this->settings['secret_key'], $isoTime, null);

        $headers = array();
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $this->settings['api_key'];
        $headers['X-BCA-Timestamp'] = $isoTime;
        $headers['X-BCA-Signature'] = $authSignature;

        $request_path = "banking/v3/corporates/$corp_id/accounts/$arraySplit";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $data = array('grant_type' => 'client_credentials');

        $body = Body::form($data);
        $response = Request::get($full_url, $headers, $body);

        return $response;
    }

    /**
     * Ambil Daftar transaksi pertanggal.
     *
     * @param string $oauth_token nilai token yang telah didapatkan setelah login
     * @param string $sourceAccount nomor akun yang akan dicek
     * @param string $startDate tanggal awal
     * @param string $endDate tanggal akhir
     * @param string $corp_id nilai CorporateID yang telah diberikan oleh pihak BCA
     *
     * @return \Unirest\Response
     */
    public function getAccountStatement($oauth_token, $sourceAccount, $startDate, $endDate)
    {
        $corp_id = $this->settings['corp_id'];
        $uriSign = "GET:/banking/v3/corporates/$corp_id/accounts/$sourceAccount/statements?EndDate=$endDate&StartDate=$startDate";
        $isoTime = self::generateIsoTime();
        $authSignature = self::generateSign($uriSign, $oauth_token, $this->settings['secret_key'], $isoTime, null);
        $headers = array();
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $this->settings['api_key'];
        $headers['X-BCA-Timestamp'] = $isoTime;
        $headers['X-BCA-Signature'] = $authSignature;
        $request_path = "banking/v3/corporates/$corp_id/accounts/$sourceAccount/statements?EndDate=$endDate&StartDate=$startDate";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $data = array('grant_type' => 'client_credentials');
        $body = Body::form($data);
        $response = Request::get($full_url, $headers, $body);
        return $response;
    }

    /**
     * Ambil informasi ATM berdasarkan lokasi GEO.
     *
     * @param string $oauth_token nilai token yang telah didapatkan setelah login
     * @param string $latitude Langitude GPS
     * @param string $longitude Longitude GPS
     * @param string $count Jumlah ATM BCA yang akan ditampilkan
     * @param string $radius Nilai radius dari lokasi GEO
     *
     * @throws BcaHttpException error
     * @return \Unirest\Response
     */
    public function getAtmLocation(
        $oauth_token,
        $latitude,
        $longitude,
        $count = '10',
        $radius = '20'
    ) {
        $params = array();
        $params['SearchBy'] = 'Distance';
        $params['Latitude'] = $latitude;
        $params['Longitude'] = $longitude;
        $params['Count'] = $count;
        $params['Radius'] = $radius;
        ksort($params);

        $auth_query_string = self::arrayImplode('=', '&', $params);

        $uriSign = "GET:/general/info-bca/atm?$auth_query_string";
        $isoTime = self::generateIsoTime();
        $authSignature = self::generateSign($uriSign, $oauth_token, $this->settings['secret_key'], $isoTime, null);

        $headers = array();
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $this->settings['api_key'];
        $headers['X-BCA-Timestamp'] = $isoTime;
        $headers['X-BCA-Signature'] = $authSignature;

        $request_path = "general/info-bca/atm?SearchBy=Distance&Latitude=$latitude&Longitude=$longitude&Count=$count&Radius=$radius";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $data = array('grant_type' => 'client_credentials');
        $body = Body::form($data);
        $response = Request::get($full_url, $headers, $body);

        return $response;
    }

    /**
     * Ambil KURS mata uang.
     *
     * @param string $oauth_token nilai token yang telah didapatkan setelah login
     * @param string $rateType type rate
     * @param string $currency Mata uang
     *
     * @throws BcaHttpException error
     * @return \Unirest\Response
     */
    public function getForexRate(
        $oauth_token,
        $rateType = 'eRate',
        $currency = 'USD'
    ) {
        $params = array();
        $params['RateType'] = strtolower($rateType);
        $params['CurrencyCode'] = strtoupper($currency);
        ksort($params);

        $auth_query_string = self::arrayImplode('=', '&', $params);

        $uriSign = "GET:/general/rate/forex?$auth_query_string";
        $isoTime = self::generateIsoTime();
        $authSignature = self::generateSign($uriSign, $oauth_token, $this->settings['secret_key'], $isoTime, null);

        $headers = array();
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $this->settings['api_key'];
        $headers['X-BCA-Timestamp'] = $isoTime;
        $headers['X-BCA-Signature'] = $authSignature;

        $request_path = "general/rate/forex?$auth_query_string";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $data = array('grant_type' => 'client_credentials');
        $body = Body::form($data);
        $response = Request::get($full_url, $headers, $body);

        return $response;
    }

    /**
     * Transfer dana kepada akun lain dengan jumlah nominal tertentu.
     *
     * @param string $oauth_token nilai token yang telah didapatkan setelah login
     * @param int $amount nilai dana dalam RUPIAH yang akan ditransfer, Format: 13.2
     * @param string $beneficiaryAccountNumber BCA Account number to be credited (Destination)
     * @param string $referenceID Sender's transaction reference ID
     * @param string $remark1 Transfer remark for receiver
     * @param string $remark2 ransfer remark for receiver
     * @param string $sourceAccountNumber Source of Fund Account Number
     * @param string $transactionID Transcation ID unique per day (using UTC+07 Time Zone). Format: Number
     * @param string $currencyCode nilai MATA Uang [Optional]
     *
     * @return \Unirest\Response
     */
    public function fundTransfers(
        $oauth_token,
        $amount,
        $sourceAccountNumber,
        $beneficiaryAccountNumber,
        $referenceID,
        $remark1,
        $remark2,
        $transactionID,
        $currencyCode = 'idr'
    ) {
        $uriSign = "POST:/banking/corporates/transfers";

        $isoTime = self::generateIsoTime();

        $headers = array();
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $this->settings['api_key'];
        $headers['X-BCA-Timestamp'] = $isoTime;

        $request_path = "banking/corporates/transfers";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $bodyData = array();
        $bodyData['Amount'] = $amount;
        $bodyData['BeneficiaryAccountNumber'] = strtolower(str_replace(' ', '', $beneficiaryAccountNumber));
        $bodyData['CorporateID'] = strtolower(str_replace(' ', '', $this->settings['corp_id']));
        $bodyData['CurrencyCode'] = $currencyCode;
        $bodyData['ReferenceID'] = strtolower(str_replace(' ', '', $referenceID));
        $bodyData['Remark1'] = strtolower(str_replace(' ', '', $remark1));
        $bodyData['Remark2'] = strtolower(str_replace(' ', '', $remark2));
        $bodyData['SourceAccountNumber'] = strtolower(str_replace(' ', '', $sourceAccountNumber));
        $bodyData['TransactionDate'] = $isoTime;
        $bodyData['TransactionID'] = strtolower(str_replace(' ', '', $transactionID));

        // Harus disort agar mudah kalkulasi HMAC
        ksort($bodyData);

        $authSignature = self::generateSign($uriSign, $oauth_token, $this->settings['secret_key'], $isoTime, $bodyData);

        $headers['X-BCA-Signature'] = $authSignature;

        // Supaya jgn strip "ReferenceID" "/" jadi "/\" karena HMAC akan menjadi tidak cocok
        $encoderData = json_encode($bodyData, JSON_UNESCAPED_SLASHES);

        $body = Body::form($encoderData);
        $response = Request::post($full_url, $headers, $body);

        return $response;
    }

    /**
     * Transfer dana kepada akun lain dengan jumlah nominal tertentu.
     *
     * @param string $oauth_token nilai token yang telah didapatkan setelah login
     * @param int $amount nilai dana dalam RUPIAH yang akan ditransfer, Format: 13.2
     * @param string $beneficiaryAccountNumber  BCA Account number to be credited (Destination)
     * @param string $beneficiaryBankCode  Bank Code of account to be credited (destination)
     * @param string $beneficiaryName  Account name to be credited (destination)
     * @param string $beneficiaryCustType  1 = Personal 2 = Corporate 3 = Government
     * @param string $beneficiaryCustResidence  1 = Resident 2 = Non Resident
     * @param string $transferType LLG or RTG
     * @param string $referenceID Sender's transaction reference ID
     * @param string $remark1 Transfer remark for receiver
     * @param string $remark2 ransfer remark for receiver
     * @param string $sourceAccountNumber Source of Fund Account Number
     * @param string $transactionID Transcation ID unique per day (using UTC+07 Time Zone). Format: Number
     * @param string $corp_id nilai CorporateID yang telah diberikan oleh pihak BCA [Optional]
     *
     * @return \Unirest\Response
     */
    public function domesticTransfers(
        $oauth_token,
        $amount,
        $sourceAccountNumber,
        $beneficiaryAccountNumber,
        $beneficiaryBankCode,
        $beneficiaryCustResidence,
        $beneficiaryCustType,
        $beneficiaryName,
        $referenceID,
        $remark1,
        $remark2,
        $transactionID,
        $transferType
    ) {
        $corp_id = $this->settings['corp_id'];
        $apikey = $this->settings['api_key'];
        $secret = $this->settings['secret_key'];
        $uriSign = "POST:/banking/corporates/transfers/domestic";
        $isoTime = self::generateIsoTime();

        $headers = array();
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $apikey;
        $headers['X-BCA-Timestamp'] = $isoTime;

        $request_path = "banking/corporates/transfers/domestic";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $bodyData = array();
        $bodyData['BeneficiaryCustType'] = strtolower(str_replace(' ', '', $beneficiaryCustType));
        $bodyData['BeneficiaryBankCode'] = str_replace(' ', '', $beneficiaryBankCode);
        $bodyData['Amount'] = $amount;
        $bodyData['BeneficiaryName'] = str_replace(' ', '', $beneficiaryName);
        $bodyData['TransferType'] = str_replace(' ', '', $transferType);
        $bodyData['TransactionID'] = strtolower(str_replace(' ', '', $transactionID));
        $bodyData['CurrencyCode'] = 'IDR';
        $bodyData['SourceAccountNumber'] = strtolower(str_replace(' ', '', $sourceAccountNumber));
        $bodyData['BeneficiaryAccountNumber'] = strtolower(str_replace(' ', '', $beneficiaryAccountNumber));
        $bodyData['ReferenceID'] = strtolower(str_replace(' ', '', $referenceID));
        $bodyData['Remark2'] = strtolower(str_replace(' ', '', $remark2));
        $bodyData['Remark1'] = strtolower(str_replace(' ', '', $remark1));
        $bodyData['BeneficiaryCustResidence'] = strtolower(str_replace(' ', '', $beneficiaryCustResidence));
        $bodyData['TransactionDate'] = date('Y-m-d', strtotime('+0 days'));

        // Harus disort agar mudah kalkulasi HMAC
        ksort($bodyData);

        $authSignature = self::generateSign($uriSign, $oauth_token, $secret, $isoTime, $bodyData);

        $headers['X-BCA-Signature'] = $authSignature;
        $headers['ChannelID'] = '95051';
        $headers['CredentialID'] = str_replace(' ', '', $corp_id);

        \Unirest\Request::curlOpts(array(
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSLVERSION => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => $this->settings['timeout'] !== 30 ? $this->settings['timeout'] : 30,
        ));

        // Supaya jgn strip "ReferenceID" "/" jadi "/\" karena HMAC akan menjadi tidak cocok
        $encoderData = json_encode($bodyData, JSON_UNESCAPED_SLASHES);
        $body = \Unirest\Request\Body::form($encoderData);
        $response = \Unirest\Request::post($full_url, $headers, $body);

        return $response;
    }

    public function oneklikRegistration(
        $oauth_token,
        $device_id,
        $cust_id_merchant,
        $additional_info,
        $merchant_logo_url
    ) {
        $corp_id = $this->settings['corp_id'];
        $apikey = $this->settings['api_key'];
        $secret = $this->settings['secret_key'];
        $uriSign = "POST:/oneklik/registration";
        $isoTime = self::generateIsoTime();

        $headers = array();
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $apikey;
        $headers['X-BCA-Timestamp'] = $isoTime;

        $request_path = "oneklik/registration";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $bodyData = array();
        $bodyData['additional_info'] = strtolower(str_replace(' ', '', $additional_info));
        $bodyData['cust_id_merchant'] = str_replace(' ', '', $cust_id_merchant);
        $bodyData['device_id'] = $str_replace(' ', '', $device_id);
        $bodyData['merchant_logo_url'] = str_replace(' ', '', $merchant_logo_url);

        // Harus disort agar mudah kalkulasi HMAC
        ksort($bodyData);

        $authSignature = self::generateSign($uriSign, $oauth_token, $secret, $isoTime, $bodyData);

        $headers['X-BCA-Signature'] = $authSignature;
        $headers['ChannelID'] = '95221';
        $headers['CredentialID'] = str_replace(' ', '', $corp_id);

        \Unirest\Request::curlOpts(array(
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSLVERSION => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => $this->settings['timeout'] !== 30 ? $this->settings['timeout'] : 30,
        ));

        // Supaya jgn strip "ReferenceID" "/" jadi "/\" karena HMAC akan menjadi tidak cocok
        $encoderData = json_encode($bodyData, JSON_UNESCAPED_SLASHES);
        $body = \Unirest\Request\Body::form($encoderData);
        $response = \Unirest\Request::post($full_url, $headers, $body);

        return $response;
    }

    /**
     * Realtime deposit untuk produk BCA.
     *
     * @param string $oauth_token nilai token yang telah didapatkan setelah login
     *
     * @return \Unirest\Response
     */
    public function getDepositRate($oauth_token)
    {
        $uriSign = "GET:/general/rate/deposit";
        $isoTime = self::generateIsoTime();
        $authSignature = self::generateSign($uriSign, $oauth_token, $this->settings['secret_key'], $isoTime, null);

        $headers = array();
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $this->settings['api_key'];
        $headers['X-BCA-Timestamp'] = $isoTime;
        $headers['X-BCA-Signature'] = $authSignature;

        $request_path = "general/rate/deposit";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $data = array('grant_type' => 'client_credentials');

        $body = Body::form($data);
        $response = Request::get($full_url, $headers, $body);

        return $response;
    }

    /**
     * Generate Signature.
     *
     * @param string $url Url yang akan disign.
     * @param string $auth_token string nilai token dari login.
     * @param string $secret_key string secretkey yang telah diberikan oleh BCA.
     * @param string $isoTime string Waktu ISO8601.
     * @param array|mixed $bodyToHash array Body yang akan dikirimkan ke Server BCA.
     *
     * @return string
     */
    public static function generateSign($url, $auth_token, $secret_key, $isoTime, $bodyToHash = [])
    {
        $hash = hash("sha256", "");
        if (is_array($bodyToHash)) {
            ksort($bodyToHash);
            $encoderData = json_encode($bodyToHash, JSON_UNESCAPED_SLASHES);
            $hash = hash("sha256", $encoderData);
        }
        $stringToSign = $url . ":" . $auth_token . ":" . $hash . ":" . $isoTime;
        $auth_signature = hash_hmac('sha256', $stringToSign, $secret_key, false);

        return $auth_signature;
    }

    /**
     * Set TimeZone.
     *
     * @param string $timeZone Time yang akan dipergunakan.
     *
     * @return string
     */
    public static function setTimeZone($timeZone)
    {
        self::$timezone = $timeZone;
    }

    /**
     * Get TimeZone.
     *
     * @return string
     */
    public static function getTimeZone()
    {
        return self::$timezone;
    }

    /**
     * Set nama domain BCA yang akan dipergunakan.
     *
     * @param string $hostName nama domain BCA yang akan dipergunakan.
     *
     * @return string
     */
    public static function setHostName($hostName)
    {
        self::$hostName = $hostName;
    }

    /**
     * Ambil nama domain BCA yang akan dipergunakan.
     *
     * @return string
     */
    public static function getHostName()
    {
        return self::$hostName;
    }

    /**
     * Ambil maximum execution time.
     *
     * @return string
     */
    public static function getTimeOut()
    {
        return self::$timeOut;
    }

    /**
     * Ambil nama domain BCA yang akan dipergunakan.
     *
     * @return string
     */
    public static function getCurlOptions()
    {
        return self::$curlOptions;
    }

    /**
     * Setup curl options.
     *
     * @param array $curlOpts
     * @return array
     */
    public static function setCurlOptions(array $curlOpts = [])
    {
        $data = self::mergeCurlOptions(self::$curlOptions, $curlOpts);
        self::$curlOptions = $data;
    }

    /**
     * Set Ambil maximum execution time.
     *
     * @param int $timeOut timeout in milisecond.
     *
     * @return string
     */
    public static function setTimeOut($timeOut)
    {
        self::$timeOut = $timeOut;
        return self::$timeOut;
    }

    /**
     * Set BCA port
     *
     * @param int $port Port yang akan dipergunakan
     *
     * @return int
     */
    public static function setPort($port)
    {
        self::$port = $port;
    }

    /**
     * Get BCA port
     *
     * @return int
     */
    public static function getPort()
    {
        return self::$port;
    }

    /**
     * Set BCA Schema
     *
     * @param int $scheme Scheme yang akan dipergunakan
     *
     * @return string
     */
    public static function setScheme($scheme)
    {
        self::$scheme = $scheme;
    }

    /**
     * Get BCA Schema
     *
     * @return string
     */
    public static function getScheme()
    {
        return self::$scheme;
    }

    /**
     * Generate ISO8601 Time.
     *
     * @param string $timeZone Time yang akan dipergunakan
     *
     * @return string
     */
    public static function generateIsoTime()
    {
        $date = Carbon::now(self::getTimeZone());
        date_default_timezone_set(self::getTimeZone());
        $fmt = $date->format('Y-m-d\TH:i:s');
        $ISO8601 = sprintf("$fmt.%s%s", substr(microtime(), 2, 3), date('P'));

        return $ISO8601;
    }

    /**
     * Merge from existing array.
     *
     * @param array $existing_options
     * @param array $new_options
     * @return array
     */
    private static function mergeCurlOptions(&$existing_options, $new_options)
    {
        $existing_options = $new_options + $existing_options;
        return $existing_options;
    }

    /**
     * Validasi jika clientsecret telah di-definsikan.
     *
     * @param array $sourceAccountId
     *
     * @throws BcaHttpException Error jika array tidak memenuhi syarat
     * @return bool
     */
    private function validateArray($sourceAccountId = [])
    {
        if (!is_array($sourceAccountId)) {
            throw new BcaHttpException('Data harus array.');
        }
        if (empty($sourceAccountId)) {
            throw new BcaHttpException('AccountNumber tidak boleh kosong.');
        } else {
            $max = sizeof($sourceAccountId);
            if ($max > 20) {
                throw new BcaHttpException('Maksimal Account Number ' . 20);
            }
        }

        return true;
    }

    /**
     * Implode an array with the key and value pair giving
     * a glue, a separator between pairs and the array
     * to implode.
     *
     * @param string $glue The glue between key and value
     * @param string $separator Separator between pairs
     * @param array $array The array to implode
     *
     * @throws BcaHttpException error
     * @return string The imploded array
     */
    public static function arrayImplode($glue, $separator, $array = [])
    {
        if (!is_array($array)) {
            throw new BcaHttpException('Data harus array.');
        }
        if (empty($array)) {
            throw new BcaHttpException('parameter array tidak boleh kosong.');
        }
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $val = implode(',', $val);
            }
            $string[] = "{$key}{$glue}{$val}";
        }

        return implode($separator, $string);
    }

    /**
     * User Registration.
     * This service is used to register customer wallet.
     * @param string $oauth_token nilai token yang telah didapatkan setelah login
     *   Field Data Type Mandatory Description
     * @param $customerName String(80) Yes Customer Name
     * @param $dateOfBirth String(10) Yes Date of Birth (yyyy-MM-dd)
     * @param $primaryID String(80) Yes Unique ID per customer from merchant system (Email or Mobile number or Customer number)
     * @param $mobileNumber String(16) Yes Mobile number with country code or “0” prefixed, e.g. +6280000000000, 080000000000
     * @param $emailAddress String(80) No Email Address.
     * @param $customerNumber String(18) Yes Unique number generated by merchant for balance topup using BCA virtual account system
     * @param $idNumber String(16) Yes Customer identifier number (KTP)
     *
     * @return \Unirest\Response
     */
    public function userRegistration(
        $oauth_token,
        $customerName,
        $dateOfBirth,
        $primaryID,
        $mobileNumber,
        $emailAddress,
        $customerNumber,
        $idNumber
    ) {
        $companyCode = $this->settings['corp_id'];
        $apikey = $this->settings['api_key'];
        $secret = $this->settings['secret_key'];
        $uriSign = "POST:/ewallet/customers";

        $isoTime = self::generateIsoTime();
        $headers = array();
        //$headers['Accept']          = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $apikey;
        $headers['X-BCA-Timestamp'] = $isoTime;
        $request_path = "ewallet/customers";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;
        $bodyData = array();
        $bodyData['CustomerName'] = $customerName; //strtolower(str_replace(' ', '', $customerName));
        $bodyData['DateOfBirth'] = strtolower(str_replace(' ', '', $dateOfBirth));
        $bodyData['PrimaryID'] = strtolower(str_replace(' ', '', $primaryID));
        $bodyData['MobileNumber'] = strtolower(str_replace(' ', '', $mobileNumber));
        $bodyData['EmailAddress'] = strtolower(str_replace(' ', '', $emailAddress));
        $bodyData['CompanyCode'] = strtolower(str_replace(' ', '', $companyCode));
        $bodyData['CustomerNumber'] = strtolower(str_replace(' ', '', $customerNumber));
        $bodyData['IDNumber'] = strtolower(str_replace(' ', '', $idNumber));
        // Harus disort agar mudah kalkulasi HMAC
        ksort($bodyData);
        $authSignature = self::generateSign($uriSign, $oauth_token, $secret, $isoTime, $bodyData);
        $headers['X-BCA-Signature'] = $authSignature;
        \Unirest\Request::curlOpts(array(
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSLVERSION => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => $this->settings['timeout'] !== 30 ? $this->settings['timeout'] : 30,
        ));
        // Supaya jgn strip "ReferenceID" "/" jadi "/\" karena HMAC akan menjadi tidak cocok
        $encoderData = json_encode($bodyData, JSON_UNESCAPED_SLASHES);
        $body = \Unirest\Request\Body::form($encoderData);
        $response = \Unirest\Request::post($full_url, $headers, $body);
        return $response;
    }

    /**
     * User Inquiry
     * This service is used to show customer data and the amount of its balance.
     * @param string $oauth_token nilai token yang telah didapatkan setelah login
     * @param array $primary_id Unique ID per customer from merchant system (Email or Mobile number or Customer number)
     *
     * @return \Unirest\Response
     *   Field Data Type Description
     *   PrimaryID String(80) Unique ID per Customer
     *   CustomerNumber String(80) Unique number generated by merchant for balance topup using BCA virtual account system
     *   CustomerName String(80) Customer Name
     *   MobileNumber String(80) Mobile number with country code or “0” prefixed, e.g. +6280000000000, 080000000000
     *   EmailAddress  String(80) Email Address
     *   DateOfBirth String(10) Date of Birth (yyyy-MM-dd)
     *   CurrencyCode String(3) Currency code, e.g. IDR
     *   Balance String(16) eWallet balance (Number, format:13.2). Maximum balance is decided by Business
     *   IDNumber  String(16) Customer identifier number (KTP)
     */
    public function userInquiry($oauth_token, $primary_id)
    {
        $corp_id = $this->settings['corp_id'];
        $apikey = $this->settings['api_key'];
        $secret = $this->settings['secret_key'];

        $uriSign = "GET:/ewallet/customers/$corp_id/$primary_id";
        $isoTime = self::generateIsoTime();
        $authSignature = self::generateSign($uriSign, $oauth_token, $secret, $isoTime, "");
        $headers = array();
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $apikey;
        $headers['X-BCA-Timestamp'] = $isoTime;
        $headers['X-BCA-Signature'] = $authSignature;
        $request_path = "ewallet/customers/$corp_id/$primary_id";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $data = array('grant_type' => 'client_credentials');
        \Unirest\Request::curlOpts(array(
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSLVERSION => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => $this->settings['timeout'] !== 30 ? $this->settings['timeout'] : 30,
        ));
        $body = \Unirest\Request\Body::form($data);
        $response = \Unirest\Request::get($full_url, $headers, $body);
        return $response;
    }

    /**
     * User Update.
     * This service is used to register customer wallet.
     * @param string $oauth_token nilai token yang telah didapatkan setelah login
     *   Field Data Type Mandatory Description
     * @param $primaryID String(80) Yes Unique ID per customer from merchant system (Email or Mobile number or Customer number)
     * @param $customerName String(80) No Customer Name
     * @param $dateOfBirth String(10) No Date of Birth (yyyy-MM-dd)
     * @param $mobileNumber String(16) No Mobile number with country code or “0” prefixed, e.g. +6280000000000, 080000000000
     * @param $emailAddress String(80) No Email Address.
     * @param $walletStatus String(18) No Status wallet (ACTIVE / BLOCKED / INACTIVE)
     * @param $idNumber String(16) Yes Customer identifier number (KTP)
     *
     * @return \Unirest\Response
     */
    public function userUpdate(
        $oauth_token,
        $primaryID,
        $updates_,
        $idNumber
    ) {
        $defaults = array(
            'customerName' => '',
            'dateOfBirth' => '',
            'mobileNumber' => '',
            'emailAddress' => '',
            'walletStatus' => '',
        );
        $param = array_merge($defaults, $updates_);
        $companyCode = $this->settings['corp_id'];
        $apikey = $this->settings['api_key'];
        $secret = $this->settings['secret_key'];
        $uriSign = "PUT:/ewallet/customers/" . $companyCode . "/" . $primaryID;

        $isoTime = self::generateIsoTime();
        $headers = array();
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $apikey;
        $headers['X-BCA-Timestamp'] = $isoTime;

        $request_path = "ewallet/customers/" . $companyCode . "/" . $primaryID;
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;
        $bodyData = array();

        if ($param['customerName'] != '') {$bodyData['CustomerName'] = $param['customerName'];}
        if ($param['dateOfBirth'] != '') {$bodyData['DateOfBirth'] = $param['dateOfBirth'];}
        if ($param['mobileNumber'] != '') {$bodyData['MobileNumber'] = $param['mobileNumber'];}
        if ($param['emailAddress'] != '') {$bodyData['EmailAddress'] = $param['emailAddress'];}
        if ($param['walletStatus'] != '') {$bodyData['WalletStatus'] = $param['walletStatus'];}

        $bodyData['IDNumber'] = strtolower(str_replace(' ', '', $idNumber));

        // Harus disort agar mudah kalkulasi HMAC
        ksort($bodyData);

        $authSignature = self::generateSign($uriSign, $oauth_token, $secret, $isoTime, $bodyData);
        $headers['X-BCA-Signature'] = $authSignature;

        \Unirest\Request::curlOpts(array(
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSLVERSION => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => $this->settings['timeout'] !== 30 ? $this->settings['timeout'] : 30,
        ));

        // Supaya jgn strip "ReferenceID" "/" jadi "/\" karena HMAC akan menjadi tidak cocok
        $encoderData = json_encode($bodyData, JSON_UNESCAPED_SLASHES);

        $body = \Unirest\Request\Body::form($encoderData);
        $response = \Unirest\Request::put($full_url, $headers, $body);

        return $response;
    }

    /**
     * TopUp.
     * This service is used to add funds to user e-wallet. Through the instruction of the e-wallet issuer.
     * @param string $oauth_token nilai token yang telah didapatkan setelah login
     *   Field Data Type Mandatory Description
     * @param $primaryID String(80) Yes Unique ID per customer from merchant system (Email or Mobile number or Customer number)
     * @param $transactionID String(18) Yes Transaction ID generated by merchant
     * @param $requestDate String(29) Yes Request Date of transaction (yyyyMM-ddTHH:mm:ss.sssZ)
     * @param $amount String(16) Yes Amount of transaction (Number, format:13.2)
     * @param $currencyCode String(3) Currency (IDR) ifier number (KTP)
     *
     * @return \Unirest\Response
     */
    public function topUp(
        $oauth_token,
        $primaryID,
        $transactionID,
        $requestDate,
        $amount,
        $currencyCode
    ) {
        $companyCode = $this->settings['corp_id'];
        $apikey = $this->settings['api_key'];
        $secret = $this->settings['secret_key'];
        $uriSign = "POST:/ewallet/topup";

        $isoTime = self::generateIsoTime();
        $headers = array();
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $apikey;
        $headers['X-BCA-Timestamp'] = $isoTime;

        $request_path = "ewallet/topup";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;
        $bodyData = array();

        $bodyData['PrimaryID'] = strtolower(str_replace(' ', '', $primaryID));
        $bodyData['TransactionID'] = str_replace(' ', '', $transactionID);
        $bodyData['RequestDate'] = str_replace(' ', '', $requestDate);
        $bodyData['CompanyCode'] = strtolower(str_replace(' ', '', $companyCode));
        $bodyData['Amount'] = strtolower(str_replace(' ', '', $amount));
        $bodyData['CurrencyCode'] = str_replace(' ', '', $currencyCode);

        // Harus disort agar mudah kalkulasi HMAC
        ksort($bodyData);

        $authSignature = self::generateSign($uriSign, $oauth_token, $secret, $isoTime, $bodyData);
        $headers['X-BCA-Signature'] = $authSignature;

        \Unirest\Request::curlOpts(array(
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSLVERSION => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => $this->settings['timeout'] !== 30 ? $this->settings['timeout'] : 30,
        ));

        // Supaya jgn strip "ReferenceID" "/" jadi "/\" karena HMAC akan menjadi tidak cocok
        $encoderData = json_encode($bodyData, JSON_UNESCAPED_SLASHES);

        $body = \Unirest\Request\Body::form($encoderData);
        $response = \Unirest\Request::post($full_url, $headers, $body);

        return $response;
    }

    /**
     * Topup Inquiry
     * This service is used to show customer data and the amount of its balance.
     * @param string $oauth_token nilai token yang telah didapatkan setelah login
     * @param array $primary_id Unique ID per customer from merchant system (Email or Mobile number or Customer number)
     *
     * @return \Unirest\Response
     *   Field Data Type Description
     *   PrimaryID String(80) Unique ID per Customer
     * @param $transactionID String(18) Yes Transaction ID generated by merchant
     * @param $requestDate String(29) Yes Request Date of transaction (yyyyMM-ddTHH:mm:ss.sssZ)
     */
    public function topUpInquiry(
        $oauth_token,
        $primaryID,
        $transactionID,
        $requestDate
    ) {
        $corp_id = $this->settings['corp_id'];
        $apikey = $this->settings['api_key'];
        $secret = $this->settings['secret_key'];

        $uriSign = "GET:/ewallet/topup/$corp_id/$primaryID?RequestDate=$requestDate&TransactionID=$transactionID";

        $isoTime = self::generateIsoTime();
        $authSignature = self::generateSign($uriSign, $oauth_token, $secret, $isoTime, "");
        $headers = array();
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $apikey;
        $headers['X-BCA-Timestamp'] = $isoTime;
        $headers['X-BCA-Signature'] = $authSignature;
        $request_path = "ewallet/topup/$corp_id/$primaryID?RequestDate=$requestDate&TransactionID=$transactionID";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $data = array('grant_type' => 'client_credentials');
        \Unirest\Request::curlOpts(array(
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSLVERSION => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => $this->settings['timeout'] !== 30 ? $this->settings['timeout'] : 30,
        ));
        $body = \Unirest\Request\Body::form($data);
        $response = \Unirest\Request::get($full_url, $headers, $body);
        return $response;
    }

    /**
     * Transactions Inquiry
     * This service is used to get the transaction list of a e-wallet user until 31 days behind. The maximum total of transactions returned is 10 .
     * @param string $oauth_token nilai token yang telah didapatkan setelah login
     * @param array $primary_id Unique ID per customer from merchant system (Email or Mobile number or Customer number)
     *
     * @return \Unirest\Response
     *   Field Data Type Description
     *   PrimaryID String(80) Unique ID per Customer
     * @param $startDate String(10) Yes Start Date of the transaction that you wants to get (yyyyMM-dd)
     * @param $endDate String(10) Yes End Date of the transaction that you wants to get, format (yyyy-MM-dd)
     * @param $lastAccountStatementID  String(18) No Last AccountStatement ID provided to get the older transaction history than ‘this’ Transaction ID
     * (every request will display 10 transaction). If you do request for the first time, ignore this field
     */
    public function transactionsInquiry(
        $oauth_token,
        $primaryID,
        $startDate,
        $endDate,
        $lastAccountStatementID = ""
    ) {
        $corp_id = $this->settings['corp_id'];
        $apikey = $this->settings['api_key'];
        $secret = $this->settings['secret_key'];

        if ($lastAccountStatementID == "") {
            $uriSign = "GET:/ewallet/transactions/$corp_id/$primaryID?EndDate=$endDate&StartDate=$startDate";
        } else {
            $uriSign = "GET:/ewallet/transactions/$corp_id/$primaryID?EndDate=$endDate&LastAccountStatementID=$lastAccountStatementID&StartDate=$startDate";
        }

        $isoTime = self::generateIsoTime();
        $authSignature = self::generateSign($uriSign, $oauth_token, $secret, $isoTime, "");
        $headers = array();
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $apikey;
        $headers['X-BCA-Timestamp'] = $isoTime;
        $headers['X-BCA-Signature'] = $authSignature;

        $request_path = substr($uriSign, 5);
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $data = array('grant_type' => 'client_credentials');
        \Unirest\Request::curlOpts(array(
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSLVERSION => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => $this->settings['timeout'] !== 30 ? $this->settings['timeout'] : 30,
        ));

        $body = \Unirest\Request\Body::form($data);
        $response = \Unirest\Request::get($full_url, $headers, $body);

        return $response;
    }

    /**
     * Transfer Company Account.
     * This service is used to transfer from SAKUKU COBRANDING to company account.
     * @param string $oauth_token nilai token yang telah didapatkan setelah login
     *   Field Data Type Mandatory Description
     * @param $primaryID String(80) Yes Unique ID per customer from merchant system (Email or Mobile number or Customer number)
     * @param $transactionID String(18) Yes Transaction ID generated by merchant
     * @param $requestDate String(29) Yes Request Date of transaction (yyyyMM-ddTHH:mm:ss.sssZ)
     * @param $amount String(16) Yes Amount of transaction (Number, format:13.2)
     * @param $currencyCode String(3) Currency (IDR) ifier number (KTP)
     *
     * @return \Unirest\Response
     */
    public function transfers(
        $oauth_token,
        $primaryID,
        $transactionID,
        $requestDate,
        $amount,
        $currencyCode
    ) {
        $companyCode = $this->settings['corp_id'];
        $apikey = $this->settings['api_key'];
        $secret = $this->settings['secret_key'];
        $uriSign = "POST:/ewallet/transfers";

        $isoTime = self::generateIsoTime();
        $headers = array();
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $apikey;
        $headers['X-BCA-Timestamp'] = $isoTime;
        $request_path = "ewallet/transfers";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;
        $bodyData = array();
        $bodyData['PrimaryID'] = strtolower(str_replace(' ', '', $primaryID));
        $bodyData['TransactionID'] = str_replace(' ', '', $transactionID);
        $bodyData['RequestDate'] = str_replace(' ', '', $requestDate);
        $bodyData['CompanyCode'] = strtolower(str_replace(' ', '', $companyCode));
        $bodyData['Amount'] = strtolower(str_replace(' ', '', $amount));
        $bodyData['CurrencyCode'] = str_replace(' ', '', $currencyCode);

        // Harus disort agar mudah kalkulasi HMAC
        ksort($bodyData);

        $authSignature = self::generateSign($uriSign, $oauth_token, $secret, $isoTime, $bodyData);
        $headers['X-BCA-Signature'] = $authSignature;
        \Unirest\Request::curlOpts(array(
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSLVERSION => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => $this->settings['timeout'] !== 30 ? $this->settings['timeout'] : 30,
        ));

        // Supaya jgn strip "ReferenceID" "/" jadi "/\" karena HMAC akan menjadi tidak cocok
        $encoderData = json_encode($bodyData, JSON_UNESCAPED_SLASHES);

        $body = \Unirest\Request\Body::form($encoderData);
        $response = \Unirest\Request::post($full_url, $headers, $body);
        return $response;
    }

    /**
     * Transfers Inquiry
     * This service is used to inquiry transfer company account.
     * @param string $oauth_token nilai token yang telah didapatkan setelah login
     * @param array $primary_id Unique ID per customer from merchant system (Email or Mobile number or Customer number)
     *
     * @return \Unirest\Response
     *   Field Data Type Description
     *   PrimaryID String(80) Unique ID per Customer
     * @param $transactionID String(18) Yes Transaction ID generated by merchant
     * @param $requestDate String(29) Yes Date of the transaction (yyyy-MMdd)
     */
    public function transfersInquiry($oauth_token,
        $primaryID,
        $transactionID,
        $requestDate) {
        $corp_id = $this->settings['corp_id'];
        $apikey = $this->settings['api_key'];
        $secret = $this->settings['secret_key'];

        $uriSign = "GET:/ewallet/transfers/$corp_id/$primaryID?RequestDate=$requestDate&TransactionID=$transactionID";

        $isoTime = self::generateIsoTime();
        $authSignature = self::generateSign($uriSign, $oauth_token, $secret, $isoTime, "");
        $headers = array();
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $apikey;
        $headers['X-BCA-Timestamp'] = $isoTime;
        $headers['X-BCA-Signature'] = $authSignature;
        $request_path = "ewallet/transfers/$corp_id/$primaryID?RequestDate=$requestDate&TransactionID=$transactionID";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $data = array('grant_type' => 'client_credentials');
        \Unirest\Request::curlOpts(array(
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSLVERSION => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => $this->settings['timeout'] !== 30 ? $this->settings['timeout'] : 30,
        ));
        $body = \Unirest\Request\Body::form($data);
        $response = \Unirest\Request::get($full_url, $headers, $body);
        return $response;
    }
}
