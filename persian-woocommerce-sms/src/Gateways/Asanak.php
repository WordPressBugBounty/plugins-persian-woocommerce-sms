<?php

namespace PW\PWSMS\Gateways;

class Asanak implements GatewayInterface {
	use GatewayTrait;

	public string $api_url = 'https://sms.asanak.ir/webservice';
	public array $failed_numbers = [];

	public static function id() {
		return 'asanak';
	}

	public static function name() {
		return 'asanak.ir';
	}

	public function endpoint(string $endpoint): string {
		return rtrim($this->api_url, '/') . '/' . ltrim($endpoint, '/');
	}

	public function send() {
		$username          = trim($this->username);
		$password          = trim($this->password);
		$message_content   = trim($this->message);
		$sender_number     = trim($this->senderNumber);
		$recipient_numbers = (array) $this->mobile;

		$this->failed_numbers = [];

		if (empty($username) || empty($password)) {
			return 'نام کاربری یا رمز عبور خالی است.';
		}

		if (strpos($message_content, 'patterncode:') === 0) {
			// Send via template (pattern)
			$message_content = str_replace(["\r\n", "\n"], ';', $message_content);
			$message_parts   = explode(';', $message_content);
			$template_id     = (int) explode(':', array_shift($message_parts))[1];

			$params = [];
			foreach ($message_parts as $parameter) {
				[$key, $value] = explode(':', $parameter, 2);
				$params[$key] = $value;
			}

			foreach ($recipient_numbers as $recipient) {
				$response = wp_remote_post($this->endpoint('/v2rest/template'), [
					'headers' => [
						'Content-Type' => 'application/json',
					],
					'body' => json_encode([
						'username'          => $username,
						'password'          => $password,
						'template_id'       => $template_id,
						'destination'       => $this->normalize_number($recipient),
						'parameters'        => $params,
						'send_to_blacklist' => 0,
					]),
					'timeout' => 30,
				]);

				$this->handle_response($response, $recipient);
			}
		} else {
			// Send normal message
			$response = wp_remote_post($this->endpoint('/v2rest/sendsms'), [
				'headers' => [
					'Content-Type' => 'application/x-www-form-urlencoded',
				],
				'body' => [
					'username'          => $username,
					'password'          => $password,
					'source'            => $sender_number,
					'destination'       => implode(',', array_map([$this, 'normalize_number'], $recipient_numbers)),
					'message'           => $message_content,
					'send_to_blacklist' => 0,
				],
				'timeout' => 30,
			]);

			$this->handle_response($response);
		}

		return empty($this->failed_numbers) ? true : $this->format_failed_numbers();
	}

	private function normalize_number($number): string {
		$number = trim($number);
		return str_replace('+98', '0', $number);
	}

	private function handle_response($response, $recipient = '') {
		if (is_wp_error($response)) {
			$message = $response->get_error_message();
			$this->record_failure($recipient, $message);
			return;
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);

		if (!isset($body['meta']['status']) || $body['meta']['status'] != 200) {
			$message = $body['meta']['message'] ?? 'پاسخ نامعتبر از سمت وبسرویس.';
			$this->record_failure($recipient, $message);
		}
	}

	private function record_failure($recipient, $message) {
		if ($recipient) {
			$this->failed_numbers[$recipient] = $message;
		} else {
			$this->failed_numbers[] = $message;
		}
	}

	private function format_failed_numbers() {
		if (empty($this->failed_numbers)) {
			return true;
		}

		$grouped = [];
		foreach ($this->failed_numbers as $number => $message) {
			$grouped[$message][] = $number;
		}

		return implode(', ', array_map(function ($message, $numbers) {
			return implode(', ', $numbers) . ': ' . $message;
		}, array_keys($grouped), $grouped));
	}

	public function get_credit( string $username, string $password ) {
		try {
			$ch = curl_init();

			curl_setopt_array($ch, [
				CURLOPT_URL            => $this->endpoint('/v2rest/getrialcredit'),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST           => true,
				CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
				CURLOPT_POSTFIELDS     => json_encode([
					'username' => $username,
					'password' => $password,
				]),
			]);

			$response = curl_exec($ch);

			if (curl_errno($ch)) {
				throw new \Exception(curl_error($ch));
			}

			curl_close($ch);

			$data = json_decode($response, true);

			if (!isset($data['meta']['status'])) {
				throw new \Exception('پاسخ نامعتبر از سمت سرور دریافت شد.');
			}

			if ((int) $data['meta']['status'] !== 200) {
				return 'خطا در دریافت موجودی: ' . ($data['meta']['message'] ?? 'Unknown error');
			}

			return isset($data['data']['credit']) ? (int) $data['data']['credit'] : 0;

		} catch ( \Exception $e ) {
			return 'خطا در دریافت موجودی: ' . $e->getMessage();
		}
	}

}
