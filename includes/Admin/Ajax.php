<?php
/**
 * Admin AJAX handlers.
 *
 * @package Scrutinizer
 */

namespace Scrutinizer\Admin;

use Scrutinizer\Profiler\Session;
use Scrutinizer\Profiler\Storage;

/**
 * Registers and handles AJAX actions for the dashboard.
 */
class Ajax {

	/**
	 * Register AJAX handlers.
	 */
	public static function register() {
		add_action( 'wp_ajax_scrutinizer_start_profiling', array( __CLASS__, 'start_profiling' ) );
		add_action( 'wp_ajax_scrutinizer_stop_profiling', array( __CLASS__, 'stop_profiling' ) );
		add_action( 'wp_ajax_scrutinizer_get_profiles', array( __CLASS__, 'get_profiles' ) );
		add_action( 'wp_ajax_scrutinizer_get_profile_detail', array( __CLASS__, 'get_profile_detail' ) );
		add_action( 'wp_ajax_scrutinizer_delete_profile', array( __CLASS__, 'delete_profile' ) );
	}

	/**
	 * Start a profiling session.
	 */
	public static function start_profiling() {
		check_ajax_referer( 'scrutinizer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'scrutinizer' ) ),
				403
			);
		}

		$target = '';
		if ( isset( $_POST['target'] ) ) {
			$target = sanitize_text_field( wp_unslash( $_POST['target'] ) );
		}

		$activation_url = Session::create_activation_url( $target );

		wp_send_json_success(
			array(
				'activation_url' => $activation_url,
				'message'        => __( 'Profiling session created. Visit the activation URL to begin capturing.', 'scrutinizer' ),
			)
		);
	}

	/**
	 * Stop the active profiling session.
	 */
	public static function stop_profiling() {
		check_ajax_referer( 'scrutinizer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'scrutinizer' ) ),
				403
			);
		}

		$session_id = Session::get_session_id();
		Session::stop_session();

		$profiles = array();
		if ( ! empty( $session_id ) ) {
			$profiles = Storage::get_profiles( $session_id );
		}

		wp_send_json_success(
			array(
				'session_id'    => $session_id,
				'profile_count' => count( $profiles ),
				'profiles'      => $profiles,
				'message'       => __( 'Profiling session stopped.', 'scrutinizer' ),
			)
		);
	}

	/**
	 * Get profiles for a session.
	 */
	public static function get_profiles() {
		check_ajax_referer( 'scrutinizer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'scrutinizer' ) ),
				403
			);
		}

		$session_id = '';
		if ( isset( $_GET['session_id'] ) ) {
			$session_id = sanitize_text_field( wp_unslash( $_GET['session_id'] ) );
		}

		if ( empty( $session_id ) ) {
			$session_id = Session::get_session_id();
		}

		if ( empty( $session_id ) ) {
			wp_send_json_success( array( 'profiles' => array() ) );
		}

		$profiles = Storage::get_profiles( $session_id );

		wp_send_json_success( array( 'profiles' => $profiles ) );
	}

	/**
	 * Get full detail for a single profile.
	 */
	public static function get_profile_detail() {
		check_ajax_referer( 'scrutinizer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'scrutinizer' ) ),
				403
			);
		}

		$profile_id = 0;
		if ( isset( $_GET['profile_id'] ) ) {
			$profile_id = absint( $_GET['profile_id'] );
		}

		if ( empty( $profile_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No profile ID specified.', 'scrutinizer' ) ),
				400
			);
		}

		$profile = Storage::get_profile( $profile_id );

		if ( null === $profile ) {
			wp_send_json_error(
				array( 'message' => __( 'Profile not found.', 'scrutinizer' ) ),
				404
			);
		}

		wp_send_json_success( array( 'profile' => $profile ) );
	}

	/**
	 * Delete a profile.
	 */
	public static function delete_profile() {
		check_ajax_referer( 'scrutinizer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'scrutinizer' ) ),
				403
			);
		}

		$profile_id = 0;
		if ( isset( $_POST['profile_id'] ) ) {
			$profile_id = absint( $_POST['profile_id'] );
		}

		if ( empty( $profile_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No profile ID specified.', 'scrutinizer' ) ),
				400
			);
		}

		$deleted = Storage::delete_profile( $profile_id );

		if ( ! $deleted ) {
			wp_send_json_error(
				array( 'message' => __( 'Failed to delete profile.', 'scrutinizer' ) ),
				500
			);
		}

		wp_send_json_success(
			array( 'message' => __( 'Profile deleted.', 'scrutinizer' ) )
		);
	}
}
