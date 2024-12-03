<?php

namespace PW\PWSMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WP_Repository
 *
 * A repository class for interacting with the WordPress database.
 */
class Repository {

	/**
	 * @var Repository The single instance of the class.
	 */
	private static $instance = null;
	/**
	 * @var wpdb $wpdb WordPress database object.
	 */
	public $wpdb;
	/**
	 * @var string $table The current table.
	 */
	public $table;

	/**
	 * @var array $data The data to insert/update.
	 */
	private $data;

	/**
	 * @var array $where The WHERE conditions.
	 */
	private $where;

	/**
	 * WP_Repository constructor.
	 */
	private function __construct() {
		$this->wpdb = $GLOBALS['wpdb'];
	}

	/**
	 * Get the single instance of the class.
	 *
	 * @return Repository
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function get_wpdb() {
		return $this->wpdb;

	}

	/**
	 * Set the table to use.
	 *
	 * @param string $table The table name.
	 *
	 * @return Repository
	 */
	public function table( $table ) {
		$this->table = $this->wpdb->prefix . $table;

		return $this;
	}

	/**
	 * Set the data for insert/update.
	 *
	 * @param array $data The data array.
	 *
	 * @return Repository
	 */
	public function data( $data ) {
		$this->data = $data;

		return $this;
	}

	/**
	 * Set the WHERE conditions.
	 *
	 * @param array $where The where conditions.
	 *
	 * @return Repository
	 */
	public function where( $where ) {
		$this->where = $where;

		return $this;
	}

	/**
	 * Get a single row from the database.
	 *
	 * @return object|null The database row as an object, or null if no row found.
	 */
	public function get_row() {
		$where_clause = $this->build_where_clause( $this->where );
		$sql          = "SELECT * FROM {$this->table} $where_clause LIMIT 1";

		return $this->wpdb->get_row( $sql );
	}

	/**
	 * Build a WHERE clause from an associative array.
	 *
	 * @param array $where WHERE conditions in key-value pairs.
	 *
	 * @return string The WHERE clause.
	 */
	protected function build_where_clause( $where ) {
		if ( empty( $where ) ) {
			return '';
		}

		$clauses = [];
		foreach ( $where as $key => $value ) {
			$clauses[] = $this->wpdb->prepare( "$key = %s", $value );
		}

		return 'WHERE ' . implode( ' AND ', $clauses );
	}

	/**
	 * Get multiple rows from the database.
	 *
	 * @return array The database rows as an array of objects.
	 */
	public function get_results() {
		$where_clause = $this->build_where_clause( $this->where );
		$sql          = "SELECT * FROM {$this->table} $where_clause";

		return $this->wpdb->get_results( $sql );
	}

	/**
	 * Insert a row into the database.
	 *
	 * @return int|false The number of rows affected, or false on error.
	 */
	public function insert() {
		$result = $this->wpdb->insert( $this->table, $this->data );

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Update a row in the database.
	 *
	 * @return int|false The number of rows affected, or false on error.
	 */
	public function update() {
		return $this->wpdb->update( $this->table, $this->data, $this->where );
	}

	/**
	 * Delete a row from the database.
	 *
	 * @return int|false The number of rows affected, or false on error.
	 */
	public function delete() {
		return $this->wpdb->delete( $this->table, $this->where );
	}
}


