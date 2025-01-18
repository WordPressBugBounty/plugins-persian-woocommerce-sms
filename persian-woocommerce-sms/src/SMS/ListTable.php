<?php

namespace PW\PWSMS\SMS;


use Automattic\WooCommerce\Utilities\OrderUtil;
use WP_List_Table;

class ListTable extends WP_List_Table {

	public static $table = 'woocommerce_ir_sms_archive';

	public function __construct() {
		parent::__construct( [
			'singular' => 'آرشیو پیامک‌های ووکامرس',
			'plural'   => 'آرشیو پیامک‌های ووکامرس',
			'ajax'     => false,
		] );
	}

	public function no_items() {
		echo 'موردی یافت نشد.';
	}

	public function column_default( $item, $column_name ): string {

		$align = is_rtl() ? 'right' : 'left';
		switch ( $column_name ) {

			case 'sender':
			case 'reciever':
				return '<div style="direction:ltr !important;text-align:' . $align . ';">' . $item[ $column_name ] . '</div>';
			default:

				if ( is_string( $item[ $column_name ] ) ) {
					return nl2br( $item[ $column_name ] );
				}

				return print_r( $item[ $column_name ], true );
		}
	}

	public function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="item[]" value="%s" />', $item['id'] );
	}

	public function column_post_id( $item ) {

		if ( empty( $item['post_id'] ) ) {
			return '-';
		}

		$post_id = intval( $item['post_id'] );

		$is_order   = OrderUtil::is_order( $post_id, wc_get_order_types() );
		$is_product = get_post_type( $post_id ) == 'product';

		$value = [];

		switch ( true ) {

			case $is_order:
				$edit_title   = 'مدیریت سفارش';
				$filter_title = 'مشاهده آرشیو پیامک‌های این سفارش';
				$value[]      = 'سفارش #' . $post_id;
				break;

			case $is_product:
				$edit_title   = 'مدیریت محصول';
				$filter_title = 'مشاهده آرشیو پیامک‌های این محصول';
				$value[]      = 'محصول';
				$value[]      = get_the_title( $post_id );
				break;

			default:
				return '-';
		}

		$actions = [
			'edit' => sprintf( '<a target="_blank" href="%s">%s</a>', get_edit_post_link( $post_id ), $edit_title ),
		];

		$post_id = '<a title="' . $filter_title . '" href="' . esc_url( add_query_arg( [ 'id' => $post_id ] ) ) . '">' . implode( ' :: ', $value ) . '</a>';

		return sprintf( '%1$s %2$s', $post_id, $this->row_actions( $actions ) );
	}

	public function column_result( $item ) {

		$result = ! empty( $item['result'] ) ? $item['result'] : '';

		if ( trim( $result ) == '_ok_' ) {
			$result = 'پیامک با موفقیت ارسال شد.';
		}

		return $result;
	}

	public function column_type( $item ) {

		if ( empty( $item['type'] ) ) {
			return '-';
		}

		switch ( $item['type'] ) {

			case '1':
				$value = 'ارسال دسته جمعی';
				break;

			/*مشتری*/ case '2':
			$value = 'مشتری - خودکار - سفارش';
			break;

			case '3':
				$value = 'مشتری - دستی - متاباکس سفارش';
				break;

			/*مدیر کل*/ case '4':
			$value = 'مدیر کل - خودکار - سفارش';
			break;

			/* مدیر محصول*/ case '5':
			$value = 'مدیر محصول - خودکار - سفارش';
			break;

			case '6':
				$value = 'مدیر محصول - دستی - متاباکس محصول';
				break;

			/*مشترک مدیر کل و مدیر محصول*/ case '7':
			$value = 'مدیران - خودکار - ناموجود شدن';
			break;

			case '8':
				$value = 'مدیران - خودکار - کم بودن موجودی';
				break;

			/*خبرنامه*/ case '9':
			$value = 'خبرنامه - حراج شدن - اتوماتیک';
			break;

			case '10':
				$value = 'خبرنامه - حراج شدن - دستی';
				break;
			/*--*/ case '11':
			$value = 'خبرنامه - موجود شدن - اتوماتیک';
			break;

			case '12':
				$value = 'خبرنامه - موجود شدن - دستی';
				break;
			/*--*/ case '13':
			$value = 'خبرنامه - کم بودن موجودی - اتوماتیک';
			break;

			case '14':
				$value = 'خبرنامه - کم بودن موجودی - دستی';
				break;
			/*--*/ case '15':
			$value = 'خبرنامه - گزینه های دلخواه - دستی';
			break;

			default:
				$value = '';
		}

		return $value;
	}

	public function column_date( $item ): string {

		$delete_nonce = wp_create_nonce( 'pwoosms_delete_archive' );

		$url = add_query_arg( [
			'action'   => 'delete',
			'item'     => absint( $item['id'] ),
			'_wpnonce' => $delete_nonce,
		] );

		$actions = [
			'delete' => sprintf( '<a href="%s">حذف</a>', htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ) ),
		];

		$date = date_i18n( 'Y-m-d H:i:s', strtotime( $item['date'] ) );
		$date = PWSMS()->maybe_jalali_date( $date );

		return sprintf( '%1$s %2$s', $date, $this->row_actions( $actions ) );
	}

	public function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = [];
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = [ $columns, $hidden, $sortable ];

		$per_page     = 20;
		$current_page = $this->get_pagenum();

		$total_items = $this->record_count();

		// Handle bulk actions.
		$this->process_bulk_action();

		// Fetch the data.
		$data = $this->fetch_data();

		// Sort data.
		usort( $data, [ $this, 'usort_reorder' ] );

		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page )
		] );

		$this->items = $this->get_items( $per_page, $current_page );
	}

	public function get_columns(): array {
		return [
			'cb'       => '<input type="checkbox" />',
			'date'     => 'زمان',
			'post_id'  => 'سفارش / محصول',
			'type'     => 'نوع پیام',
			'message'  => 'متن پیام',
			'reciever' => 'گیرندگان',
			'sender'   => 'وبسرویس',
			'result'   => 'نتیجه وبسرویس',
		];
	}

	public function get_sortable_columns(): array {
		return [
			'post_id'  => [ 'post_id', false ],
			'type'     => [ 'type', false ],
			'sender'   => [ 'sender', false ],
			'reciever' => [ 'reciever', false ],
			'date'     => [ 'date', false ],
		];
	}

	public function process_bulk_action() {

		$action = $this->current_action();

		if ( 'delete' === $action ) {

			if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? null, 'pwoosms_delete_archive' ) ) {
				wp_die( 'خطایی رخ داده است. بعدا تلاش کنید.' );
			}

			$this->delete_item( intval( $_REQUEST['item'] ?? 0 ) );

			echo '<div class="updated notice is-dismissible below-h2"><p>آیتم حذف شد.</p></div>';

		} elseif ( $action == 'bulk_delete' ) {

			if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? null, 'bulk-' . $this->_args['plural'] ) ) {
				wp_die( 'خطایی رخ داده است. بعدا تلاش کنید.' );
			}

			$delete_ids = array_map( 'intval', $_REQUEST['item'] ?? [] );

			foreach ( (array) $delete_ids as $id ) {
				$this->delete_item( $id );
			}

			echo '<div class="updated notice is-dismissible below-h2"><p>آیتم ها حذف شدند.</p></div>';
		}
	}

	public function delete_item( int $id ) {
		$wpdb = $GLOBALS['wpdb'];

		$wpdb->delete( self::table(), [ 'id' => $id ] );
	}

	public static function table(): string {
		$wpdb = $GLOBALS['wpdb'];

		return $wpdb->prefix . self::$table;
	}

	protected function fetch_data( $per_page = 20, $offset = 0 ) {
		$wpdb = $GLOBALS['wpdb'];

		$orderby = ! empty( $_REQUEST['orderby'] ) ? esc_sql( $_REQUEST['orderby'] ) : 'date';
		$order   = ! empty( $_REQUEST['order'] ) ? esc_sql( $_REQUEST['order'] ) : 'desc';

		$query = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}woocommerce_ir_sms_archive ORDER BY $orderby $order LIMIT %d OFFSET %d", $per_page, $offset );

		$data = $wpdb->get_results( $query, ARRAY_A );

		return $data;
	}

	/*--------------------------------------------*/

	public function record_count(): int {
		$wpdb = $GLOBALS['wpdb'];

		if ( ! $this->table_exists() ) {
			return 0;
		}

		return $wpdb->get_var( $this->get_query( true ) );
	}

	private function table_exists() {
		$wpdb = $GLOBALS['wpdb'];

		$wild   = '%';
		$like   = $wild . $wpdb->esc_like( self::table() ) . $wild;
		$result = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES  LIKE %s", $like ) );

		return ! is_null( $result ) ? 1 : 0;
	}

	private function get_query( $count = false ) {
		$wpdb = $GLOBALS['wpdb'];

		$select = $count ? 'count(*)' : '*';

		$sql = sprintf( "SELECT %s FROM %s", $select, self::table() );

		if ( isset( $_POST['s'] ) ) {
			$s   = ltrim( sanitize_text_field( $_REQUEST['s'] ), '0' );
			$sql .= $wpdb->prepare( " WHERE (`message` LIKE %s OR `reciever` LIKE %s  OR `sender` LIKE %s)", '%' . $wpdb->esc_like( $s ) . '%', '%' . $wpdb->esc_like( $s ) . '%', '%' . $wpdb->esc_like( $s ) . '%' );

		}

		if ( ! empty( $_REQUEST['id'] ) ) {
			$post_id = array_map( 'intval', is_array( $_REQUEST['id'] ) ? $_REQUEST['id'] : explode( ',', (string) $_REQUEST['id'] ) );
			$sql     .= ( isset( $s ) ? ' AND' : ' WHERE' ) . ' (`post_id` IN (' . implode( ',', is_array( $post_id ) ? $post_id : [ $post_id ] ) . '))';
		}

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$sql .= $wpdb->prepare( ' ORDER BY %s', sanitize_text_field( $_REQUEST['orderby'] ) );
			$sql .= $_REQUEST['order'] == 'DESC' ? ' DESC' : ' ASC';
		} else {
			$sql .= ' ORDER BY id DESC';
		}

		return $sql;
	}

	public function get_items( int $per_page = 20, int $page_number = 1 ) {
		$wpdb = $GLOBALS['wpdb'];

		if ( ! $this->table_exists() ) {
			return [];
		}

		$query = $this->get_query();
		$query .= $wpdb->prepare( " LIMIT %d, %d ", ( $page_number - 1 ) * $per_page, $per_page );

		return $wpdb->get_results( $query, 'ARRAY_A' );
	}

	public function get_bulk_actions(): array {
		return [
			'bulk_delete' => 'حذف',
		];
	}


	public function render_export_csv(): void {

		?>
        <div style="margin-block-end: 10px;">
            <input type="submit" name="export_csv" class="button button-primary" value="<?php esc_attr_e( 'برون بری همه' ); ?>"/>
        </div>
		<?php
	}

	public function render_period_delete(): void {
		?>
        <div>
            <select name="delete_period">
                <option value="">انتخاب بازه زمانی</option>
                <option value="last_week">هفته گذشته</option>
                <option value="last_month">ماه گذشته</option>
                <option value="last_three_months">سه ماه گذشته</option>
                <option value="last_six_months">شش ماه گذشته</option>
                <option value="last_year">سال گذشته</option>
                <option value="everything_before_today">همه به جز امروز</option>
            </select>
            <input type="submit" name="delete_records" class="button action" value="حذف">
        </div>
		<?php
	}

	public function export_csv() {

		if ( ! isset( $_POST['export_csv'] ) || ! current_user_can( 'manage_options' ) ) {
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

		$offset = ( $current_page - 1 ) * $per_page;
		$data   = $this->fetch_data( $per_page, $offset );

		if ( empty( $data ) ) {
			return;
		}

		$file_name = 'PWSMS-sms-archive-export-' . date( 'Y-m-d' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $file_name );

		$output = fopen( 'php://output', 'w' );

		// Output the column headings
		fputcsv( $output, [ 'Product', 'Receiver', 'Message', 'Type', 'Sender', 'Result', 'Date' ] );

		// Output the grouped data
		foreach ( $data as $row ) {
			fputcsv( $output, [
				$row['post_id'],
				$row['reciever'],
				$row['message'],
				$row['type'],
				$row['sender'],
				$row['result'],
				$row['date']
			] );
		}

		fclose( $output );
		exit;
	}

	protected function get_total_items() {
		$wpdb = $GLOBALS['wpdb'];

		$query = "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_ir_sms_archive";

		return $wpdb->get_var( $query );
	}

	public function delete_records_by_period( $period ) {
		$wpdb         = $GLOBALS['wpdb'];
		$table_name   = $wpdb->prefix . 'woocommerce_ir_sms_archive';
		$date_column  = 'date';
		$current_date = current_time( 'mysql' );

		$date_threshold = $this->get_date_threshold( $period, $current_date );

		if ( $date_threshold ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE $date_column <= %s", $date_threshold ) );
		}

	}

	private function get_date_threshold( $period, $current_date ) {
		switch ( $period ) {
			case 'last_week':
				return date( 'Y-m-d H:i:s', strtotime( '-1 week', strtotime( $current_date ) ) );
			case 'last_month':
				return date( 'Y-m-d H:i:s', strtotime( '-1 month', strtotime( $current_date ) ) );
			case 'last_three_months':
				return date( 'Y-m-d H:i:s', strtotime( '-3 months', strtotime( $current_date ) ) );
			case 'last_six_months':
				return date( 'Y-m-d H:i:s', strtotime( '-6 months', strtotime( $current_date ) ) );
			case 'last_year':
				return date( 'Y-m-d H:i:s', strtotime( '-1 year', strtotime( $current_date ) ) );
			case 'everything_before_today':
				return date( 'Y-m-d H:i:s', strtotime( '-1 day', strtotime( $current_date ) ) );
			default:
				return false;
		}
	}

	protected function usort_reorder( $a, $b ) {
		$orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : 'date';
		$order   = ( ! empty( $_REQUEST['order'] ) ) ? $_REQUEST['order'] : 'desc';
		$result  = strcmp( $a[ $orderby ], $b[ $orderby ] );

		return ( $order === 'asc' ) ? $result : - $result;
	}

}
