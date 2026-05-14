<?php

namespace PW\PWSMS\Subscription;

use PW\PWSMS\Settings\Settings;
use PW\PWSMS\Shortcode;
use WP_Widget;

defined( 'ABSPATH' ) || exit;

class Widget extends WP_Widget {

	private static $form_id = 0;
	private static $groups = [];
	private $enable_notification = false;

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
			add_action( 'wp_ajax_wc_sms_save_notification_data', [ $this, 'update_subscription' ] );
			add_action( 'wp_ajax_nopriv_wc_sms_save_notification_data', [ $this, 'update_subscription' ] );
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

		$show_form = PWSMS()->get_product_meta_value( 'enable_notif_sms', $product_id );
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
					<strong><?php echo esc_attr( PWSMS()->get_product_meta_value( 'notif_title', $product_id ) ); ?></strong>
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
						<?php echo esc_attr( PWSMS()->get_product_meta_value( 'notif_only_loggedin_text', $product_id ) ); ?>
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
			wc_enqueue_js( '
			jQuery(document).ready(function($){
				$(".sms-notif-content").hide();
			    $(document.body).on( "change", ".sms-notif-enable", function() {
					if( $(this).is(":checked") )
						$(this).closest("form").find(".sms-notif-content").fadeIn();			
					else
				    	$(this).closest("form").find(".sms-notif-content").fadeOut();
				}).on( "click", ".sms-notif-submit", function() {
				    var form = $(this).closest("form");
				    var result = form.find(".sms-notif-result");
				    result.html( "<img style=\"width:16px;display:inline;\" src=\"' . esc_url( PWSMS_URL . '/assets/images/ajax-loader.gif' ) . '\" />" );
			    	var sms_group = [];
				    form.find(".sms-notif-groups:checked").each(function(i){
					    sms_group[i] = $(this).val();
			    	});
				    $.ajax({
					    url : "' . esc_url( admin_url( "admin-ajax.php" ) ) . '",
				    	type : "post",
					    data : {
						    action : "wc_sms_save_notification_data",
					    	security: "' . esc_js( wp_create_nonce( "wc_sms_save_notification_data" ) ) . '",
						    sms_mobile : form.find(".sms-notif-mobile").val(),
						    sms_group : sms_group,
						    product_id : "' . $product_id . '",
					    },
				    	success : function( response ) {
					    	result.html( response );
					    }
			    	});
				    return false;
		    	});
		    });
		' );
		}
	}

	public function update_subscription() {

		check_ajax_referer( 'wc_sms_save_notification_data', 'security' );

		$error_image = wp_kses( '<img style="width:16px;display:inline;" src="' . esc_url( PWSMS_URL . '/assets/images/false.png' ) . '">&nbsp;', [ 'img' => [ 'style' => [], 'src' => [] ] ] );

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		if ( empty( $product_id ) ) {
			die( $error_image . 'حطایی رخ داده است.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$can_be_subscribe = ! PWSMS()->has_notif_condition( 'notif_only_loggedin', $product_id ) || is_user_logged_in();
		if ( ! $can_be_subscribe ) {
			die( $error_image . esc_attr( PWSMS()->get_product_meta_value( 'notif_only_loggedin_text', $product_id ) ) ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$mobile = PWSMS()->modify_mobile( sanitize_text_field( $_POST['sms_mobile'] ?? '' ) );
		if ( empty( $mobile ) ) {
			die( $error_image . 'شماره موبایل را وارد نمایید.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		if ( ! PWSMS()->validate_mobile( $mobile ) ) {
			die( $error_image . 'شماره موبایل معتبر نیست.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		if ( empty( $_POST['sms_group'] ) ) {
			die( $error_image . 'انتخاب یکی از گزینه ها الزامیست.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$groups = ( new Settings() )->sanitize_array_text_fields( (array) $_POST['sms_group'] );

		$success_image = wp_kses( '<img style="width:16px;display:inline;" src="' . esc_url( PWSMS_URL . '/assets/images/tick.png' ) . '">&nbsp;', [ 'img' => [ 'src' => [], 'style' => [] ] ] );

		$contact = (array) Contacts::get_contact_by_mobile( $product_id, $mobile );

		if ( ! empty( $contact['id'] ) ) {

			$old_groups = ! empty( $contact['groups'] ) ? explode( ',', $contact['groups'] ) : [];
			$new_groups = array_merge( $old_groups, $groups );

			$update = Contacts::update_contact( [
				'id'         => $contact['id'],
				'product_id' => $product_id,
				'mobile'     => $mobile,
				'groups'     => $new_groups,
			] );

			if ( $update !== false ) {
				die( $success_image . 'اطلاعات شما با موفقیت بروز شد.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

		} else {

			$insert = Contacts::insert_contact( [
				'product_id' => $product_id,
				'mobile'     => $mobile,
				'groups'     => $groups,
			] );

			if ( $insert ) {
				die( $success_image . 'اطلاعات شما با موفقیت ثبت شد.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		die( $error_image . 'خطایی رخ داده است. مجددا تلاش کنید.' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

