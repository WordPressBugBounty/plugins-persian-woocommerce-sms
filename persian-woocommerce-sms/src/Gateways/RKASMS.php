<?php

namespace PW\PWSMS\Gateways;

use Exception;
use SoapClient;
use SoapFault;

class RKASMS extends Gateway {

	public string $api_url = 'https://rkasms.ir/services/SMSServices.asmx?WSDL';

	public static function id(): string {
		return 'rkasms';
	}

	public static function name(): string {
		return 'آرکا اس ام اس - RKASMS.ir';
	}

	public function send() {

		if ( empty( $this->username ) || empty( $this->password ) ) {
			throw new Exception( 'لطفا نام کاربری و رمز عبور آرکا اس ام اس را در بخش وبسرویس ثبت نمایید.' );
		}

		foreach ( $this->mobile as $recipient ) {

			$param = [
				'userName'    => $this->username,
				'password'    => $this->password,
				'msg'         => $this->message,
				'from'        => $this->senderNumber,
				'to'          => $recipient,
				'isFlashSend' =>
					false,
			];

			try {
				$client   = new SoapClient( $this->api_url );
				$response = $client->Send( $param );
			} catch ( SoapFault $e ) {
				$this->failed_numbers[ $recipient ] = $e->getMessage();
				continue;
			}

			if ( $response == 1 ) {
				continue;
			}

			$this->failed_numbers[ $recipient ] = sprintf( 'خطای %s در ارسال پیامک رخ داده است.', $response );

		}

		return $this->format_failed_numbers();
	}
}
