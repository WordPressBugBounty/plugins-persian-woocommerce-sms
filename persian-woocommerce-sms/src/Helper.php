<?php

namespace PW\PWSMS;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Exception;
use PW\PWSMS\Gateways\GatewayInterface;
use PW\PWSMS\Gateways\Logger;
use PW\PWSMS\Settings\Settings;
use PW\PWSMS\SMS\Archive;
use ReflectionClass;
use WC_Meta_Box_Order_Notes;
use WC_Order;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//  WoocommerceIR_SMS_Helper
class Helper {

	private static $_instance = false;
	private static $all_options = [];

	public static function multi_select_and_checkbox( $field, $key, $args, $value ) {

		$after = ! empty( $args['clear'] ) ? '<div class="clear"></div>' : '';

		if ( $args['required'] ) {
			$args['class'][] = 'validate-required';
			$required        = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce' ) . '">*</abbr>';
		} else {
			$required = '';
		}

		$custom_attributes = [];
		if ( ! empty( $args['custom_attributes'] ) && is_array( $args['custom_attributes'] ) ) {
			foreach ( $args['custom_attributes'] as $attribute => $attribute_value ) {
				$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
			}
		}

		if ( $args['type'] == "pwoosms_multiselect" ) {
			$value = is_array( $value ) ? $value : [ $value ];
			if ( ! empty( $args['options'] ) ) {
				$options = '';
				foreach ( $args['options'] as $option_key => $option_text ) {
					$options .= '<option value="' . esc_attr( $option_key ) . '" ' . selected( in_array( $option_key, $value ), 1, false ) . '>' . esc_attr( $option_text ) . '</option>';
				}
				$field = '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">';
				if ( $args['label'] ) {
					$field .= '<label for="' . esc_attr( $key ) . '" class="' . implode( ' ', $args['label_class'] ) . '">' . $args['label'] . $required . '</label>';
				}
				$field .= '<select name="' . esc_attr( $key ) . '[]" id="' . esc_attr( $key ) . '" class="select" multiple="multiple" ' . implode( ' ', $custom_attributes ) . '>' . $options . ' </select>';

				if ( $args['description'] ) {
					$field .= '<span class="description">' . ( $args['description'] ) . '</span>';
				}

				$field .= '</p>' . $after;
			}
		}

		if ( $args['type'] == "pwoosms_multicheckbox" ) {
			$value = is_array( $value ) ? $value : [ $value ];
			if ( ! empty( $args['options'] ) ) {
				$field .= '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">';
				if ( $args['label'] ) {
					$field .= '<label for="' . esc_attr( current( array_keys( $args['options'] ) ) ) . '" class="' . implode( ' ', $args['label_class'] ) . '">' . $args['label'] . $required . '</label>';
				}
				foreach ( $args['options'] as $option_key => $option_text ) {
					$field .= '<input type="checkbox" class="input-checkbox" value="' . esc_attr( $option_key ) . '" name="' . esc_attr( $key ) . '[]" id="' . esc_attr( $key ) . '_' . esc_attr( $option_key ) . '"' . checked( in_array( $option_key, $value ), 1, false ) . ' />';
					$field .= '<label for="' . esc_attr( $key ) . '_' . esc_attr( $option_key ) . '" class="checkbox ' . implode( ' ', $args['label_class'] ) . '">' . $option_text . '</label><br>';
				}
				if ( $args['description'] ) {
					$field .= '<span class="description">' . ( $args['description'] ) . '</span>';
				}
				$field .= '</p>' . $after;
			}
		}

		return $field;
	}

