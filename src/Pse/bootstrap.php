<?php
/**
 * Load modular application classes (development includes).
 *
 * The load order lives in load-order.php, shared with build/pack-snippet.php.
 * The Code Snippets bundle inlines the same files in that same order.
 *
 * @package Pse
 */

if ( ! defined( 'PSE_SRC_DIR' ) ) {
	define( 'PSE_SRC_DIR', __DIR__ );
}

// When the Code Snippets bundle already defined the classes, skip file loads
// (require_once is path-based; the bundle is a different file and would redeclare).
if ( ! class_exists( \Pse\App::class, false ) ) {
	$pse_app_files = require __DIR__ . '/load-order.php';

	foreach ( $pse_app_files as $pse_app_file ) {
		$pse_app_path = PSE_SRC_DIR . '/' . $pse_app_file;
		if ( is_readable( $pse_app_path ) ) {
			require_once $pse_app_path;
		}
	}
}

if ( class_exists( \Pse\App::class ) ) {
	\Pse\App::boot();
}
