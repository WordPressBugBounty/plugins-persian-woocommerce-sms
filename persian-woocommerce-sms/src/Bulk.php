<?php

namespace PW\PWSMS;

defined( 'ABSPATH' ) || exit;

class Bulk {

	public function __construct() {
		add_action( 'pwoosms_settings_form_bottom_sms_send', [ $this, 'bulk_form' ] );


		add_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this, 'add_bulk_action' ], 100, 1 );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', [ $this, 'handle_bulk_actions' ], 10, 3 );

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	public function add_bulk_action( $actions ) {
		$actions['send_sms'] = "ارسال پیامک دسته جمعی";

		return $actions;
	}


	public function admin_enqueue_scripts() {

		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'persian-woocommerce-sms-pro' || ! isset( $_GET['tab'] ) || $_GET['tab'] !== 'send' ) {
			return;
		}

		wp_enqueue_style( 'pwsms-sweetalert2' );
		wp_enqueue_script( 'pwsms-sweetalert2' );

		wp_enqueue_script( 'pwsms-bulk-sms', PWSMS_URL . '/assets/js/bulk-sms.js', [ 'pwsms-sweetalert2', 'jquery' ], PWSMS_VERSION, true );
		wp_localize_script( 'pwsms-bulk-sms', 'pwsms_bulk_sms', [
			'rest_url' => esc_url_raw( rest_url() ),
			'nonce'    => wp_create_nonce( 'wp_rest' )
		] );
	}

	public function bulk_form() {
		$mobiles = $_POST['pwsms_mobiles'] ?? '';
		?>

		<div class="notice notice-info below-h2">
			<p>با استفاده از قسمت ارسال پیامک، می‌توانید آزمایش کنید که آیا پنل پیامک شما به خوبی به افزونه متصل شده است یا خیر.</p>
		</div>

		<form id="pwoosms-send-sms-bulk-form">

			<p>
				<label for="pwoosms_mobile">شماره دریافت کننده</label><br>
				<input type="text" name="pwoosms_mobile" id="pwoosms_mobile"
				       style="direction:ltr; text-align:left; width:100%;"
				       value="<?php echo $mobiles; ?>"
				/>
				<span>شماره‌ها را با کاما (,) جدا نمایید.</span>
			</p>

			<p>
				<label for="pwoosms_message">متن پیامک</label><br>
				<textarea name="pwoosms_message" id="pwoosms_message" rows="10" style="width:100%;"></textarea>
				<span>متن دلخواهی که می‌خواهید ارسال شود را وارد کنید.</span>
			</p>

			<p>
				<button type="submit" class="button button-primary" id="pwoosms-submit-button" style="display: inline-flex; align-items: center;">
					<span class="text">ارسال پیامک</span>
				</button>

			</p>
		</form>
		<?php
	}


	public function handle_bulk_actions( string $redirect_to, string $action, array $post_ids ) {

		if ( $action != 'send_sms' ) {
			return;
		}

		$mobiles = [];

		foreach ( $post_ids as $order_id ) {
			$mobiles[] = PWSMS()->buyer_mobile( $order_id );
		}

		$mobiles = implode( ',', array_unique( array_filter( $mobiles ) ) );

		echo '<form method="POST" name="pwsms_order_bulk_send" action="' . esc_url( admin_url( 'admin.php?page=persian-woocommerce-sms-pro&tab=send' ) ) . '">
		<input type="hidden" value="' . esc_attr( $mobiles ) . '" name="pwsms_mobiles" />
		</form>
		<script language="javascript" type="text/javascript">document.pwsms_order_bulk_send.submit(); </script>';
		exit();
	}
}

