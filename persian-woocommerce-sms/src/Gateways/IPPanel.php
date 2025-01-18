<?php

namespace PW\PWSMS\Gateways;

class IPPanel implements GatewayInterface {
	use GatewayTrait;

	/**
	 * @var string
	 */
	public string $api_url = 'https://ippanel.com/api/select';

	/**
	 * @var array
	 */
	public array $failed_numbers = [];

	public static function id() {
		return 'ippanel';
	}

	public static function name() {
		return 'ippanel.com';
	}

	public function send() {
		$username          = trim( $this->username );
		$password          = trim( $this->password );
		$message_content   = trim( $this->message );
		$sender_number     = trim( $this->senderNumber );
		$recipient_numbers = $this->mobile;
		if ( empty( $sender_number ) ) {
			$sender_number = '+983000505';
		}

		$this->failed_numbers = []; // Reset the property for each send operation

		// Replace "pcode" with "patterncode" in the message
		$message_content = str_replace( 'pcode', 'patterncode', $message_content );

		// Handle pattern-based message
		if ( substr( $message_content, 0, 11 ) === "patterncode" ) {
			$message_content = str_replace( [ "\r\n", "\n" ], ';', $message_content );
			$message_parts   = explode( ';', $message_content );
			$pattern_code    = explode( ':', $message_parts[0] )[1];
			unset( $message_parts[0] );

			$pattern_data = [];
			foreach ( $message_parts as $parameter ) {
				$split_parameter                     = explode( ':', $parameter, 2 );
				$pattern_data[ $split_parameter[0] ] = $split_parameter[1];
			}

			// Loop over recipients to send pattern messages
			foreach ( $recipient_numbers as $recipient ) {
				$payload = [
					'op'          => 'pattern',
					'user'        => $username,
					'pass'        => $password,
					'fromNum'     => $sender_number,
					'toNum'       => $recipient,
					'patternCode' => $pattern_code,
					'inputData'   => [ $pattern_data ],
				];

				$response = wp_remote_post( $this->api_url, [
					'method'  => 'POST',
					'body'    => json_encode( $payload ),
					'timeout' => 30,
					'headers' => [
						'Content-Type' => 'application/json',
					],
				] );
				// Handle response for each recipient
				$this->handle_response( $response, $recipient );
			}

		} else {
			// Non-pattern message
			$payload = [
				'op'      => 'send',
				'uname'   => $username,
				'pass'    => $password,
				'from'    => $sender_number,
				'to'      => implode( ",", $recipient_numbers ),
				'message' => $message_content,
			];

			// Loop over recipients to send messages
			foreach ( $recipient_numbers as $recipient ) {
				$response = wp_remote_post( $this->api_url, [
					'method'  => 'POST',
					'body'    => json_encode( $payload ),
					'timeout' => 30,
					'headers' => [
						'Content-Type' => 'application/json',
					],
				] );
				// Handle response for each recipient
				$this->handle_response( $response, $recipient );
			}
		}

		// Check for failed numbers and return error message
		if ( ! empty( $this->failed_numbers ) ) {
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

		// If no failed numbers, return true for success
		return true;
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

		$response_code    = wp_remote_retrieve_response_code( $response );
		$response_message = wp_remote_retrieve_response_message( $response );

		if ( empty( $response_code ) || 200 != $response_code ) {

			$this->failed_numbers[ $recipient ] = $response_code . ' -> ' . $response_message;

			return;

		}

		$response_body = wp_remote_retrieve_body( $response );

		if ( empty( $response_body ) ) {

			$this->failed_numbers[ $recipient ] = 'بدون پاسخ دریافتی از سمت وب سرویس.';

			return;

		}

		$response_data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {

			$this->failed_numbers[ $recipient ] = 'فرمت نامعتبر پاسخ از سمت وب سرویس.';

			return;

		}

		if ( is_numeric( $response_data ) || ( isset( $response_data[0] ) && $response_data[0] == '0' ) ) {
			// Successful response, no need to do anything further.
			return;
		}

		// Handle error based on the response
		$this->failed_numbers[ $recipient ] = $response_data[1] ?? 'خطای ناشناخته.';
	}


	/**
	 * Generate error message based on returning codes
	 *
	 * @param string $error_code
	 *
	 * @return string
	 */
	private function errors_describe( $error_code ) {
		$error_messages = [
			'-1'    => 'ارتباط با سامانه پیامک انجام نشد.',
			'0'     => 'عملیات با موفقیت انجام شده است.',
			'1'     => 'متن پیام خالی می باشد.',
			'2'     => 'کاربر محدود گردیده است.',
			'3'     => 'خط به شما تعلق ندارد.',
			'4'     => 'گیرندگان خالی است.',
			'5'     => 'اعتبار کافی نیست.',
			'7'     => 'خط مورد نظر برای ارسال انبوه مناسب نمیباشد.',
			'9'     => 'خط مورد نظر در این ساعت امکان ارسال ندارد.',
			'98'    => 'حداکثر تعداد گیرنده رعایت نشده است.',
			'99'    => 'اپراتور خط ارسالی قطع می باشد.',
			'21'    => 'پسوند فایل صوتی نامعتبر است.',
			'22'    => 'سایز فایل صوتی نامعتبر است.',
			'23'    => 'تعداد تلاش در پیام صوتی نامعتبر است.',
			'100'   => 'شماره مخاطب دفترچه تلفن نامعتبر می باشد.',
			'101'   => 'شماره مخاطب در دفترچه تلفن وجود دارد.',
			'102'   => 'شماره مخاطب با موفقیت در دفترچه تلفن ذخیره گردید.',
			'111'   => 'حداکثر تعداد گیرنده برای ارسال پیام صوتی رعایت نشده است.',
			'131'   => 'تعداد تلاش در پیام صوتی باید یکبار باشد.',
			'132'   => 'آدرس فایل صوتی وارد نگردیده است.',
			'266'   => 'شما نمی توانید از خط اشتراکی استفاده نمایید.',
			'301'   => 'از حرف ویژه در نام کاربری استفاده گردیده است.',
			'302'   => 'قیمت گذاری انجام نگردیده است.',
			'303'   => 'نام کاربری وارد نگردیده است.',
			'304'   => 'نام کاربری قبلا انتخاب گردیده است.',
			'305'   => 'نام کاربری وارد نگردیده است.',
			'306'   => 'کد ملی وارد نگردیده است.',
			'307'   => 'کد ملی به خطا وارد شده است.',
			'308'   => 'شماره شناسنامه نامعتبر است.',
			'309'   => 'شماره شناسنامه وارد نگردیده است.',
			'310'   => 'ایمیل کاربر وارد نگردیده است.',
			'311'   => 'شماره تلفن وارد نگردیده است.',
			'312'   => 'تلفن به درستی وارد نگردیده است.',
			'313'   => 'آدرس شما وارد نگردیده است.',
			'314'   => 'شماره موبایل را وارد نکرده اید.',
			'315'   => 'شماره موبایل به نادرستی وارد گردیده است.',
			'316'   => 'سطح دسترسی به نادرستی وارد گردیده است.',
			'317'   => 'کلمه عبور وارد نگردیده است.',
			'404'   => 'پترن در دسترس نیست.',
			'455'   => 'ارسال در آینده برای کد بالک ارسالی لغو شد.',
			'456'   => 'کد بالک ارسالی نامعتبر است.',
			'458'   => 'کد تیکت نامعتبر است.',
			'964'   => 'شما دسترسی نمایندگی ندارید.',
			'962'   => 'نام کاربری یا کلمه عبور نادرست می باشد.',
			'963'   => 'دسترسی نامعتبر می باشد.',
			'971'   => 'پترن ارسالی نامعتبر است.',
			'970'   => 'پارامتر های ارسالی برای پترن نامعتبر است.',
			'972'   => 'دریافت کننده برای ارسال پترن نامعتبر می باشد.',
			'992'   => 'ارسال پیام از ساعت 8 تا 23 می باشد.',
			'993'   => 'دفترچه تلفن باید یک آرایه باشد',
			'994'   => 'لطفا تصویری از کارت بانکی خود را از منو مدارک ارسال کنید',
			'995'   => 'جهت ارسال با خطوط اشتراکی سامانه، لطفا شماره کارت بانکی خود را به دلیل تکمیل فرایند احراز هویت از بخش ارسال مدارک ثبت نمایید.',
			'996'   => 'پترن فعال نیست.',
			'997'   => 'شما اجازه ارسال از این پترن را ندارید.',
			'998'   => 'کارت ملی یا کارت بانکی شما تایید نشده است.',
			'1001'  => 'فرمت نام کاربری درست نمی باشد)حداقل 5 کاراکتر، فقط حروف و اعداد(.',
			'1002'  => 'گذرواژه خیلی ساده می باشد. باید حداقل 8 کاراکتر بوده و از نام کاربری و ایمیل و شماره موبایل خود در آن استفاده نکنید.',
			'1003'  => 'مشکل در ثبت، با پشتیبانی تماس بگیرید.',
			'1004'  => 'مشکل در ثبت، با پشتیبانی تماس بگیرید.',
			'1005'  => 'مشکل در ثبت، با پشتیبانی تماس بگیرید.',
			'1006'  => 'تاریخ ارسال پیام برای گذشته می باشد، لطفا تاریخ ارسال پیام را به درستی وارد نمایید.',
			'1401'  => 'اعتبارسنجی کاربر خطا دارد.',
			'1402'  => 'کلید وب سرویس معتبر نیست',
			'1403'  => 'کلید وب سرویس لغو شده است.',
			'10001' => 'اعتبار پنل کافی نیست.',
			'10002' => 'متن پیام خالی است.',
			'10003' => 'کاربر محدود گردیده است.',
			'10004' => 'شماره ارسال کننده به شما تعلق ندارد.',
			'10005' => 'مخاطب پیامک خالی است.',
			'10006' => 'اعتبار پنل کافی نیست.',
			'10007' => 'خط مورد نظر برای ارسال انبوه مناسب نمیباشد.',
			'10008' => 'خط ارسال کننده به صورت موقت غیرفعال شده است.',
			'10009' => 'مخاطبان بیش از حد مجاز است.',
			'10010' => 'درگاه پیامک غیرفعال است.',
			'10011' => 'قیمتگذاری در پنل کاربر انجام نشده است.',
			'10012' => 'تیکت غیرمعتبر است.',
			'10013' => 'دسترسی ممنوع است.',
			'10014' => 'پترن نامعتبر است.',
			'10015' => 'پارامترهای پترن نامعتبر است.',
			'10016' => 'پترن غیرفعال است.',
			'10017' => 'گیرنده پیامک پترن نامعتبر است.',
			'10019' => 'ارسال از این خط در ساعات شبانه ممنوع است.',
			'10021' => 'برخی مدارک شما تایید نشده است.',
			'10022' => 'خطای داخلی.',
			'10023' => 'خط ارسال کننده یافت نشد',
			'12404' => 'خط ارسال کننده یافت نشد',
			'13001' => 'کارت ملی تایید نشده است',
			'13002' => 'شماره کارت بانکی شما تایید نشده است',
			'13003' => 'رمز عبور شما بسیار ضعیف است',
			'13004' => 'خط ارسالی متعلق به شما نیست',
			'13005' => 'پترن غیر فعال است.',
			'13006' => 'شما نمی توانید از این پترن استفاده نمایید.',
			'13007' => 'پترن در دسترس نیست.',
			'13008' => 'گیرنده نامعتبر است.',
			'13009' => 'درگاه غیرفعال است.',
			'13010' => 'پارامتر های پترن نامعتبر است.',
			'13011' => 'مقادیر پترن خیلی طولانی است.',
			'13012' => 'پارامتر های پترن نامعتبر است.',
			'422'   => 'خطایی در ورودی ها وجود دارد.',
		];

		return ( isset( $error_messages[ $error_code ] ) ) ? $error_messages[ $error_code ] : 'اشکال تعریف نشده با کد :' . $error_code;
	}
}
