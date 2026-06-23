<?php
/**
 * Profile data storage.
 *
 * @package Scrutinizer
 */

namespace Scrutinizer\Profiler;

/**
 * Persists profile data in a custom database table.
 */
class Storage {

	/**
	 * Return the table name including the WordPress prefix.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'scrutinizer_profiles';
	}

	/**
	 * Create the profiles table using dbDelta.
	 */
	public static function create_table() {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(64) NOT NULL DEFAULT '',
			request_url text NOT NULL,
			request_method varchar(10) NOT NULL DEFAULT 'GET',
			route_class varchar(50) NOT NULL DEFAULT '',
			duration_ns bigint(20) unsigned NOT NULL DEFAULT 0,
			profile_data longtext NOT NULL,
			captured_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			is_baseline tinyint(1) NOT NULL DEFAULT 0,
			baseline_name varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY session_id (session_id),
			KEY is_baseline (is_baseline)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drop the profiles table.
	 */
	public static function drop_table() {
		global $wpdb;

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	/**
	 * Save a profile.
	 *
	 * @param string $session_id   Session identifier.
	 * @param array  $profile_data  Compiled profile data.
	 * @return int|false  Inserted row ID or false on failure.
	 */
	public static function save_profile( $session_id, $profile_data ) {
		global $wpdb;

		$url    = isset( $profile_data['request']['url'] ) ? $profile_data['request']['url'] : '';
		$method = isset( $profile_data['request']['method'] ) ? $profile_data['request']['method'] : 'GET';
		$route  = isset( $profile_data['request']['route_class'] ) ? $profile_data['request']['route_class'] : '';
		$dur_ns = isset( $profile_data['summary']['duration_ns'] ) ? $profile_data['summary']['duration_ns'] : 0;

		$result = $wpdb->insert(
			self::table_name(),
			array(
				'session_id'     => $session_id,
				'request_url'    => $url,
				'request_method' => $method,
				'route_class'    => $route,
				'duration_ns'    => $dur_ns,
				'profile_data'   => wp_json_encode( $profile_data ),
				'captured_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get all profiles for a session.
	 *
	 * @param string $session_id  Session identifier.
	 * @return array
	 */
	public static function get_profiles( $session_id ) {
		global $wpdb;

		$table = self::table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe from self::table_name().
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, session_id, request_url, request_method, route_class, duration_ns, captured_at, is_baseline, baseline_name FROM {$table} WHERE session_id = %s ORDER BY captured_at DESC",
				$session_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get a single profile by ID.
	 *
	 * @param int $id  Profile row ID.
	 * @return array|null
	 */
	public static function get_profile( $id ) {
		global $wpdb;

		$table = self::table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe from self::table_name().
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( null === $row ) {
			return null;
		}

		$row['profile_data'] = json_decode( $row['profile_data'], true );

		return $row;
	}

	/**
	 * Delete a profile by ID.
	 *
	 * @param int $id  Profile row ID.
	 * @return bool
	 */
	public static function delete_profile( $id ) {
		global $wpdb;

		$result = $wpdb->delete(
			self::table_name(),
			array( 'id' => $id ),
			array( '%d' )
		);

		return ( false !== $result );
	}

	/**
	 * Mark a profile as a baseline.
	 *
	 * @param int    $profile_id  Profile row ID.
	 * @param string $name        Baseline name.
	 * @return bool
	 */
	public static function save_baseline( $profile_id, $name ) {
		global $wpdb;

		$result = $wpdb->update(
			self::table_name(),
			array(
				'is_baseline'   => 1,
				'baseline_name' => $name,
			),
			array( 'id' => $profile_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return ( false !== $result );
	}

	/**
	 * Get all baselines.
	 *
	 * @return array
	 */
	public static function get_baselines() {
		global $wpdb;

		$table = self::table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe from self::table_name().
		return $wpdb->get_results(
			"SELECT id, session_id, request_url, request_method, route_class, duration_ns, captured_at, baseline_name FROM {$table} WHERE is_baseline = 1 ORDER BY captured_at DESC",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
