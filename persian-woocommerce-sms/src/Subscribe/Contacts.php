<?php

namespace PW\PWSMS\Subscribe;


defined( 'ABSPATH' ) || exit;

class Contacts {

	public function __construct() {
		add_action( 'pwoosms_settings_form_bottom_sms_contacts', [ $this, 'contacts_table' ] );
		add_action( 'init', [ $this, 'create_table' ] );
		add_action( 'init', [ $this, 'move_old_contents_38' ] );

		if ( isset( $_GET['test'] ) ) {

			self::get_contacts_mobile( 361, '_in' );

		}
	}

	public static function get_contacts_mobile( int $product_id, string $group ) {

		$wpdb = $GLOBALS['wpdb'];

		$table = ListTable::table();
		$query = "SELECT `mobile` FROM `{$table}` WHERE product_id=%d";
		$query .= ' AND ( `groups` LIKE %s )';

		$group_name = '%' . $wpdb->esc_like( $group ) . '%';

		$mobiles = $wpdb->get_col( $wpdb->prepare( $query, $product_id, $group_name ) );

		$mobiles = array_unique( array_filter( $mobiles ) );


		return $mobiles;
	}

	public static function group_name( $group_id, $product_id, $cond = true ) {
		$groups = self::get_groups( $product_id, false, $cond );

		return isset( $groups[ $group_id ] ) ? $groups[ $group_id ] : '';
	}

	/*------------------------------------------------------------------------------*/

	public static function get_groups( $product_id, $check = true, $cond = true ) {

		$groups = [];
		if ( ! $check || ! PWSMS()->product_has_prop( $product_id, 'is_on_sale' ) ) {
			if ( ! $cond || PWSMS()->has_notif_condition( 'enable_onsale', $product_id ) ) {
				$groups['_onsale'] = PWSMS()->get_product_meta_value( 'notif_onsale_text', $product_id );
			}
		}

		if ( ! $check || ! PWSMS()->product_has_prop( $product_id, 'is_in_stock' ) ) {
			if ( ! $cond || PWSMS()->has_notif_condition( 'enable_notif_no_stock', $product_id ) ) {
				$groups['_in'] = PWSMS()->get_product_meta_value( 'notif_no_stock_text', $product_id );
			}
		}

		if ( ! $check || PWSMS()->product_has_prop( $product_id, 'is_not_low_stock' ) ) {
			if ( ! $cond || PWSMS()->has_notif_condition( 'enable_notif_low_stock', $product_id ) ) {
				$groups['_low'] = PWSMS()->get_product_meta_value( 'notif_low_stock_text', $product_id );
			}
		}

		// Processing the custom groups are not so easy,
		// There's a string like below in database:
		// 1:زمانیکه محصول توقف فروش شد 2:زمانیکه نسخه جدید محصول منتشر شد
		// We have to use regex to separate these two sentences
		// And We also need to remove potential : from each string
		$product_notification_groups = (string) PWSMS()->get_product_meta_value( 'notif_options', $product_id );
		$product_notification_groups = array_filter( preg_split( '/\d+:/', $product_notification_groups ) );

		$product_notification_groups_keys = array_map( function ( $key ) {
			return (string) ( $key );
		}, array_keys( $product_notification_groups ) );

		$product_notification_groups = array_combine( $product_notification_groups_keys, $product_notification_groups );

		if ( ! empty( $product_notification_groups ) ) {
			$groups += $product_notification_groups;
		}


		return array_filter( $groups );
	}

