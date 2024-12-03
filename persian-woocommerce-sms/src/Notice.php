<?php

namespace PW\PWSMS;

use DOMDocument;

defined( 'ABSPATH' ) || exit;

class Notice {

	public function __construct() {
		add_action( 'admin_notices', [ $this, 'admin_notices' ], 10 );
		add_action( 'wp_ajax_pwsms_dismiss_notice', [ $this, 'dismiss_notice' ] );
		add_action( 'wp_ajax_pwsms_update_notice', [ $this, 'update_notice' ] );
	}

	public function admin_notices() {

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( $this->is_dismiss( 'all' ) ) {
			return;
		}

		foreach ( $this->notices() as $notice ) {

			if ( $notice['condition'] == false || $this->is_dismiss( $notice['id'] ) ) {
				continue;
			}

			$dismissible = $notice['dismiss'] ? 'is-dismissible' : '';

			$notice_id      = esc_attr( $notice['id'] );
			$notice_content = strip_tags( $notice['content'], '<p><a><input><b><img><ul><ol><li>' );

			printf( '<div class="notice pwsms_notice notice-success %s" id="pwsms_%s"><p>%s</p></div>', $dismissible,
				$notice_id, $notice_content );

			break;
		}

		?>
		<script type="text/javascript">
            jQuery(document).ready(function ($) {

                $(document.body).on('click', '.notice-dismiss', function () {

                    let notice = $(this).closest('.pwsms_notice');
                    notice = notice.attr('id');

                    if (notice !== undefined && notice.indexOf('pwsms_') !== -1) {

                        notice = notice.replace('pwsms_', '');

                        $.ajax({
                            url: "<?php echo admin_url( 'admin-ajax.php' ) ?>",
                            type: 'post',
                            data: {
                                notice: notice,
                                action: 'pwsms_dismiss_notice',
                                nonce: "<?php echo wp_create_nonce( 'pwsms_dismiss_notice' ); ?>"
                            }
                        });
                    }

                });

                $.ajax({
                    url: "<?php echo admin_url( 'admin-ajax.php' ) ?>",
                    type: 'post',
                    data: {
                        action: 'pwsms_update_notice',
                        nonce: '<?php echo wp_create_nonce( 'pwsms_update_notice' ); ?>'
                    }
                });
            });
		</script>
		<?php
	}

	public function is_dismiss( $notice_id ): bool {
		return get_transient( 'pwsms_notice_' . $notice_id ) !== false;
	}

	public function notices(): array {

		global $pagenow;

		$post_type    = sanitize_text_field( $_GET['post_type'] ?? null );
		$page         = sanitize_text_field( $_GET['page'] ?? null );
		$tab          = sanitize_text_field( $_GET['tab'] ?? null );
		$has_shipping = function_exists( 'wc_shipping_enabled' ) && wc_shipping_enabled();

		$notices = [
			[
				'id'        => 'nrr_product_reviews',
				'content'   => sprintf( '<b>نظرسنجی خودکار ووکامرس:</b> جهت افزایش تعداد نظرات فروشگاه‌تان، می‌توانید با استفاده از <a href="%s" target="_blank">افزونه نظرسنجی خودکار ندا</a> با ارسال خودکار پیامک، برای هر سفارش از مشتریان خود درخواست ثبت نظر کنید. | کدتخفیف: pwsms20',
					'https://yun.ir/pwsmsneda' ),
				'condition' => $page == 'product-reviews' && is_plugin_inactive( 'nabik-review-reminder/nabik-review-reminder.php' ) && is_plugin_inactive( 'persian-woocommerce-shipping/woocommerce-shipping.php' ),
				'dismiss'   => 6 * MONTH_IN_SECONDS,
			],
			[
				'id'        => 'pw_plugin',
				'content'   => sprintf( '<b>پیامک حرفه‌ای ووکامرس: </b> برای استفاده از امکانات تازه و بهره بردن از قابلیت های جدید افزونه پیامک حرفه‌ای در نسخه های بعدی، لطفا افزونه <a href="%s" target="_blank">ووکامرس فارسی</a> را نصب و فعال نمایید.',
					admin_url( 'plugin-install.php?tab=plugin-information&plugin=persian-woocommerce' ) ),
				'condition' => is_plugin_inactive( 'persian-woocommerce/woocommerce-persian.php' ),
				'dismiss'   => 6 * MONTH_IN_SECONDS,
			],
		];

		$_notices = get_option( 'pwsms_notices', [] );

		foreach ( $_notices['notices'] ?? [] as $_notice ) {

			$_notice['condition'] = 1;

			$rules = $_notice['rules'];

			if ( isset( $rules['pagenow'] ) && $rules['pagenow'] != $pagenow ) {
				$_notice['condition'] = 0;
			}

			if ( isset( $rules['page'] ) && $rules['page'] != $page ) {
				$_notice['condition'] = 0;
			}

			if ( isset( $rules['tab'] ) && $rules['tab'] != $tab ) {
				$_notice['condition'] = 0;
			}

			if ( isset( $rules['active'] ) && is_plugin_inactive( $rules['active'] ) ) {
				$_notice['condition'] = 0;
			}

			if ( isset( $rules['inactive'] ) && is_plugin_active( $rules['inactive'] ) ) {
				$_notice['condition'] = 0;
			}

			if ( isset( $rules['has_shipping'] ) && $rules['has_shipping'] != $has_shipping ) {
				$_notice['condition'] = 0;
			}

			unset( $_notice['rules'] );

			array_unshift( $notices, $_notice );
		}

		return $notices;
	}

