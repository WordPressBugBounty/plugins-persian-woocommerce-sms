<?php

namespace PW\PWSMS\Enums;

class EventsEnum extends EnumBase {
	
	public const BULK_SEND = 1;
	public const CUSTOMER_AUTOMATIC_ORDER = 2;
	public const CUSTOMER_MANUAL_ORDER_METABOX = 3;
	public const SUPER_ADMIN_AUTOMATIC_ORDER = 4;
	public const PRODUCT_MANAGER_AUTOMATIC_ORDER = 5;
	public const PRODUCT_MANAGER_MANUAL_PRODUCT_METABOX = 6;
	public const MANAGERS_AUTOMATIC_OUT_OF_STOCK = 7;
	public const MANAGERS_AUTOMATIC_LOW_STOCK = 8;
	public const NEWSLETTER_SALE_AUTOMATIC = 9;
	public const NEWSLETTER_SALE_MANUAL = 10;
	public const NEWSLETTER_IN_STOCK_AUTOMATIC = 11;
	public const NEWSLETTER_IN_STOCK_MANUAL = 12;
	public const NEWSLETTER_LOW_STOCK_AUTOMATIC = 13;
	public const NEWSLETTER_LOW_STOCK_MANUAL = 14;
	public const NEWSLETTER_CUSTOM_OPTIONS_MANUAL = 15;
	public const NEWSLETTER_DELAYED_PRODUCT_SMS_AUTOMATIC = 16;

	public static function label( int $key ): string {

		switch ( $key ) {
			case self::BULK_SEND:
				return 'ارسال دسته جمعی';
			case self::CUSTOMER_AUTOMATIC_ORDER:
				return 'مشتری - خودکار - سفارش';
			case self::CUSTOMER_MANUAL_ORDER_METABOX:
				return 'مشتری - دستی - متاباکس سفارش';
			case self::SUPER_ADMIN_AUTOMATIC_ORDER:
				return 'مدیر کل - خودکار - سفارش';
			case self::PRODUCT_MANAGER_AUTOMATIC_ORDER:
				return 'مدیر محصول - خودکار - سفارش';
			case self::PRODUCT_MANAGER_MANUAL_PRODUCT_METABOX:
				return 'مدیر محصول - دستی - متاباکس محصول';
			case self::MANAGERS_AUTOMATIC_OUT_OF_STOCK:
				return 'مدیران - خودکار - ناموجود شدن';
			case self::MANAGERS_AUTOMATIC_LOW_STOCK:
				return 'مدیران - خودکار - کم بودن موجودی';
			case self::NEWSLETTER_SALE_AUTOMATIC:
				return 'خبرنامه - حراج شدن - اتوماتیک';
			case self::NEWSLETTER_SALE_MANUAL:
				return 'خبرنامه - حراج شدن - دستی';
			case self::NEWSLETTER_IN_STOCK_AUTOMATIC:
				return 'خبرنامه - موجود شدن - اتوماتیک';
			case self::NEWSLETTER_IN_STOCK_MANUAL:
				return 'خبرنامه - موجود شدن - دستی';
			case self::NEWSLETTER_LOW_STOCK_AUTOMATIC:
				return 'خبرنامه - کم بودن موجودی - اتوماتیک';
			case self::NEWSLETTER_LOW_STOCK_MANUAL:
				return 'خبرنامه - کم بودن موجودی - دستی';
			case self::NEWSLETTER_CUSTOM_OPTIONS_MANUAL:
				return 'خبرنامه - گزینه های دلخواه - دستی';
			/*case self::NEWSLETTER_DELAYED_PRODUCT_SMS_AUTOMATIC:
				return 'خبرنامه - پیامک زمان‌دار محصول - اتوماتیک';*/
			default:
				return '';
		}

	}
}