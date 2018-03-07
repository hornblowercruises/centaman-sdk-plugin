<?php
namespace Zao\ZCSDK\Services;

abstract class API_Request {
	protected $endpoint;
	protected $args;
	protected $logging;
	protected $username;
	protected $password;
	protected $response;
	protected $request;
	protected $code;
	protected static $last_instance = null;

	public function __construct() {
		self::$last_instance = $this;
		$this->username      = CENTAMAN_API_USERNAME;
		$this->password      = CENTAMAN_API_PASSWORD;
		$this->endpoint      = CENTAMAN_API_URL;
		$this->logging       = false;
		$this->args          = array();
		$this->init();
	}

	abstract protected function init();

	protected function set_endpoint( $endpoint ) {
		$this->endpoint .= ltrim( $endpoint, '/' );
		$this->endpoint  = trailingslashit( $this->endpoint );

		return $this;
	}

	protected function set_query_args( $args ) {
		$this->endpoint = add_query_arg( $args, $this->endpoint );
		return $this;
	}

	protected function set_args( Array $args ) {
		$this->args = $args;
		return $this;
	}

	public function dispatch( $method ) {

		$url  = $this->get_endpoint();

		$args = array_merge( $this->args, $this->get_default_args() );

		add_filter( 'https_ssl_verify', '__return_false' );

		switch ( $method ) :
			case 'PUT' :
				$request = wp_remote_request( $url, array_merge( $args, array( 'method' => 'PUT' ) ) );
				break;
			case 'POST' :
				$request = wp_remote_post( $url, $args );
				break;
			default :
			case 'GET' :
				$request = wp_remote_get( $url, $args );
		endswitch;

		remove_filter( 'https_ssl_verify', '__return_false' );

		$this->request  = $request;
		$this->response = wp_remote_retrieve_body( $request );
		$this->code     = wp_remote_retrieve_response_code( $request );

		if ( $this->logging ) {
			$this->log_entry();
		}

		return $this;
	}

	public function log_entry() {
		$entry = array(
			'request'  => $this->request,
			'response' => $this->response,
			'code'     => $this->code,
		);

		error_log( var_export( $entry, 1 ), 3, '/var/log/ecommerce-errors.log' );
	}

	public function set_logging( bool $logging = false ) {
		$this->logging = $logging;
		return $this;
	}

	public function get_request() {
		return $this->request;
	}

	public function get_raw_response() {
		return $this->response;
	}

	public function get_response() {
		switch ( $this->get_code_level() ) {
			case 4:
			case 5:
				$msg = sprintf( __( 'The request resulted in a %s.', 'zao-centaman' ), $this->code );
				if ( ! empty( $this->response ) && is_string( $this->response ) ) {
					$msg = $this->response;
					$json = json_decode( $msg );
					if ( $json && ! empty( $json->Message ) ) {
						$msg = $json->Message;
					}
				}
				return new \WP_Error( 'centaman_' . $this->code, $msg, $this );
		}

		$response = json_decode( $this->response );

		if ( null === $response && JSON_ERROR_NONE !== json_last_error() ) {
			if ( is_wp_error( $this->request ) ) {
				return $this->request;
			}

			return new \WP_Error(
				'centaman_json_error',
				sprintf(
					__( 'The response was not valid JSON. The json error: %s.', 'zao-centaman' ),
					self::get_json_error_message( json_last_error() )
				),
				$this
			);
		}

		return $response;
	}

	public function get_code() {
		return $this->code;
	}

	public function get_code_level() {
		$code = (string) $this->code;
		return isset( $code[0] ) ? $code[0] : 0;;
	}

	public function get_endpoint() {
		return $this->endpoint;
	}

	private function get_default_args() {
		return array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->username . ':' . $this->password ),
				'Content-Type'  => 'application/json'
			)
		);
	}

	/**
	 * Get human-friendly JSON error description.
	 *
	 * @see http://php.net/manual/en/function.json-last-error.php#refsect1-function.json-last-error-returnvalues
	 *
	 * @param  mixed  $json_error_id The json_last_error() value.
	 *
	 * @return string                The error description.
	 */
	public static function get_json_error_message( $json_error_id ) {
		$errors = array();
		if ( defined( 'JSON_ERROR_NONE' ) ) {
			$errors[JSON_ERROR_NONE] = __( 'No error has occurred', 'zao-centaman' );
		}
		if ( defined( 'JSON_ERROR_DEPTH' ) ) {
			$errors[JSON_ERROR_DEPTH] = __( 'The maximum stack depth has been exceeded', 'zao-centaman' );
		}
		if ( defined( 'JSON_ERROR_STATE_MISMATCH' ) ) {
			$errors[JSON_ERROR_STATE_MISMATCH] = __( 'Invalid or malformed JSON', 'zao-centaman' );
		}
		if ( defined( 'JSON_ERROR_CTRL_CHAR' ) ) {
			$errors[JSON_ERROR_CTRL_CHAR] = __( 'Control character error, possibly incorrectly encoded', 'zao-centaman' );
		}
		if ( defined( 'JSON_ERROR_SYNTAX' ) ) {
			$errors[JSON_ERROR_SYNTAX] = __( 'Syntax error', 'zao-centaman' );
		}
		if ( defined( 'JSON_ERROR_UTF8' ) ) {
			$errors[JSON_ERROR_UTF8] = __( 'Malformed UTF-8 characters, possibly incorrectly encoded', 'zao-centaman' );
		}
		if ( defined( 'JSON_ERROR_RECURSION' ) ) {
			$errors[JSON_ERROR_RECURSION] = __( 'One or more recursive references in the value to be encoded', 'zao-centaman' );
		}
		if ( defined( 'JSON_ERROR_INF_OR_NAN' ) ) {
			$errors[JSON_ERROR_INF_OR_NAN] = __( 'One or more NAN or INF values in the value to be encoded', 'zao-centaman' );
		}
		if ( defined( 'JSON_ERROR_UNSUPPORTED_TYPE' ) ) {
			$errors[JSON_ERROR_UNSUPPORTED_TYPE] = __( 'A value of a type that cannot be encoded was given', 'zao-centaman' );
		}
		if ( defined( 'JSON_ERROR_INVALID_PROPERTY_NAME' ) ) {
			$errors[JSON_ERROR_INVALID_PROPERTY_NAME] = __( 'A property name that cannot be encoded was given', 'zao-centaman' );
		}
		if ( defined( 'JSON_ERROR_UTF16' ) ) {
			$errors[JSON_ERROR_UTF16] = __( 'Malformed UTF-16 characters, possibly incorrectly encoded', 'zao-centaman' );
		}

		return isset( $errors[ $json_error_id ] )
			? $errors[ $json_error_id ]
			: __( 'Unknown error.', 'zao-centaman' );
	}

	public static function get_last_instance() {
		return self::$last_instance;
	}

	public function __call( $endpoint, $args ) {
		$this->set_endpoint( $endpoint );
		if ( ! empty( $args[0]['endpoint'] ) ) {
			$this->set_endpoint( $args[0]['endpoint'] );
			unset( $args[0]['endpoint'] );
		}

		if ( ! empty( $args[0] ) ) {
			$this->set_query_args( $args[0] );
		}

		return $this
			->dispatch( 'GET' )
			->get_response();
	}
}
