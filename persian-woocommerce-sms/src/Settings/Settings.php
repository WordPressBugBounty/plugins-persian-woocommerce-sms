<?php

namespace PW\PWSMS\Settings;

use PW\PWSMS\ChangeLog;
use PW\PWSMS\Shortcode;

defined( 'ABSPATH' ) || exit;

class Settings {
	private $settings_api;

	public function __construct() {

		$this->settings_api = new API();

		add_action( 'init', [ $this, 'update_option_38' ] );

		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

		add_action( 'admin_init', [ $this, 'admin_init' ] );
		add_action( 'admin_menu', [ $this, 'admin_menu' ], 60 );
		add_filter( 'woocommerce_settings_tabs_array', [ $this, 'admin_submenu' ], 99999 );
		add_action( 'wp_before_admin_bar_render', [ $this, 'admin_bar' ] );

		add_filter( 'pwoosms_buyer_settings', [ $this, 'buyer_settings' ] );
		add_filter( 'pwoosms_super_admin_settings', [ $this, 'super_admin_settings' ] );
		add_filter( 'pwoosms_product_admin_settings', [ $this, 'product_admin_settings' ] );

		add_filter( 'admin_footer_text', [ $this, 'footer_note' ] );
		add_filter( 'update_footer', [ $this, 'footer_version' ], 11 );

		add_filter( "plugin_action_links_persian-woocommerce-sms/WoocommerceIR_SMS.php", function ( $actions, $plugin_file, $plugin_data, $context ) {
			$woo = [
				'woo_ir' => sprintf( '<a href="%s" target="blank" style="background: #763ec2;color: white;padding: 0px 5px;border-radius: 2px;">%s</a>', 'https://woosupport.ir', 'ووکامرس فارسی' ),
			];

			return $woo + $actions;
		}, 100, 4 );

	}

	/**
	 * Get the URL for the "Persian WooCommerce SMS Pro" settings page
	 *
	 * @return string
	 */
	public static function get_page_url(): string {
		return admin_url( 'admin.php?page=persian-woocommerce-sms-pro' );
	}

	public function admin_enqueue_scripts() {
		if ( ! isset( $_GET['page'] ) || ! isset( $_GET['tab'] ) ) {
			return;
		}

		$page = $_GET['page'];
		$tab  = $_GET['tab'];

		if ( $page !== 'persian-woocommerce-sms-pro' ) {
			return;
		}

		if ( in_array( $tab, [ 'buyer', 'product_admin', 'super_admin', 'notif' ] ) ) {
			wp_enqueue_script( 'pwsms-shortcode-buttons', PWSMS_URL . '/assets/js/shortcode-buttons.js', [ 'jquery' ], PWSMS_VERSION, true );

			wp_localize_script( 'pwsms-shortcode-buttons', 'pwsms_shortcodes', self::shortcodes() );
		}

	}

