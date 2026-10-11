<?php

namespace PW\PWSMS\Gateways;

use Exception;
use PW\PWSMS\Helpers\Curl;


class MeliPayamak extends Gateway {

	public string $api_url = 'https://rest.payamak-panel.com/api/SendSMS';

	public const ERRORS = [
		- 110 => 'الزام استفاده از ApiKey به جای رمز عبور',
		- 109 => 'الزام تنظیم IP مجاز برای استفاده از API',
		- 108 => 'مسدود شدن IP به دلیل تلاش ناموفق استفاده از API',
		- 10  => 'در میان متغییر های ارسالی ، لینک وجود دارد.',
		- 7   => 'خطایی در شماره فرستنده پیامک رخ داده است، لطفاً با پشتیبانی فنی تماس بگیرید.',
		- 6   => 'خطای داخلی رخ داده است، لطفاً با پشتیبانی فنی تماس بگیرید.',
		- 5   => 'تعداد متغیرهای پترن با متن ارسالی مطابقت ندارد.',
		- 4   => 'کد پترن صحیح نیست یا تایید نشده است.',
		- 3   => 'سرشماره تعریف نشده یا تعداد گیرندگان مجاز نیست.',
		- 2   => 'در هر بار ارسال، تنها یک گیرنده مجاز است.',
		- 1   => 'دسترسی به وب‌سرویس غیرفعال است.',
		0     => 'نام کاربری یا رمز عبور اشتباه است.',
		2     => 'اعتبار کافی نیست.',
		3     => 'محدودیت در ارسال روزانه.',
		4     => 'محدودیت تعداد یا حجم پیامک.',
		5     => 'شماره فرستنده معتبر نیست.',
		6     => 'سامانه در حال بروزرسانی است.',
		7     => 'متن پیامک شامل کلمات فیلتر شده است.',
		8     => 'تعداد پیامک کمتر از حداقل مجاز.',
		9     => 'ارسال از خطوط عمومی غیرمجاز است.',
		10    => 'پنل غیرفعال یا مسدود است.',
		11    => 'شماره گیرنده در لیست سیاه است.',
		12    => 'مدارک پنل ناقص است.',
		14    => 'ارسال لینک از این سرشماره مجاز نیست.',
		15    => 'ارسال به چند شماره بدون لغو11 مجاز نیست.',
		16    => 'شماره گیرنده ای یافت نشد',
		17    => 'متن پیامک خالی می باشد',
		18    => 'شماره گیرنده نامعتبر است',
		19    => 'از محدودیت ساعتی فراتر رفته اید.',
		35    => 'شماره در لیست سیاه مخابرات است.',
	];

	public static function id(): string {
		return 'melipayamak_unified';
	}

	public static function name(): string {
		return 'melipayamak.com - ملی پیامک';
	}

	public function send(): bool {

		if ( empty( $this->username ) || empty( $this->password ) ) {
			throw new Exception( 'لطفا نام کاربری و کلید دسترسی ملی پیامک را در بخش وبسرویس ثبت نمایید.' );
		}

		$headers = [
			"Content-Type: application/json; charset=utf-8",
		];

		if ( $this->is_pattern() ) {

			$pattern = $this->parse_pattern();

			$url = $this->api_url . '/BaseServiceNumber';

			$data = [
				'username' => $this->username,
				'password' => $this->password,
				'text'     => implode( ';', $pattern['vars'] ),
				'bodyId'   => intval( $pattern['code'] ),
			];

		} else {

			$url = $this->api_url . '/SendSMS';

			$data = [
				'username' => $this->username,
				'password' => $this->password,
				'from'     => $this->senderNumber,
				'text'     => $this->message,
				'isflash'  => false,
			];

		}

		foreach ( $this->mobile as $recipient ) {

			$data['to'] = $recipient;

			try {
				$response = Curl::post( $url, wp_json_encode( $data ), $headers );
			} catch ( Exception $e ) {
				$this->failed_numbers[ $recipient ] = $e->getMessage();
				continue;
			}

			$value = $response['Value'] ?? '';

			if ( strlen( $value ) > 15 ) {
				continue;
			}

			if ( isset( self::ERRORS[ $value ] ) ) {
				$this->failed_numbers[ $recipient ] = sprintf( 'خطای %s: %s', $value, self::ERRORS[ $value ] );
				continue;
			}

			if ( $value ) {
				$this->failed_numbers[ $recipient ] = sprintf( 'خطای %s در ارسال پیامک رخ داده است.', $value );
				continue;
			}

			if ( isset( $response['StrRetStatus'] ) ) {
				$this->failed_numbers[ $recipient ] = sprintf( 'خطای %s در ارسال پیامک رخ داده است.', $response['StrRetStatus'] );
				continue;
			}

			$this->failed_numbers[ $recipient ] = 'خطای ناشناخته در ارسال به ملی پیامک رخ داده است.';
		}

		return $this->format_failed_numbers();
	}

	/**
	 * @throws Exception
	 */
	public function get_credit(): int {

		$data = [
			'username' => $this->username,
			'password' => $this->password,
		];

		$response = Curl::post( $this->api_url . '/GetCredit', wp_json_encode( $data ), [
			"Content-Type: application/json; charset=utf-8",
		] );

		$credit = $response['Value'] ?? 0;

		return is_numeric( $credit ) ? intval( $credit ) : 0;
	}

	/**
	 * @throws Exception
	 */
	public function send_pattern_sms(): bool {
		return $this->send();
	}

	public function is_pattern(): bool {
		if ( parent::is_pattern() ) {
			return true;
		}

		return $this->is_legacy_pattern();
	}

	// @PATTERN_CODE@{var1};{var2};{var3}##shared
	public function is_legacy_pattern(): bool {
		return str_starts_with( $this->message, '@' ) && str_contains( $this->message, '##' );
	}

	public function parse_pattern(): array {
		if ( $this->is_legacy_pattern() ) {
			return $this->parse_legacy_pattern();
		}

		return parent::parse_pattern();
	}

	public function parse_legacy_pattern(): array {

		$parts = explode( '@', $this->message );
		$code  = trim( $parts[1] ?? '' );
		$vars  = trim( $parts[2] ?? '' );

		$vars = str_ireplace( [ ';;##shared', ';;##' ], [ ';##shared', ';##' ], $vars );

		$vars = str_replace( [ '##shared', '##' ], '', $vars );

		return [
			'code' => $code,
			'vars' => array_map( 'trim', explode( ';', $vars ) ),
		];
	}
}