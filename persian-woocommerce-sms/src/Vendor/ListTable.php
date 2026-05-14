<?php

namespace PW\PWSMS\Vendor;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Admin\Overrides\Order;
use PW\PWSMS\Helper;
use PW\PWSMS\PWSMS;
use WeDevs\Dokan\Vendor\Vendor;
use WP_List_Table;
use WP_User;
use WP_User_Query;

class ListTable extends WP_List_Table {
	protected $screen_option_per_page = 'pwsms_sellers_per_page';

	public function __construct() {
		parent::__construct( [
			'singular' => 'فروشندگان دکان',
			'plural'   => 'فروشندگان دکان',
			'ajax'     => false,
		] );
	}

	public function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = [];
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = [ $columns, $hidden, $sortable ];

		$this->process_bulk_action();

		$per_page = $this->get_items_per_page( $this->screen_option_per_page, 20 );

		$current_page = $this->get_pagenum();

		// Arguments for fetching paginated seller data
		$args = [
			'role__in'   => [ 'seller', 'administrator' ],
			'number'     => $per_page,
			'offset'     => ( $current_page - 1 ) * $per_page,
			'orderby'    => 'ID',
			'order'      => 'ASC',
			'meta_query' => [
				[
					'key'     => 'dokan_enable_selling',
					'value'   => 'yes',
					'compare' => '=',
				],
			],
			'status'     => [ 'approved' ],
			'featured'   => '',
			'fields'     => 'all',
		];

		// Fetch paginated vendors
		$sellers = dokan()->vendor->all( $args );

		$count_args           = $args;
		$count_args['fields'] = 'ID';
		$count_args['paged']  = $current_page;

		$count_query = new WP_User_Query( $count_args );

		$total_items = $count_query->get_total();

		// Build table data
		$this->items = [];
		foreach ( $sellers as $vendor ) {
			$this->items[] = [
				'ID'                  => $vendor->get_id(),
				'vendor_display_name' => $vendor->get_name(),
				'vendor_name'         => $vendor->get_shop_name(),
				'vendor_email'        => $vendor->get_email(),
				'vendor_phone'        => $vendor->get_phone() ?: 'N/A',
			];
		}

		// Pagination
		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		] );
	}

	public function get_columns() {
		$columns = [
			'cb'                  => '<input type="checkbox" />',
			'vendor_display_name' => 'نام فروشنده',
			'vendor_name'         => 'نام فروشگاه',
			'vendor_phone'        => 'تلفن',
			'actions'             => 'ویرایش',
		];

		return $columns;
	}

	protected function get_sortable_columns() {

		return [
			'vendor_display_name' => [ 'vendor_display_name', false ],
			'vendor_name'         => [ 'vendor_name', false ],
			'vendor_phone'        => [ 'vendor_phone', false ],
		];
	}

	protected function process_bulk_action() {
		$vendor_ids = isset( $_GET['bulk_action_ids'] ) ? array_map( 'intval', $_GET['bulk_action_ids'] ) : [];
		if ( empty( $vendor_ids ) ) {
			return;
		}

		switch ( $this->current_action() ) {
			case 'send_custom_sms':
				// It's javascript feature
				exit;
			case 'view_logs':
				$phones = [];

				foreach ( $vendor_ids as $vendor_id ) {
					$vendor = PWSMS()->get_vendor( $vendor_id );

					if ( ! $vendor ) {
						continue;
					}

					$phone = PWSMS()->get_vendor_phone( $vendor );

					if ( PWSMS()->validate_mobile( $phone ) ) {
						$phones[] = $phone;
					}
				}

				$redirect_url = admin_url( 'admin.php?page=persian-woocommerce-sms-pro&tab=archive' );

				if ( ! empty( $phones ) ) {
					$redirect_url = add_query_arg( 'phones', implode( ',', $phones ), $redirect_url );
				}

				wp_redirect( $redirect_url );
				exit;
		}
	}

	// Handle the sorting of columns

	public function column_cb( $item ) {
		$vendor_phone = $item['vendor_phone'];

		if ( ! PWSMS()->validate_mobile( $vendor_phone ) ) {
			$vendor_phone = '';
		}

		return sprintf( '<input type="checkbox" name="bulk_action_ids[]" value="%s" data-phone="%s" />', $item['ID'], $vendor_phone );
	}

	// Handle bulk actions like delete

	public function column_default( $item, $column_name ) {

		switch ( $column_name ) {

			case 'vendor_display_name':
				$user_edit_url = get_edit_user_link( $item['ID'] );

				return '<a href="' . esc_url( $user_edit_url ) . '" target="_blank">' . esc_html( $item['vendor_display_name'] ) . '</a>';

			case 'vendor_name':
				$store_url = dokan_get_store_url( $item['ID'] );

				return sprintf( "<a href='%s'>%s</a>", esc_url( $store_url ), esc_html( $item['vendor_name'] ) );

			case 'vendor_phone':
				return esc_html( $item['vendor_phone'] );

			case 'actions':
				$store_edit_url          = admin_url( 'admin.php?page=dokan#/vendors/' . $item['ID'] . '?edit=true' );
				$store_products_url      = admin_url( 'edit.php?post_type=product&author=' . $item['ID'] );
				$store_placed_orders_url = PWSMS()->get_admin_order_dashboard_url( 'vendor_id=' . $item['ID'] );
				$sms_archive_url         = add_query_arg( [
						'page'  => 'persian-woocommerce-sms-pro',
						'tab'   => 'archive',
						'phone' => $item['vendor_phone'],
					], admin_url( 'admin.php' ) );

				$actions = '<a class="" href="' . esc_url( $store_edit_url ) . '" target="_blank">ویرایش</a> | ';
				$actions .= '<a class="" href="' . esc_url( $store_products_url ) . '" target="_blank">محصولات</a> | ';
				$actions .= '<a class="" href="' . esc_url( $store_placed_orders_url ) . '" target="_blank">سفارش‌ها</a> | ';
				$actions .= '<a href="' . esc_url( $sms_archive_url ) . '" target="_blank">آرشیو پیامک</a>';


				return $actions;

			default:
				return print_r( $item, true );  // Display the whole array for debugging
		}
	}


	// Handle bulk action processing

	protected function get_bulk_actions() {
		return [
			'custom_sms' => 'ارسال پیامک سفارشی',
			'view_logs'  => 'مشاهده آرشیو پیامک',
		];
	}
}
