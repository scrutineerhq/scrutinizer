<?php
/**
 * Call stack tracker for nested callback execution.
 *
 * @package Scrutinizer
 */

namespace Scrutinizer\Profiler;

/**
 * Tracks nested callback execution for exclusive/inclusive time calculation.
 *
 * Maintains a stack of active callback frames. When a frame pops, its
 * inclusive time is added to the parent frame's children_time_ns so that
 * the parent can later derive its own exclusive time.
 */
class CallStack {

	/**
	 * Stack of active frames.
	 *
	 * Each frame is an associative array:
	 *   - id              (string)  Unique frame identifier.
	 *   - start_ns        (int)     hrtime start.
	 *   - children_time_ns (int)    Sum of direct children's wall time.
	 *
	 * @var array<int, array{id: string, start_ns: int, children_time_ns: int}>
	 */
	private $stack = array();

	/**
	 * Completed trace entries, built as frames are popped.
	 *
	 * @var array<int, array>
	 */
	private $trace = array();

	/**
	 * Push a new frame onto the stack.
	 *
	 * @param string $frame_id  Unique callback identifier.
	 * @param int    $start_ns  Monotonic nanosecond timestamp.
	 */
	public function push( $frame_id, $start_ns ) {
		$this->stack[] = array(
			'id'               => $frame_id,
			'start_ns'         => $start_ns,
			'children_time_ns' => 0,
		);
	}

	/**
	 * Pop a frame off the stack and return its data.
	 *
	 * Adds this frame's inclusive time to the parent frame's children_time_ns.
	 *
	 * @param string $frame_id  Expected frame identifier (for sanity).
	 * @param int    $end_ns    Monotonic nanosecond timestamp.
	 * @return array{id: string, start_ns: int, end_ns: int, inclusive_ns: int, exclusive_ns: int}|null
	 */
	public function pop( $frame_id, $end_ns ) {
		if ( empty( $this->stack ) ) {
			return null;
		}

		$frame        = array_pop( $this->stack );
		$inclusive_ns = $end_ns - $frame['start_ns'];
		$exclusive_ns = $inclusive_ns - $frame['children_time_ns'];

		// Guard against negative exclusive time from clock jitter.
		if ( $exclusive_ns < 0 ) {
			$exclusive_ns = 0;
		}

		// Attribute this frame's wall time to the parent.
		$parent_idx = count( $this->stack ) - 1;
		if ( $parent_idx >= 0 ) {
			$this->stack[ $parent_idx ]['children_time_ns'] += $inclusive_ns;
		}

		$result = array(
			'id'           => $frame['id'],
			'start_ns'     => $frame['start_ns'],
			'end_ns'       => $end_ns,
			'inclusive_ns' => $inclusive_ns,
			'exclusive_ns' => $exclusive_ns,
		);

		$this->trace[] = $result;

		return $result;
	}

	/**
	 * Return current nesting depth.
	 *
	 * @return int
	 */
	public function depth() {
		return count( $this->stack );
	}

	/**
	 * Return the full trace of completed frames.
	 *
	 * @return array<int, array>
	 */
	public function get_trace() {
		return $this->trace;
	}

	/**
	 * Reset the call stack for a new profiling session.
	 */
	public function reset() {
		$this->stack = array();
		$this->trace = array();
	}
}
