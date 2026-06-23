<?php
/**
 * Callback-to-source attribution utility.
 *
 * @package Scrutinizer
 */

namespace Scrutinizer\Profiler;

/**
 * Maps a WordPress callback to its originating plugin, theme, or core file.
 *
 * Results are memoized per callback identity so that reflection is never
 * performed in the hot path after the first resolution.
 */
class Attribution {

	/**
	 * Memoization cache keyed by callback identity string.
	 *
	 * @var array<string, array>
	 */
	private static $cache = array();

	/**
	 * Resolve a WordPress callback to its source attribution.
	 *
	 * @param callable $callback  A WordPress callback (string, array, or Closure).
	 * @return array{type: string, slug: string, name: string, file: string, line: int}
	 */
	public static function resolve( $callback ) {
		$key = self::callback_identity( $callback );

		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}

		$file = '';
		$line = 0;

		try {
			if ( $callback instanceof \Closure ) {
				$ref  = new \ReflectionFunction( $callback );
				$file = (string) $ref->getFileName();
				$line = (int) $ref->getStartLine();
			} elseif ( is_array( $callback ) && count( $callback ) >= 2 ) {
				$ref  = new \ReflectionMethod( $callback[0], $callback[1] );
				$file = (string) $ref->getFileName();
				$line = (int) $ref->getStartLine();
			} elseif ( is_string( $callback ) && function_exists( $callback ) ) {
				$ref  = new \ReflectionFunction( $callback );
				$file = (string) $ref->getFileName();
				$line = (int) $ref->getStartLine();
			}
		} catch ( \ReflectionException $e ) {
			// Silently fall through to unknown.
			$file = '';
		}

		$result = self::classify( $file );

		$result['file'] = $file;
		$result['line'] = $line;

		self::$cache[ $key ] = $result;

		return $result;
	}

	/**
	 * Classify a source file path as plugin, theme, core, mu-plugin, drop-in, or unknown.
	 *
	 * @param string $file  Absolute file path.
	 * @return array{type: string, slug: string, name: string}
	 */
	public static function classify( $file ) {
		$result = array(
			'type' => 'unknown',
			'slug' => '',
			'name' => '',
		);

		if ( empty( $file ) ) {
			return $result;
		}

		$file = wp_normalize_path( $file );

		// Plugin directory.
		$plugin_dir = wp_normalize_path( WP_PLUGIN_DIR );
		if ( 0 === strpos( $file, $plugin_dir . '/' ) ) {
			$relative = substr( $file, strlen( $plugin_dir ) + 1 );
			$parts    = explode( '/', $relative, 2 );
			$slug     = $parts[0];

			$result['type'] = 'plugin';
			$result['slug'] = $slug;
			$result['name'] = self::plugin_name_from_slug( $slug );

			return $result;
		}

		// MU-plugins directory.
		$mu_dir = wp_normalize_path( WPMU_PLUGIN_DIR );
		if ( 0 === strpos( $file, $mu_dir . '/' ) ) {
			$relative = substr( $file, strlen( $mu_dir ) + 1 );
			$parts    = explode( '/', $relative, 2 );

			$result['type'] = 'mu-plugin';
			$result['slug'] = $parts[0];
			$result['name'] = $parts[0];

			return $result;
		}

		// Theme directory.
		$theme_roots = (array) get_theme_root();
		foreach ( $theme_roots as $theme_root ) {
			$theme_root = wp_normalize_path( $theme_root );
			if ( 0 === strpos( $file, $theme_root . '/' ) ) {
				$relative = substr( $file, strlen( $theme_root ) + 1 );
				$parts    = explode( '/', $relative, 2 );
				$slug     = $parts[0];

				$result['type'] = 'theme';
				$result['slug'] = $slug;
				$result['name'] = $slug;

				return $result;
			}
		}

		// wp-content drop-ins (e.g. object-cache.php, advanced-cache.php).
		$content_dir = wp_normalize_path( WP_CONTENT_DIR );
		if ( 0 === strpos( $file, $content_dir . '/' ) ) {
			$relative = substr( $file, strlen( $content_dir ) + 1 );
			if ( false === strpos( $relative, '/' ) ) {
				$result['type'] = 'drop-in';
				$result['slug'] = basename( $relative, '.php' );
				$result['name'] = basename( $relative );

				return $result;
			}
		}

		// WordPress core (ABSPATH).
		$abspath = wp_normalize_path( ABSPATH );
		if ( 0 === strpos( $file, $abspath ) ) {
			$result['type'] = 'core';
			$result['slug'] = 'wordpress'; // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- data slug, not prose.
			$result['name'] = 'WordPress Core';

			return $result;
		}

		return $result;
	}

	/**
	 * Derive a human-readable name from a plugin slug.
	 *
	 * Falls back to the slug itself if the plugin data is unavailable.
	 *
	 * @param string $slug  Plugin directory name.
	 * @return string
	 */
	private static function plugin_name_from_slug( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		foreach ( $plugins as $path => $data ) {
			if ( 0 === strpos( $path, $slug . '/' ) ) {
				return $data['Name'];
			}
		}

		return $slug;
	}

	/**
	 * Build a string identity for a callback suitable as a memoization key.
	 *
	 * @param callable $callback  WordPress callback.
	 * @return string
	 */
	public static function callback_identity( $callback ) {
		if ( $callback instanceof \Closure ) {
			return 'closure_' . spl_object_id( $callback );
		}

		if ( is_array( $callback ) && count( $callback ) >= 2 ) {
			$class = is_object( $callback[0] )
				? get_class( $callback[0] ) . '#' . spl_object_id( $callback[0] )
				: (string) $callback[0];

			return $class . '::' . $callback[1];
		}

		if ( is_string( $callback ) ) {
			return $callback;
		}

		if ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
			return get_class( $callback ) . '#' . spl_object_id( $callback ) . '::__invoke';
		}

		return 'unknown_' . md5( wp_json_encode( $callback ) );
	}

	/**
	 * Build a short human-readable label for a callback.
	 *
	 * @param callable $callback  WordPress callback.
	 * @return string
	 */
	public static function callback_label( $callback ) {
		if ( $callback instanceof \Closure ) {
			try {
				$ref = new \ReflectionFunction( $callback );
				return sprintf( '{closure:%s:%d}', basename( $ref->getFileName() ), $ref->getStartLine() );
			} catch ( \ReflectionException $e ) {
				return '{closure}';
			}
		}

		if ( is_array( $callback ) && count( $callback ) >= 2 ) {
			$class = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
			return $class . '::' . $callback[1];
		}

		if ( is_string( $callback ) ) {
			return $callback;
		}

		if ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
			return get_class( $callback ) . '::__invoke';
		}

		return '{unknown}';
	}

	/**
	 * Check whether a callback belongs to the Scrutinizer plugin.
	 *
	 * @param array $attribution  Result from resolve().
	 * @return bool
	 */
	public static function is_self( $attribution ) {
		return 'plugin' === $attribution['type'] && 'scrutinizer' === $attribution['slug'];
	}

	/**
	 * Clear the memoization cache.
	 */
	public static function clear_cache() {
		self::$cache = array();
	}
}