	public static function shortcodes(): array {
		$final_shortcodes = [];

		$core_shortcodes = [
			'{mobile}'          => 'شماره موبایل مشتری',
			'{phone}'           => 'شماره تلفن مشتری',
			'{email}'           => 'ایمیل مشتری',
			'{status}'          => 'وضعیت سفارش',
			'{all_items}'       => 'محصولات سفارش',
			'{all_items_full}'  => 'محصولات سفارش با نام کامل متغیر',
			'{all_items_qty}'   => 'محصولات سفارش بهمراه تعداد',
			'{count_items}'     => 'تعداد محصولات سفارش',
			'{price}'           => 'مبلغ سفارش',
			'{post_id}'         => 'شماره سفارش اصلی',
			'{order_id}'        => 'شماره سفارش',
			'{transaction_id}'  => 'شماره تراکنش',
			'{date}'            => 'تاریخ سفارش',
			'{description}'     => 'توضیحات مشتری',
			'{payment_method}'  => 'روش پرداخت',
			'{shipping_method}' => 'روش ارسال',
			'{payment_url}'     => 'لینک پرداخت',
			'{b_first_name}'    => 'نام مشتری',
			'{b_last_name}'     => 'نام خانوادگی مشتری',
			'{b_company}'       => 'نام شرکت',
			'{b_country}'       => 'کشور',
			'{b_state}'         => 'ایالت/استان',
			'{b_city}'          => 'شهر',
			'{b_address_1}'     => 'آدرس 1',
			'{b_address_2}'     => 'آدرس 2',
			'{b_postcode}'      => 'کد پستی',
			'{sh_first_name}'   => 'نام مشتری (حمل و نقل)',
			'{sh_last_name}'    => 'نام خانوادگی مشتری (حمل و نقل)',
			'{sh_company}'      => 'نام شرکت (حمل و نقل)',
			'{sh_country}'      => 'کشور (حمل و نقل)',
			'{sh_state}'        => 'ایالت/استان (حمل و نقل)',
			'{sh_city}'         => 'شهر (حمل و نقل)',
			'{sh_address_1}'    => 'آدرس 1 (حمل و نقل)',
			'{sh_address_2}'    => 'آدرس 2 (حمل و نقل)',
			'{sh_postcode}'     => 'کد پستی (حمل و نقل)',
		];

		// Get filtered shortcode list string (HTML-like)
		$filtered = apply_filters( 'pwoosms_shortcodes_list', '' );

		// Parse filtered string to extract shortcode and description pairs
		$filtered_shortcodes = [];

		if ( ! empty( $filtered ) ) {
			// Regex to match lines like: <code>{shortcode}</code> = description ...
			// Rollback support to other plugins
			// It will match multiple occurrences
			preg_match_all( '/<code>\s*(\{[^}]+\})\s*<\/code>\s*=\s*([^<,]+)[,،]?/u', $filtered, $matches, PREG_SET_ORDER );

			foreach ( $matches as $m ) {
				// m[1] = shortcode (with braces)
				// m[2] = description (until comma or tag)
				$shortcode = trim( $m[1] );
				$desc      = trim( $m[2] );
				if ( $shortcode && $desc ) {
					$filtered_shortcodes[ $shortcode ] = $desc;
				}
			}
		}

		// If 'product_admin' tab, add vendor shortcodes
		if ( ! empty( $_GET['tab'] ) && $_GET['tab'] === 'product_admin' ) {
			$filtered_shortcodes['{vendor_name}']        = 'نام فروشنده';
			$filtered_shortcodes['{vendor_items}']       = 'محصولات سفارش هر فروشنده';
			$filtered_shortcodes['{vendor_items_qty}']   = 'محصولات سفارش هر فروشنده بهمراه تعداد';
			$filtered_shortcodes['{count_vendor_items}'] = 'تعداد محصولات سفارش هر فروشنده';
			$filtered_shortcodes['{vendor_price}']       = 'مجموع قیمت محصولات سفارش هر فروشنده';
		}


		$final_shortcodes['core'] = array_merge( $core_shortcodes, $filtered_shortcodes );


		$final_shortcodes['notification'] = [
			"{product_id}"    => "آیدی محصول",
			"{product_url}"   => "لینک محصول",
			"{sku}"           => "شناسه محصول",
			"{product_title}" => "عنوان محصول",
			"{regular_price}" => "قیمت اصلی",
			"{onsale_price}"  => "قیمت فروش فوق العاده",
			"{onsale_from}"   => "تاریخ شروع فروش فوق العاده",
			"{onsale_to}"     => "تاریخ اتمام فروش فوق العاده",
			"{stock}"         => "موجودی انبار",
		];


		$final_shortcodes['stock'] = [
			"{product_id}"    => "آیدی محصول",
			"{product_url}"   => "لینک محصول",
			"{sku}"           => "شناسه محصول",
			"{product_title}" => "عنوان محصول",
			"{stock}"         => "موجودی انبار",
		];

		$final_shortcodes['post_tracking'] = [
			'{post_tracking_code}' => 'کد رهگیری پستی',
			'{post_tracking_url}'  => 'آدرس اینترنتی رهگیری پستی',
		];

		return $final_shortcodes;
	}

	public function update_option_38() {
		global $wpdb;

		if ( get_option( 'pwoosms_update_gateway_options' ) ) {
			return;
		}

		$wpdb->query( "UPDATE {$wpdb->options} SET option_value=REPLACE(option_value, 's:24:\"persian_woo_sms_username\"', 's:20:\"sms_gateway_username\"') WHERE option_name='sms_main_settings'" );
		$wpdb->query( "UPDATE {$wpdb->options} SET option_value=REPLACE(option_value, 's:24:\"persian_woo_sms_password\"', 's:20:\"sms_gateway_password\"') WHERE option_name='sms_main_settings'" );
		$wpdb->query( "UPDATE {$wpdb->options} SET option_value=REPLACE(option_value, 's:22:\"persian_woo_sms_sender\"', 's:18:\"sms_gateway_sender\"') WHERE option_name='sms_main_settings'" );
		update_option( 'pwoosms_update_gateway_options', '1' );
	}

	public function admin_menu() {
		add_submenu_page( 'persian-wc', 'پیامک ووکامرس', 'پیامک ووکامرس', 'manage_woocommerce', 'persian-woocommerce-sms-pro', [
			$this,
			'settings_page',
		] );
	}

	public function admin_init() {

		if ( ! empty( $_GET['tab'] ) && $_GET['tab'] == 'pwoosms_settings_page' ) {
			wp_redirect( admin_url( 'admin.php?page=persian-woocommerce-sms-pro' ) );
			exit();
		}

		$this->settings_api->set_sections( self::settings_sections() );
		$this->settings_api->set_fields( $this->settings_fields() );
		$this->settings_api->admin_init();
	}

