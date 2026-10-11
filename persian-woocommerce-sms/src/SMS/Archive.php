<?php

namespace PW\PWSMS\SMS;

class Archive {

	public function __construct() {
		add_action( 'pwoosms_settings_form_bottom_sms_archive', [ $this, 'archive_table' ] );
		add_action( 'init', [ $this, 'create_table' ] );

		add_action( 'current_screen', [ $this, 'add_screen_options' ] );
		add_filter( 'set-screen-option', [ $this, 'set_screen_option' ], 10, 3 );
	}

	public static function insert_record( $data ) {
		global $wpdb;

		$time = time();

		if ( function_exists( 'wc_timezone_offset' ) ) {
			$time += wc_timezone_offset();
		}

		$wpdb->insert( $wpdb->prefix . 'woocommerce_ir_sms_archive', [
			'post_id'  => ! empty( $data['post_id'] ) ? $data['post_id'] : 0,
			'type'     => ! empty( $data['type'] ) ? $data['type'] : 0,
			'reciever' => ! empty( $data['reciever'] ) ? $data['reciever'] : '',
			'message'  => ! empty( $data['message'] ) ? $data['message'] : '',
			'sender'   => ! empty( $data['sender'] ) ? $data['sender'] : '',
			'result'   => ! empty( $data['result'] ) ? $data['result'] : '',
			'date'     => gmdate( 'Y-m-d H:i:s', $time ),
		], [ '%d', '%d', '%s', '%s', '%s', '%s', '%s' ] );
	}

	public function create_table() {
		global $wpdb;

		if ( get_option( 'pwoosms_table_archive' ) ) {
			return;
		}

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
		}

		$charset_collate = '';

		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}

		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
		}

		$table = ListTable::table();

		dbDelta( "CREATE TABLE IF NOT EXISTS $table (
			id mediumint(8) unsigned NOT NULL auto_increment,
			post_id mediumint(8) unsigned,
            type tinyint(2),
			reciever TEXT NOT NULL,
			message TEXT NOT NULL,
			sender VARCHAR(100),
			result TEXT,
			date DATETIME,
			PRIMARY KEY  (id)
		) $charset_collate;" );

		update_option( 'pwoosms_table_archive', '1' );
	}

	public function archive_table() {
		$list = new ListTable();
		if ( isset( $_POST['export_csv'] ) ) {
			$list->export_csv();
		}

		if ( isset( $_POST['delete_records'] ) ) {
			$period = sanitize_text_field( $_POST['delete_period'] );
			$list->delete_records_by_period( $period );
		}


		$list->prepare_items();
		?>

		<style type="text/css">
            .wp-list-table .column-id {
                max-width: 5%;
            }
		</style>

		<?php if ( ! empty( $_GET['id'] ) ) : ?>
			<a class="page-title-action" href="<?php echo esc_url( remove_query_arg( [ 'id' ] ) ); ?>">
				بازگشت به لیست آرشیو همه پیامک‌ها
			</a>
		<?php endif; ?>

		<form method="post">
			<input type="hidden" name="page" value="WoocommerceIR_SMS_Archive_list_table">
			<?php
			$list->search_box( 'جستجوی گیرنده', 'search_id' );
			$list->render_export_csv();
			$list->render_period_delete();
			$list->display();
			?>
		</form>

		<script type="text/javascript">
            jQuery(document).ready(function ($) {
                $('.delete a, a.delete, .button.action').on('click', function (e) {
                    var action1 = $('select[name="action"]').val();
                    var action2 = $('select[name="action2"]').val();
                    if ($(this).is('a') || action1 === 'bulk_delete' || action2 === 'bulk_delete') {
                        if (!confirm('آیا از انجام عملیات حذف مطمئن هستید؟ این عمل غیرقابل برگشت است.')) {
                            e.preventDefault();
                            return false;
                        }
                    }
                });
            });
		</script>
		<?php
	}

	public function add_screen_options(): void {
		$screen         = get_current_screen();
		$is_archive_tab = ( $_GET['tab'] ?? '' ) === 'archive';

		$is_plugin_page = $screen &&
		                  (
			                  str_contains( $screen->id, 'pwsms' ) ||
			                  str_contains( $screen->id, 'woocommerce' )
		                  );

		if ( ! $is_archive_tab || ! $is_plugin_page ) {
			return;
		}

		add_screen_option( 'per_page', [
			'label'   => 'تعداد پیامک در هر صفحه',
			'default' => 20,
			'option'  => 'pwsms_archive_per_page',
		] );
	}

	public function set_screen_option( $status, string $option, $value ) {

		if ( 'pwsms_archive_per_page' === $option ) {
			return intval( $value );
		}

		return $status;
	}
}
