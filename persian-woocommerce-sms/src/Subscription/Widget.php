<?php

namespace PW\PWSMS\Subscription;

use PW\PWSMS\Settings\Settings;
use PW\PWSMS\Shortcode;
use WP_Widget;

defined( 'ABSPATH' ) || exit;

class Widget extends WP_Widget {

	private static int $form_id = 0;
	private static array $groups = [];
	private bool $enable_notification = false;

	public function __construct() {

		parent::__construct(
			'WoocommerceIR_Widget_SMS',
			'خبرنامه پیامکی محصولات ووکامرس'
		);
		$shortcode_tag = Shortcode::shortcode( true, true );
		add_shortcode( $shortcode_tag, [ $this, 'display_form' ] );

		$this->enable_notification = PWSMS()->get_option( 'enable_notif_sms_main' );

		if ( $this->enable_notification ) {
			add_action( 'woocommerce_product_thumbnails', [ $this, 'show_in_single_product' ], 100 );
			add_action( 'woocommerce_single_product_summary', [ $this, 'show_in_single_product' ], 39 );
		}
	}

	/*widget*/
	public function form( $instance ) {

		$title = isset( $instance['title'] ) ? $instance['title'] : 'خبرنامه پیامکی'; ?>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php echo esc_html( 'عنوان:' ); ?>
				<span class="description">این ابزارک را فقط باید در صفحه محصولات استفاده کنید.</span>
			</label>

			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
			       name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
			       value="<?php echo esc_attr( $title ); ?>"/>
		</p>
		<?php
	}

	/*widget*/
	public function update( $new_instance, $old_instance ) {

		$instance = ! empty( $old_instance ) && is_array( $old_instance ) ? $old_instance : [];

		if ( ! empty( $new_instance['title'] ) ) {
			$instance['title'] = strip_tags( $new_instance['title'] );
		}

		if ( ! isset( $instance['title'] ) ) {
			$instance['title'] = '';
		}

		return $instance;
	}