	public static function settings_sections() {
		$sections = [
			[
				'id'    => 'sms_main_settings',
				'title' => 'وبسرویس',
			],
			[
				'id'    => 'sms_super_admin_settings',
				'title' => 'پیامک مدیر کل',
			],
			[
				'id'    => 'sms_buyer_settings',
				'title' => 'پیامک مشتری',

			],
			[
				'id'    => 'sms_notif_settings',
				'title' => 'خبرنامه محصولات',
			],
			[
				'id'       => 'sms_contacts',
				'title'    => 'مشترکین خبرنامه',
				'form_tag' => false,
			],
			[
				'id'       => 'sms_send',
				'title'    => 'ارسال پیامک',
				'form_tag' => false,
			],
			[
				'id'       => 'sms_archive',
				'title'    => 'آرشیو پیامک‌ها',
				'form_tag' => false,
			],
			[
				'id'       => 'sms_bulk_vendors',
				'title'    => 'فروشندگان دکان',
				'form_tag' => false,
			],
			[
				'id'    => 'sms_product_admin_settings',
				'title' => 'پیامک فروشندگان',
			],
		];

		return apply_filters( 'pwoosms_settings_sections', $sections );
	}

	/*
	 * Returns the shortcode groups : core, notification, stock
	 * @return array
	 * */

