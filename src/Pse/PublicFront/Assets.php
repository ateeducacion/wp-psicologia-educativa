<?php
/**
 * The application stylesheet, inlined in the bundle.
 *
 * @package Pse
 */

namespace Pse\PublicFront;

/**
 * En producción no hay ficheros que servir: el empaquetador mete el CSS
 * dentro del bundle y llama a `set_inline()`. En desarrollo y en los tests se
 * lee de `assets/`.
 */
final class Assets {

	/**
	 * Asset contents inlined by the bundler, keyed by path relative to assets/.
	 *
	 * @var array<string, string>
	 */
	private static $inline = array();

	/**
	 * Receive the asset contents that `build/pack-snippet.php` inlined.
	 *
	 * @param array<string, string> $assets Path => contents.
	 * @return void
	 */
	public static function set_inline( array $assets ): void {
		self::$inline = $assets;
	}

	/**
	 * Contents of an asset.
	 *
	 * @param string $rel Path relative to assets/.
	 * @return string
	 */
	public static function contents( string $rel ): string {
		if ( isset( self::$inline[ $rel ] ) ) {
			return self::$inline[ $rel ];
		}
		$path = defined( 'PSE_SRC_DIR' ) ? dirname( PSE_SRC_DIR, 2 ) . '/assets/' . $rel : '';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero del repositorio, solo en desarrollo.
		return '' !== $path && is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}

	/**
	 * The application stylesheet.
	 *
	 * @return string
	 */
	public static function css(): string {
		return self::contents( 'css/pse-app.css' );
	}
}
