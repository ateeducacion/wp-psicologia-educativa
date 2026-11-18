<?php
/**
 * What depends on the site is stored per site (ADR-0008).
 *
 * @package Pse
 */

/**
 * La meta de usuario es de toda la red en un multisitio: solo guarda datos de la persona.
 */
class Test_Site_Data extends WP_UnitTestCase {

	/**
	 * User meta keys that are data of the person, the same on any site.
	 *
	 * Añadir una aquí es decidir que el dato es de la persona y no del sitio.
	 */
	private const PERSONAL_KEYS = array( 'pse_centre_code' );

	/**
	 * Ninguna escritura de meta de usuario fuera de los datos de la persona.
	 */
	public function test_user_meta_only_holds_personal_data() {
		$raiz     = dirname( __DIR__, 2 );
		$ficheros = array_merge(
			(array) glob( $raiz . '/snippets/*.php' ),
			$this->php_under( $raiz . '/src' )
		);
		$malos    = array();
		foreach ( $ficheros as $fichero ) {
			if ( false !== strpos( (string) $fichero, '.bundle.php' ) ) {
				continue;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions -- se lee el código del propio repositorio.
			$codigo = (string) file_get_contents( (string) $fichero );
			if ( ! preg_match_all( '/\b(?:update|add)_user_meta\s*\(\s*[^,]+,\s*([^,]+),/', $codigo, $m ) ) {
				continue;
			}
			foreach ( $m[1] as $clave ) {
				$clave = trim( $clave, " \t\n'\"" );
				if ( ! in_array( $clave, self::PERSONAL_KEYS, true ) ) {
					$malos[] = basename( (string) $fichero ) . ': ' . $clave;
				}
			}
		}

		$this->assertSame( array(), $malos, 'Un dato que depende del sitio va en una opción o en update_user_option() (ADR-0008)' );
	}

	/**
	 * Las zonas y quién las atiende son opciones de este sitio.
	 */
	public function test_zones_are_options_of_the_site() {
		$this->assertTrue( class_exists( '\Pse\Centre\Zones' ) );
		update_option( \Pse\Centre\Zones::OPTION_STAFF, array( 'Norte' => array( 'ana@example.org' ) ), false );
		$this->assertSame( array( 'Norte' ), \Pse\Centre\Zones::of_email( 'ana@example.org' ) );
		delete_option( \Pse\Centre\Zones::OPTION_STAFF );
		$this->assertSame( array(), \Pse\Centre\Zones::of_email( 'ana@example.org' ), 'sin la opción del sitio, nadie atiende esa zona' );
	}

	/**
	 * Every PHP file under a folder.
	 *
	 * @param string $dir Folder.
	 * @return string[]
	 */
	private function php_under( string $dir ): array {
		$out = array();
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $fichero ) {
			if ( 'php' === $fichero->getExtension() ) {
				$out[] = $fichero->getPathname();
			}
		}
		return $out;
	}
}
