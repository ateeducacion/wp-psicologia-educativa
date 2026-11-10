<?php
/**
 * Tests for the load order and the wiring it feeds.
 *
 * @package Pse
 */

use Pse\Access\Access;
use Pse\PostType\Requests;

/**
 * La lista de carga: única fuente de verdad de qué entra en el bundle.
 *
 * Los tests cargan los módulos por `require` directo, así que un fichero nuevo
 * sin listar pasaría en verde y desaparecería del artefacto desplegado. Aquí
 * falla antes, y con el nombre del que falta.
 */
class Test_Load_Order extends WP_UnitTestCase {

	/**
	 * Root of the modular sources.
	 *
	 * @return string
	 */
	private function raiz(): string {
		return dirname( __DIR__, 2 ) . '/src/Pse/';
	}

	/**
	 * The load order, re-evaluated so it is measured like any other code.
	 *
	 * @return string[]
	 */
	private function orden(): array {
		return (array) require $this->raiz() . 'load-order.php';
	}

	/**
	 * Source of one of the listed files.
	 *
	 * @param string $rel Path relative to src/Pse/.
	 * @return string
	 */
	private function codigo( string $rel ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions -- se lee el código fuente del propio repositorio, no del sistema de ficheros de un sitio.
		return (string) file_get_contents( $this->raiz() . $rel );
	}

	/**
	 * Name of the first PHP statement of a file, ignoring comments.
	 *
	 * @param string $rel Path relative to src/Pse/.
	 * @return string Token name, e.g. `T_NAMESPACE`.
	 */
	private function primer_token( string $rel ): string {
		foreach ( token_get_all( $this->codigo( $rel ) ) as $token ) {
			if ( ! is_array( $token ) ) {
				return (string) $token;
			}
			if ( in_array( $token[0], array( T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			return token_name( $token[0] );
		}
		return '';
	}

	/**
	 * Cada fichero de la lista existe, y ninguno está dos veces.
	 */
	public function test_every_listed_file_exists_once() {
		$orden = $this->orden();

		$this->assertNotEmpty( $orden );
		$this->assertSame( $orden, array_values( array_unique( $orden ) ), 'ningún fichero repetido' );
		foreach ( $orden as $rel ) {
			$this->assertFileExists( $this->raiz() . $rel );
		}
	}

	/**
	 * Y al revés: todo fichero de `src/Pse/` está en la lista, que es lo que el
	 * empaquetado exige para no dejarse nada fuera del snippet.
	 */
	public function test_every_source_file_is_listed() {
		$raiz  = $this->raiz();
		$orden = $this->orden();

		$encontrados = array();
		$it          = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $raiz ) );
		foreach ( $it as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			$rel = str_replace( $raiz, '', $file->getPathname() );
			if ( in_array( $rel, array( 'bootstrap.php', 'load-order.php' ), true ) ) {
				continue;
			}
			$encontrados[] = $rel;
		}

		$this->assertEqualsCanonicalizing( $encontrados, $orden, 'un fichero nuevo de src/Pse/ tiene que entrar en load-order.php' );
	}

	/**
	 * El primero de la lista abre con su `namespace`: el empaquetador inyecta
	 * justo detrás la constante de guarda del bundle.
	 */
	public function test_the_first_file_opens_with_its_namespace() {
		$orden = $this->orden();
		$this->assertSame( 'T_NAMESPACE', $this->primer_token( $orden[0] ), $orden[0] . ' no abre con namespace' );
	}

	/**
	 * `App.php` va el último: arranca lo que los demás ficheros acaban de definir.
	 */
	public function test_the_application_loads_last() {
		$orden = $this->orden();
		$this->assertSame( 'App.php', end( $orden ) );
	}

	/**
	 * Ni un `declare(strict_types=1)`: es legal por fichero y fatal en cuanto
	 * el empaquetado los concatena en un solo snippet.
	 */
	public function test_no_file_declares_strict_types() {
		foreach ( $this->orden() as $rel ) {
			$this->assertDoesNotMatchRegularExpression( '/declare\s*\(\s*strict_types/', $this->codigo( $rel ), $rel );
		}
	}

	/**
	 * El arranque dejó enganchado lo que sostiene el aplicativo: si alguien
	 * quita un `register()` de `App::boot()`, esto lo dice.
	 */
	public function test_booting_wired_the_application() {
		$this->assertNotFalse( has_action( 'init', array( Requests::class, 'register' ) ) );
		$this->assertNotFalse( has_action( 'init', array( Access::class, 'grant_caps_to_roles' ) ) );
		$this->assertTrue( post_type_exists( Requests::TYPE ) );
	}
}
