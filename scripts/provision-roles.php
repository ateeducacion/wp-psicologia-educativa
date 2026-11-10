<?php
/**
 * Idempotent role registration for local provision (wp eval-file).
 *
 * Relies on the roles snippet functions after Code Snippets has activated
 * them, or loads the versioned file directly if functions are missing.
 *
 * @package Pse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pse_snippet = dirname( __DIR__ ) . '/snippets/roles-and-profiles.php';
if ( ! function_exists( 'pse_register_roles' ) && is_readable( $pse_snippet ) ) {
	require_once $pse_snippet;
}

if ( ! function_exists( 'pse_register_roles' ) ) {
	throw new RuntimeException( 'No está disponible pse_register_roles().' );
}

pse_register_roles();

// Las capacidades propias las reparte el aplicativo en
// `init` prioridad 11. En la primera provisión los roles todavía no existían
// cuando pasó ese `init`, así que se vuelve a llamar aquí: es idempotente y
// aditiva, y así los roles salen completos en la misma ejecución.
if ( class_exists( '\\Pse\\Access\\Access' ) ) {
	\Pse\Access\Access::grant_caps_to_roles();
} else {
	echo "AVISO: el bundle del aplicativo no está cargado; los roles quedan sin las capacidades del aplicativo.\n";
}

$pse_slugs = function_exists( 'pse_role_slugs' ) ? pse_role_slugs() : array();
echo esc_html( 'Roles PSE asegurados: ' . implode( ', ', $pse_slugs ) ) . "\n";
foreach ( $pse_slugs as $pse_slug ) {
	$pse_role = get_role( $pse_slug );
	if ( ! $pse_role ) {
		throw new RuntimeException( esc_html( 'No se pudo crear el rol ' . $pse_slug . '.' ) );
	}
	// translators: 1: role slug, 2: capability count.
	echo esc_html( sprintf( '- %s: OK (%d caps)', $pse_slug, count( $pse_role->capabilities ) ) ) . "\n";
}
