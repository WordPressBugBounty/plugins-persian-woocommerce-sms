<?php

namespace PW\PWSMS\Repositories;

use PW\PWSMS\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class ContactsRepository
 *
 * A repository class for interacting with the woocommerce_ir_sms_contacts table.
 */
class ContactsRepository {

	/**
	 * @var ContactsRepository The single instance of the class.
	 */
	private static $instance = null;
	/**
	 * @var Repository $repository The Repository instance.
	 */
	private $repository;

	/**
	 * ContactsRepository constructor.
	 */
	private function __construct() {
		$this->repository = Repository::instance();
		$this->repository->table( 'woocommerce_ir_sms_contacts' );
	}

	/**
	 * Get the single instance of the class.
	 *
	 * @return ContactsRepository
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Remove the contacts from subscription when specific event triggered on them
	 *
	 * @param array $mobiles Array of mobile numbers.
	 * @param string $group The group to remove.
	 * @param int $post_id The post ID to filter by.
	 */
	public function remove_contacts_group( $mobiles, $group, $post_id ) {
		// Convert the array of mobile numbers to a comma-separated string for use in SQL
		$mobiles_list = "'" . implode( "', '", $mobiles ) . "'";

		// Step 1: Fetch the existing 'groups' values for the given mobiles and post_id
		$query = "
        SELECT id, groups
        FROM {$this->repository->table}
        WHERE mobile IN ($mobiles_list)
        AND product_id = %d
    ";
		$results = $this->repository->wpdb->get_results( $this->repository->wpdb->prepare( $query, $post_id ) );

		// Step 2: Process each result
		foreach ( $results as $row ) {
			// Split the groups into an array
			$groups = explode( ',', $row->groups );

			// Step 3: Remove the specified group from the array if it exists
			$groups = array_diff( $groups, [ $group ] );

			// Step 4: If the array is empty after removing the group, delete the row
			if ( empty( $groups ) ) {
				// Delete the row from the database
				$delete_sql = "
                DELETE FROM {$this->repository->table}
                WHERE id = %d
            ";
				$this->repository->wpdb->query( $this->repository->wpdb->prepare( $delete_sql, $row->id ) );
			} else {
				// Step 5: Otherwise, update the row with the new groups list
				$new_groups = implode( ',', $groups );
				$update_sql = "
                UPDATE {$this->repository->table}
                SET groups = %s
                WHERE id = %d
            ";
				$this->repository->wpdb->query( $this->repository->wpdb->prepare( $update_sql, $new_groups, $row->id ) );
			}
		}

		return true;
	}



}