	/*widget*/
	public function widget( $args, $instance ) {

		if ( ! $this->enable_notification || ! is_product() ) {
			return;
		}

		$groups = $this->get_groups();
		if ( empty( $groups ) ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$title = apply_filters( 'widget_title', $instance['title'] );
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . esc_attr( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		Shortcode::shortcode();

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/*نمایش در صفحه محصول*/
	private function get_groups( $product_id = '' ) {

		if ( empty( self::$groups ) ) {
			$product_id   = PWSMS()->product_ID( $product_id );
			self::$groups = Contacts::get_groups( $product_id, true, true );
		}

		return self::$groups;
	}

	/*فرم ثبت شماره برای محصول*/
	public function show_in_single_product() {

		$product_id = intval( get_the_ID() );
		$product    = wc_get_product( $product_id );
		if ( ! PWSMS()->is_wc_product( $product ) ) {
			return;
		}

		$is_old_product = ! $product->get_meta( '_is_sms_set', true );

		if ( $is_old_product && ! PWSMS()->get_option( 'notif_old_pr' ) ) {
			$this->enable_notification = false;

			return;
		}

		$show_form = PWSMS()->get_sms_setting( 'enable_notif_sms', $product_id );
		if ( ! PWSMS()->maybe_bool( $show_form ) ) { //این شرط اگر ریترن کنه یعنی نمایش دستی انتخاب شده
			return;
		}

		if ( strval( $show_form ) == 'thumbnail' ) {
			$stop = current_action() != 'woocommerce_product_thumbnails';
		} else {
			$stop = current_action() == 'woocommerce_product_thumbnails';
		}

		if ( $stop ) {
			return;
		}

		$this->display_form( $product_id );
	}

	public function display_form( $product = '' ) {

		if ( ! $this->enable_notification ) {
			return;
		}

		$product_id = intval( PWSMS()->product_ID( $product ) );
		if ( ! is_product() || empty( $product_id ) ) {
			return;
		}

		$product = wc_get_product( $product_id );

		if ( ! PWSMS()->is_wc_product( $product ) ) {
			return;
		}

		$groups = $this->get_groups( $product_id );

		if ( empty( $groups ) ) {
			return;
		}

		do_action( 'pwoosms_before_product_newsletter_form', $product );

		$id = ++ self::$form_id;

		$can_be_subscribe = ! PWSMS()->has_notif_condition( 'notif_only_loggedin', $product_id ) || is_user_logged_in();

		$disabled = '';
		if ( ! $can_be_subscribe ) {
			$disabled = 'disabled="disabled"';
		}

		?>

		<form class="sms-notif-form" id="sms-notif-form-<?php echo intval( $id ); ?>" method="post">
			<div style="display:none !important;width:0 !important;height:0 !important;">
				<img style="width:16px;display:inline;"
				     src="<?php echo esc_url( PWSMS_URL . '/assets/images/tick.png' ); ?>"/>
				<img style="width:16px;display:inline;"
				     src="<?php echo esc_url( PWSMS_URL . '/assets/images/false.png' ); ?>"/>
				<img style="width:16px;display:inline;"
				     src="<?php echo esc_url( PWSMS_URL . '/assets/images/ajax-loader.gif' ); ?>"/>
			</div>

			<div class="sms-notif-enable-p" id="sms-notif-enable-p-<?php echo intval( $id ); ?>">
				<label id="sms-notif-enable-label-<?php echo intval( $id ); ?>" class="sms-notif-enable-label"
				       for="sms-notif-enable-<?php echo intval( $id ); ?>">
					<input type="checkbox" id="sms-notif-enable-<?php echo intval( $id ); ?>" class="sms-notif-enable"
					       name="sms_notif_enable"
					       value="1">
					<strong><?php echo esc_attr( PWSMS()->get_sms_setting( 'notif_title', $product_id ) ); ?></strong>
				</label>
			</div>

			<div class="sms-notif-content" id="sms-notif-content">
				<?php foreach ( $groups as $code => $text ) : ?>
					<label class="sms-notif-groups-label sms-notif-groups-label-<?php echo esc_attr( $code ); ?>"
					       for="sms-notif-groups-<?php echo esc_attr( $code . '_' . $id ); ?>">
						<input type="checkbox"
						       id="sms-notif-groups-<?php echo esc_attr( $code . '_' . $id ); ?>" <?php echo esc_attr( $disabled ); ?>
						       class="sms-notif-groups" name="sms_notif_groups[]"
						       value="<?php echo esc_attr( $code ); ?>"/>
						<?php echo esc_html( $text ); ?>
					</label><br>
					<!--</p>-->
				<?php endforeach; ?>

				<div class="sms-notif-mobile-div">
					<input type="text" id="sms-notif-mobile-<?php echo intval( $id ); ?>" class="sms-notif-mobile"
					       name="sms_notif_mobile"
					       value="<?php echo esc_attr( get_user_meta( get_current_user_id(), PWSMS()->buyer_mobile_meta(),
						       true ) ); ?>"
					       style="text-align: left; direction: ltr" <?php echo esc_attr( $disabled ); ?>
					       title="شماره موبایل" placeholder="شماره موبایل"/>
				</div>

				<?php if ( ! $can_be_subscribe ) : ?>
					<p id="sms-notif-disabled-<?php echo intval( $id ); ?>" class="sms-notif-disabled">
						<?php echo esc_attr( PWSMS()->get_sms_setting( 'notif_only_loggedin_text', $product_id ) ); ?>
					</p>
				<?php else : ?>
					<button id="sms-notif-submit-<?php echo intval( $id ); ?>"
					        class="sms-notif-submit single_add_to_cart_button button alt"
					        style="margin-top: 5px;"
					        type="submit">ثبت
					</button>
				<?php endif; ?>

				<p id="sms-notif-result-p-<?php echo intval( $id ); ?>" class="sms-notif-result-p">
					<span id="sms-notif-result-<?php echo intval( $id ); ?>" class="sms-notif-result"></span>
				</p>
			</div>
		</form>

		<?php
		do_action( 'pwoosms_after_product_newsletter_form', $product );

		if ( $id == 1 ) {

			wp_enqueue_script(
				'pwsms-notification',
				PWSMS_URL . '/assets/js/sms-notification.js',
				[ 'jquery' ],
				PWSMS_VERSION,
				true
			);

			wp_localize_script(
				'pwsms-notification',
				'pwsms_notification',
				[
					'rest_url'   => esc_url_raw( rest_url() ),
					'nonce'      => wp_create_nonce( 'wp_rest' ),
					'product_id' => $product_id,
					'loader'     => esc_url( PWSMS_URL . '/assets/images/ajax-loader.gif' ),
				]
			);

		}
	}
}

