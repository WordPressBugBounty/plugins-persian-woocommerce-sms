<?php

namespace PW\PWSMS;

use PW\PWSMS\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts the readme.txt change log to WordPress page
 */
class ChangeLog {

	/**
	 * @var string the changelog page slug in WordPress
	 */
	public static string $page_slug = 'pwsms-changelog';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
	}

	/**
	 * Registers the page, but doesn't add it to the menu
	 *
	 * @action admin_init
	 *
	 * @return void
	 */
	public function register_admin_page(): void {

		add_submenu_page(
			'pwsms-void',
			'لیست تغییرات پیامک حرفه ای ووکامرس',
			'لیست تغییرات پیامک',
			'manage_options',
			'pwsms-changelog',
			[ $this, 'render_changelog_page' ]
		);

	}

	/**
	 * Reads the readme.txt and printout the changelog page
	 *
	 * @used-by register_admin_page
	 *
	 * @return void
	 */
	public function render_changelog_page(): void {
		$readme_file = PWSMS_DIR . '/readme.txt';

		echo '<div class="pwsms-changelog__container"><h1>لیست تغییرات  ';
		echo 'افزونه پیامک حرفه‌ای ووکامرس';
		echo '</h1>';

		if ( ! file_exists( $readme_file ) ) {
			echo '<p><strong>خطا:</strong> فایل readme.txt یافت نشد.</p></div>';

			return;
		}

		$contents = file_get_contents( $readme_file );
		// Using strpos to determine line of changeleog
		$changelog_start = strpos( $contents, '== Changelog ==' );

		if ( $changelog_start === false ) {
			echo '<p><strong>خطا:</strong> لیست تغییراتی وجود ندارد.</p></div>';

			return;
		}

		$changelog = substr( $contents, $changelog_start );
		echo wp_kses( $this->convert_changelog_to_html( $changelog ), [ 'div' => [ 'class' => [] ], 'ul' => [ 'class' => [] ], 'li' => [ 'class' => [] ], 'h2' => [ 'class' => [] ], 'a' => [ 'href' => [], 'target' => [], 'class' => [] ], 'b' => [] ], );

		echo '</div>';
	}

	/**
	 * Convert custom format in readme.txt to fancy html
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	private function convert_changelog_to_html( string $text ): string {
		$lines    = explode( "\n", $text );
		$versions = [];
		$current  = null;

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( preg_match( '/^==\s*Changelog\s*==$/', $line ) ) {
				continue;
			}

			if ( preg_match( '/^= (.+) =$/', $line, $matches ) ) {
				if ( $current ) {
					$versions[] = $current;
				}
				$current = [
					'version' => trim( $matches[1] ),
					'changes' => [],
				];
			} elseif ( strpos( $line, '*' ) === 0 && $current ) {
				$current['changes'][] = trim( ltrim( $line, '* ' ) );
			}
		}

		if ( $current ) {
			$versions[] = $current;
		}

		// Only keep the latest 3 versions
		$versions       = array_slice( $versions, 0, 3 );
		$pwsms_page_url = Settings::get_page_url();

		$html = '<div class="pwsms-changelog">';
		foreach ( $versions as $entry ) {
			$html .= '<div class="changelog-version">';
			$html .= '<h2 class="changelog-title">نسخه ' . esc_html( $entry['version'] ) . '</h2>';
			$html .= '<ul class="changelog-list">';
			foreach ( $entry['changes'] as $change ) {
				$html .= '<li><b>' . esc_html( $change ) . '</b></li>';
			}
			$html .= '</ul>';
			$html .= '</div>';
		}

		$html .= '<div class="changelog-footer">';
		$html .= '<a href="https://wordpress.org/plugins/persian-woocommerce-sms/#developers" target="_blank" class="changelog-link pwsms-button">مشاهده تمام تغییرات</a>';
		$html .= '<a class="pwsms-button secondary" href="' . esc_url( $pwsms_page_url ) . '">بازگشت به پنل تنظیمات</a>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}


	/**
	 * Get the changelog page url
	 *
	 * @return string
	 */
	public static function get_page_url(): string {
		return admin_url( 'admin.php?page=' . self::$page_slug );
	}

}
