<?php
/**
 * Profiler orchestrator.
 *
 * @package Scrutinizer
 */

namespace Scrutinizer\Profiler;

/**
 * Central profiler orchestrator. Singleton.
 *
 * Checks for an active session, instruments hooks, collects timing data,
 * compiles a report, and stores it when the request ends.
 */
class Profiler {

	/**
	 * Singleton instance.
	 *
	 * @var Profiler|null
	 */
	private static $instance = null;

	/**
	 * Whether profiling is active for this request.
	 *
	 * @var bool
	 */
	private $active = false;

	/**
	 * The instrumentor instance.
	 *
	 * @var Instrumentor|null
	 */
	private $instrumentor = null;

	/**
	 * The call stack tracker.
	 *
	 * @var CallStack|null
	 */
	private $call_stack = null;

	/**
	 * Request start time in nanoseconds.
	 *
	 * @var int
	 */
	private $request_start_ns = 0;

	/**
	 * Route class, refined after WP query is parsed.
	 *
	 * @var string
	 */
	private $route_class = '';

	/**
	 * Get the singleton instance.
	 *
	 * @return Profiler
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use instance().
	 */
	private function __construct() {}

	/**
	 * Initialize the profiler. Called early on `plugins_loaded` priority 0.
	 *
	 * Checks for a valid profiling session and starts instrumentation if found.
	 */
	public function init() {
		if ( ! Session::has_valid_cookie() ) {
			return;
		}

		$this->start();
	}

	/**
	 * Begin profiling this request.
	 */
	public function start() {
		$this->active = true;

		// Always use hrtime for consistent monotonic clock domain.
		// We lose the few ms between SAPI start and plugin load, but
		// mixing REQUEST_TIME_FLOAT (wall clock) with hrtime (monotonic)
		// produces garbage durations.
		$this->request_start_ns = hrtime( true );

		$this->call_stack   = new CallStack();
		$this->instrumentor = new Instrumentor( $this->call_stack );

		// Instrument all currently registered hooks.
		$this->instrumentor->instrument_all();

		// Catch late-registered hooks at key lifecycle points.
		add_action( 'wp_loaded', array( $this, 'reinstrument' ), PHP_INT_MAX );
		add_action( 'admin_init', array( $this, 'reinstrument' ), PHP_INT_MAX );

		// Refine route classification after query parsing.
		add_action( 'wp', array( $this, 'capture_route_class' ), PHP_INT_MAX );

		// Stop and save at shutdown.
		add_action( 'shutdown', array( $this, 'stop' ), PHP_INT_MAX );
	}

	/**
	 * Re-instrument to catch any hooks registered after the initial pass.
	 *
	 * Hooked at `wp_loaded` and `admin_init` with PHP_INT_MAX priority.
	 */
	public function reinstrument() {
		if ( $this->active && null !== $this->instrumentor ) {
			$this->instrumentor->instrument_all();
		}
	}

	/**
	 * Capture the refined route class after WP parses the query.
	 */
	public function capture_route_class() {
		$this->route_class = Report::classify_frontend_route();
	}

	/**
	 * Stop profiling: compile the report and store it.
	 */
	public function stop() {
		if ( ! $this->active ) {
			return;
		}

		$this->active = false;
		$end_ns       = hrtime( true );
		$duration_ns  = $end_ns - $this->request_start_ns;

		// Guard against negative durations from clock issues.
		if ( $duration_ns < 0 ) {
			$duration_ns = 0;
		}

		$session_id = Session::get_session_id();
		if ( empty( $session_id ) ) {
			return;
		}

		$request_url = '';
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_url = home_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		}

		$request_method = 'GET';
		if ( isset( $_SERVER['REQUEST_METHOD'] ) ) {
			$request_method = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) );
		}

		$metadata = array(
			'url'         => $request_url,
			'method'      => $request_method,
			'duration_ns' => $duration_ns,
			'route_class' => $this->route_class,
			'wp_version'  => get_bloginfo( 'version' ),
			'timestamp'   => time(),
		);

		try {
			$raw_timings = $this->instrumentor->get_timings();
			$trace       = $this->call_stack->get_trace();
			$report      = Report::compile( $raw_timings, $trace, $metadata );

			Storage::save_profile( $session_id, $report );
		} catch ( \Throwable $e ) {
			// Fail silently — never break the site.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Scrutinizer profiler error: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Whether profiling is active for this request.
	 *
	 * @return bool
	 */
	public function is_profiling() {
		return $this->active;
	}
}
