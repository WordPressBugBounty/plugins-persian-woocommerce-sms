<?php

namespace PW\PWSMS;

use PW\PWSMS\Enums\EventsEnum;
use PWS_Tapin;
use WC_Order;

defined( 'ABSPATH' ) || exit;

class Orders {

	public function __construct() {

		add_action( 'wp_enqueue_scripts', [ $this, 'checkout_script' ] );
		add_action( 'woocommerce_checkout_process', [ $this, 'checkout_fields_validation' ] );

		/*بعد از تغییر وضعیت سفارش*/
		add_action( 'woocommerce_order_status_changed', [ $this, 'send_order_sms' ], 99, 3 );

		/*بعد از ثبت سفارش*/
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'send_order_sms' ], 99, 1 );
		add_action( 'woocommerce_process_shop_order_meta', [ $this, 'send_order_sms' ], 999, 1 );

		/*جلوگیری از ارسال بعد از ثبت مجدد سفارش از صفحه تسویه حساب*/
		add_action( 'woocommerce_resume_order', function () {
			remove_action( 'woocommerce_checkout_order_processed', [ $this, 'send_order_sms' ], 99 );
		} );

		/*هنگامی که بارکد پستی مرسوله در تاپین ثبت شد*/
		add_action( 'pws_save_order_post_barcode', [ $this, 'send_order_post_tracking_code' ], 100, 2 );

		add_filter( 'woocommerce_form_field_pwoosms_multiselect', [
			Helper::class,
			'multi_select_and_checkbox',
		], 11, 4 );
		add_filter( 'woocommerce_form_field_pwoosms_multicheckbox', [
			Helper::class,
			'multi_select_and_checkbox',
		], 11, 4 );

		if ( is_admin() ) {
			add_action( 'woocommerce_admin_order_data_after_billing_address', [
				$this,
				'buyer_sms_details',
			], 10, 1 );
			add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'change_sms_text_js' ] );
			add_action( 'wp_ajax_change_sms_text', [ $this, 'change_sms_text_callback' ] );
			add_action( 'wp_ajax_nopriv_change_sms_text', [ $this, 'change_sms_text_callback' ] );
		}

	}

	public function checkout_script() {

		if ( ! function_exists( 'is_checkout' ) || ! function_exists( 'wc_enqueue_js' ) ) {
			return;
		}

		if ( is_checkout() ) {

			wp_register_script( 'pwoosms-multiselect', PWSMS_URL . '/assets/js/multi-select.js', [ 'jquery' ], PWSMS_VERSION, true );

			wp_localize_script( 'pwoosms-multiselect', 'pwoosms', [
				'ajax_url'                  => admin_url( 'admin-ajax.php' ),
				'chosen_placeholder_single' => 'گزینه مورد نظر را انتخاب نمایید.',
				'chosen_placeholder_multi'  => 'گزینه های مورد نظر را انتخاب نمایید.',
				'chosen_no_results_text'    => 'هیچ گزینه ای وجود ندارد.',
			] );
			wp_enqueue_script( 'pwoosms-multiselect' );

		}
	}

	public function checkout_fields_validation() {

		$mobile_meta = PWSMS()->buyer_mobile_meta();

		$_POST[ $mobile_meta ] = PWSMS()->modify_mobile( sanitize_text_field( $_POST[ $mobile_meta ] ?? null ) );

		if ( count( PWSMS()->get_buyer_allowed_statuses() ) < 0 ) {
			return;
		}

		if ( ! PWSMS()->validate_mobile( $_POST[ $mobile_meta ] ?? null ) ) {
			wc_add_notice( 'شماره موبایل معتبر نیست.', 'error' );
		}

	}

	public function buyer_sms_details( WC_Order $order ) {

		if ( count( PWSMS()->get_buyer_allowed_statuses() ) < 0 ) {
			return;
		}

		$mobile = PWSMS()->buyer_mobile( $order->get_id() );

		if ( empty( $mobile ) ) {
			return;
		}

		if ( ! PWSMS()->validate_mobile( $mobile ) ) {
			echo '<p>شماره موبایل مشتری معتبر نیست.</p>';

			return;
		}

		echo '<p>';
	}

	public function send_order_post_tracking_code( WC_Order $order, $tracking_code ) {

		if ( ! class_exists( 'PWS_Tapin' ) || PWS_Tapin::is_enable() ) {
			return;
		}

		$order_id     = $order->get_id();
		$order_status = $order->get_status();
		$mobile       = PWSMS()->buyer_mobile( $order_id );
		$message      = PWSMS()->get_option( 'sms_body_set-post-tracking-code' );
		$data         = [
			'post_id' => $order_id,
			'mobile'  => $mobile,
			'type'    => EventsEnum::SUPER_ADMIN_AUTOMATIC_ORDER,
			'message' => PWSMS()->replace_short_codes( $message, $order_status, $order, [ 'post_tracking_code' => $tracking_code, 'post_tracking_url' => 'https://radgir.net' ] ),
		];

		if ( ( $result = PWSMS()->send_sms( $data ) ) === true ) {
			$order->add_order_note( sprintf( 'پیامک کد رهگیری مرسوله با موفقیت به مشتری با شماره %s ارسال گردید.', $mobile ) );
		} else {
			$order->add_order_note( sprintf( 'پیامک کد رهگیری بخاطر خطا به مشتری با شماره %s ارسال نشد.<br>پاسخ وبسرویس: %s', $mobile, $result ) );
		}
	}

	public function send_order_sms( int $order_id, $old_status = '', $new_status = 'created' ) {

		if ( current_action() == 'woocommerce_process_shop_order_meta' ) {
			if ( ! is_admin() ) {
				return;
			}
		} else {
			remove_action( 'woocommerce_process_shop_order_meta', [ $this, 'send_order_sms' ], 999 );
		}

		$new_status = PWSMS()->modify_status( $new_status );

		if ( ! $order_id ) {
			return;
		}

		$order = new WC_Order( $order_id );

		$order_page = ( $_POST['is_shop_order'] ?? null ) == 'true';

		if ( ( $order_page && ! empty( $_POST['sms_order_send'] ) ) || ( ! $order_page && $this->buyer_can_get_sms( $order_id, $new_status ) ) ) {

			$mobile  = PWSMS()->buyer_mobile( $order_id );
			$message = isset( $_POST['sms_order_text'] ) ? sanitize_textarea_field( $_POST['sms_order_text'] ) : PWSMS()->get_option( 'sms_body_' . $new_status );

			$data = [
				'post_id' => $order_id,
				'type'    => EventsEnum::CUSTOMER_AUTOMATIC_ORDER,
				'mobile'  => $mobile,
				'message' => PWSMS()->replace_short_codes( $message, $new_status, $order ),
			];

			if ( ( $result = PWSMS()->send_sms( $data ) ) === true ) {
				$order->add_order_note( sprintf( 'پیامک با موفقیت به مشتری با شماره %s ارسال گردید.', $mobile ) );
			} else {
				$order->add_order_note( sprintf( 'پیامک بخاطر خطا به مشتری با شماره %s ارسال نشد.<br>پاسخ وبسرویس: %s', $mobile, $result ) );
			}
		}

		// send sms to Super Admin, Excluded sub orders
		if (
			in_array( $new_status, (array) PWSMS()->get_option( 'super_admin_order_status' ) ) &&
			! $order->get_parent_id()
		) {

			$mobile  = PWSMS()->get_option( 'super_admin_phone' );
			$message = PWSMS()->get_option( 'super_admin_sms_body_' . $new_status );

			$data = [
				'post_id' => $order_id,
				'type'    => EventsEnum::SUPER_ADMIN_AUTOMATIC_ORDER,
				'mobile'  => $mobile,
				'message' => PWSMS()->replace_short_codes( $message, $new_status, $order ),
			];

			Bot::send_async( $data );

			if ( ( $result = PWSMS()->send_sms( $data ) ) === true ) {
				$order->add_order_note( sprintf( 'پیامک با موفقیت به مدیر کل با شماره %s ارسال گردید.', $mobile ) );
			} else {
				$order->add_order_note( sprintf( 'پیامک بخاطر خطا به مدیر کل با شماره %s ارسال نشد.<br>پاسخ وبسرویس: %s', $mobile, $result ) );
			}

		}

		$order_products = PWSMS()->get_product_lists( $order, 'product_id' );

		if ( $order->get_parent_id() ) {

			$sources = [
				'dokan_vendor',
				'user_meta',
				'post_meta',
			];

		} else {

			$sources = [
				'product_meta',
			];

		}

		$mobiles = PWSMS()->product_admin_mobiles( $order_products['product_id'], $new_status, $sources );

		foreach ( (array) $mobiles as $mobile => $product_ids ) {

			$vendor_items = PWSMS()->product_admin_items( $order_products, $product_ids );
			$message      = PWSMS()->get_option( 'product_admin_sms_body_' . $new_status );

			$data = [
				'post_id' => $order_id,
				'type'    => EventsEnum::PRODUCT_MANAGER_AUTOMATIC_ORDER,
				'mobile'  => $mobile,
				'message' => PWSMS()->replace_short_codes( $message, $new_status, $order, $vendor_items ),
			];

			if ( ( $result = PWSMS()->send_sms( $data ) ) === true ) {
				$order->add_order_note( sprintf( 'پیامک با موفقیت به مدیر محصول با شماره %s ارسال گردید.', $mobile ) );
			} else {
				$order->add_order_note( sprintf( 'پیامک بخاطر خطا به مدیر محصول با شماره %s ارسال نشد.<br>پاسخ وبسرویس: %s', $mobile, $result ) );
			}
		}

	}

	public function buyer_can_get_sms( int $order_id, string $new_status ): bool {

		if ( ! $order_id ) {
			return false;
		}

		$order = wc_get_order( $order_id );

		if ( ! PWSMS()->is_wc_order( $order ) ) {
			return false;
		}

		if ( ! PWSMS()->validate_mobile( PWSMS()->buyer_mobile( $order_id ) ) ) {
			return false;
		}

		$sub_orders = wc_get_orders( [
			'parent' => $order_id,
			'limit'  => 1,
			'return' => 'ids',
		] );

		if ( ! $order->get_parent_id() && ! empty( $sub_orders ) ) {
			return false;
		}

		$allowed_status = array_keys( PWSMS()->get_buyer_allowed_statuses() );

		$buyer_can_get_sms = in_array( $new_status, $allowed_status );

		return apply_filters( 'pwoosms_buyer_can_get_order_sms', $buyer_can_get_sms, $order, $new_status );
	}

	public function change_sms_text_js( WC_Order $order ) {

		if ( PWSMS()->validate_mobile( PWSMS()->buyer_mobile( $order->get_id() ) ) ) { ?>
			<script type="text/javascript">
                jQuery(document).ready(function ($) {
                    $("#order_status").change(function () {
                        $("#pwoosms_textbox").html('<img src="<?php echo esc_url( PWSMS_URL . '/assets/images/ajax-loader.gif' ); ?>" />');
                        $.ajax({
                            url: "<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>",
                            type: "post",
                            data: {
                                action: "change_sms_text",
                                security: "<?php echo esc_js( wp_create_nonce( "change-sms-text" ) ); ?>",
                                order_id: "<?php echo intval( $order->get_id() ); ?>",
                                order_status: $("#order_status").val()
                            },
                            success: function (response) {
                                $("#pwoosms_textbox").html(response);
                            }
                        });
                    });
                });
			</script>
			<p class="form-field form-field-wide" id="pwoosms_textbox_p">
				<span id="pwoosms_textbox" class="pwoosms_textbox"></span>
			</p>
			<?php
		}
	}

	public function change_sms_text_callback() {

		check_ajax_referer( 'change-sms-text', 'security' );

		$order_id = intval( $_POST['order_id'] ?? 0 );

		if ( empty( $order_id ) ) {
			die( 'خطای آیجکس رخ داده است.' );
		}

		$new_status = '';

		if ( isset( $_POST['order_status'] ) ) {
			$_order_status = is_array( $_POST['order_status'] ) ? array_map( 'sanitize_text_field', $_POST['order_status'] ) : sanitize_text_field( $_POST['order_status'] );
			$new_status    = PWSMS()->modify_status( $_order_status );
		}

		$order   = new WC_Order( $order_id );
		$message = PWSMS()->get_option( 'sms_body_' . $new_status );
		$message = PWSMS()->replace_short_codes( $message, $new_status, $order );

		echo '<textarea id="sms_order_text" name="sms_order_text" style="width:100%;height:120px;"> ' . esc_attr( $message ) . ' </textarea>';
		echo '<input type="hidden" name="is_shop_order" value="true" />';

		if ( $this->buyer_can_get_sms( $order_id, $new_status ) ) {
			$sms_checked = 'checked="checked"';
			$description = 'با توجه به تنظیمات و انتخاب ها، مشتری باید این پیامک را دریافت کند. ولی میتوانید ارسال پیامک به وی را از طریق این چک باکس غیرفعال نمایید.';
		} else {
			$sms_checked = '';
			$description = 'با توجه به تنظیمات و انتخاب ها، مشتری نباید این پیامک را دریافت کند. ولی میتوانید ارسال پیامک به وی را از طریق این چک باکس فعال نمایید.';
		}

		echo '<input type="checkbox" id="sms_order_send" class="sms_order_send" name="sms_order_send" value="true" style="margin-top:2px;width:20px; float:right" ' . $sms_checked . '/>
					<label class="sms_order_send_label" for="sms_order_send" >ارسال پیامک به مشتری</label>
					<span class="description">' . esc_attr( $description ) . '</span>';

		die();
	}
}

