<?php

namespace PW\PWSMS\Gateways;

use nusoap_client;
use PW\PWSMS\PWSMS;
use soapclient;
use SoapFault;

class MeliPayamak implements GatewayInterface {
	use GatewayTrait;

	public const ERRORS = [
		- 1 => 'خطای نامشخصی رخ داده است. با پشتیبانی تماس بگیرید.',
		0   => 'پنل اس ام اس امکان اتصال به وب سرویس را ندارد / نام کاربری یا رمز عبور وارد شده صحیح نیست.',
		2   => ' موجودی و اعتبار پنل اس ام اس کافی نیست.',
		3   => 'محدودیت در ارسال روزانه',
		4   => ' محدودیت در حجم و تعداد ارسال پیامک',
		5   => 'شماره فرستنده یا سرشماره پیامکی معتبر نمی‌باشد.',
		6   => 'سامانه در حال بروزرسانی است.',
		7   => 'متن پیامک حاوی کلمه یا کلمات فیلتر شده است.',
		8   => 'عدم رسیدن به حداقل تعداد ارسال پیامک',
		9   => 'ارسال از خطوط عمومی از طریق وب سرویس امکان‌پذیر نمی‌باشد.',
		10  => 'پنل اس ام اس کاربر فعال نمی‌باشد و یا پنل پیامک کاربر مسدود شده است.',
		11  => 'ارسال نشده / شماره موبایل گیرنده در لیست سیاه مخابرات قرار دارد.',
		12  => 'مدارک پنل اس ام اس کاربر کامل نمی‌باشد.',
		14  => 'سرشماره فرستنده پیامک، امکان ارسال لینک را ندارد.',
		15  => 'درصورتیکه در رکورد درخواست ارسال پیامک از طریق وبسرویس، بیش از 1 شماره موبایل درخواست دهید و عبارت «لغو11» را در انتهای متن پیامک ننوشته باشید، با خطای 15 مواجه خواهید شد.',
		35  => 'وجود شماره موبایل گیرنده در لیست سیاه مخابرات است.'
	];

	public static function id() {
		return 'melipayamak';
	}

	public static function name() {
		return 'melipayamak.com';
	}

	public function send() {
		$response = false;
		$username = $this->username;
		$password = $this->password;
		$from     = $this->senderNumber;
		$to       = $this->mobile;
		$massage  = $this->message;

		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		try {
			// The address should be written with the https protocol by default.
			$client = new \SoapClient( "https://api.payamak-panel.com/post/Send.asmx?wsdl", [
				'encoding'     => 'UTF-8',
				'cache_wsdl'   => WSDL_CACHE_MEMORY,
				'compression'  => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
				'soap_version' => SOAP_1_2,
				'keep_alive'   => true,
				'exceptions'   => true,
				'features'     => SOAP_WAIT_ONE_WAY_CALLS,
				'trace'        => true
			] );

			$encoding   = "UTF-8";
			$parameters = [
				'username' => $username,
				'password' => $password,
				'from'     => $from,
				'to'       => $to,
				'text'     => iconv( $encoding, 'UTF-8//TRANSLIT', $massage ),
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

		return $response;
	}
}