	public static function instance() {
		if ( empty( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function get_all_product_admin_statuses( $pending = false ) {

		$opt_statuses = $this->get_all_super_admin_statuses( $pending );

		return $opt_statuses;
	}

	public function get_all_super_admin_statuses( $pending = false ) {
		$opt_statuses        = $this->get_all_statuses( $pending );
		$opt_statuses['low'] = 'کم بودن موجودی انبار';
		$opt_statuses['out'] = 'تمام شدن موجودی انبار';

		return $opt_statuses;
	}

	public function get_all_statuses( $pending = false ) {

		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return [];
		}

		$statuses = wc_get_order_statuses();

		$pending_label = _x( 'Pending payment', 'Order status', 'woocommerce' );
		if ( ! empty( $statuses['wc-pending'] ) ) {
			$statuses['wc-pending'] = $pending ? $pending_label : $pending_label . ' (بعد از تغییر وضعیت سفارش)';
		}
		if ( empty( $statuses['wc-created'] ) ) {
			$statuses = array_merge( [ 'wc-created' => $pending ? 'بعد از ثبت سفارش' : $pending_label . ' (بلافاصله بعد از ثبت سفارش)' ], $statuses );
		}

		$opt_statuses = [];
		foreach ( (array) $statuses as $status_val => $status_name ) {
			$opt_statuses[ $this->modify_status( $status_val ) ] = $status_name;
		}

		return $opt_statuses;
	}

	public function modify_status( $status ) {
		return str_ireplace( [ 'wc-', 'wc_' ], '', $status );
	}

	public function get_buyer_allowed_statuses( $pending = false ) {

		$statuses              = $this->get_all_statuses( $pending );
		$order_status_settings = (array) $this->get_option( 'order_status', [] );

		$allowed_statuses = [];
		foreach ( (array) $statuses as $status_val => $status_name ) {
			if ( in_array( $status_val, array_keys( $order_status_settings ) ) ) {
				$allowed_statuses[ $status_val ] = $status_name;
			}
		}

		return $allowed_statuses;
	}

	public function get_option( $option, $section = '', $default = '' ) {

		if ( ! empty( $section ) && ( ! is_string( $section ) || stripos( $section, '_settings' ) === false ) ) {

			if ( $section == '__' ) {
				$skip = true;
			} else {
				$default = $section;
			}

			unset( $section );
		}

		if ( ! empty( $section ) ) {
			$options = get_option( $section );
		} else {

			if ( empty( self::$all_options ) ) {

				$sections = Settings::settings_sections();
				$sections = wp_list_pluck( $sections, 'id' );

				$options = [];
				foreach ( $sections as $section ) {
					$section = get_option( $section );
					if ( ! empty( $section ) ) {
						$options = array_merge( $options, $section );
					}
				}

				self::$all_options = $options;
			}

			$options = self::$all_options;
		}

		$option = isset( $options[ $option ] ) ? $options[ $option ] : $default;

		if ( empty( $skip ) && ! empty( $option ) && is_string( $option ) ) {
			$option = $this->maybe_bool( $option );
		}


		return $option;
	}

	public function maybe_bool( $value ) {

		if ( empty( $value ) ) {
			return false;
		}

		if ( is_string( $value ) ) {

			if ( in_array( $value, [ 'on', 'true', 'yes' ] ) ) {
				return true;
			}

			if ( in_array( $value, [ 'off', 'false', 'no' ] ) ) {
				return false;
			}
		}

		return $value;
	}

	public function product_has_prop( $product, $prop ) {

		$check = true;

		$product_ids = (array) $this->maybe_variable( $product );
		foreach ( $product_ids as $product_id ) {

			$product = wc_get_product( $product_id );
			if ( ! PWSMS()->is_wc_product( $product ) ) {
				return false;
			}

			if ( $prop == 'is_not_low_stock' ) {

				if ( $check = ( PWSMS()->is_stock_managing( $product ) && $product->is_in_stock() && $this->product_stock_qty( $product_id ) > get_option( 'woocommerce_notify_low_stock_amount' ) ) ) {
					break;
				}

			} elseif ( method_exists( $product, $prop ) ) {
				$check = $check && $product->$prop();
			} else {
				$check = false;
			}
		}

		return $check;
	}

	public function maybe_variable( $product ) {

		$product_id = $this->product_ID( $product );
		$product    = wc_get_product( $product_id );
		if ( ! PWSMS()->is_wc_product( $product ) ) {
			return $product_id;
		}
		if ( $product->is_type( 'variable' ) ) {
			unset( $product_id );
			$product_ids = [];

			foreach ( (array) $this->product_prop( $product, 'children' ) as $product_id ) {
				$product_ids[] = $product_id;
			}

			return $product_ids;//array
		} else {

			return $product_id;//int
		}
	}

	public function product_ID( $product = '' ) {

		if ( empty( $product ) ) {
			$product_id = get_the_ID();
		} elseif ( is_numeric( $product ) ) {
			$product_id = $product;
		} elseif ( is_object( $product ) ) {
			$product_id = $this->product_prop( $product, 'id' );
		} else {
			$product_id = false;
		}

		return $product_id;
	}

	public function product_prop( $product, $prop ) {
		$method = 'get_' . $prop;

		return method_exists( $product, $method ) ? $product->$method() : ( ! empty( $product->{$prop} ) ? $product->{$prop} : '' );
	}

	public function is_wc_product( $product ) {

		if ( empty( $product ) || ! is_a( $product, WC_Product::class ) ) {
			return false;
		}

		return true;
	}

	public function is_stock_managing( $product ) {

		if ( 'yes' !== get_option( 'woocommerce_manage_stock' ) ) {
			return false;
		}

		if ( ! is_object( $product ) ) {
			$product = wc_get_product( $product );
			if ( ! PWSMS()->is_wc_product( $product ) ) {
				return false;
			}
		}

		if ( method_exists( $product, 'get_manage_stock' ) ) {
			$manage = $product->get_manage_stock();
		} elseif ( method_exists( $product, 'managing_stock' ) ) {
			$manage = $product->managing_stock();
		} else {
			$manage = true;
		}

		if ( strtolower( $manage ) == 'parent' ) {
			$manage = false;
		}

		return $manage;
	}

	public function product_stock_qty( $product ) {

		if ( ! is_object( $product ) ) {
			$product = wc_get_product( $product );
			if ( ! PWSMS()->is_wc_product( $product ) ) {
				return 0;
			}
		}

		if ( method_exists( $product, 'get_stock_quantity' ) ) {
			$quantity = $product->get_stock_quantity();
		} else {
			$quantity = $this->product_prop( $product, 'total_stock' );
		}

		if ( empty( $quantity ) ) {
			$quantity = (int) $product->get_meta( '_stock', true );
		}

		return ! empty( $quantity ) ? $quantity : 0;
	}

	public function multi_select_admin_field( $field ) {

		if ( ! isset( $field['placeholder'] ) ) {
			$field['placeholder'] = '';
		}
		if ( ! isset( $field['class'] ) ) {
			$field['class'] = 'short';
		}
		if ( ! isset( $field['options'] ) ) {
			$field['options'] = [];
		}

		if ( ! empty( $field['value'] ) ) {
			$field['value'] = array_filter( (array) $field['value'] );
		}
		//dont use else
		if ( empty( $field['value'] ) ) {
			$field['value'] = isset( $field['default'] ) ? $field['default'] : [];
		}

		$field['value']   = (array) $field['value'];
		$field['options'] = (array) $field['options'];

		echo '<p class="form-field ' . esc_attr( $field['id'] ) . '_field"><label style="display:block;" for="' . esc_attr( $field['id'] ) . '">' . esc_attr( $field['label'] ) . '</label>';
		echo '<select multiple="multiple" class="' . esc_attr( $field['class'] ) . '" name="' . esc_attr( $field['id'] ) . '[]" id="' . esc_attr( $field['id'] ) . '" ' . '>';

		foreach ( $field['options'] as $status_value => $status_name ) {
			echo '<option value="' . esc_attr( $status_value ) . '"' . selected( in_array( $status_value, $field['value'] ), true, true ) . '>' . esc_attr( $status_name ) . '</option>';
		}

		echo '</select>';
		echo '</p>';
	}

	public function replace_short_codes( $content, $order_status, WC_Order $order, $vendor_items_array = [] ) {

		$price = strip_tags( $this->order_prop( $order, 'formatted_order_total', [ '', false ] ) );
		$price = html_entity_decode( $price );

		$all_product_list = $this->all_items( $order );
		$all_product_ids  = ! empty( $all_product_list['product_ids'] ) ? $all_product_list['product_ids'] : [];
		$all_items        = ! empty( $all_product_list['items'] ) ? $all_product_list['items'] : [];
		$all_items_full   = ! empty( $all_product_list['items_full'] ) ? $all_product_list['items_full'] : [];
		$all_items_qty    = ! empty( $all_product_list['items_qty'] ) ? $all_product_list['items_qty'] : [];

		$vendor_product_ids = ! empty( $vendor_items_array['product_ids'] ) ? $vendor_items_array['product_ids'] : [];
		$vendor_items       = ! empty( $vendor_items_array['items'] ) ? $vendor_items_array['items'] : [];
		$vendor_items_qty   = ! empty( $vendor_items_array['items_qty'] ) ? $vendor_items_array['items_qty'] : [];
		$vendor_price       = ! empty( $vendor_items_array['price'] ) ? array_sum( (array) $vendor_items_array['price'] ) : 0;
		$vendor_price       = strip_tags( wc_price( $vendor_price ) );

		$payment_gateways = [];
		if ( WC()->payment_gateways() ) {
			$payment_gateways = WC()->payment_gateways->payment_gateways();
		}

		$payment_method  = $this->order_prop( $order, 'payment_method' );
		$payment_method  = ( isset( $payment_gateways[ $payment_method ] ) ? esc_html( $payment_gateways[ $payment_method ]->get_title() ) : esc_html( $payment_method ) );
		$shipping_method = esc_html( $this->order_prop( $order, 'shipping_method' ) );

		$country = WC()->countries;

		$bill_country = ( isset( $country->countries[ $this->order_prop( $order, 'billing_country' ) ] ) ) ? $country->countries[ $this->order_prop( $order, 'billing_country' ) ] : $this->order_prop( $order, 'billing_country' );
		$bill_state   = ( $this->order_prop( $order, 'billing_country' ) && $this->order_prop( $order, 'billing_state' ) && isset( $country->states[ $this->order_prop( $order, 'billing_country' ) ][ $this->order_prop( $order, 'billing_state' ) ] ) ) ? $country->states[ $this->order_prop( $order, 'billing_country' ) ][ $this->order_prop( $order, 'billing_state' ) ] : $this->order_prop( $order, 'billing_state' );

		$ship_country = ( isset( $country->countries[ $this->order_prop( $order, 'shipping_country' ) ] ) ) ? $country->countries[ $this->order_prop( $order, 'shipping_country' ) ] : $this->order_prop( $order, 'shipping_country' );
		$ship_state   = ( $this->order_prop( $order, 'shipping_country' ) && $this->order_prop( $order, 'shipping_state' ) && isset( $country->states[ $this->order_prop( $order, 'shipping_country' ) ][ $this->order_prop( $order, 'shipping_state' ) ] ) ) ? $country->states[ $this->order_prop( $order, 'shipping_country' ) ][ $this->order_prop( $order, 'shipping_state' ) ] : $this->order_prop( $order, 'shipping_state' );

		$tags = [
			'{b_first_name}'  => $this->order_prop( $order, 'billing_first_name' ),
			'{b_last_name}'   => $this->order_prop( $order, 'billing_last_name' ),
			'{b_company}'     => $this->order_prop( $order, 'billing_company' ),
			'{b_address_1}'   => $this->order_prop( $order, 'billing_address_1' ),
			'{b_address_2}'   => $this->order_prop( $order, 'billing_address_2' ),
			'{b_state}'       => $bill_state,
			'{b_city}'        => $this->order_prop( $order, 'billing_city' ),
			'{b_postcode}'    => $this->order_prop( $order, 'billing_postcode' ),
			'{b_country}'     => $bill_country,
			'{sh_first_name}' => $this->order_prop( $order, 'shipping_first_name' ),
			'{sh_last_name}'  => $this->order_prop( $order, 'shipping_last_name' ),
			'{sh_company}'    => $this->order_prop( $order, 'shipping_company' ),
			'{sh_address_1}'  => $this->order_prop( $order, 'shipping_address_1' ),
			'{sh_address_2}'  => $this->order_prop( $order, 'shipping_address_2' ),
			'{sh_state}'      => $ship_state,
			'{sh_city}'       => $this->order_prop( $order, 'shipping_city' ),
			'{sh_postcode}'   => $this->order_prop( $order, 'shipping_postcode' ),
			'{sh_country}'    => $ship_country,
			'{phone}'         => $this->buyer_mobile( $order->get_id() ),
			'{mobile}'        => $this->buyer_mobile( $order->get_id() ),
			'{email}'         => $this->order_prop( $order, 'billing_email' ),
			'{order_id}'      => $this->order_prop( $order, 'order_number' ),
			'{date}'          => $this->order_date( $order ),
			'{post_id}'       => $order->get_id(),
			'{status}'        => $this->status_name( $order_status, true ),
			'{price}'         => $price,

			'{all_items}'      => implode( ' - ', $all_items ),
			'{all_items_full}' => implode( ' - ', $all_items_full ),
			'{all_items_qty}'  => implode( ' - ', $all_items_qty ),
			'{count_items}'    => count( $all_items ),

			'{vendor_items}'       => implode( ' - ', $vendor_items ),
			'{vendor_items_qty}'   => implode( ' - ', $vendor_items_qty ),
			'{count_vendor_items}' => count( $vendor_items ),
			'{vendor_price}'       => $vendor_price,

			'{transaction_id}'  => $order->get_transaction_id(),
			'{payment_method}'  => $payment_method,
			'{shipping_method}' => $shipping_method,
			'{description}'     => nl2br( esc_html( $order->get_customer_note() ) )
		];

		$content = apply_filters( 'pwoosms_order_sms_body_before_replace', $content, array_keys( $tags ), array_values( $tags ), $order->get_id(), $order, $all_product_ids, $vendor_product_ids );

		$content = str_ireplace( array_keys( $tags ), array_values( $tags ), $content );
		$content = str_ireplace( [ '<br>', '<br/>', '<br />', '&nbsp;' ], [ '', '', '', ' ' ], $content );

		$content = apply_filters( 'pwoosms_order_sms_body_after_replace', $content, $order->get_id(), $order, $all_product_ids, $vendor_product_ids );

		return $content;
	}

	public function order_prop( $order, $prop, $args = [] ) {
		$method = 'get_' . $prop;

		if ( method_exists( $order, $method ) ) {
			if ( empty( $args ) || ! is_array( $args ) ) {
				return $order->$method();
			} else {
				return call_user_func_array( [ $order, $method ], $args );
			}
		}

		return ! empty( $order->{$prop} ) ? $order->{$prop} : '';
	}

	public function all_items( $order ) {

		$order_products = $this->get_prodcut_lists( $order );
		$items          = [];
		foreach ( (array) $order_products as $item_datas ) {
			foreach ( (array) $item_datas as $item_data ) {
				$this->prepare_items( $items, $item_data );
			}
		}

		$items['product_ids'] = array_keys( $order_products );

		return $items;
	}

	public function get_prodcut_lists( $order, $field = '' ) {

		$products = [];
		$fields   = [];

		foreach ( (array) $this->order_prop( $order, 'items' ) as $product ) {

			$parent_product_id = ! empty( $product['product_id'] ) ? $product['product_id'] : $this->product_ID( $product );
			$product_id        = $this->product_prop( $product, 'variation_id' );
			$product_id        = ! empty( $product_id ) ? $product_id : $parent_product_id;

			$item = [
				'id'         => $product_id,
				'product_id' => $parent_product_id,
				'qty'        => ! empty( $product['qty'] ) ? $product['qty'] : 0,
				'total'      => ! empty( $product['total'] ) ? $product['total'] : 0,
			];

			if ( ! empty( $field ) && isset( $item[ $field ] ) ) {
				$fields[] = $item[ $field ];
			}

			$products[ $parent_product_id ][] = $item;
		}

		if ( ! empty( $field ) ) {
			$products[ $field ] = $fields;
		}

		return $products;
	}

	public function prepare_items( &$items, $item_data ) {

		if ( ! empty( $item_data['id'] ) ) {
			$title                 = $this->product_title( $item_data['id'] );
			$title_full            = $this->product_title_full( $item_data['id'] );
			$items['items'][]      = $title;
			$items['items_full'][] = $title_full;
			$items['items_qty'][]  = $title . ' (' . $item_data['qty'] . ')';
			$items['price'][]      = $item_data['total'];
		}
	}

	/**
	 * This method will return product title only
	 * The variable product is without variations in title
	 *
	 * @param WC_Product|int $product
	 *
	 * @return string
	 */
	public function product_title( $product ): string {
		$product_id = $this->product_ID( $product );

		if ( ! is_object( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! PWSMS()->is_wc_product( $product ) ) {
			return '-';
		}

		$parent_id = $this->product_prop( $product, 'parent_id' );

		if ( ! empty( $parent_id ) ) {

			$parent = wc_get_product( $parent_id );

			if ( ! PWSMS()->is_wc_product( $parent ) ) {
				return '-';
			}

			$product_title = get_the_title( $parent_id );

		} else {
			$product_title = get_the_title( $product_id );
		}

		return html_entity_decode( urldecode( $product_title ) );
	}


	/**
	 * This method will return the full product title (variable products with variations in title)
	 * As it returns the variables in product title
	 *
	 * @param WC_Product|int $product
	 *
	 * @return string
	 */
	public function product_title_full( $product ) {
		$product_id = $this->product_ID( $product );

		if ( ! is_object( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! PWSMS()->is_wc_product( $product ) ) {
			return '-';
		}

		$product_title = get_the_title( $product_id );

		return html_entity_decode( urldecode( $product_title ) );
	}

	public function maybe_variable_product_title( $product ) {

		$product_id = $this->product_ID( $product );

		if ( ! is_object( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! PWSMS()->is_wc_product( $product ) ) {
			return '-';
		}

		$attributes = $this->product_prop( $product, 'variation_attributes' );
		$parent_id  = $this->product_prop( $product, 'parent_id' );

		if ( ! empty( $attributes ) && ! empty( $parent_id ) ) {

			$parent = wc_get_product( $parent_id );

			if ( ! PWSMS()->is_wc_product( $parent ) ) {
				return '-';
			}

			$variation_attributes = $this->product_prop( $parent, 'variation_attributes' );

			$variable_title = [];
			foreach ( (array) $attributes as $attribute_name => $options ) {

				$attribute_name = str_ireplace( 'attribute_', '', $attribute_name );

				foreach ( (array) $variation_attributes as $key => $value ) {
					$key = str_ireplace( 'attribute_', '', $key );

					if ( sanitize_title( $key ) == sanitize_title( $attribute_name ) ) {
						$attribute_name = $key;
						break;
					}
				}

				if ( ! empty( $options ) && substr( strtolower( $attribute_name ), 0, 3 ) !== 'pa_' ) {
					$variable_title[] = $attribute_name . ':' . $options;
				}
			}

			$product_title = get_the_title( $product_id );

			if ( ! empty( $variable_title ) ) {
				$product_title .= ' (' . implode( ' - ', $variable_title ) . ')';
			}
		} else {
			$product_title = get_the_title( $product_id );
		}

		return html_entity_decode( urldecode( $product_title ) );
	}

	public function buyer_mobile( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( ! PWSMS()->is_wc_order( $order ) ) {
			return '';
		}

		$meta = $this->buyer_mobile_meta();

		if ( is_callable( [ $order, 'get_' . $meta ] ) ) {
			$buyer_mobile = $order->{'get_' . $meta}();
		} else {
			$buyer_mobile = $order->get_meta( '_' . $meta );
		}

		return apply_filters( 'pwoosms_order_buyer_mobile', $buyer_mobile, $order_id, $order );
	}

	public function is_wc_order( $order ) {

		if ( empty( $order ) || ! is_a( $order, WC_Order::class ) ) {
			return false;
		}

		return true;
	}

	public function buyer_mobile_meta() {
		return apply_filters( 'pwoosms_mobile_meta', 'billing_phone' );
	}

	public function order_date( $order ) {

		$order_date = $this->order_prop( $order, 'date_paid' );
		if ( empty( $order_date ) ) {
			$order_date = $this->order_prop( $order, 'date_created' );
		}
		if ( empty( $order_date ) ) {
			$order_date = $this->order_prop( $order, 'date_modified' );
		}
		if ( ! empty( $order_date ) ) {
			if ( method_exists( $order_date, 'getOffsetTimestamp' ) ) {
				$order_date = gmdate( 'Y-m-d H:i:s', $order_date->getOffsetTimestamp() );
			}
		} else {
			$order_date = date_i18n( 'Y-m-d H:i:s' );
		}

		return $this->maybe_jalali_date( $order_date );
	}

	public function maybe_jalali_date( $date_time ) {

		if ( empty( $date_time ) ) {
			return '';
		}

		$date_time = $this->mobile_english_numbers( $date_time );

		$_date_time = explode( ' ', $date_time );
		$date       = ! empty( $_date_time[0] ) ? explode( '-', $_date_time[0], 3 ) : '';
		$time       = ! empty( $_date_time[1] ) ? $_date_time[1] : '';

		if ( count( $date ) != 3 || $date[0] < 2000 ) {
			return $date_time;
		}

		[ $year, $month, $day ] = $date;

		$date = $this->jalali_date( $year, $month, $day, '/' ) . ' - ' . $time;

		return trim( trim( $date ), '- ' );
	}

	public function mobile_english_numbers( $mobile ) {
		if ( is_array( $mobile ) ) {
			return array_map( [ $this, __FUNCTION__ ], $mobile );
		} else {

			$mobile = sanitize_text_field( $mobile );

			$mobile = str_ireplace( [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ], [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ], $mobile ); //farsi
			$mobile = str_ireplace( [ '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ], [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ], $mobile ); //arabi

			return $mobile;
		}
	}

	public function jalali_date( int $g_y, int $g_m, int $g_d, $mod = '' ) {
		$d_4   = $g_y % 4;
		$g_a   = [ 0, 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 ];
		$doy_g = $g_a[ (int) $g_m ] + $g_d;
		if ( $d_4 == 0 and $g_m > 2 ) {
			$doy_g ++;
		}
		$d_33 = (int) ( ( ( $g_y - 16 ) % 132 ) * .0305 );
		$a    = ( $d_33 == 3 or $d_33 < ( $d_4 - 1 ) or $d_4 == 0 ) ? 286 : 287;
		$b    = ( ( $d_33 == 1 or $d_33 == 2 ) and ( $d_33 == $d_4 or $d_4 == 1 ) ) ? 78 : ( ( $d_33 == 3 and $d_4 == 0 ) ? 80 : 79 );
		if ( (int) ( ( $g_y - 10 ) / 63 ) == 30 ) {
			$a --;
			$b ++;
		}
		if ( $doy_g > $b ) {
			$jy    = $g_y - 621;
			$doy_j = $doy_g - $b;
		} else {
			$jy    = $g_y - 622;
			$doy_j = $doy_g + $a;
		}
		if ( $doy_j < 187 ) {
			$jm = (int) ( ( $doy_j - 1 ) / 31 );
			$jd = $doy_j - ( 31 * $jm ++ );
		} else {
			$jm = (int) ( ( $doy_j - 187 ) / 30 );
			$jd = $doy_j - 186 - ( $jm * 30 );
			$jm += 7;
		}

		$jd = $jd > 9 ? $jd : '0' . $jd;
		$jm = $jm > 9 ? $jm : '0' . $jm;

		return ( $mod == '' ) ? [ $jy, $jm, $jd ] : $jy . $mod . $jm . $mod . $jd;
	}

	public function status_name( $status, $pending = false ) {

		$status = wc_get_order_status_name( $status );
		if ( $status == 'created' ) {
			$pending_label = _x( 'Pending payment', 'Order status', 'woocommerce' );
			$status        = $pending ? $pending_label : $pending_label . ' (بلافاصله بعد از ثبت سفارش)';
		}

		return $status;
	}

	public function sanitize_text_field( $post ) {
		if ( is_array( $post ) ) {
			return array_map( [ $this, __FUNCTION__ ], $post );
		}

		return sanitize_text_field( $post );
	}

	public function has_notif_condition( $key, $product_id ) {
		return $this->get_option( 'enable_notif_sms_main' ) && $this->maybe_bool( $this->get_product_meta_value( $key, $product_id ) );
	}

	public function get_product_meta_value( $key, $product_id ) {

		// _is_sms_set -> is_sms_set : it's the real value
		$key     = ltrim( $key, '_' );
		$product = wc_get_product( $product_id );

		if ( ! PWSMS()->is_wc_product( $product ) ) {
			return '-';
		}

		$sms_set = $product->get_meta( '_is_sms_set', true );

		if ( ( is_string( $sms_set ) && $this->maybe_bool( $sms_set ) ) || ( is_array( $sms_set ) && in_array( $key, $sms_set ) ) ) {
			return $product->get_meta( '_' . $key, true );

		}

		return $this->get_option( $key, '__' );
	}

	public function replace_tags( $key, $product_id, $parent_product_id ) {

		$sale_price_dates_from = ( $date = $this->product_sale_price_time( $product_id, 'from' ) ) ? date_i18n( 'Y-m-d', $date ) : '';
		$sale_price_dates_to   = ( $date = $this->product_sale_price_time( $product_id, 'to' ) ) ? date_i18n( 'Y-m-d', $date ) : '';

		$product        = wc_get_product( $product_id );
		$parent_product = wc_get_product( $parent_product_id );

		if ( ! PWSMS()->is_wc_product( $product ) ) {
			return '';
		}

		$sku = $this->product_prop( $product, 'sku' );
		if ( empty( $sku ) ) {
			$sku = $this->product_prop( $parent_product, 'sku' );
		}

		$tags = [
			'{product_id}'         => $parent_product_id,
			'{sku}'                => $sku,
			'{product_title}'      => $this->product_title( $product ),
			'{product_title_full}' => $this->product_title_full( $product ),
			'{regular_price}'      => strip_tags( wc_price( $this->product_prop( $product, 'regular_price' ) ) ),
			'{onsale_price}'       => strip_tags( wc_price( $this->product_prop( $product, 'sale_price' ) ) ),
			'{onsale_from}'        => $this->maybe_jalali_date( $sale_price_dates_from ),
			'{onsale_to}'          => $this->maybe_jalali_date( $sale_price_dates_to ),
			'{stock}'              => $this->product_stock_qty( $product ),
		];


		$content = $this->get_product_meta_value( $key, $parent_product_id );

		return str_replace( [ '<br>', '<br>', '<br />', '&nbsp;' ], [ '', '', '', ' ' ], str_replace( array_keys( $tags ), array_values( $tags ), $content ) );
	}

	public function product_sale_price_time( $product, $type = '' ) {

		if ( is_numeric( $product ) ) {
			$product_id = $product;
		} else {
			$product_id = $this->product_ID( $product );
		}

		$product = wc_get_product( $product_id );
		if ( ! PWSMS()->is_wc_product( $product ) ) {
			return '';
		}

		$timestamp = '';
		$method    = 'get_date_on_sale_' . $type;

		if ( method_exists( $product, $method ) ) {
			$timestamp = $product->$method();

			if ( is_object( $timestamp ) && method_exists( $timestamp, 'getOffsetTimestamp' ) ) {
				$timestamp = $timestamp->getOffsetTimestamp();
			}
		}

		if ( empty( $timestamp ) ) {
			$timestamp = $product->get_meta( '_sale_price_dates_' . $type, true );

		}

		return $timestamp;
	}

	public function product_admin_mobiles( $product_ids, $status = '' ) {

		$product_ids = array_unique( (array) $product_ids );
		$mobiles     = [];
		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! PWSMS()->is_wc_product( $product ) ) {
				return '';
			}

			$product_admin   = (array) $product->get_meta( '_pwoosms_product_admin_data', true );
			$product_admin[] = $this->user_mobile_meta( $product_id );
			$product_admin[] = $this->get_post_mobile_meta( $product_id );
			$product_admin   = array_filter( $product_admin );

			foreach ( (array) $product_admin as $data ) {

				if ( ! empty( $data['mobile'] ) && ! empty( $data['statuses'] ) && $this->validate_mobile( $data['mobile'] ) ) {

					$statuses = $this->prepare_admin_product_status( $data['statuses'] );

					if ( empty( $status ) || in_array( $status, $statuses ) ) {
						$_mobiles = array_map( 'trim', explode( ',', $data['mobile'] ) );
						foreach ( $_mobiles as $_mobile ) {
							$mobiles[ $_mobile ][] = $product_id;
						}
					}
				}
			}
		}

		return $mobiles;
	}

	public function user_mobile_meta( $post_id = 0 ) {

		$meta        = 'user';
		$empty_array = [ 'meta' => $meta, 'mobile' => '', 'statuses' => '' ];
		$data        = $this->get_saved_mobile_data( $meta, $post_id, $empty_array );
		if ( is_array( $data ) ) {
			return $data;
		}

		$meta_key = $this->get_option( "product_admin_{$meta}_meta" );
		if ( empty( $meta_key ) ) {
			unset( $empty_array['meta'] );

			return $empty_array;
		}

		$post_id = intval( $data );
		$post    = get_post( $post_id );

		if ( empty( $post->post_author ) ) {
			return $empty_array;
		}

		return [
			'meta'     => $meta,
			'mobile'   => get_user_meta( $post->post_author, $meta_key, true ),
			'statuses' => $this->get_option( 'product_admin_meta_order_status' ),
		];
	}

	public function get_saved_mobile_data( $meta, $post_id = 0, $empty_array = [] ) {

		if ( empty( $post_id ) ) {
			$screen = get_current_screen();
			// Check if we are on the post editing screen
			if ( empty( $screen ) || 'product' !== $screen->post_type ) {
				return $empty_array;
			}
			$post_id = isset( $_GET['post'] ) ? intval( $_GET['post'] ) : 0;
		}
		if ( empty( $post_id ) ) {
			return $empty_array;
		}

		$product = wc_get_product( $post_id );

		if ( ! PWSMS()->is_wc_product( $product ) ) {
			return $empty_array;
		}

		$data = $product->get_meta( '_pwoosms_product_admin_meta_' . $meta, true );

		if ( ! empty( $data ) ) {
			return (array) $data;//mobile and statuses that set via admin
		}

		return $post_id;
	}

	public function get_post_mobile_meta( $post_id = 0 ) {

		$meta        = 'post';
		$empty_array = [ 'meta' => $meta, 'mobile' => '', 'statuses' => '' ];
		$data        = $this->get_saved_mobile_data( $meta, $post_id, $empty_array );
		if ( is_array( $data ) ) {
			return $data;
		}

		$meta_key = $this->get_option( "product_admin_{$meta}_meta" );
		if ( empty( $meta_key ) ) {
			unset( $empty_array['meta'] );

			return $empty_array;
		}

		$post_id = intval( $data );
		$product = wc_get_product( $post_id );
		$mobile  = $product->get_meta( $meta_key, true );

		return [
			'meta'     => $meta,
			'mobile'   => $mobile,
			'statuses' => $this->get_option( 'product_admin_meta_order_status' ),
		];
	}

	public function validate_mobile( $mobile ) {

		$mobile = $this->modify_mobile( $mobile );

		return preg_match( '/9\d{9,}?$/', trim( $mobile ) );
	}

	public function modify_mobile( $mobile ) {

		if ( is_array( $mobile ) ) {
			return array_map( [ $this, __FUNCTION__ ], $mobile );
		}

		$mobile = $this->mobile_english_numbers( $mobile );

		$modified = preg_replace( '/\D/is', '', (string) $mobile );

		if ( substr( $mobile, 0, 1 ) == '+' ) {
			return '+' . $modified;
		} elseif ( substr( $modified, 0, 2 ) == '00' ) {
			return '+' . substr( $modified, 2 );
		} elseif ( substr( $modified, 0, 1 ) == '0' ) {
			return $modified;
		} elseif ( ! empty( $modified ) ) {
			$modified = '0' . $modified;
		}

		return str_replace( '+980', '0', $modified );
	}

	public function prepare_admin_product_status( $statuses, $array = true ) {

		$delimator = '-sv-';

		if ( ! is_array( $statuses ) ) {
			$statuses = explode( $delimator, $statuses );
		}

		$statuses = array_map( 'trim', $statuses );
		$statuses = array_map( [ $this, 'sanitize_text_field' ], $statuses );
		$statuses = array_unique( array_filter( $statuses ) );

		//واسه مقایسه کردن لازم میشه
		sort( $statuses );

		if ( $array ) {
			return $statuses;
		}

		return implode( $delimator, $statuses );
	}

	public function product_admin_items( $order_products, $product_ids ) {

		$product_ids = array_unique( $product_ids );

		$items = [];
		foreach ( $product_ids as $product_id ) {
			$item_datas = $order_products[ $product_id ];
			foreach ( (array) $item_datas as $item_data ) {
				$this->prepare_items( $items, $item_data );
			}
		}

		$items['product_ids'] = $product_ids;

		return $items;
	}

	public function order_ID( $order ) {
		return $this->order_prop( $order, 'id' );
	}

	public function order_note_metabox( WC_Order $order ) {

		if ( ! class_exists( 'WC_Meta_Box_Order_Notes' ) ) {
			return '';
		}

		if ( ! method_exists( 'WC_Meta_Box_Order_Notes', 'output' ) ) {
			return '';
		}

		ob_start();
		WC_Meta_Box_Order_Notes::output( $order );

		return ob_get_clean();
	}

	public function SendSMS( $data ) {
		_doing_it_wrong( __METHOD__, 'SendSMS() is deprecated. Use send_sms() instead.', '1.0.0' );

		return $this->send_sms( $data );
	}

	public function send_sms( $data ) {
		// TODO: Set mobile string handling in better way
		$message = ! empty( $data['message'] ) ? esc_textarea( $data['message'] ) : '';

		$mobile = ! empty( $data['mobile'] ) ? $data['mobile'] : '';
		if ( ! is_array( $mobile ) ) {
			$mobile = explode( ',', $mobile );
		}

		$mobile = $this->modify_mobile( $mobile );
		$mobile = explode( ',', implode( ',', (array) $mobile ) );
		$mobile = array_map( 'trim', $mobile );
		$mobile = array_unique( array_filter( $mobile ) );

		$gateway_obj   = $this->get_sms_gateway();
		$gateway_class = get_class( $gateway_obj );

		if ( empty( $mobile ) ) {
			$result = 'شماره موبایل خالی است . ';
		} elseif ( empty( $message ) ) {
			$result = 'متن پیامک خالی است . ';
		} elseif ( empty( $gateway_class ) ) {
			$result = 'تنظیمات درگاه پیامک انجام نشده است . ';
		} elseif ( ! class_exists( $gateway_class ) ) {
			$result = 'درگاه پیامکی شما وجود ندارد.';
		} else {

			try {

				$gateway_obj->mobile  = $mobile;
				$gateway_obj->message = $message;

				$result = $gateway_obj->send( $data );
			} catch ( Exception $e ) {
				$result = $e->getMessage();
			}
		}

		if ( $result !== true && ! is_string( $result ) ) {
			ob_start();
			var_dump( $result );
			$result = ob_get_clean();
		}

		if ( ! empty( $mobile ) && ! empty( $message ) ) {

			$sender = '( ' . $gateway_obj->senderNumber . ' ) ' . $gateway_obj->name();

			Archive::insert_record( [
				'post_id'  => ! empty( $data['post_id'] ) ? $data['post_id'] : '',
				'type'     => ! empty( $data['type'] ) ? $data['type'] : 0,
				'reciever' => implode( ',', (array) $mobile ),
				'message'  => $message,
				'sender'   => $sender,
				'result'   => $result === true ? '_ok_' : $result,
			] );
		}

		return $result;
	}

	/**
	 * Return the current active gateway
	 *
	 * @return GatewayInterface
	 */
	public static function get_sms_gateway() {

		$active_gateway = PWSMS()->get_option( 'sms_gateway' );

		if ( ! class_exists( $active_gateway ) || ! is_subclass_of( $active_gateway, GatewayInterface::class ) ) {
			$active_gateway = Logger::class;
		}

		return new $active_gateway();
	}

	public function get_sms_gateways() {

		$gateways          = [];
		$excluded_gateways = [//'PW\PWSMS\Gateways\IppanelSms' => 'ippanelsms',
		];
		// Gateways are static as namespace and directory
		$namespace = 'PW\PWSMS\Gateways';
		$dir       = PWSMS_DIR . '/src/Gateways';

		// Scan the directory for PHP files
		foreach ( glob( "$dir/*.php" ) as $file ) {
			$class = basename( $file, '.php' );
			// Create Full Qualified Class Name based on file names without .php
			$fqcn           = "$namespace\\$class";
			$active_gateway = PWSMS()->get_option( 'sms_gateway' );

			if ( empty( $active_gateway ) ) {
				$active_gateway = Logger::class;
			}
			if ( class_exists( $active_gateway ) ) {
				$active_gateway_id = $active_gateway::id();
			} else {
				// Rollback support on older systems where the raw id of sms gateway got stored
				$active_gateway_id = $active_gateway;
			}

			if ( class_exists( $fqcn ) ) {
				$reflectionClass = new ReflectionClass( $fqcn );
				// Check if the target class implements GatewayInterface and exclude main classes
				$is_normal_class = $reflectionClass->implementsInterface( GatewayInterface::class ) && ! $reflectionClass->isAbstract() && ! $reflectionClass->isTrait();

				if ( $is_normal_class ) {
					$id   = $fqcn::id();
					$name = $fqcn::name();

					if ( $id == $active_gateway_id ) {

						// Migrate sms_gateway to the fully qualified class name
						$this->update_option( 'sms_main_settings', 'sms_gateway', $fqcn );
					}

					$gateways[ $fqcn ] = $name;
				}

			}

		}

		// Purify gateways
		$gateways = array_diff( $gateways, $excluded_gateways );

		return apply_filters( 'pwoosms_sms_gateways', $gateways );
	}

	public function update_option( $section, $option, $value ) {
		$section_settings = get_option( $section );

		if ( $section_settings && isset( $section_settings[ $option ] ) ) {
			$section_settings[ $option ] = $value;
			update_option( $section, $section_settings );
		}

	}

	/**
	 * Check if HPOS enabled.
	 */
	public function is_wc_order_hpos_enabled() {
		return function_exists( 'wc_get_container' ) ? wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled() : false;
	}

	public function extract_last_ten_digits( $phone_number ) {
		// Remove non-digit characters
		$phone_number = preg_replace( '/\D/', '', $phone_number );

		// Check the length of the cleaned phone number
		if ( strlen( $phone_number ) < 10 ) {
			return false; // Not enough digits
		}

		// Extract the last 10 digits
		return substr( $phone_number, - 10 );
	}


}

