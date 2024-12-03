<?php

namespace PW\PWSMS\Repositories;

use PW\PWSMS\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class SMSArchiveRepository
 *
 * A repository class for interacting with the woocommerce_ir_sms_archive table.
 */
class SMSRepository {

	/**
	 * @var SMSRepository The single instance of the class.
	 */
	private static $instance = null;
	/**
	 * @var Repository $repository The Repository instance.
	 */
	private $repository;

	/**
	 * SMSArchiveRepository constructor.
	 */
	private function __construct() {
		$this->repository = Repository::instance();
		$this->repository->table( 'woocommerce_ir_sms_archive' );
	}

	/**
	 * Get the single instance of the class.
	 *
	 * @return SMSRepository
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Remove sent mobiles from the given array based on the receiver column in the database.
	 *
	 * @param array $mobiles Array of mobile numbers.
	 * @param int $type The type to filter by.
	 * @param int $post_id The post ID to filter by.
	 *
	 * @return array Array of unique mobile numbers.
	 */
	public function remove_sent_mobiles( $mobiles, $type, $post_id ) {
		// TODO : Migrate from reciever to receiver
		// Prepare the SQL query to get receivers with the given type and post_id
		$query = $this->repository->wpdb->prepare(
			"SELECT reciever FROM {$this->repository->table} WHERE type = %d AND post_id = %d",
			$type, $post_id
		);

		// Get the results from the database
		$results = $this->repository->wpdb->get_col( $query );

		// Initialize an array to store all found mobile numbers
		$sent_mobiles = [];

		// Loop through each result and extract the mobile numbers
		foreach ( $results as $receivers ) {
			$sent_mobiles = array_merge( $sent_mobiles, explode( ',', $receivers ) );
		}

		// Remove any duplicates from the $sent_mobiles array
		$sent_mobiles = array_unique( $sent_mobiles );

		// Exclude sent mobiles from the input array of mobiles
		$unique_mobiles = array_diff( $mobiles, $sent_mobiles );

		return $unique_mobiles;
	}
}
