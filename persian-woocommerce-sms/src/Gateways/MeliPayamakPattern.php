<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class MeliPayamakPattern implements GatewayInterface {
	use GatewayTrait;

	public const ERRORS = [
		- 7 => 'خطایی در شماره فرستنده پیامک رخ داده است، لطفاً با پشتیبانی فنی تماس بگیرید.',
		- 6 => 'خطای داخلی رخ داده است، لطفاً با پشتیبانی فنی تماس بگیرید. (در این حالت ممکن است الگو یا پترن شما به‌درستی درج نشده باشد یعنی در متغیرها به‌جای استفاده از {} از () یا کاراکتر دیگری استفاده کردید و یا ممکن است اعداد درون {} را به‌ترتیب ننوشته باشید و یا از اعداد فارسی در درون {} استفاده کرده باشید)',
		- 5 => 'تعداد اندیس‌های آرایه پارامتر text با تعداد متغیرهای پترن شما مطابقت ندارد. (مثلاً اگر پترن شما 3 متغیر داشته باشد، شما در پارامتر text از چیزی تعریف نکرده‌اید و یا تعداد اندیس‌های آن برابر با عدد 3 نیست، یعنی ممکن است تعداد اندیس‌های پارامتر text شما 0، 1، 2 یا 4 یا بیشتر است.)',
		- 4 => 'کد متن یا همان کد پترن یا همان کد الگو یا همان شماره قالب وارد شده صحیح نیست / کد متن شما توسط مدیر سامانه تایید نشده است.',
		- 3 => 'سرشماره فرستنده پیامک‌ها در سیستم تعریف نشده است، لطفاً با پشتیبانی فنی تماس بگیرید. / تعداد شماره موبایل گیرنده مجاز نیست.',
		- 2 => 'محدودیت تعداد شماره موبایل گیرنده، در هر بار ارسال یک شماره موبایل مجاز است.',
		- 1 => 'دسترسی برای استفاده از این وبسرویس غیرفعال است. لطفاً با پشتیبانی تماس بگیرید.',
		0   => 'پنل اس ام اس امکان اتصال به وب سرویس را ندارد / نام کاربری یا رمز عبور وارد شده صحیح نیست.',
		2   => 'موجودی و اعتبار پنل اس ام اس کافی نیست.',
		6   => 'سامانه در حال بروزرسانی است.',
		7   => 'متن پیامک حاوی کلمه یا کلمات فیلتر شده است.',
		10  => 'پنل اس ام اس کاربر فعال نمی‌باشد و یا پنل پیامک کاربر مسدود شده است.',
		11  => 'ارسال نشده / شماره موبایل گیرنده در لیست سیاه پنل اس ام اس قرار دارد.',
		12  => 'مدارک پنل اس ام اس کاربر کامل نمی‌باشد.'
	];

	public static function id() {
		return 'melipayamakpattern';
	}

	public static function name() {
		return 'melipayamak.com خدماتی';
	}

	public function send() {
		$response = false;
		//$response = false;
		$username = $this->username;
		$password = $this->password;
		$from     = $this->senderNumber;
		$to       = $this->mobile;
		$massage  = $this->message;

		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		$text      = explode( '@', $massage );
		$textarray = array_pop( $text );
		$bodyid    = array_pop( $text );
		$textarray = explode( '##', $textarray );
		$key       = array_pop( $textarray );
		//بررسی وجود shared & محدودیت تعداد شماره موبایل
		if ( trim( $key ) == "shared" && count( $to ) < 5 ) {
			try {
				for ( $i = 0; $i < count( $to ); $i ++ ) {
					$client     = new SoapClient( "https://api.payamak-panel.com/post/send.asmx?wsdl",
						[ 'encoding' => 'UTF-8' ] );
					$parameters = [
						'username' => $username,
						'password' => $password,
						'text'     => reset( $textarray ),
						'to'       => $to[$i],
						'bodyId'   => $bodyid
					];
					//استفاده از  متد SendByBaseNumber2 جایگزین SendByBaseNumber3
					$sms_response = $client->SendByBaseNumber2( $parameters )->SendByBaseNumber2Result;
				}
			} catch ( SoapFault $ex ) {
				$sms_response = $ex->getMessage();
			}

			if ( $sms_response > 20 ) {
				return true; //success
			} else {
				$response = $sms_response;
			}

		} else {

			try {
				$client       = new SoapClient( "http://api.payamak-panel.com/post/send.asmx?wsdl" );
				$encoding     = "UTF-8";
				$parameters   = [
					'username' => $username,
					'password' => $password,
					'from'     => $from,
					'to'       => $to,
					'text'     => $massage,
					'isflash'  => false,
					'udh'      => "",
					'recId'    => [ 0 ],
					'status'   => 0,
				];
				$sms_response = $client->SendSms( $parameters )->SendSmsResult;
			} catch ( SoapFault $ex ) {
				$sms_response = $ex->getMessage();
			}

			if ( $sms_response == 1 ) {
				return true; // Success
			} else {
				$response = self::ERRORS[ $sms_response ] ?? $sms_response;
			}

		}

		return $response;
	}
}
