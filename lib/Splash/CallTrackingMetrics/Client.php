<?php
namespace Splash\CallTrackingMetrics;

class Client {
    public $domain = "https://api.calltrackingmetrics.com/api/v1";

    /**
     * Curl handle
     *
     * @var resource
     */
    protected $curl;

    /**
     * @var array
     */
    protected $login;

    /**
     * @var array
     */
    protected $auth;

    /**
     * @var bool
     */
    protected $authFailed = false;

    /**
     * @param string $login
     * @param string $password
     * @param null|resource $curl
     */
    public function __construct($login, $password, $curl = null) {
        $this->setLogin($login, $password);
        if ($curl) $this->setCurl($curl);
    }

    /**
     * Fires an authentication request using stored login credentials and stores the resulting token
     *
     * @throws Exception
     */
    public function authenticate() {
        $resp = $this->_request("authentication", $this->getLogin(), 'POST');

        if (isset($resp['success']) && $resp['success']) {
            $this->setAuth($resp);
        } else {
            $this->authFailed = true;
            throw new Exception(sprintf("Invalid CallTrackingMetrics authentication credentials: %s", isset($resp['message']) ? $resp['message'] : 'No reason given...'));
        }
    }

    /**
     * @param string $uri URI relative to /api/v1/<URI>
     * @param array $payload Payload values to passed in through GET or POST parameters
     * @param string $method HTTP method for request (GET, PUT, POST, ...)
     * @param bool $requiresAuth This method requires authentication
     * @return array JSON-decoded response
     * @throws Exception
     */
    public function api($uri, array $payload = array(), $method = 'GET', $requiresAuth = true) {
        if ($requiresAuth && !$this->isAuthenticated() && !$this->authFailed) {
            $this->authenticate();

            $payload['auth_token'] = $this->getAuthToken();
        }

        return $this->_request("$uri.json", $payload, $method);
    }

    /**
     * @param int $account_id account id to be included in URL
     * @param string $uri URI relative to /api/v1/accounts/:account_id/<URI>
     * @param array $payload Payload values to passed in through GET or POST parameters
     * @param string $method HTTP method for request (GET, PUT, POST, ...)
     * @param bool $requiresAuth This method requires authentication
     * @return array JSON-decoded response
     * @throws Exception
     */
    public function account($account_id, $uri, array $payload = array(), $method = 'GET', $requiresAuth = true) {
        return $this->api("accounts/$account_id/$uri.json", $payload, $method);
    }

    /**
     * @param string $uri URI relative to /api/v1/<URI>
     * @param array $payload Payload values to passed in through GET or POST parameters
     * @param string $method HTTP method for request (GET, PUT, POST, ...)
     * @return array JSON-decoded response
     * @throws Exception
     */
    public function _request($uri, array $payload = array(), $method = 'GET') {
        $ch = $this->getCurl();
        $url = "{$this->domain}/$uri";

        if (!empty($payload) && $method == 'GET') {
            $url .= "?" . http_build_query($payload);
        }

        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_HEADER => 0
        ));

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
                break;
            case 'GET':
                break;
            default:
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        }

        $body = curl_exec($ch);

        var_dump($url);
        var_dump($body);

        $errno = curl_errno($ch);
        if ($errno !== 0) {
            throw new Exception(sprintf("Error connecting to CallTrackingMetrics: [%s] %s", $errno, curl_error($ch)), $errno);
        }

        return json_decode($body, true);
    }

    /**
     * @param array $auth
     */
    protected function setAuth(array $auth) {
        $this->auth = $auth;
    }

    /**
     * @return array
     */
    public function getAuth() {
        return $this->auth;
    }

    /**
     * @return string|false
     */
    public function getAuthToken() {
        return isset($this->auth['token']) ? $this->auth['token'] : false;
    }

    /**
     * @return bool
     * @throws AuthException
     */
    public function isAuthenticated() {
        if (!$this->getAuth()) return false;
        if ($this->isAuthenticationExpired()) return true;

        return true;
    }

    /**
     * @return bool
     * @throws AuthException
     */
    public function isAuthenticationExpired() {
        $auth = $this->getAuth();

        if (!isset($auth['expires'])) throw new AuthException("Malformed CallTrackingMetrics authentication information");

        $now = new \DateTime();
        $expires = new \DateTime($auth['expires']);

        return $now >= $expires;
    }

    /**
     * Clears authentication tokens and status, effectively resets for an authentication() call
     */
    public function clearAuth() {
        $this->auth = null;
        $this->authFailed = false;
    }

    /**
     * @param string $login
     * @param string $password
     */
    public function setLogin($login, $password) {
        $this->login = array('user' => array('login' => $login, 'password' => $password));
        $this->clearAuth();
    }

    /**
     * @return array
     */
    public function getLogin() {
        return $this->login;
    }

    /**
     * @param resource $curl
     */
    public function setCurl($curl) {
        $this->curl = $curl;
    }

    /**
     * @return resource
     */
    public function getCurl() {
        if (!is_resource($this->curl)) {
            $this->curl = curl_init();

            curl_setopt_array($this->curl, array(
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS      => 1,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 30,
            ));
        }

        return $this->curl;
    }
}