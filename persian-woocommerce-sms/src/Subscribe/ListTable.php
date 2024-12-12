<?php

namespace PW\PWSMS\Subscribe;


use WP_List_Table;
use WP_User_Query;

defined( 'ABSPATH' ) || exit;

class ListTable extends WP_List_Table {

	public static $table = 'woocommerce_ir_sms_contacts';
	private static $users = [];

	public function __construct() {
		parent::__construct( [
			'singular' => 'لیست مشترکین خبرنامه پیامکی محصولات ووکامرس',
			'plural'   => 'لیست مشترکین خبرنامه پیامکی محصولات ووکامرس',
			'ajax'     => false,
		] );
	}

	public function display() {
		$this->export_csv_button();
		parent::display();
	}

	public function export_csv_button() {
		?>
        <input type="submit" name="export_csv" class="button button-primary"
               value="<?php esc_attr_e( 'برون بری' ); ?>"/>
		<?php
	}

	public function export_csv() {
		if ( isset( $_POST['export_csv'] ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// Prepare CSV file
			ob_clean(); // Clean the output buffer

			// Fetch only the items for the current page
			$current_page = $this->get_pagenum();
			$per_page     = $this->get_items_per_page( 'posts_per_page', 20 ); // Adjust with your own per page value

			$this->set_pagination_args( [
				'total_items' => $this->get_total_items(),
				'per_page'    => $per_page,
				'total_pages' => ceil( $this->get_total_items() / $per_page )
			] );

			$offset   = ( $current_page - 1 ) * $per_page;
			$all_data = $this->fetch_data( $per_page, $offset );

			if ( empty( $all_data ) ) {
				return;
			}


			// Group data by mobile numbers
			$grouped_data = [];
			foreach ( $all_data as $row ) {
				$mobile = $row['mobile'];
				if ( ! isset( $grouped_data[ $mobile ] ) ) {
					$grouped_data[ $mobile ] = [];
				}
				$grouped_data[ $mobile ][] = $row;
			}

			$file_name = 'PWSMS-subscription-export-' . date( 'Y-m-d' ) . '.csv';
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=' . $file_name );

			$output = fopen( 'php://output', 'w' );

			// Output the column headings
			fputcsv( $output, [ 'Mobile', 'Product', 'Groups' ] );

			// Output the grouped data
			foreach ( $grouped_data as $mobile => $rows ) {
				foreach ( $rows as $row ) {
					$product = wc_get_product( $row['product_id'] );
					if ( ! PWSMS()->is_wc_product( $product ) ) {
						$product_name = '-';
					} else {
						$product_name = $product->get_name();
					}

					fputcsv( $output, [ $mobile, $product_name, $row['groups'] ] );
				}
			}

			fclose( $output );
			exit;
		}
	}

	protected function get_total_items() {
		$wpdb = $GLOBALS['wpdb'];

		$query = "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_ir_sms_contacts";

		return $wpdb->get_var( $query );
	}

	protected function fetch_data( $per_page = 20, $offset = 0 ) {
		$wpdb = $GLOBALS['wpdb'];

		$orderby = ! empty( $_REQUEST['orderby'] ) ? esc_sql( $_REQUEST['orderby'] ) : 'mobile';
		$order   = ! empty( $_REQUEST['order'] ) ? esc_sql( $_REQUEST['order'] ) : 'asc';

		$query = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}woocommerce_ir_sms_contacts ORDER BY $orderby $order LIMIT %d OFFSET %d", $per_page, $offset );

		$data = $wpdb->get_results( $query, ARRAY_A );

		return $data;
	}

