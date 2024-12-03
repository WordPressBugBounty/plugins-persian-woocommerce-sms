<?php

namespace PW\PWSMS;

defined( 'ABSPATH' ) || exit;

class Promotions {

	public function __construct() {

		if ( ! is_admin() || get_option( 'pwoosms_ads_noticemelli' ) ) {
			return;
		}

		add_action( 'pwoosms_settings_form_admin_notices', [ $this, 'admin_notice' ] );
		add_action( 'wp_ajax_pwoosms_notice_dismiss', [ $this, 'ajax_callback' ] );
	}

	public function admin_notice() { ?>
        <div class="notice notice-info below-h2" id="sms-notic-block"><p>
                شما برای شروع کار با پلاگین نیاز به یک پنل پیامک دارید. پیشنهاد ما استفاده از <strong>پنل پیامک
                    ملی پیامک</strong> است. این سامانه 20% تخفیف با کد <code>wcsms20</code> به مدیران سایت های وردپرس
                ارائه می‌دهد.
                <br><br>
                <a href='http://www.melipayamak.com/' class='button button-primary button-large' target='_blank'>خرید
                    پنل ملی پیامک با 20% تخفیف</a>
                <a href='#' onclick='return false;' class='button button-secondary button-large'>من از قبل پنل اس ام
                    اس دارم</a>
            </p>
        </div>
        <script type="text/javascript">
            jQuery(document).on('click', '#sms-notic-block .button-secondary', function () {
                jQuery.ajax({
                    url: ajaxurl,
                    data: {
                        action: 'pwoosms_notice_dismiss'
                    }
                }).done(function () {
                    jQuery("#sms-notic-block").slideUp(1000);
                });
            })
        </script>
		<?php
	}

	public function ajax_callback() {
		update_option( 'pwoosms_ads_noticemelli', 1 );
		die();
	}

}