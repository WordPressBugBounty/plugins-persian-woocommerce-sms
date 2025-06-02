<?php

namespace PW\PWSMS\Gateways;

class PanelChi implements GatewayInterface {
	use GatewayTrait;

	/**
	 * @var string
	 */
	public string $api_url = 'http://185.141.171.123/wbs/send.php?wsdl';

	/**
	 * @var array
	 */
	public array $failed_numbers = [];

	public static function id() {
		return 'panelchi';
	}

	public static function name() {
		return 'panelchi.com';
	}

	public function send() {

		$message_content   = trim( $this->message );
		$sender_number     = trim( $this->senderNumber );
		$recipient_numbers = $this->mobile;

		if ( empty( $sender_number ) ) {
			$sender_number = '+9810001';
		}

		$this->failed_numbers = []; // Reset the property for each send operation

		// Replace "pcode" with "patterncode" in the message
		$message_content = str_replace( 'pcode', 'patterncode', $message_content );

		// Set token statically or from property
		$token = ! empty( $this->username ) ? trim( $this->username ) : trim( $this->password ); // Fixed typo: $this->$password → $this->password

		$soap = new \SoapClient( $this->api_url );

		if ( substr( $message_content, 0, 11 ) === "patterncode" ) {
			// Handle pattern-based message
			$message_content = str_replace( [ "\r\n", "\n" ], ';', $message_content );
			$message_parts   = explode( ';', $message_content );
			if ( count( $message_parts ) == 1 ) {
				$message_parts = explode( ' ', $message_content );
			}

			$pattern_code = explode( ':', $message_parts[0] )[1];
			unset( $message_parts[0] );

			$pattern_data = [];
			foreach ( $message_parts as $parameter ) {
				$split_parameter                     = explode( ':', $parameter, 2 );
				$pattern_data[ $split_parameter[0] ] = $split_parameter[1];
			}

			foreach ( $recipient_numbers as $recipient ) {
				$params = [
					'fromNum'   => $sender_number,
					'toNum'     => [ $recipient ],
					'Content'   => json_encode( $pattern_data, JSON_UNESCAPED_UNICODE ),
					'patternID' => $pattern_code,
					'Type'      => 0,
					'token'     => $token,
				];

				$array = $soap->__soapCall( 'SendSMSByPattern', [ $params ] );

				// Handle response for each recipient
				$this->handle_response( $array, $recipient );
			}

		} else {
			// Handle regular message
			foreach ( $recipient_numbers as $recipient ) {
				$params = [
					'fromNum' => $sender_number,
					'toNum'   => [ $recipient ],
					'Content' => $message_content,
					'Type'    => 0,
					'token'   => $token,
				];

				$array = $soap->__soapCall( 'SendSMS', [ $params ] );

				// Handle response for each recipient
				$this->handle_response( $array, $recipient );
			}
		}

		// Check for failed numbers and return error message
		if ( empty( $this->failed_numbers ) ) {
			return true;
		}

		// Group numbers by their messages
		$grouped = [];
		foreach ( $this->failed_numbers as $number => $message ) {
			if ( ! isset( $grouped[ $message ] ) ) {
				$grouped[ $message ] = [];
			}
			$grouped[ $message ][] = $number;
		}

		// Format the grouped data
		return implode( ', ', array_map(
			function ( string $message, array $numbers ) {
				return implode( ',', $numbers ) . ': ' . $message;
			},
			array_keys( $grouped ),
			$grouped
		) );


	}


	/**
	 * Handle the response for each recipient.
	 *
	 * @param mixed $response
	 * @param string $recipient
	 */
	private function handle_response( $response, $recipient ) {

		if ( is_wp_error( $response ) ) {
			$this->failed_numbers[ $recipient ] = $response->get_error_message();

			return;
		}

		if ( empty( $response ) ) {

			$this->failed_numbers[ $recipient ] = 'بدون پاسخ دریافتی از سمت وب سرویس.';

			return;
		}

		$response_data = $response[0];

		if ( ( is_numeric( $response_data ) && $response_data > 100 ) || ( isset( $response_data[0] ) && $response_data[0] == '0' ) ) {
			// Successful response, no need to do anything further.
			return;
		}

		// Handle error based on the response
		$this->failed_numbers[ $recipient ] = $response_data[1] ?? 'خطای ناشناخته.';
	}
}