	public function settings_fields() {

		$gateway = PWSMS()->get_option( 'sms_gateway' );
		$gateway = ! empty( $gateway ) && $gateway != 'none';

		$gateways_list = PWSMS()->get_sms_gateways();

		asort( $gateways_list );
		$gateways_list = array_merge( [ 'none' => 'انتخاب کنید' ], $gateways_list );

		$shortcode = Shortcode::shortcode( true );

		$settings_fields = [

			'sms_main_settings' => apply_filters( 'pwoosms_main_settings', [
				[
					'name'    => 'sms_gateway',
					'label'   => 'وبسرویس پیامک',
					'type'    => 'select',
					'default' => 'maxsms',
					'desc'    => 'برای اطلاع از هزینه ها و شرایط خدمات دهی هر سرویس به وب سایت های آن ها مراجعه کنید. ووکامرس فارسی تعهدی در قبال ارائه خدمات این شرکت ها ندارد و صرفا ارائه دهنده افزونه پیامک هستیم.',
					'options' => $gateways_list,
					'ltr'     => true,
				],
				[
					'name'  => 'sms_gateway_username',
					'label' => 'نام کاربری وبسرویس',
					'type'  => 'text',
					'ltr'   => true,
				],
				[
					'name'  => 'sms_gateway_password',
					'label' => 'کلمه عبور وبسرویس',
					'type'  => 'text',
					'ltr'   => true,
				],
				[
					'name'  => 'sms_gateway_sender',
					'label' => 'شماره ارسال کننده پیامک',
					'type'  => 'text',
					'ltr'   => true,
					'desc'  => $gateway ? sprintf( 'یک پیامک تستی جهت بررسی صحت تنظیمات درگاه پیامک %sارسال نمایید.%s', '<a href="' . admin_url( 'admin.php?page=persian-woocommerce-sms-pro&tab=send' ) . '">', '</a>' ) : '',
				],
				[
					'name'  => 'enable_admin_bar',
					'label' => 'لینک ارسال پیامک در ادمین بار',
					'desc'  => 'با فعالسازی این گزینه، لینک ارسال پیامک جهت دسترسی سریع تر به ادمین بار اضافه خواهد شد.',
					'type'  => 'checkbox',
				],
			] ),

			'sms_super_admin_settings' => apply_filters( 'pwoosms_super_admin_settings', [
				[
					'name'  => 'super_admin_phone',
					'label' => 'شماره موبایل های مدیران کل',
					'desc'  => 'شماره ها را با کاما (,) جدا نمایید.',
					'type'  => 'text',
					'ltr'   => true,
				],
				[
					'name'  => 'super_admin_bots',
					'label' => 'شناسه یکتا ربات',
					'desc'  => 'برای استفاده از ربات‌های ووکامرس فارسی، کافیست به ربات‌های زیر پیام بدهید و شناسه یکتا خود را دریافت کنید:<br>
ربات ووکامرس فارسی در تلگرام: <a href="https://t.me/PersianWoocommerceBot" target="_blank">https://t.me/PersianWoocommerceBot</a><br>
ربات ووکامرس فارسی در بله: <a href="https://ble.ir/PersianWoocommerceBot" target="_blank">https://ble.ir/PersianWoocommerceBot</a><br>
در هر سطر یک شناسه یکتا وارد کنید. (حداکثر ۵ شناسه یکتا)',
					'type'  => 'textarea',
					'ltr'   => true,
				],
				[
					'name'    => 'super_admin_order_status',
					'label'   => 'وضعیت های دریافت پیامک',
					'desc'    => 'می توانید مشخص کنید مدیران کل سایت در چه وضعیت هایی از سفارش پیامک دریافت کنند.',
					'type'    => 'multicheck',
					'options' => PWSMS()->get_all_super_admin_statuses(),
				],
				[
					'name'  => 'header_super_admin',
					'label' => '<h2>متن پیامک مدیر کل</h2>',
					'type'  => 'html',
				],
			] ),

			'sms_buyer_settings' => apply_filters( 'pwoosms_buyer_settings', [
				[
					'name'  => 'enable_metabox',
					'label' => 'متاباکس ارسال پیامک',
					'desc'  => 'با فعالسازی این گزینه، در صورت فعال بودن قابلیت ارسال پیامک به مشتری، در صفحه سفارشات متاباکس ارسال پیامک به مشتریان اضافه می‌شود.',
					'type'  => 'checkbox',
				],
				[
					'name'    => 'buyer_checkbox_text',
					'label'   => 'متن تمایل داشتن به دریافت پیامک',
					'desc'    => 'این متن بالای چک باکس انتخاب دریافت پیامک در صفحه تسویه حساب نمایش داده خواهد شد.',
					'type'    => 'text',
					'default' => 'میخواهم از وضعیت سفارش از طریق پیامک آگاه شوم.',
				],
				[
					'name'  => 'header_2',
					'label' => '<h2>وضعیت های پیامک</h2>',
					'type'  => 'html',
				],
				[
					'name'    => 'order_status',
					'label'   => 'وضعیت های دریافت پیامک',
					'desc'    => 'می توانید مشخص کنید مشتری در چه وضعیت هایی از سفارش قادر به دریافت پیامک باشد.',
					'type'    => 'multicheck',
					'options' => PWSMS()->get_all_statuses(),
				],
				[
					'name'  => 'header_3',
					'label' => '<h2>متن پیامک مشتری</h2>',
					'type'  => 'html',
				],
			] ),

			'sms_product_admin_settings' => apply_filters( 'pwoosms_product_admin_settings', [
				[
					'name'    => 'product_admin_user_meta',
					'label'   => 'یوزر متای موبایل فروشندگان (اختیاری)',
					'desc'    => 'با فعالسازی گزینه بالا یعنی "ارسال پیامک به فروشندگان محصول"، داخل ویرایش و مدیریت هر محصول، یک تب جدید به اسم "پیامک" اضافه خواهد شد که در آنجا میتوانید به صورت دستی شماره موبایل فروشندگان (مدیران محصول) را وارد نمایید. ولی با توجه به اینکه وارد کردن دستی شماره موبایل فروشنده هر محصول ممکن است کار بسیار سخت و زمانبری باشد، این قابلیت وجود خواهد داشت که کلید متای کاربر یا User Meta Key مربوط به شماره موبایل فروشندگان را در این فیلد وارد کنید تا به صورت خودکار پیامک به شماره موبایل ثبت شده برای آن متا ارسال شود.<br>این قابلیت اکثرا زمانی مورد استفاده قرار میگیرد که از افزونه های چند فروشندگی ووکامرس استفاده نمایید. در صورتی که دانش کافی در این مورد را ندارید، بدون نگرانی آن را خالی رها کنید.',
					'type'    => 'text',
					'ltr'     => true,
					'default' => '',
				],
				[
					'name'    => 'product_admin_post_meta',
					'label'   => 'پست متای موبایل فروشندگان (اختیاری)',
					'desc'    => 'بعضی اوقات ممکن است شما از طریق برخی دیگر از افزونه های چند فروشندگی ووکامرس و یا کدنویسی شخصی، شماره موبایل فروشندگان را بجای user_meta در post_meta ی محصول متعلق به آن فروشنده ذخیره نمایید که در این صورت بجای استفاده از یوزر متا میتوانید از پست متا و یا هر دو استفاده نمایید. این بار نیز، در صورتی که دانش کافی در این مورد را ندارید، بدون نگرانی آن را خالی رها کنید.',
					'type'    => 'text',
					'ltr'     => true,
					'default' => '',
				],
				[
					'name'    => 'product_admin_dokan_integration',
					'label'   => 'هماهنگی با دکان',
					'desc'    => 'با فعالسازی این گزینه، پیامک سفارشات هر فروشنده، در وضعیت‌های فعال و تنظیم شده، به شماره موبایل تنظیم شده در مسیر «دکان > فروشنده‌ها » ویرایش فروشنده > تنظیمات عمومی > اطلاعات فروشگاه > تلفن» ارسال می‌شود.',
					'type'    => 'checkbox',
					'default' => 'no',
				],
				[
					'name'    => 'product_admin_meta_order_status',
					'label'   => 'وضعیت های دریافت پیامک',
					'desc'    => 'این وضعیت های دریافت پیامک برای فروشندگانی که از طریق user_meta و یا post_meta تنظیم شده اند، لحاظ خواهد شد. برای تنظیم وضعیت پیامک فروشندگانی که به صورت دستی به محصول اضافه میشوند، میتوانید به صفحه ویرایش همان محصول مراجعه نموده و از تب پیامک، شماره موبایل مدیر آن محصول و وضعیت های سفارش متناظر با آن را اضافه کنید.',
					'type'    => 'multicheck',
					'options' => PWSMS()->get_all_super_admin_statuses(),
				],
				[
					'name'  => 'header_product_admin',
					'label' => '<h2>متن پیامک فروشندگان محصول</h2>',
					'type'  => 'html',
				],

			] ),

			'sms_notif_settings' => apply_filters( 'pwoosms_notif_settings', [
				[
					'name'  => 'header_whatis_notif',
					'label' => '<h2>خبرنامه محصولات چیست؟</h2>',
					'desc'  => 'منظور از خبرنامه محصولات که در نسخه های قبلی افزونه پیامک از آن تحت عنوان "اطلاع رسانی" یاد میشد، آگاه سازی کاربران از جزییات و تغییرات محصولات مورد علاقه شان است.<br>بعنوان مثال کاربران پس از عضویت در خبرنامه محصول میتوانند از فروش ویژه (حراج) شدن آن محصول از طریق پیامک با خبر شوند. و یا در صورتی که محصول مورد نظرشان در سایت موجود شد بلافاصله از این موضوع مطلع گردند. و مثال های دیگری از این دست.',
					'type'  => 'html',
				],
				[
					'name'    => 'enable_notif_sms_main',
					'label'   => 'فعال سازی خبرنامه محصولات',
					'desc'    => 'با فعالسازی این گزینه، خبرنامه پیامکی محصولات فعال می‌شود. در غیر این صورت کلیه قسمت های زیر بی تاثیر خواهند شد.',
					'type'    => 'checkbox',
					'default' => 'no',
				],
				[
					'name'    => 'notif_old_pr',
					'label'   => 'خبرنامه محصولات قدیمی',
					'desc'    => 'خبرنامه هر محصول در صفحه ایجاد و یا ویرایش همان محصول (مدیریت محصول) به صورت مجزا قابل تنظیم است. و این قابلیت وجود دارد که خبرنامه هر محصول شخصی سازی شود. اما در صورتی که قبل از نصب افزونه پیامک ووکامرس دارای محصولات بسیار زیادی بوده اید که ویرایش و تنظیم خبرنامه پیامکی آن ها به صورت تک تک مشکل و زمانبر است، میتوانید تنظیمات زیر که تنظیمات پیشفرض هستند را برای محصولات قبلی سایت خود لحاظ نمایید، تا درصورتی که برای محصولی خبرنامه پیامکی ست نشده بود از همین تنظیمات استفاده شود.',
					'type'    => 'radio',
					'default' => 'no',
					'options' => [
						'yes' => 'اعمال تنظیمات پیشفرض برای محصولات قدیمی',
						'no'  => 'غیرفعالسازی خبرنامه برای محصولات قدیمی',
					],
				],
				[
					'name'  => 'header_2',
					'label' => '<h2>فرم عضویت در خبرنامه</h2>',
					'type'  => 'html',
				],
				[
					'name'    => 'enable_notif_sms',
					'label'   => 'نمایش فرم عضویت در صفحه محصول',
					'desc'    => 'توسط این گزینه میتوانید نحوه نمایش فرم عضویت خبرنامه را در صفحه محصولات تعیین نمایید. در صورتی که قصد استفاده از اکشن های ووکامرس را دارید میتوانید تابع <code>pwsms_shortcode()</code> را به اکشن مورد نظر هوک کنید.',
					'type'    => 'radio',
					'default' => 'no',
					'br'      => true,
					'options' => [
						'on'        => 'نمایش خودکار در بدنه محصول',
						'thumbnail' => 'نمایش خودکار زیر تصویر شاخص',
						'no'        => sprintf( 'نمایش دستی به وسیله هوک های ووکامرس یا ابزارک خبرنامه پیامکی محصولات ووکامرس و یا شورتکد %s', "<code>$shortcode</code>" ),
					],
				],
				[
					'name'    => 'notif_title',
					'label'   => 'متن عضویت در خبرنامه محصولات',
					'desc'    => 'این متن در صفحه محصول به صورت چک باکس ظاهر خواهد شد و کاربر با انتخاب آن میتواند شماره موبایل و گروه های مورد نظر خود را برای عضویت در خبرنامه محصول وارد نماید.',
					'type'    => 'text',
					'default' => "به من از طریق پیامک اطلاع بده",
				],
				[
					'name'    => 'notif_only_loggedin',
					'label'   => 'عضویت فقط برای اعضای سایت',
					'desc'    => 'با فعالسازی این گزینه، فقط کاربران وارد شده قادر به عضویت در خبرنامه محصول خواهند بود.',
					'type'    => 'checkbox',
					'default' => 'no',
				],
				[
					'name'    => 'notif_only_loggedin_text',
					'label'   => 'متن جلوگیری از عضویت مهمانان',
					'desc'    => 'در صورتی که گزینه "عضویت فقط برای اعضای سایت" را فعال کرده باشید، هنگامیکه کاربران مهمان قصد عضویت در خبرنامه محصول را داشته باشند، با این متن وارد شده مواجه خواهند شد.',
					'type'    => 'text',
					'default' => "عضویت در خبرنامه محصول فقط برای اعضای سایت امکان پذیر خواهد بود.",
				],
				[
					'name'  => 'header_notif_group',
					'label' => '<h2>گروه (رویداد) های خبرنامه</h2>',
					'type'  => 'html',
				],
				[
					'name'  => 'header_3',
					'label' => '<h2>رویداد های اتوماتیک</h2>',
					'desc'  => '۳ رویداد اتوماتیک (ویژه شدن یا حراج شدن محصول - موجود شدن محصول - رو به اتمام بودن انبار محصول) برای خبرنامه وجود دارند که پیامک مربوط به این رویداد ها به صورت خودکار به مشترکین خبرنامه ارسال می‌شود و نیازی به ارسال دستی پیامک‌ها توسط شما نیست.<br>توجه داشته باشید که عملکرد گزینه های مربوط به "موجودی و انبار" وابسته به <a href="' . admin_url( 'admin.php?page=wc-settings&tab=products&section=inventory' ) . '" target="_blank">تنظیمات ووکامرس</a> خواهد بود.',
					'type'  => 'html',
				],

				[
					'name'    => 'notif_options',
					'label'   => '<h2>رویداد های دستی</h2>',
					'desc'    => 'علاوه بر ۳ رویداد اتوماتیک ذکر شده، میتوانید گزینه های دلخواه دیگری را نیز به گزینه های خبرنامه اضافه نمایید و از طریق متاباکسی که در صفحه ویرایش محصول اضافه خواهد شد، به هر کدام از مشترکین این گروه ها به صورت دستی پیامک ارسال کنید.
						<br>برای اضافه کردن گزینه ها، همانند نمونه بالا ابتدا یک کد عددی دلخواه تعریف کنید، سپس بعد از قرار دادن عبارت ":" متن مورد نظر را بنویسید.
						دقت کنید که کد عددی هر گزینه بسیار مهم بوده و از تغییر کد مربوط به هر گزینه بعد از ذخیره تنظیمات خودداری نمایید.',
					'type'    => 'textarea',
					'default' => "1:زمانیکه محصول توقف فروش شد\n2:زمانیکه نسخه جدید محصول منتشر شد\n",
				],
				[
					'name'  => 'header_notif_sms',
					'label' => '<h2>پیامک رویداد های اتوماتیک</h2>',
					'type'  => 'html',
				],
				[
					'name'    => 'enable_onsale',
					'label'   => 'زمانیکه محصول حراج شد',
					'desc'    => 'با فعالسازی این گزینه، در صورت حراج نبودن محصول، گزینه "زمانیکه محصول حراج شد" در فرم عضویت خبرنامه نمایش داده خواهد شد.',
					'type'    => 'checkbox',
					'default' => 'no',
				],
				[
					'name'    => 'notif_onsale_text',
					'label'   => 'متن گزینه "زمانیکه محصول حراج شد"',
					'desc'    => 'میتوانید متن دلخواه خود را جایگزین جمله "زمانیکه محصول حراج شد" نمایید.',
					'type'    => 'text',
					'default' => "زمانیکه محصول حراج شد",
				],
				[
					'name'    => 'notif_onsale_sms',
					'label'   => 'متن پیامک "زمانیکه محصول حراج شد"',
					'type'    => 'textarea',
					'default' => "سلام\nقیمت محصول {product_title} از {regular_price} به {onsale_price} کاهش یافت.",
					'row'     => 2,
				],
				[
					'name'  => 'header_null_2',
					'label' => '',
					'type'  => 'html',
				],
				[
					'name'    => 'enable_notif_no_stock',
					'label'   => 'زمانیکه محصول موجود شد',
					'desc'    => 'با فعالسازی این گزینه، در صورت ناموجود بودن محصول، گزینه "زمانیکه محصول موجود شد" در فرم عضویت خبرنامه نمایش داده خواهد شد.',
					'type'    => 'checkbox',
					'default' => 'no',
				],
				[
					'name'    => 'notif_no_stock_text',
					'label'   => 'متن گزینه "زمانیکه محصول موجود شد"',
					'desc'    => 'میتوانید متن دلخواه خود را جایگزین جمله "زمانیکه محصول موجود شد" نمایید.',
					'type'    => 'text',
					'default' => "زمانیکه محصول موجود شد",
				],
				[
					'name'    => 'notif_no_stock_sms',
					'label'   => 'متن پیامک "زمانیکه محصول موجود شد"',
					'type'    => 'textarea',
					'default' => "سلام\nمحصول {product_title} هم اکنون موجود و قابل خرید می‌باشد.",
					'row'     => 2,
				],
				[
					'name'    => 'notif_no_stock_remove_contacts',
					'label'   => 'حذف کاربر از این گروه پس از ارسال',
					'desc'    => 'با فعالسازی این گزینه، پس از ارسال پیامک، گروه "زمانیکه محصول موجود شد" از لیست گروه های اطلاع رسانی به کاربر حذف خواهد شد.',
					'type'    => 'checkbox',
					'default' => "yes",
				],
				[
					'name'  => 'header_null_3',
					'label' => '',
					'type'  => 'html',
				],
				[
					'name'    => 'enable_notif_low_stock',
					'label'   => 'زمانیکه محصول رو به اتمام است',
					'desc'    => 'با فعال سازی این گزینه، اگر موجودی محصول رو به اتمام باشد، گزینه‌ی "زمانی که موجودی رو به اتمام است" در فرم عضویت خبرنامه نمایش داده می شود.',
					'type'    => 'checkbox',
					'default' => 'no',
				],
				[
					'name'    => 'notif_low_stock_text',
					'label'   => 'متن گزینه "زمانیکه محصول رو به اتمام است"',
					'desc'    => 'میتوانید متن دلخواه خود را جایگزین جمله "زمانیکه محصول رو به اتمام است" نمایید.',
					'type'    => 'text',
					'default' => "زمانیکه محصول رو به اتمام است",
				],
				[
					'name'    => 'notif_low_stock_sms',
					'label'   => 'متن پیامک "زمانیکه محصول رو به اتمام است"',
					'desc'    => '',
					'type'    => 'textarea',
					'default' => "سلام\nموجودی محصول {product_title} کم می‌باشد. لطفا در صورت تمایل به خرید سریعتر اقدام نمایید.",
					'row'     => 2,
				],
				[
					'name'  => 'header_null_4',
					'label' => '',
					'type'  => 'html',
				],
				[
					'name'  => 'header_7',
					'label' => '<h2>پیامک رویداد های دستی</h2>',
					'desc'  => 'برای این دسته از رویداد ها، می‌بایست از طریق متاباکسی که در صفحه ویرایش محصول اضافه خواهد شد، به هر کدام از مشترکین این گروه ها به صورت دستی پیامک ارسال کنید.',
					'type'  => 'html',
				],
			] ),
		];

		return apply_filters( 'pwoosms_settings_fields', $settings_fields );
	}