	public function no_items() {
		echo 'موردی یافت نشد.';
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			default:
				return print_r( $item, true );
		}
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="item[]" value="%s" />', $item['id'] );
	}

	public function column_mobile( $item ) {

		if ( empty( $item['mobile'] ) ) {
			return '-';
		}

		$mobile = $this->mobile_with_user( $item['mobile'] );

		$product_ids = self::request_product_id( true );
		if ( count( $product_ids ) == 1 ) {
			$mobile .= $this->column_product_id( $item, false );
		}

		return '<div style="direction:ltr !important; text-align:' . ( is_rtl() ? 'right' : 'left' ) . ';">' . $mobile . '</div>';
	}

	private function mobile_with_user( $_mobile ) {

		$mobile = self::prepareMobile( $_mobile );
		$user   = ! empty( self::$users[ $mobile ] ) ? self::$users[ $mobile ] : (object) [];

		$mobile = PWSMS()->modify_mobile( $mobile );

		if ( ! empty( $user->ID ) ) {

			$user_id = $user->ID;

			$full_name = get_user_meta( $user_id, 'billing_first_name', true ) . ' ' . get_user_meta( $user_id, 'billing_last_name', true );
			$full_name = trim( $full_name );

			if ( empty( $full_name ) && ! empty( $user->display_name ) ) {
				$full_name = ucwords( $user->display_name );
			}

			if ( ! empty( $full_name ) ) {
				$mobile = '(' . $full_name . ')&lrm;  ' . $_mobile;
				$mobile = '<a target="_blank" href="' . get_edit_user_link( $user_id ) . '">' . $mobile . '</a>';
			}
		}

		return $mobile;
	}

	public static function prepareMobile( $mobile ) {
		return substr( ltrim( $mobile, '0' ), - 10 );
	}

	public static function request_product_id( $array = false ) {

		$product_ids = ! empty( $_REQUEST['product_id'] ) ? $_REQUEST['product_id'] : [];
		if ( ! is_array( $product_ids ) ) {
			$product_ids = explode( ',', (string) $product_ids );
		}
		$product_ids = array_map( 'intval', $product_ids );
		$product_ids = array_unique( array_filter( $product_ids ) );
		if ( $array ) {
			return $product_ids;
		}

		return implode( ',', $product_ids );
	}

	public function column_product_id( $item, $this_column = true ) {

		$product_id = intval( $item['product_id'] );

		$column_value = '';

		if ( $this_column ) {
			$column_value = '-';
			if ( $product_id ) {
				$title        = get_the_title( $product_id );
				$title        = ! empty( $title ) ? $product_id . ' :: ' . $title : $product_id;
				$column_value = '<a title="مشاهده لیست مشترکین این محصول" href="' . add_query_arg( [ 'product_id' => $product_id ] ) . '">' . $title . '</a>';
			}
		}

		$query_args  = [ 'edit' => absint( $item['id'] ) ];
		$product_ids = self::request_product_id();
		if ( ! empty( $product_ids ) ) {
			$query_args['product_id'] = $product_ids;
		}

		$edit_url = add_query_arg( $query_args, admin_url( 'admin.php?page=persian-woocommerce-sms-pro&tab=contacts' ) );

		$delete_url = add_query_arg( [
			'action'   => 'delete',
			'item'     => absint( $item['id'] ),
			'_wpnonce' => wp_create_nonce( 'pwoosms_delete_contact' ),
		] );

		$actions = [
			'edit'   => sprintf( '<a href="%s">%s</a>', $edit_url, 'ویرایش مشترک' ),
			'delete' => sprintf( '<a href="%s">%s</a>', $delete_url, 'حذف مشترک' ),
		];

		if ( ! empty( $product_id ) ) {
			$actions['edit_product'] = sprintf( '<a target="_blank" href="%s">%s</a>', get_edit_post_link( $product_id ), 'مدیریت محصول' );
		}

		return sprintf( '%1$s %2$s', $column_value, $this->row_actions( $actions ) );
	}

	public function column_groups( $item ) {

		if ( empty( $item['groups'] ) || empty( $item['product_id'] ) ) {
			return '-';
		}

		$product_id  = intval( $item['product_id'] );
		$groups      = explode( ',', $item['groups'] );
		$group_names = [];
		foreach ( $groups as $group_id ) {
			$name = Contacts::group_name( $group_id, $product_id, true );
			if ( empty( $name ) ) {
				$name = Contacts::group_name( $group_id, $product_id, false );
				if ( ! empty( $name ) ) {
					$name .= ' (غیرفعال)';
				} else {
					$name = 'گروه حذف شده';
				}
			}
			$group_names[] = $name;
		}

		return implode( ' | ', array_filter( $group_names ) );
	}

	public function prepare_items() {

		$columns               = $this->get_columns();
		$hidden                = [];
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = [ $columns, $hidden, $sortable ];

		$this->process_bulk_action();

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$total_items  = $this->record_count();

		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page,
		] );

		$this->items = $this->get_items( $per_page, $current_page );

	}

	public function get_columns() {

		$columns = [
			'cb'         => '<input type="checkbox" />',
			'product_id' => 'محصول',
			'mobile'     => 'موبایل',
			'groups'     => 'گروه',
		];

		$product_ids = self::request_product_id( true );

		if ( count( $product_ids ) == 1 ) {
			unset( $columns['product_id'] );
		}

		return $columns;
	}

	public function get_sortable_columns() {
		return [
			'product_id' => [ 'product_id', false ],
			'mobile'     => [ 'mobile', false ],
			'groups'     => [ 'groups', false ],
		];
	}

	public function process_bulk_action() {

		$action = $this->current_action();

		if ( 'delete' === $action ) {

			if ( ! empty( $_REQUEST ) && ! wp_verify_nonce( sanitize_text_field( $_REQUEST['_wpnonce'] ), 'pwoosms_delete_contact' ) ) {
				die( 'خطایی رخ داده است. بعدا تلاش کنید.' );
			}

			$this->delete_item( absint( $_REQUEST['item'] ) );

			echo '<div class="updated notice is-dismissible below-h2"><p>آیتم حذف شد.</p></div>';
		} elseif ( $action == 'bulk_delete' ) {

			if ( ! empty( $_REQUEST ) && ! wp_verify_nonce( sanitize_text_field( $_REQUEST['_wpnonce'] ), 'bulk-' . $this->_args['plural'] ) ) {
				die( 'خطایی رخ داده است. بعدا تلاش کنید.' );
			}

			$delete_ids = array_map( 'intval', $_REQUEST['item'] ?? [] );

			foreach ( (array) $delete_ids as $id ) {
				$this->delete_item( absint( $id ) );
			}

			echo '<div class="updated notice is-dismissible below-h2"><p>آیتم ها حذف شدند.</p></div>';
		}
	}

	public function delete_item( $id ) {
		$wpdb = $GLOBALS['wpdb'];

		$wpdb->delete( self::table(), [ 'id' => $id ] );
	}

	public static function table(): string {
		$wpdb = $GLOBALS['wpdb'];

		return $wpdb->prefix . self::$table;
	}

	public function record_count() {

		$wpdb = $GLOBALS['wpdb'];

		return $wpdb->get_var( $this->get_query( true ) );
	}

	private function get_query( $count = false ) {
		$wpdb = $GLOBALS['wpdb'];

		$table = self::table();

		$where = [];

		if ( isset( $_REQUEST['s'] ) ) {

			$s = sanitize_text_field( $_REQUEST['s'] );
			$s = self::prepareMobile( $s );
            // Search in mobile and product id
			$where[] = $wpdb->prepare( '(`mobile` LIKE %s OR `product_id` = %s)', '%' . $s . '%', $s );

		}

		if ( ! empty( $_REQUEST['product_id'] ) ) {
			$where[] = '(`product_id` IN (' . self::request_product_id() . '))';
		}

		$where = ! empty( $where ) ? '(' . implode( ' AND ', $where ) . ')' : '';

		$order_by = ! empty( $_REQUEST['orderby'] ) ? sanitize_text_field( trim( $_REQUEST['orderby'] ) ) : '';
		$order    = ! empty( $_REQUEST['order'] ) ? sanitize_text_field( trim( $_REQUEST['order'] ) ) : '';

		$select = $count ? 'count(*)' : '*';

		if ( $order_by == 'groups' ) {

			$sql = $wpdb->prepare( "SELECT %s, SUBSTRING_INDEX(SUBSTRING_INDEX(t.groups, ',', n.n), ',', -1) groups
                    FROM %s t CROSS JOIN (SELECT a.N + b.N * 10 + 1 n FROM
                    (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a,
                    (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b
                    ORDER BY n) n WHERE (n.n <= 1 + (LENGTH(t.groups) - LENGTH(REPLACE(t.groups, ',', ''))))", $select, $table );

			if ( ! empty( $where ) ) {
				$sql .= " AND {$where}";
			}

		} else {
			$sql = sprintf( "SELECT %s FROM %s", $select, $table );
			if ( ! empty( $where ) ) {
				$sql .= " WHERE {$where}";
			}
		}

		if ( ! empty( $order_by ) ) {
			$sql .= $_REQUEST['order'] == 'DESC' ? ' DESC' : ' ASC';
			$sql .= $wpdb->prepare( " ORDER BY %s %s", $order_by, $order );
			if ( $order_by != 'product_id' ) {
				$sql .= $wpdb->prepare( ", product_id %s", $order );
			}
		} else {
			$sql .= ' ORDER BY id DESC';
		}

		return $sql;
	}

	public function get_items( int $per_page = 20, int $page_number = 1 ) {
		$wpdb = $GLOBALS['wpdb'];

		$sql     = $this->get_query();
		$offset  = ( $page_number - 1 ) * $per_page;
		$sql     .= $wpdb->prepare( " LIMIT %d OFFSET %d", $per_page, $offset );
		$results = $wpdb->get_results( $sql, 'ARRAY_A' );

		$this->set_users_mobile( $results );

		return $results;
	}

	public function set_users_mobile( $result ) {

		$mobiles = array_unique( wp_list_pluck( $result, 'mobile' ) );

		$meta_key  = PWSMS()->buyer_mobile_meta();
		$user_meta = [ 'relation' => 'OR' ];
		foreach ( $mobiles as $mobile ) {
			$user_meta[] = [
				'key'     => $meta_key,
				'value'   => self::prepareMobile( $mobile ),
				'compare' => 'LIKE',
			];
		}

		$users = ( new WP_User_Query( [ 'meta_query' => $user_meta ] ) )->get_results();

		$_users = [];
		foreach ( $users as $user ) {
			if ( ! empty( $user->ID ) ) {
				$_mobile = get_user_meta( $user->ID, $meta_key, true );
				$_mobile = self::prepareMobile( $_mobile );
				foreach ( $mobiles as $mobile ) {
					$mobile = self::prepareMobile( $mobile );
					if ( stripos( $_mobile, $mobile ) !== false ) {
						$_mobile = $mobile;
						break;
					}
				}
				$_users[ $_mobile ] = $user;
			}
		}

		self::$users = $_users;

		return $_users;
	}

	public function get_bulk_actions() {
		return [
			'bulk_delete' => 'حذف',
		];
	}
}