	public static function get_contact_by_mobile( $product_id, $mobile ) {

		$table = ListTable::table();

		$product_id = intval( $product_id );

		$mobile = ListTable::prepareMobile( $mobile );

		$wpdb = $GLOBALS['wpdb'];

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE product_id=%d AND mobile LIKE '%s'",
			$product_id, "%$mobile%" ), ARRAY_A );
	}

	public function create_table() {

		if ( get_option( 'pwoosms_table_contacts_created' ) ) {
			return;
		}

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
		}

		$wpdb = $GLOBALS['wpdb'];

		$charset_collate = '';
		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		}
		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}

		$table = ListTable::table();
		dbDelta( "CREATE TABLE IF NOT EXISTS {$table} (
			`id` mediumint(8) unsigned NOT NULL auto_increment,
			`product_id` mediumint(8) unsigned,
			`mobile` VARCHAR(250),
			`groups` VARCHAR(250),
			PRIMARY KEY  (id)
		) $charset_collate;" );

		update_option( 'pwoosms_table_contacts_created', '1' );
	}

	/*-------------------------------------------------------------------------------*/

	public function move_old_contents_38() {

		/*انتقال مخاطبین از پست متا به تیبل مجزا بعد از بروز رسانی به نسخه 4.0.0*/

		if ( get_option( 'pwoosms_table_contacts_updated' ) ) {
			return;
		}

		$wpdb = $GLOBALS['wpdb'];

		if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", ListTable::table() ) ) ) {
			return;
		}

		/*به نظرم در هر بار درخواست، مشترکین 50 تا محصول منتقل بشن کافیه. چون ممکنه یه محصول خودش ۱۰۰۰ تا مشترک داشته باشه*/
		$sql = $wpdb->prepare( "SELECT * FROM %s WHERE `meta_key`='_hannanstd_sms_notification' LIMIT 50",
			$wpdb->postmeta );

		$results = $wpdb->get_results( $sql, 'ARRAY_A' );
		if ( empty( $results ) ) {
			update_option( 'pwoosms_table_contacts_updated', '1' );
		}

		foreach ( $results as $result ) {

			$contacts = [];
			foreach ( explode( '***', (string) $result['meta_value'] ) as $contact ) {
				//تو نسخه های قبلی نوع پیام (تلگرام - پیامک) با این جدا میشد.
				[ $contact ] = explode( '_vsh_', $contact, 1 );
				if ( strlen( $contact ) < 2 ) {
					continue;
				}
				[ $mobile, $groups ] = explode( '|', $contact, 2 );
				if ( PWSMS()->validate_mobile( $mobile ) ) {
					$groups              = explode( ',', $groups );
					$_groups             = ! empty( $contacts[ $mobile ] ) ? $contacts[ $mobile ] : [];
					$contacts[ $mobile ] = array_unique( array_merge( $groups, $_groups ) );
				}
			}

			$insert     = true;
			$meta_id    = $result['meta_id'];
			$product_id = $result['post_id'];

			foreach ( $contacts as $mobile => $groups ) {
				$insert = self::insert_contact( [
						'product_id' => $product_id,
						'mobile'     => $mobile,
						'groups'     => $groups,
					] ) && $insert;
			}

			if ( $insert ) {
				$wpdb->update( $wpdb->postmeta, [
					'meta_key' => '_pwoosms_newsletter_contacts__moved',
				], [
					'meta_id' => intval( $meta_id ),
				] );
			}
		}
	}

	public static function insert_contact( $data ) {

		if ( empty( $data['product_id'] ) || empty( $data['mobile'] ) || empty( $data['groups'] ) ) {
			return false;
		}

		$wpdb = $GLOBALS['wpdb'];

		return $wpdb->insert( ListTable::table(), [
			'product_id' => intval( $data['product_id'] ),
			'mobile'     => PWSMS()->modify_mobile( $data['mobile'] ),
			'groups'     => self::prepare_groups( $data['groups'] ),
		], [ '%d', '%s', '%s' ] );
	}

	private static function prepare_groups( $groups ) {

		if ( ! is_array( $groups ) ) {
			$groups = explode( ',', (string) $groups );
		}

		$groups = array_map( 'sanitize_text_field', $groups );
		$groups = array_map( 'trim', $groups );
		$groups = array_unique( array_filter( $groups ) );
		$groups = implode( ',', $groups );

		return $groups;
	}

	public function contacts_table() {

		$updated = get_option( 'pwoosms_table_contacts_updated' );
		if ( ! $updated ) { ?>
			<div class="notice notice-info below-h2">
				<p>
					<strong>
						در حال انتقال دیتابیس مشترکین خبرنامه سایت شما از جدول post_meta به یک جدول مستقل هستیم.
						این عمل با توجه به حجم مشترکین شما ممکن است کمی زمانبر باشد.
						لطفا لحظات دیگری پس از انتقال کامل مشترکین مراجعه نمایید.
					</strong>
				</p>
			</div>
			<?php return;
		} elseif ( $updated == '1' ) { ?>
			<div class="notice notice-success is-dismissible below-h2">
				<p>
					<strong>
						انتقال دیتابیس مشترکین خبرنامه سایت شما از جدول post_meta به یک جدول مستقل با موفقیت انجام شد.
					</strong>
				</p>
			</div>
			<?php update_option( 'pwoosms_table_contacts_updated', '2' );
		}

		/*----------------------------------------------------------------------------*/
		if ( isset( $_GET['edit'] ) ) {
			$this->edit_contact( intval( $_GET['edit'] ) );
		} elseif ( isset( $_GET['add'] ) ) {
			$this->add_contact( intval( $_GET['add'] ) );
		} else {

			$list = new ListTable();
			if ( isset( $_POST['export_csv'] ) ) {
				$list->export_csv();
			}

			$list->prepare_items();

			echo '<style type="text/css">';
			echo '.wp-list-table .column-id { width: 5%; }';
			echo '</style>';


			$product_id = ListTable::request_product_id( true );
			$product_id = count( $product_id ) == 1 ? reset( $product_id ) : 0;

			if ( ! empty( $product_id ) && $title = get_the_title( $product_id ) ) {
				echo sprintf( '<h1>مشترکین محصول "%s"</h1>', esc_attr( $title ) ) . '<br><br>';
			}

			$query_args = [ 'add' => $product_id ];
			if ( ! empty( $product_id ) ) {
				$query_args['product_id'] = $product_id;
			}

			$add_url = add_query_arg( $query_args,
				admin_url( 'admin.php?page=persian-woocommerce-sms-pro&tab=contacts' ) ); ?>
			<a class="page-title-action" href="<?php echo esc_url( $add_url ); ?>">افزودن مشترک جدید</a>

			<?php
			// Check if the GET parameters exist and sanitize them
			$product_id = isset( $_GET['product_id'] ) ? filter_input( INPUT_GET, 'product_id',
				FILTER_SANITIZE_NUMBER_INT ) : '';
			$add        = isset( $_GET['add'] ) ? htmlspecialchars( $_GET['add'], ENT_QUOTES, 'UTF-8' ) : '';
			$edit       = isset( $_GET['edit'] ) ? htmlspecialchars( $_GET['edit'], ENT_QUOTES, 'UTF-8' ) : '';

			if ( ! empty( $product_id ) || ! empty( $add ) || ! empty( $edit ) ) : ?>
				<a class="page-title-action"
				   href="<?php echo esc_url( remove_query_arg( [ 'product_id', 'add', 'edit' ] ) ); ?>">
					بازگشت به لیست همه مشترکین
				</a>
			<?php endif; ?>


			<form method="post">
				<input type="hidden" name="page" value="WoocommerceIR_SMS_Contacts_list_table">
				<?php
				$list->search_box( 'جستجوی تلفن/ شناسه محصول', 'search_id' );
				$list->display();
				?>
			</form>
		<?php } ?>

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

	private function edit_contact( $contact_id = 0 ) {

		$operation  = empty( $contact_id ) ? 'add' : 'edit';
		$return_url = remove_query_arg( [ 'add', 'edit', 'added' ] );

		$data = $operation == 'edit' ? self::get_contact_by_ID( $contact_id ) : [];

		if ( $operation == 'edit' ) {
			$product_id = ! empty( $data['product_id'] ) ? intval( $data['product_id'] ) : 0;
		} else {
			$product_id = intval( $_GET['add'] ?? 0 );
		}

		if ( ! empty( $_POST['_wpnonce'] ) ) {

			if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'pwoosms_contact_nonce' ) ) {
				wp_die( 'خطایی رخ داده است.' );
			}

			$mobile = sanitize_text_field( $_POST['mobile'] ?? null );

			if ( empty( $mobile ) ) {
				$error = 'شماره موبایل الزامی است.';
			} elseif ( ! PWSMS()->validate_mobile( $mobile ) ) {
				$error = 'شماره موبایل وارد شده معتبر نیست.';
			}


			$groups = self::prepare_groups( $_POST['groups'] );


			if ( empty( $groups ) ) {
				$error = 'انتخاب حداقل یک گروه الزامی است.';
			}

			if ( empty( $error ) ) {

				$params = [
					'product_id' => $product_id,
					'mobile'     => $mobile,
					'groups'     => $groups,
				];

				if ( $operation == 'edit' ) {
					$save = self::update_contact( array_merge( [ 'id' => $contact_id ], $params ) );
				} else {
					$save = self::insert_contact( $params );
				}

				if ( $save !== false ) {
					if ( $operation == 'edit' ) {
						$saved = true;
					} else {
						$wpdb       = $GLOBALS['wpdb'];
						$contact_id = $wpdb->insert_id;
						wp_redirect( add_query_arg( [ 'edit' => $contact_id, 'added' => 'true' ], $return_url ) );
						exit();
					}
				} else {
					$error = 'در حین ذخیره خطایی رخ داده است. مجددا تلاش کنید.';
				}
			}

			if ( ! empty( $error ) ) { ?>
				<div class="notice notice-error below-h2">
					<p><strong>خطا: </strong><?php echo esc_attr( $error ); ?></p>
				</div>
				<?php
			}
		} else {
			$mobile = ! empty( $data['mobile'] ) ? PWSMS()->modify_mobile( $data['mobile'] ) : '';
			$groups = ! empty( $data['groups'] ) ? $data['groups'] : '';
		}

		$contact_groups = array_map( 'strval', explode( ',', $groups ) );
		$contact_groups = array_map( 'trim', $contact_groups );

		if ( ! empty( $saved ) || ! empty( $_GET['added'] ) ) { ?>
			<div class="notice notice-success below-h2">
				<p><strong>مشترک ذخیره شد.</strong>
					<a href="<?php echo esc_url( $return_url ); ?>">بازگشت به لیست مشترکین</a>
				</p>
			</div>
			<?php
		}

		$title = $operation == 'edit' ? 'ویرایش مشترک خبرنامه محصول "%s"' : 'افزودن مشترک جدید برای خبرنامه محصول "%s"'; ?>
		<h3><?php printf( $title, get_the_title( $product_id ) ); ?></h3>

		<form action="<?php echo esc_url( remove_query_arg( [ 'added' ] ) ); ?>" method="post">
			<table class="form-table">
				<tbody>
				<tr>
					<th><label for="mobile">شماره موبایل</label></th>
					<td><input type="text" id="mobile" name="mobile" value="<?php echo esc_attr( $mobile ); ?>"
					           style="text-align: left; direction: ltr"></td>
				</tr>
				<tr>
					<th><label for="mobile">گروه ها</label></th>
					<td>
						<?php
						$all_groups    = (array) Contacts::get_groups( $product_id, false, false );
						$active_groups = (array) Contacts::get_groups( $product_id, false, true );

						foreach ( $all_groups as $group => $label ) :
							$group = strval( $group );

							?>
							<label for="groups_<?php echo esc_attr( $group ); ?>">
								<input type="checkbox" name="groups[]" id="groups_<?php echo esc_attr( $group ); ?>"
								       value="<?php echo esc_attr( $group ); ?>" <?php checked( in_array( $group,
									$contact_groups ) ) ?>>
								<?php
								echo esc_attr( $label );
								if ( ! in_array( $group, array_keys( $active_groups ) ) ) {
									echo ' (غیرفعال)';
								}
								?>
							</label><br>
						<?php
						endforeach;
						?>
					</td>
				</tr>
				</tbody>
			</table>

			<?php
			wp_nonce_field( 'pwoosms_contact_nonce', '_wpnonce' );
			$title = $operation == 'edit' ? 'بروز رسانی مشترک' : 'افزودن مشترک';
			?>

			<p class="submit">
				<input name="submit" class="button button-primary" value="<?php echo esc_attr( $title ); ?>"
				       type="submit">
				<a href="<?php echo esc_url( $return_url ); ?>" class="button button-secondary">بازگشت</a>

				<?php if ( ! empty( $contact_id ) ) :

					$delete_url = add_query_arg( [
						'action'   => 'delete',
						'item'     => absint( $contact_id ),
						'_wpnonce' => wp_create_nonce( 'pwoosms_delete_contact' ),
					], $return_url ); ?>

					<a class="delete" href="<?php echo esc_url( $delete_url ); ?>"
					   style="text-decoration: none; color: red">حذف
						این مشترک</a>
				<?php endif; ?>
			</p>

		</form>
		<?php
	}

	/*-----------------------------------------------------------------------------------*/

	public static function get_contact_by_ID( $contact_id ) {
		$wpdb  = $GLOBALS['wpdb'];
		$table = ListTable::table();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", intval( $contact_id ) ), ARRAY_A );
	}

	public static function update_contact( array $data ) {
		$wpdb = $GLOBALS['wpdb'];

		if ( empty( $data['id'] ) || empty( $data['mobile'] ) || empty( $data['groups'] ) ) {
			return false;
		}

		return $wpdb->update( ListTable::table(), [
			'mobile' => PWSMS()->modify_mobile( $data['mobile'] ),
			'groups' => self::prepare_groups( $data['groups'] ),
		], [ 'id' => intval( $data['id'] ) ], [ '%s', '%s' ], [ '%d' ] );
	}

	private function add_contact( $product_id = 0 ) {

		if ( ! empty( $product_id ) ) {
			$this->edit_contact();

			return;
		}
		?>

		<table class="form-table">
			<tbody>
			<tr>
				<th>
					<label for="select_product_id">یک محصول انتخاب کنید</label>
				</th>
				<td>
					<select id="select_product_id" class="wc-product-search">
						<option value="">یک محصول انتخاب کنید</option>
					</select>
				</td>
			</tr>
			</tbody>
		</table>

		<script type="text/javascript">
            jQuery(document).ready(function ($) {
                $('select#select_product_id').on('change', function () {
					<?php $url = esc_url_raw( remove_query_arg( [ 'add' ] ) ); ?>
                    document.location = '<?php echo str_replace( '&amp;', '&', esc_js( $url ) ); ?>' + "&add=" + encodeURIComponent($(this).val());
                });
            });
		</script>
		<?php
	}
}