	public function admin_submenu( $pages ) {
		$pages['pwoosms_settings_page'] = 'پیامک ووکامرس';

		return $pages;
	}

	public function settings_page() {
		// Sidebars
		echo '<div>';

		if ( is_plugin_inactive( 'persian-woocommerce/woocommerce-persian.php' ) ) {
			echo '<div class="notice notice-success below-h2">
                <p><img class="نصب شده" src="' . esc_url( PWSMS_URL . '/assets/images/false.png' ) . '"/> برای کارکرد بهتر افزونه پیامک ، و افزوده شدن امکانات بومی مانند شهرها ، اعداد فارسی و... به ووکامرس پیشنهاد می‌کنیم افزونه "ووکامرس فارسی" را نصب نمایید.
                    <a href="' . esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=persian-woocommerce' ) ) . '">نصب سریع</a>
                </p>
            </div>';
		}

		if ( is_plugin_inactive( 'persian-woocommerce-shipping/woocommerce-shipping.php' ) ) {
			echo '<div class="notice notice-error below-h2">
                <p><img class="نصب شده" src="' . esc_url( PWSMS_URL . '/assets/images/false.png' ) . '"/> برای محاسبه خودکار هزینه های حمل و نقل پست پیشتاز و سفارشی و پیک موتوری افزونه "حمل و نقل ووکامرس" را نصب نمایید.
                    <a href="' . esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=persian-woocommerce-shipping' ) ) . '">نصب سریع</a>
                </p>
            </div>';
		}

		echo '</div>';


		// Main content
		echo '<div class="wrap woocommerce persian_woocommerce_sms">';
		$changelog_url = ChangeLog::get_page_url();
		echo '<img alt="Persian WooCommerce SMS" class="logo" src="' . esc_url( PWSMS_URL . '/assets/images/persian-woocommerce-sms-logo.png' ) . '"/>
		<a href="https://wordpress.org/plugins/persian-woocommerce-sms" target="_blank" class="button button-secondary float-left-buttons">نسخه ' . esc_html( PWSMS_VERSION ) . '</a>
		<a href="' . esc_url( $changelog_url ) . '" target="_blank" class="button button-primary float-left-buttons">تاریخچه تغییرات</a>
		<div class="clear"></div>
		<hr class="pwoo_line"/>';

		$this->settings_api->show_navigation();
		$this->settings_api->show_forms();
		echo '</div>';

	}

