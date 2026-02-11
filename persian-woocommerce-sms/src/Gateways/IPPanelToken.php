<?php

namespace PW\PWSMS\Gateways;

/**
 * The new IPPanel service based on https://ippanelcom.github.io
 * Can send Pattern and Simple SMS at same time (based on message)
 *
 * Example pattern message, you can use pcode instead of patterncode too.
 * patterncode:confirmedpatterncode
 * param1:value
 * param2:value
 */
class IPPanelToken implements GatewayInterface {
	use GatewayTrait;

	/**
	 * @var string
	 */
	public string $api_url = 'https://edge.ippanel.com/v1/';

	/**
	 * @var array
	 */
	public array $failed_numbers = [];

	/**
	 * @var string
	 */
	private string $token = '';

	public static function id() {
		return 'ippanel-token';
	}

	public static function name() {
		return 'ippanel.com (کلید دسترسی)';
	}

	/**
	 * Creates the complete request url based on api_url
	 *
	 * @param string $endpoint
	 *
	 * @return string the full request url
	 */
	public function endpoint( string $endpoint ): string {
		return $this->api_url . $endpoint;
	}

	/**
	 * Handle sending SMS based on its content (pattern, simple)
	 *
	 * @return bool|string (only true if send process was successful)
	 */
	public function send() {
		$this->token        = $this->get_token();
		$this->senderNumber = trim( $this->senderNumber ) ?: '+983000505';

		if ( empty( $this->message ) ) {
			return 'متن پیام برای ارسال تعریف نشده.';
		}

		if ( empty( $this->token ) ) {
			return 'کلید وبسرویس را در بخش تنظیمات وبسرویس تعریف کنید.';
		}

		// Change pcode to patterncode before anything (ensure message content is valid)
		$this->message = str_replace( 'pcode', 'patterncode', $this->message );

		// Check for pattern message
		if ( substr( $this->message, 0, 11 ) === "patterncode" ) {
			$this->send_pattern_sms();
		} else {
			$this->send_simple_sms();
		}

		return empty( $this->failed_numbers ) ? true : $this->format_failed_numbers();
	}

	/**
	 * Send the pattern SMS
	 *
	 * @return void
	 */
	private function send_pattern_sms() {
		$this->message = str_replace( [ "\r\n", "\n" ], ';', $this->message );
		$message_parts = explode( ';', $this->message );
		$pattern_code  = explode( ':', $message_parts[0] )[1];
		unset( $message_parts[0] );

		$pattern_data = [];

		foreach ( $message_parts as $parameter ) {
			$split_parameter                     = explode( ':', $parameter, 2 );
			$pattern_data[ $split_parameter[0] ] = $split_parameter[1];
		}

		foreach ( $this->mobile as $recipient ) {

			$payload = [
				'sending_type' => 'pattern',
				'from_number'  => $this->senderNumber,
				'code'         => $pattern_code,
				'recipients'   => [ $recipient ],
				'params'       => $pattern_data,
			];

			$response = wp_remote_post( $this->endpoint( 'api/send' ), [
				'method'  => 'POST',
				'body'    => json_encode( $payload ),
				'timeout' => 10,
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => $this->token,
				],
			] );

			$this->handle_response( $response, $recipient );
		}
	}

	/**
	 * Send the simple SMS
	 *
	 * @return void
	 */
	private function send_simple_sms() {

		$payload = [
			'sending_type' => 'webservice',
			'from_number'  => $this->senderNumber,
			'message'      => $this->message,
			'params'       => [
				'recipients' => $this->mobile,
			],
		];

		$response = wp_remote_post( $this->endpoint( 'api/send' ), [
			'method'  => 'POST',
			'body'    => json_encode( $payload ),
			'timeout' => 10,
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => $this->token,
			],
		] );

		// Handle single response for all recipients
		$this->handle_response( $response );
	}

	/**
	 * Handle the response for each recipient.
	 *
	 * @param mixed $response
	 * @param string $recipient
	 *
	 * @return void
	 */
	private function handle_response( $response, string $recipient = '' ): void {

		if ( is_wp_error( $response ) ) {

			$message = $response->get_error_message();

			if ( $recipient ) {
				$this->failed_numbers[ $recipient ] = $message;
			} else {
				$this->failed_numbers[] = $message;
			}

			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['meta']['status'] ) && $body['meta']['status'] ) {
			return;
		}

		// Determine error message
		if ( isset( $body['meta']['message'] ) ) {

			$message = $body['meta']['message'];

		} elseif ( isset( $body['meta']['errors'] ) && is_array( $body['meta']['errors'] ) ) {

			$all_errors = [];

			foreach ( $body['meta']['errors'] as $field_errors ) {

				if ( ! is_array( $field_errors ) ) {
					continue;
				}

				$all_errors = array_merge( $all_errors, $field_errors );

			}

			$message = implode( ' ', $all_errors );

		} else {

			$message = 'خطای نامشخص';

		}

		if ( ! empty( $recipient ) ) {
			$this->failed_numbers[ $recipient ] = $message;
		} else {
			$this->failed_numbers[] = $message;
		}
	}

	/**
	 * Create proper output message to show if message is failed
	 *
	 * @return bool|string (only true if there's no failed sms)
	 */
	private function format_failed_numbers() {

		if ( empty( $this->failed_numbers ) ) {
			return true;
		}

		$grouped = [];

		foreach ( $this->failed_numbers as $number => $message ) {

			if ( ! isset( $grouped[ $message ] ) ) {
				$grouped[ $message ] = [];
			}

			$grouped[ $message ][] = $number;

		}

		return implode( ', ', array_map( function ( string $message, array $numbers ) {
			return implode( ',', $numbers ) . ': ' . $message;
		}, array_keys( $grouped ), $grouped ) );
	}

}
