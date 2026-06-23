/**
 * Scrutinizer Dashboard JavaScript
 *
 * Handles start/stop profiling, polling for new profiles, and
 * navigating between profile list and detail views.
 *
 * @package Scrutinizer
 */

/* global jQuery, scrutinizerAdmin */
( function( $ ) {
	'use strict';

	var pollingTimer = null;
	var currentView  = 'list'; // 'list' or 'detail'

	/**
	 * Initialize dashboard behavior.
	 */
	function init() {
		bindEvents();

		if ( scrutinizerAdmin.isActive ) {
			startPolling();
			showStopButton();
		}
	}

	/**
	 * Bind DOM event handlers.
	 */
	function bindEvents() {
		// Decision cards — start profiling.
		$( document ).on( 'click', '.scrutinizer-decision-card', function() {
			var target = $( this ).data( 'target' ) || '';
			startProfiling( target );
		} );

		// Stop button.
		$( document ).on( 'click', '#scrutinizer-stop', function() {
			stopProfiling();
		} );

		// Copy activation URL.
		$( document ).on( 'click', '#scrutinizer-copy-url', function() {
			var input = document.getElementById( 'scrutinizer-activation-url' );
			if ( input ) {
				input.select();
				if ( navigator.clipboard ) {
					navigator.clipboard.writeText( input.value );
				} else {
					document.execCommand( 'copy' );
				}
				showNotice( scrutinizerAdmin.i18n.copied, 'success' );
			}
		} );

		// View profile detail.
		$( document ).on( 'click', '.scrutinizer-view-profile', function( e ) {
			e.preventDefault();
			var id = $( this ).data( 'profile-id' );
			loadProfileDetail( id );
		} );

		// Delete profile.
		$( document ).on( 'click', '.scrutinizer-delete-profile', function( e ) {
			e.preventDefault();
			/* eslint-disable no-alert */
			if ( ! confirm( scrutinizerAdmin.i18n.confirmDelete ) ) {
				return;
			}
			/* eslint-enable no-alert */
			var id = $( this ).data( 'profile-id' );
			deleteProfile( id );
		} );

		// Back to list.
		$( document ).on( 'click', '#scrutinizer-back-to-list', function() {
			showListView();
		} );
	}

	/**
	 * Start a profiling session via AJAX.
	 *
	 * @param {string} target URL target for activation.
	 */
	function startProfiling( target ) {
		$.post( scrutinizerAdmin.ajaxUrl, {
			action: 'scrutinizer_start_profiling',
			nonce:  scrutinizerAdmin.nonce,
			target: target
		}, function( response ) {
			if ( response.success ) {
				var url = response.data.activation_url;

				// Show activation URL.
				$( '#scrutinizer-activation-url' ).val( url );
				$( '#scrutinizer-activation' ).show();

				// Redirect to the activation URL to set the cookie.
				window.location.href = url;
			} else {
				showNotice( response.data.message || scrutinizerAdmin.i18n.error, 'error' );
			}
		} ).fail( function() {
			showNotice( scrutinizerAdmin.i18n.error, 'error' );
		} );
	}

	/**
	 * Stop the active profiling session.
	 */
	function stopProfiling() {
		stopPolling();

		$.post( scrutinizerAdmin.ajaxUrl, {
			action: 'scrutinizer_stop_profiling',
			nonce:  scrutinizerAdmin.nonce
		}, function( response ) {
			if ( response.success ) {
				showNotice( response.data.message, 'success' );

				// Refresh the page to show updated state.
				window.location.reload();
			} else {
				showNotice( response.data.message || scrutinizerAdmin.i18n.error, 'error' );
			}
		} ).fail( function() {
			showNotice( scrutinizerAdmin.i18n.error, 'error' );
		} );
	}

	/**
	 * Show the stop button and polling indicator.
	 */
	function showStopButton() {
		var $controls = $( '#scrutinizer-controls' );
		$controls.html(
			'<div class="scrutinizer-polling">' +
				'<span class="spinner is-active"></span>' +
				scrutinizerAdmin.i18n.profiling +
			'</div>' +
			'<button type="button" class="button button-secondary button-large" id="scrutinizer-stop">' +
				scrutinizerAdmin.i18n.stopProfiling +
			'</button>'
		);

		$( '.scrutinizer-status-card' ).addClass( 'is-active' );
		$( '.scrutinizer-dot' ).addClass( 'active' ).removeClass( 'inactive' );
		$( '#scrutinizer-status-text' ).text( scrutinizerAdmin.i18n.profiling );
	}

	/**
	 * Start polling for new profiles every 2 seconds.
	 */
	function startPolling() {
		if ( pollingTimer ) {
			return;
		}
		fetchProfiles();
		pollingTimer = setInterval( fetchProfiles, 2000 );
	}

	/**
	 * Stop the polling timer.
	 */
	function stopPolling() {
		if ( pollingTimer ) {
			clearInterval( pollingTimer );
			pollingTimer = null;
		}
	}

	/**
	 * Fetch profiles for the active session.
	 */
	function fetchProfiles() {
		$.get( scrutinizerAdmin.ajaxUrl, {
			action:     'scrutinizer_get_profiles',
			nonce:      scrutinizerAdmin.nonce,
			session_id: scrutinizerAdmin.sessionId
		}, function( response ) {
			if ( response.success ) {
				renderProfileList( response.data.profiles );
			}
		} );
	}

	/**
	 * Render the profile list table.
	 *
	 * @param {Array} profiles List of profile summaries.
	 */
	function renderProfileList( profiles ) {
		var $list = $( '#scrutinizer-profile-list' );

		if ( ! profiles || 0 === profiles.length ) {
			$list.html( '<p class="scrutinizer-empty">' + scrutinizerAdmin.i18n.noProfiles + '</p>' );
			return;
		}

		var html = '<table class="scrutinizer-profile-table widefat">';
		html += '<thead><tr>';
		html += '<th>' + esc( scrutinizerAdmin.i18n.serverDuration ) + '</th>';
		html += '<th>URL</th>';
		html += '<th>Method</th>';
		html += '<th>Route</th>';
		html += '<th>Captured</th>';
		html += '<th>Actions</th>';
		html += '</tr></thead><tbody>';

		for ( var i = 0; i < profiles.length; i++ ) {
			var p       = profiles[ i ];
			var durMs   = ( parseInt( p.duration_ns, 10 ) / 1e6 ).toFixed( 1 );
			var urlPath = p.request_url || '—';

			// Truncate long URLs for display.
			if ( urlPath.length > 60 ) {
				urlPath = urlPath.substring( 0, 57 ) + '…';
			}

			html += '<tr>';
			html += '<td class="scrutinizer-duration">' + esc( durMs ) + ' ms</td>';
			html += '<td title="' + esc( p.request_url ) + '">' + esc( urlPath ) + '</td>';
			html += '<td>' + esc( p.request_method ) + '</td>';
			html += '<td>' + esc( p.route_class || '—' ) + '</td>';
			html += '<td>' + esc( p.captured_at ) + '</td>';
			html += '<td class="scrutinizer-actions">';
			html += '<a href="#" class="scrutinizer-view-profile" data-profile-id="' + parseInt( p.id, 10 ) + '">View</a>';
			html += ' | ';
			html += '<a href="#" class="scrutinizer-delete-profile" data-profile-id="' + parseInt( p.id, 10 ) + '">Delete</a>';
			html += '</td>';
			html += '</tr>';
		}

		html += '</tbody></table>';
		$list.html( html );
	}

	/**
	 * Load and display a single profile's detail.
	 *
	 * @param {number} profileId Profile row ID.
	 */
	function loadProfileDetail( profileId ) {
		$.get( scrutinizerAdmin.ajaxUrl, {
			action:     'scrutinizer_get_profile_detail',
			nonce:      scrutinizerAdmin.nonce,
			profile_id: profileId
		}, function( response ) {
			if ( response.success ) {
				renderProfileDetail( response.data.profile );
				showDetailView();
			} else {
				showNotice( response.data.message || scrutinizerAdmin.i18n.error, 'error' );
			}
		} ).fail( function() {
			showNotice( scrutinizerAdmin.i18n.error, 'error' );
		} );
	}

	/**
	 * Render profile detail HTML.
	 *
	 * @param {Object} profile Full profile data.
	 */
	function renderProfileDetail( profile ) {
		var data   = profile.profile_data || {};
		var summary = data.summary || {};
		var sources = data.sources || [];
		var request = data.request || {};

		var durMs = ( summary.duration_ms || 0 ).toFixed( 1 );

		var html = '<div class="scrutinizer-detail-summary">';
		html += '<h3>' + esc( request.method ) + ' ' + esc( request.url ) + '</h3>';
		html += '<div class="scrutinizer-duration-display">' + esc( durMs ) + ' <small>ms</small></div>';
		html += '<p>' + scrutinizerAdmin.i18n.serverDuration + '</p>';

		// Breakdown bar.
		var breakdown = summary.breakdown || {};
		html += '<div class="scrutinizer-breakdown">';
		html += '<div class="scrutinizer-breakdown-bar">';

		var barTypes = [ 'plugin', 'theme', 'core', 'mu-plugin', 'unattributed' ];
		for ( var b = 0; b < barTypes.length; b++ ) {
			var bt = barTypes[ b ];
			if ( breakdown[ bt ] && breakdown[ bt ].percent > 0 ) {
				html += '<div class="segment ' + esc( bt ) + '" style="width:' + breakdown[ bt ].percent + '%" title="' + esc( bt ) + ': ' + breakdown[ bt ].percent + '%"></div>';
			}
		}

		html += '</div>'; // breakdown-bar

		// Legend.
		html += '<div class="scrutinizer-breakdown-legend">';
		var legendColors = {
			plugin:       '#2271b1',
			theme:        '#9b59b6',
			core:         '#50575e',
			'mu-plugin':  '#e67e22',
			unattributed: '#dcdcde'
		};
		for ( var lt in breakdown ) {
			if ( breakdown.hasOwnProperty( lt ) && breakdown[ lt ].ms > 0 ) {
				var color = legendColors[ lt ] || '#888';
				html += '<span class="legend-item">';
				html += '<span class="legend-swatch" style="background:' + color + '"></span>';
				html += esc( lt ) + ': ' + breakdown[ lt ].ms + ' ms (' + breakdown[ lt ].percent + '%)';
				html += '</span>';
			}
		}
		html += '</div>'; // legend
		html += '</div>'; // breakdown
		html += '</div>'; // detail-summary

		// Per-source table.
		if ( sources.length > 0 ) {
			html += '<h3>' + scrutinizerAdmin.i18n.exclusiveTime + '</h3>';
			html += '<table class="scrutinizer-source-table widefat">';
			html += '<thead><tr>';
			html += '<th>Source</th>';
			html += '<th>Type</th>';
			html += '<th class="numeric">' + scrutinizerAdmin.i18n.exclusiveTime + '</th>';
			html += '<th class="numeric">' + scrutinizerAdmin.i18n.inclusiveTime + '</th>';
			html += '<th class="numeric">' + scrutinizerAdmin.i18n.callCount + '</th>';
			html += '</tr></thead><tbody>';

			for ( var s = 0; s < sources.length; s++ ) {
				var src = sources[ s ];
				html += '<tr>';
				html += '<td>' + esc( src.name || src.slug ) + '</td>';
				html += '<td>' + esc( src.type ) + '</td>';
				html += '<td class="numeric">' + ( src.exclusive_ns / 1e6 ).toFixed( 2 ) + ' ms</td>';
				html += '<td class="numeric">' + ( src.inclusive_ns / 1e6 ).toFixed( 2 ) + ' ms</td>';
				html += '<td class="numeric">' + src.call_count + '</td>';
				html += '</tr>';
			}

			html += '</tbody></table>';
		}

		// Request metadata.
		html += '<h3>Request Metadata</h3>';
		html += '<table class="scrutinizer-source-table widefat">';
		html += '<tbody>';
		html += '<tr><td>Route</td><td>' + esc( request.route_class || '—' ) + '</td></tr>';
		html += '<tr><td>PHP</td><td>' + esc( request.php_version || '—' ) + '</td></tr>';
		html += '<tr><td>WordPress</td><td>' + esc( request.wp_version || '—' ) + '</td></tr>';
		html += '<tr><td>Peak Memory</td><td>' + formatBytes( request.memory_peak || 0 ) + '</td></tr>';
		html += '<tr><td>Callbacks Observed</td><td>' + ( summary.callback_count || 0 ) + '</td></tr>';
		html += '<tr><td>Sources Identified</td><td>' + ( summary.source_count || 0 ) + '</td></tr>';
		html += '</tbody></table>';

		$( '#scrutinizer-detail-content' ).html( html );
	}

	/**
	 * Delete a profile via AJAX.
	 *
	 * @param {number} profileId Profile row ID.
	 */
	function deleteProfile( profileId ) {
		$.post( scrutinizerAdmin.ajaxUrl, {
			action:     'scrutinizer_delete_profile',
			nonce:      scrutinizerAdmin.nonce,
			profile_id: profileId
		}, function( response ) {
			if ( response.success ) {
				fetchProfiles();
			} else {
				showNotice( response.data.message || scrutinizerAdmin.i18n.error, 'error' );
			}
		} );
	}

	/**
	 * Show the list view, hide detail.
	 */
	function showListView() {
		currentView = 'list';
		$( '#scrutinizer-results' ).show();
		$( '#scrutinizer-detail' ).hide();
	}

	/**
	 * Show the detail view, hide list.
	 */
	function showDetailView() {
		currentView = 'detail';
		$( '#scrutinizer-results' ).hide();
		$( '#scrutinizer-detail' ).show();
	}

	/**
	 * Show a temporary notice.
	 *
	 * @param {string} message Notice text.
	 * @param {string} type    'success' or 'error'.
	 */
	function showNotice( message, type ) {
		var $notice = $( '<div class="scrutinizer-notice ' + type + '">' + esc( message ) + '</div>' );
		$( '#scrutinizer-dashboard h1' ).after( $notice );

		setTimeout( function() {
			$notice.fadeOut( 300, function() {
				$notice.remove();
			} );
		}, 4000 );
	}

	/**
	 * Basic HTML escaping.
	 *
	 * @param {*} str Value to escape.
	 * @return {string}
	 */
	function esc( str ) {
		if ( null === str || undefined === str ) {
			return '';
		}
		var div       = document.createElement( 'div' );
		div.appendChild( document.createTextNode( String( str ) ) );
		return div.innerHTML;
	}

	/**
	 * Format bytes into a human-readable string.
	 *
	 * @param {number} bytes Byte count.
	 * @return {string}
	 */
	function formatBytes( bytes ) {
		if ( 0 === bytes ) {
			return '0 B';
		}
		var units = [ 'B', 'KB', 'MB', 'GB' ];
		var i     = Math.floor( Math.log( bytes ) / Math.log( 1024 ) );
		return ( bytes / Math.pow( 1024, i ) ).toFixed( 1 ) + ' ' + units[ i ];
	}

	// Initialize on DOM ready.
	$( init );
}( jQuery ) );