	public function dismiss_notice() {

		check_ajax_referer( 'pwsms_dismiss_notice', 'nonce' );

		$this->set_dismiss( $_POST['notice'] );

		die();
	}

	public function set_dismiss( $notice_id ) {

		$notices = wp_list_pluck( $this->notices(), 'dismiss', 'id' );

		if ( isset( $notices[ $notice_id ] ) && $notices[ $notice_id ] ) {
			set_transient( 'pwsms_notice_' . $notice_id, 'DISMISS', intval( $notices[ $notice_id ] ) );
			set_transient( 'pwsms_notice_all', 'DISMISS', HOUR_IN_SECONDS );
		}
	}

	public function update_notice() {
		$update = get_transient( 'pwsms_update_notices' );

		if ( $update ) {
			return;
		}

		set_transient( 'pwsms_update_notices', 1, DAY_IN_SECONDS / 4 );

		check_ajax_referer( 'pwsms_update_notice', 'nonce' );

		$notices = wp_remote_get( 'https://woonotice.ir/pwsms.json', [ 'timeout' => 5, ] );
		$sign    = wp_remote_get( 'https://woohash.ir/pwsms.hash', [ 'timeout' => 5, ] );

		if ( is_wp_error( $notices ) || is_wp_error( $sign ) ) {
			die();
		}

		if ( ! is_array( $notices ) || ! is_array( $sign ) ) {
			die();
		}

		$notices = trim( $notices['body'] );
		$sign    = trim( $sign['body'] );

		if ( sha1( $notices ) !== $sign ) {
			die();
		}

		$notices = json_decode( $notices, JSON_OBJECT_AS_ARRAY );

		if ( empty( $notices ) || ! is_array( $notices ) ) {
			die();
		}

		foreach ( $notices['notices'] as &$_notice ) {

			$doc     = new DOMDocument();
			$content = strip_tags( $_notice['content'], '<p><a><b><img><ul><ol><li>' );
			$content = str_replace( [ 'javascript', 'java', 'script' ], '', $content );
			$doc->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ) );

			foreach ( $doc->getElementsByTagName( '*' ) as $element ) {

				$href  = null;
				$src   = null;
				$style = $element->getAttribute( 'style' );

				if ( $element->nodeName == 'a' ) {
					$href = $element->getAttribute( 'href' );
				}

				if ( $element->nodeName == 'img' ) {
					$src = $element->getAttribute( 'src' );
				}

				foreach ( $element->attributes as $attribute ) {
					$element->removeAttribute( $attribute->name );
				}

				if ( $href && filter_var( $href, FILTER_VALIDATE_URL ) ) {
					$element->setAttribute( 'href', $href );
					$element->setAttribute( 'target', '_blank' );
				}

				if ( $src && filter_var( $src, FILTER_VALIDATE_URL ) && strpos( $src, 'https://woonotice.ir' ) === 0 ) {
					$element->setAttribute( 'src', $src );
				}

				if ( $style ) {
					$element->setAttribute( 'style', $style );
				}
			}

			$_notice['content'] = $doc->saveHTML();
		}

		update_option( 'pwsms_notices', $notices );

		die();
	}

}

