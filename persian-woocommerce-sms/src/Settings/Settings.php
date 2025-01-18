<?php

namespace PW\PWSMS\Settings;

use PW\PWSMS\PWSMS;
use PW\PWSMS\Shortcode;

defined( 'ABSPATH' ) || exit;

class Settings {
	private $settings_api;

	public function __construct() {

		$this->settings_api = new API();

		add_action( 'init', [ $this, 'update_option_38' ] );

		if ( is_admin() ) {

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
					'woo_ir' => sprintf( '<a href="%s" target="blank" style="background: #763ec2;color: white;padding: 0px 5px;border-radius: 2px;">%s</a>', 'https://woosupport.ir', 'ووکامرس فارسی' )
				];

				return $woo + $actions;
			}, 100, 4 );
		}
	}

	public function update_option_38() {
		$wpdb = $GLOBALS['wpdb'];

		if ( get_option( 'pwoosms_update_gateway_options' ) ) {
			return;
		}

		$wpdb->query( "UPDATE {$wpdb->options} SET option_value=REPLACE(option_value, 's:24:\"persian_woo_sms_username\"', 's:20:\"sms_gateway_username\"') WHERE option_name='sms_main_settings'" );
		$wpdb->query( "UPDATE {$wpdb->options} SET option_value=REPLACE(option_value, 's:24:\"persian_woo_sms_password\"', 's:20:\"sms_gateway_password\"') WHERE option_name='sms_main_settings'" );
		$wpdb->query( "UPDATE {$wpdb->options} SET option_value=REPLACE(option_value, 's:22:\"persian_woo_sms_sender\"', 's:18:\"sms_gateway_sender\"') WHERE option_name='sms_main_settings'" );
		update_option( 'pwoosms_update_gateway_options', '1' );
	}

	public function admin_menu() {
		add_submenu_page( 'persian-wc', 'پیامک ووکامرس', 'پیامک ووکامرس', 'manage_woocommerce', 'persian-woocommerce-sms-pro', [ $this, 'settings_page' ] );
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
				'id'    => 'sms_product_admin_settings',
				'title' => 'پیامک فروشندگان',
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
		];

		return apply_filters( 'pwoosms_settings_sections', $sections );
	}

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
					'name'    => 'enable_super_admin_sms',
					'label'   => 'ارسال پیامک به مدیران کل',
					'desc'    => 'با فعالسازی این گزینه، در هنگام ثبت و یا تغییر سفارش، برای مدیران کل سایت پیامک ارسال می‌گردد.',
					'type'    => 'checkbox',
					'default' => 'no',
				],
				[
					'name'  => 'super_admin_phone',
					'label' => 'شماره موبایل های مدیران کل',
					'desc'  => 'شماره ها را با کاما (,) جدا نمایید.',
					'type'  => 'text',
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
				[
					'name'  => 'sms_body_shortcodes_super_admin',
					'label' => 'شورت کد های قابل استفاده',
					'type'  => 'html',
					'desc'  => $this->shortcodes(),
				],
			] ),

			'sms_buyer_settings' => apply_filters( 'pwoosms_buyer_settings', [
				[
					'name'  => 'enable_buyer',
					'label' => 'ارسال پیامک به مشتری',
					'desc'  => 'با فعالسازی این گزینه، در هنگام ثبت و یا تغییر وضعیت سفارش و یا به صورت دست جمعی، به مشتری پیامک ارسال می‌گردد.',
					'type'  => 'checkbox',
				],
				[
					'name'  => 'enable_metabox',
					'label' => 'متاباکس ارسال پیامک',
					'desc'  => 'با فعالسازی این گزینه، در صورت فعال بودن قابلیت ارسال پیامک به مشتری، در صفحه سفارشات متاباکس ارسال پیامک به مشتریان اضافه می‌شود.',
					'type'  => 'checkbox',
				],
				[
					'name'    => 'buyer_phone_label',
					'label'   => 'عنوان فیلد شماره موبایل',
					'desc'    => 'این عنوان در صفحه تسویه حساب نمایش داده خواهد شد و جایگزین کلمه ی "تلفن" میگردد.',
					'type'    => 'text',
					'default' => 'تلفن همراه',
				],
				[
					'name'    => 'force_enable_buyer',
					'label'   => 'اختیاری بودن دریافت پیامک',
					'desc'    => 'با فعال سازی این گزینه، مشتری میتواند انتخاب کند که پیامک را دریافت کند و یا نکند. در غیر این صورت پیامک همواره ارسال خواهد شد.',
					'type'    => 'radio',
					'default' => 'yes',
					'options' => [
						'no'  => 'بله', // inja no mishe bale , yes mishe kheyr :D... doroste. moshkeli nis.
						'yes' => 'خیر',
					],
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
					'name'    => 'allow_buyer_select_status',
					'label'   => 'انتخاب وضعیت ها توسط مشتری',
					'desc'    => 'با فعالسازی این گزینه، مشتری میتواند در صفحه تسویه حساب، وضعیت های دلخواه خود برای دریافت پیامک را از میان وضعیت های انتخاب شده در بالا، انتخاب نماید. در صورت عدم فعالسازی این قسمت، در تمام وضعیت های انتخاب شده در بالا پیامک ارسال می‌شود.',
					'type'    => 'radio',
					'default' => 'no',
					'options' => [
						'yes' => 'بله',
						'no'  => 'خیر',
					],
				],
				[
					'name'    => 'force_buyer_select_status',
					'label'   => 'الزامی بودن انتخاب حداقل یک وضعیت',
					'desc'    => 'با فعال سازی این گزینه، کاربر می‌بایست حداقل یک وضعیت سفارش را از بین وضعیت های انتخاب شده در بالا انتخاب کند. این قسمت ملزم به "بله" بودن تنظیمات "انتخاب وضعیت ها توسط مشتری" است.',
					'type'    => 'radio',
					'default' => 'no',
					'options' => [
						'yes' => 'بله',
						'no'  => 'خیر',
					],
				],
				[
					'name'    => 'buyer_status_mode',
					'label'   => 'نوع انتخاب وضعیت ها',
					'desc'    => 'این قسمت نیز ملزم به "بله" بودن تنظیمات "انتخاب وضعیت ها توسط مشتری" است. و نوع فیلد انتخاب وضعیت های سفارش توسط مشتری را تعیین میکند.',
					'type'    => 'radio',
					'default' => 'selector',
					'options' => [
						'selector' => 'چند انتخابی',
						'checkbox' => 'چک باکس',
					],
				],
				[
					'name'    => 'buyer_select_status_text_top',
					'label'   => 'متن بالای انتخاب وضعیت ها',
					'desc'    => 'این متن بالای لیست وضعیت ها در صفحه تسویه حساب برای انتخاب مشتری قرار میگیرد.',
					'type'    => 'text',
					'default' => 'وضعیت هایی که مایل به دریافت پیامک هستید را انتخاب نمایید',
				],
				[
					'name'    => 'buyer_select_status_text_bellow',
					'label'   => 'متن پایین انتخاب وضعیت ها',
					'desc'    => 'این متن پایین لیست وضعیت ها در صفحه تسویه حساب برای انتخاب مشتری قرار میگیرد.',
					'type'    => 'text',
					'default' => '',
				],
				[
					'name'  => 'header_3',
					'label' => '<h2>متن پیامک مشتری</h2>',
					'type'  => 'html',
				],
				[
					'name'  => 'sms_body_shortcodes',
					'label' => 'شورت کد های قابل استفاده',
					'type'  => 'html',
					'desc'  => $this->shortcodes(),
				],
			] ),

			'sms_product_admin_settings' => apply_filters( 'pwoosms_product_admin_settings', [
				[
					'name'    => 'enable_product_admin_sms',
					'label'   => 'ارسال پیامک به فروشندگان محصول',
					'desc'    => 'با فعالسازی این گزینه، در هنگام ثبت و یا تغییر سفارش، برای مدیران هر محصول (فروشندگان) پیامک ارسال می‌گردد.',
					'type'    => 'checkbox',
					'default' => 'no',
				],
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
				[
					'name'  => 'sms_body_shortcodes_product_admin',
					'label' => 'شورت کد های قابل استفاده',
					'type'  => 'html',
					'desc'  => $this->shortcodes(),
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
					'desc'    => 'با فعالسازی این گزینه، فقط کاربران لاگین شده قادر به عضویت در خبرنامه محصول خواهند بود.',
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
					'name'  => 'header_4',
					'label' => 'شورت کد های قابل استفاده',
					'desc'  => "<code>{product_id}</code> : آیدی محصول، <code>{sku}</code> : شناسه محصول، <code>{product_title}</code> : عنوان محصول، <code>{regular_price}</code> قیمت اصلی، <code>{onsale_price}</code> : قیمت فروش فوق العاده<br><code>{onsale_from}</code> : تاریخ شروع فروش فوق العاده، <code>{onsale_to}</code> : تاریخ اتمام فروش فوق العاده، <code>{stock}</code> : موجودی انبار",
					'type'  => 'html',
				],
				[
					'name'  => 'header_null_1',
					'label' => '',
					'type'  => 'html',
				],
				[
					'name'    => 'enable_onsale',
					'label'   => 'زمانیکه محصول حراج شد',
					'desc'    => 'با فعالسازی این گزینه، در صورت حراج نبودن محصول، گزینه "زمانیکه که محصول حراج شد" در فرم عضویت خبرنامه نمایش داده خواهد شد.',
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
					'label'   => 'زمانیکه که محصول موجود شد',
					'desc'    => 'با فعالسازی این گزینه، در صورت ناموجود بودن محصول، گزینه "زمانیکه که محصول موجود شد" در فرم عضویت خبرنامه نمایش داده خواهد شد.',
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
					'desc'    => 'با فعالسازی این گزینه، در صورتی که موجودی انبار زیاد بود، گزینه "زمانیکه که محصول رو به اتمام است" در فرم عضویت خبرنامه نمایش داده خواهد شد.',
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

	public function shortcodes() {

		$shortcode_list = apply_filters( 'pwoosms_shortcodes_list', '' );

		$product_admin_shortcodes = '';

		if ( ! empty( $_GET['tab'] ) && $_GET['tab'] == 'product_admin' ) {
			$product_admin_shortcodes = "
				<strong>شورتکدهای اختصاصی فروشندگان : </strong><br>
				<code>{vendor_items}</code> = محصولات سفارش هر فروشنده   ، 
				<code>{vendor_items_qty}</code> = محصولات سفارش هر فروشنده بهمراه تعداد   ،<br>
				<code>{count_vendor_items}</code> = تعداد محصولات سفارش هر فروشنده   ،
				<code>{vendor_price}</code> = مجموع قیمت محصولات سفارش هر فروشنده   ، <br><br>
			";
		}

		return "
		<a href='' onclick='jQuery(\".pwoosms_settings_shortcodes\").slideToggle(); return false;' style='text-decoration: none;'>
			برای مشاهده شورتکدهای قابل استفاده در متن پیامک‌ها کلیک کنید.
		</a>
		<div class='pwoosms_settings_shortcodes' style='display: none'>
			<strong>جزییات سفارش : </strong><br>
			<code>{mobile}</code> = شماره موبایل مشتری   ، 
			<code>{phone}</code> = شماره تلفن مشتری   ،
			<code>{email}</code> = ایمیل مشتری   ،
			<code>{status}</code> = وضعیت سفارش   ، <br>
			<code>{all_items}</code> = محصولات سفارش   ،
            <code>{all_items_full}</code> = محصولات سفارش با نام کامل متغیر   ،
			<code>{all_items_qty}</code> = محصولات سفارش بهمراه تعداد   ،
			<code>{count_items}</code> = تعداد محصولات سفارش   ،<br>
			<code>{price}</code> = مبلغ سفارش   ،
			<code>{post_id}</code> = شماره سفارش اصلی   ، 
			<code>{order_id}</code> = شماره سفارش   ،
			<code>{transaction_id}</code> = شماره تراکنش   ،<br>
			<code>{date}</code> = تاریخ سفارش   ،
			<code>{description}</code> = توضیحات مشتری   ،
			<code>{payment_method}</code> = روش پرداخت   ،
			<code>{shipping_method}</code> = روش ارسال   ،<br><br>
		
			<strong>جزییات صورت حساب : </strong><br>
			<code>{b_first_name}</code> = نام مشتری   ،
			<code>{b_last_name}</code> = نام خانوادگی مشتری   ،
			<code>{b_company}</code> = نام شرکت   ،
			<code>{b_country}</code> = کشور   ،<br>
			<code>{b_state}</code> = ایالت/استان   ،
			<code>{b_city}</code> = شهر   ،
			<code>{b_address_1}</code> = آدرس 1   ،
			<code>{b_address_2}</code> = آدرس 2   ،
			<code>{b_postcode}</code> = کد پستی   ،<br><br>
		
			<strong>جزییات حمل و نقل : </strong><br>
			<code>{sh_first_name}</code> = نام مشتری   ،
			<code>{sh_last_name}</code> = نام خانوادگی مشتری   ،
			<code>{sh_company}</code> = نام شرکت   ،
			<code>{sh_country}</code> = کشور   ،<br>  
			<code>{sh_state}</code> = ایالت/استان   ،
			<code>{sh_city}</code> = شهر   ،
			<code>{sh_address_1}</code> = آدرس 1   ،
			<code>{sh_address_2}</code> = آدرس 2   ،
			<code>{sh_postcode}</code> = کد پستی   ،<br><br>
		
			{$product_admin_shortcodes}
		
			{$shortcode_list}
		</div>
	";
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
                <p><img class="نصب شده" src="' . PWSMS_URL . '/assets/images/false.png' . '"/> برای کارکرد بهتر افزونه پیامک ، و افزوده شدن امکانات بومی مانند شهرها ، اعداد فارسی و... به ووکامرس پیشنهاد می‌کنیم افزونه "ووکامرس فارسی" را نصب نمایید.
                    <a href="' . admin_url( 'plugin-install.php?tab=plugin-information&plugin=persian-woocommerce' ) . '">نصب سریع</a>
                </p>
            </div>';
		}

		if ( is_plugin_inactive( 'persian-woocommerce-shipping/woocommerce-shipping.php' ) ) {
			echo '<div class="notice notice-error below-h2">
                <p><img class="نصب شده" src="' . PWSMS_URL . '/assets/images/false.png' . '"/> برای محاسبه خودکار هزینه های حمل و نقل پست پیشتاز و سفارشی و پیک موتوری افزونه "حمل و نقل ووکامرس" را نصب نمایید.
                    <a href="' . admin_url( 'plugin-install.php?tab=plugin-information&plugin=persian-woocommerce-shipping' ) . '">نصب سریع</a>
                </p>
            </div>';
		}

		echo '</div>';


		// Main content
		echo '<div class="wrap woocommerce persian_woocommerce_sms">';
		echo '<img class="logo" src="' . PWSMS_URL . '/assets/images/persian-woocommerce-sms-logo.png' . '"/>
		<a href="https://wordpress.org/plugins/persian-woocommerce-sms" target="_blank" class="button button-secondary float-left-buttons">نسخه ' . PWSMS_VERSION . '</a>
		&nbsp;
		<a href="https://hits.ir/sms-chl" target="_blank" class="button button-primary float-left-buttons">تاریخچه تغییرات</a>
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
					'desc'    => "میتوانید از شورت کد های معرفی شده در بالای این بخش استفاده نمایید.",
					'type'    => 'textarea',
					'default' => "سلام {b_first_name} {b_last_name}\nسفارش {order_id} دریافت شد و هم اکنون در وضعیت " . $_status_name . " می‌باشد.\nآیتم های سفارش : {all_items}\nمبلغ سفارش : {price}\nشماره تراکنش : {transaction_id}",
				],
			];

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
					'desc'    => "میتوانید از شورت کد های معرفی شده در بالای این بخش استفاده نمایید.",
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
				'name'  => 'header_4',
				'label' => 'شورت کد های قابل استفاده',
				'desc'  => "شورت کد های قابل استفاده در متن پیامک‌های مرتبط با موجوی انبار :<br><code>{product_id}</code> : آیدی محصول، <code>{sku}</code> : شناسه محصول، <code>{product_title}</code> : عنوان محصول، <code>{stock}</code> : موجودی انبار",
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
					'desc'    => "میتوانید از شورت کد های معرفی شده در بالای این بخش استفاده نمایید.",
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
			$text = 'پیامک ووکامرس نگارش ' . PWSMS_VERSION;
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