	public function admin_bar() {
		if ( PWSMS()->get_option( 'enable_admin_bar' ) ) {
			if ( current_user_can( 'manage_woocommerce' ) && is_admin_bar_showing() ) {
				global $wp_admin_bar;
				$wp_admin_bar->add_menu( [
					'id'    => 'adminBar_send',
					'title' => '<span class="ab-icon"></span>پیامک ووکامرس',
					'href'  => admin_url( 'admin.php?page=persian-woocommerce-sms-pro&tab=send' ),
				] );
			}
		}
	}

	public function buyer_settings( $settings ) {

		$statuses = PWSMS()->get_all_statuses();

		foreach ( ( array ) $statuses as $status_val => $status_name ) {

			$_status_name = preg_replace( '/\(.*\)/is', '', $status_name );
			$_status_name = trim( $_status_name );

			$text = [
				[
					'name'    => 'sms_body_' . $status_val,
					'label'   => 'وضعیت ' . $status_name,
					'type'    => 'textarea',
					'default' => "سلام {b_first_name} {b_last_name}\nسفارش {order_id} دریافت شد و هم اکنون در وضعیت " . $_status_name . " می‌باشد.\nآیتم های سفارش : {all_items}\nمبلغ سفارش : {price}\nشماره تراکنش : {transaction_id}",
				],
			];

			if ( 'set-post-tracking-code' == $status_val ) {
				$text[0]['default'] = "{b_first_name} {b_last_name}\nسفارش {order_id} با کد رهگیری  {post_tracking_code} برای شما ارسال شد. پیگیری خرید {post_tracking_url}";
			}

			$settings = array_merge( $settings, $text );
		}


		return $settings;
	}

