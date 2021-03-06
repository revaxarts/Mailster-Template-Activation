<?php


/**
 * Exception handling class.
 */
class EnvatoException extends Exception {}


class envato_api_basic{

	private static $instance = null;
	public static function getInstance () {
        if (is_null(self::$instance)) { self::$instance = new self(); }
        return self::$instance;
    }

	private $_api_url = 'https://api.envato.com/';

	private $_client_id = false;
	private $_client_secret = false;
	private $_personal_token = false;
	private $_redirect_url = false;
	private $_cookie = false;

	public $curl_info = null;

	public $token = false; // token returned from oauth

	public function set_client_id($token){
		$this->_client_id = $token;
	}
	public function set_client_secret($token){
		$this->_client_secret = $token;
	}
	public function set_personal_token($token){
		$this->_personal_token = $token;
	}
	public function set_redirect_url($token){
		$this->_redirect_url = $token;
	}
	public function set_cookie($cookie){
		$this->_cookie = $cookie;
	}
	public function get($endpoint, $params=array(), $personal = true){
		$headers = array();
		if($personal && !empty($this->_personal_token)){
			$headers['headers'] = array(
		        'Authorization: Bearer ' . $this->_personal_token,
		    );
		}else if(!empty($this->token['access_token'])){
			$headers['headers'] = array(
		        'Authorization: Bearer ' . $this->token['access_token'],
		    );
		}
		$response = $this->get_url($this->_api_url . $endpoint, false, $headers['headers'], false);
	    $body = @json_decode($response,true);
		return $body;

		return false;
	}

    public function get_token_account(){
        $url = 'https://account.envato.com/account/edit';
        $response = $this->get_url($url);
        if($response && preg_match_all('#<input[^>]+name="user\[([^\]]*)\]"[^>]+value="([^"]*)"[^>]*>#imsU',$response,$matches)){
            if(count($matches[1]) == 4){
                // first_name last_name email username
                $data = array();
                foreach($matches[1] as $key=>$val){
                    $data[$val] = $matches[2][$key];
                }
                if(!empty($data['username'])){
                    // grab image from API
                    $api_result = $this->api('v1/market/user:'.$data['username'].'.json');
                    if(!empty($api_result['user']['image'])){
                        $data['image'] = $api_result['user']['image'];
                    }
                }
                return $data;
            }
        }
        return false;
    }

	private $ch = false;
	private $cookies = array();
	private $cookie_file = false;
	public function curl_init($cookies = true) {
		if ( ! function_exists( 'curl_init' ) ) {
			echo 'Please contact hosting provider and enable CURL for PHP';

			return false;
		}
		$this->ch = curl_init();
		curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, true );
		@curl_setopt( $this->ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $this->ch, CURLOPT_CONNECTTIMEOUT, 10 );
		curl_setopt( $this->ch, CURLOPT_TIMEOUT, 20 );
		curl_setopt( $this->ch, CURLOPT_HEADER, false );
		curl_setopt( $this->ch, CURLOPT_USERAGENT, "30% fee for support packages? are you kidding" );
		if ( $cookies ) {
			if ( ! $this->cookie_file ) {
				$this->cookie_file = tempnam( sys_get_temp_dir(), 'SupportHub' );
			}
			curl_setopt( $this->ch, CURLOPT_COOKIEJAR, $this->cookie_file );
			curl_setopt( $this->ch, CURLOPT_COOKIEFILE, $this->cookie_file );
			curl_setopt( $this->ch, CURLOPT_HEADERFUNCTION, array( $this, "curl_header_callback" ) );
		}
	}
	public function curl_done(){
		@unlink($this->cookie_file);
	}
	public function get_url($url, $post = false, $extra_headers = array(), $cookies = true) {

		if($this->ch){
			curl_close($this->ch);
		}
		$this->curl_init($cookies);

		if($cookies) {
			$cookies                        = '';
			$this->cookies['envatosession'] = $this->_cookie;
			foreach ( $this->cookies as $key => $val ) {
				if ( ! strpos( $url, 'account.envato' ) && $key == 'envatosession' ) {
					continue;
				}
				$cookies = $cookies . $key . '=' . $val . '; ';
			}
			curl_setopt( $this->ch, CURLOPT_COOKIE, $cookies );
		}
		curl_setopt( $this->ch, CURLOPT_URL, $url );
		if($extra_headers){
			curl_setopt( $this->ch, CURLOPT_HTTPHEADER, $extra_headers);
		}

		if ( is_string( $post ) && strlen( $post ) ) {
			curl_setopt( $this->ch, CURLOPT_POST, true );
			curl_setopt( $this->ch, CURLOPT_POSTFIELDS, $post );
		}else if ( is_array( $post ) && count( $post ) ) {
			curl_setopt( $this->ch, CURLOPT_POST, true );
			curl_setopt( $this->ch, CURLOPT_POSTFIELDS, $post );
		} else {
			curl_setopt( $this->ch, CURLOPT_POST, 0 );
		}
		$return = curl_exec( $this->ch );

		$this->curl_info = curl_getinfo( $this->ch );

		return $return;

	}

	public function curl_header_callback($ch, $headerLine) {
		//echo $headerLine."\n";
	    if (preg_match('/^Set-Cookie:\s*([^;]*)/mi', $headerLine, $cookie) == 1){
		    $bits = explode('=',$cookie[1]);
		    $this->cookies[$bits[0]] = $bits[1];
	    }
	    return strlen($headerLine); // Needed by curl
	}

	/**
	 * OAUTH STUFF
	 */

	public function get_authorization_url() {
	    return 'https://api.envato.com/authorization?response_type=code&client_id='.$this->_client_id."&redirect_uri=".urlencode($this->_redirect_url);
	  }
	public function get_token_url() {
		return 'https://api.envato.com/token';
    }
	public function get_authentication($code) {
		$url = $this->get_token_url();
		$parameters = array();
		$parameters['grant_type']    = "authorization_code";
		$parameters['code']          = $code;
		$parameters['redirect_uri']  = $this->_redirect_url;
		$parameters['client_id']     = $this->_client_id;
		$parameters['client_secret'] = $this->_client_secret;
		$fields_string = '';
		foreach ( $parameters as $key => $value ) {
			$fields_string .= $key . '=' . urlencode($value) . '&';
		}
		try {
			$response = $this->get_url($url, $fields_string, false, false);
		} catch ( EnvatoException $e ) {
			echo 'OAuth API Fail', $e->__toString();
			return false;
		}
		$this->token = json_decode( $response, true );
		return $this->token;
	}
    public function set_manual_token($token){
        $this->token = $token;
    }
	public function refresh_token(){
	    $url = $this->get_token_url();

	    $parameters = array();
	    $parameters['grant_type'] = "refresh_token";

	    $parameters['refresh_token']  = $this->token['refresh_token'];
	    $parameters['redirect_uri']   = $this->_redirect_url;
	    $parameters['client_id']      = $this->_client_id;
	    $parameters['client_secret']  = $this->_client_secret;

		$fields_string = '';
		foreach ( $parameters as $key => $value ) {
			$fields_string .= $key . '=' . urlencode($value) . '&';
		}
	    try {
	      $response = $this->get_url($url, $fields_string, false, false);
	    }
	    catch (EnvatoException $e) {
	      echo 'OAuth API Fail', $e->__toString();
	      return false;
	    }
	    $new_token = json_decode($response, true);
	    $this->token['access_token'] = $new_token['access_token'];
		return $this->token['access_token'];
	  }



}