	public function super_admin_settings( $settings ) {

		$statuses = PWSMS()->get_all_statuses();
		foreach ( ( array ) $statuses as $status_val => $status_name ) {

			$_status_name = preg_replace( '/\(.*\)/is', '', $status_name );
			$_status_name = trim( $_status_name );

			$text = [
				[
					'name'    => 'super_admin_sms_body_' . $status_val,
					'label'   => 'وضعیت ' . $status_name,
					'type'    => 'textarea',
					'row'     => 5,
					'default' => "سلام مدیر\nسفارش {order_id} ثبت شده است و هم اکنون در وضعیت " . $_status_name . " می‌باشد.\nآیتم های سفارش : {all_items}\nمبلغ سفارش : {price}",
				],
			];

			$settings = array_merge( $settings, $text );
		}

		$text     = [
			[
				'name'  => 'header_3',
				'label' => '<h2>متن پیامک موجودی انبار</h2>',
				'desc'  => 'توجه داشته باشید که متن پیامک‌های مربوط به "موجودی و انبار" برای "فروشندگان محصول" نیز اعمال خواهد شد و تنظیمات و آستانه موجودی انبار وابسته به <a href="' . admin_url( 'admin.php?page=wc-settings&tab=products&section=inventory' ) . '" target="_blank">تنظیمات ووکامرس</a> می‌باشد.',
				'type'  => 'html',
			],
			[
				'name'    => 'admin_low_stock',
				'label'   => 'کم بودن موجودی انبار',
				'desc'    => "متن پیامک زمانیکه موجودی انبار کم است.",
				'type'    => 'textarea',
				'row'     => 3,
				'default' => "سلام\nموجودی انبار محصول {product_title} رو به اتمام است.",
			],
			[
				'name'    => 'admin_out_stock',
				'label'   => 'تمام شدن موجودی انبار',
				'desc'    => "متن پیامک زمانیکه موجودی انبار تمام شد.",
				'type'    => 'textarea',
				'row'     => 3,
				'default' => "سلام\nموجودی انبار محصول {product_title} به اتمام رسیده است.",
			],
		];
		$settings = array_merge( $settings, $text );

		return $settings;
	}

	public function product_admin_settings( $settings ) {

		$statuses = PWSMS()->get_all_statuses();

		foreach ( ( array ) $statuses as $status_val => $status_name ) {

			$_status_name = preg_replace( '/\(.*\)/is', '', $status_name );
			$_status_name = trim( $_status_name );

			$text = [
				[
					'name'    => 'product_admin_sms_body_' . $status_val,
					'label'   => 'وضعیت ' . $status_name,
					'type'    => 'textarea',
					'row'     => 4,
					'default' => "سلام\nسفارش {order_id} ثبت شده است و هم اکنون در وضعیت " . $_status_name . " می‌باشد.\nآیتم های سفارش متعلق به شما: {vendor_items}",
				],
			];

			$settings = array_merge( $settings, $text );
		}

		$text = [
			[
				'name'  => 'sms_body_stock_product_admin',
				'label' => '<h2>متن پیامک موجودی انبار</h2>',
				'desc'  => sprintf( 'با توجه به مشترک بودن متن پیامک‌های موجودی انبار بین مدیران کل و فروشندگان محصول، برای تنظیم متن این پیامک‌ها از %s استفاده کنید.', '<a href="' . admin_url( 'admin.php?page=persian-woocommerce-sms-pro&tab=super_admin#sms_super_admin_settings[admin_low_stock]' ) . '" target="_blank">این لینک</a>' ),
				'type'  => 'html',
			],
		];

		$settings = array_merge( $settings, $text );

		return $settings;
	}

	public function footer_note( $text ) {
		if ( isset( $_GET['page'] ) && $_GET['page'] == 'persian-woocommerce-sms-pro' ) {
			return ' این افزونه به صورت رایگان از سوی <a href="http://woosupport.ir/" target="_blank">ووکامرس فارسی</a> ارائه شده است. هر گونه کپی برداری و کسب درآمد از آن توسط سایرین غیر مجاز می‌باشد.';
		}

		return $text;
	}

	public function footer_version( $text ) {
		if ( isset( $_GET['page'] ) && $_GET['page'] == 'persian-woocommerce-sms-pro' ) {
			$text = 'پیامک ووکامرس نگارش ' . esc_html( PWSMS_VERSION );
		}

		return $text;
	}

	public function sanitize_array_text_fields( $array ) {
		foreach ( $array as $key => &$value ) {
			if ( is_array( $value ) ) {
				$value = $this->sanitize_array_text_fields( $value );
			} else {
				$value = sanitize_text_field( $value );
			}
		}

		return $array;
	}
}