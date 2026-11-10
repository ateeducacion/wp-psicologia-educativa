<?php
/**
 * Tests for App::boot(): what it hooks and that it only hooks it once.
 *
 * @package Pse
 */

use Pse\Access\Access;
use Pse\App;
use Pse\Notifications\Mailer;
use Pse\PostType\Requests;
use Pse\PublicFront\Screen;

/**
 * El arranque del aplicativo.
 *
 * `App::boot()` se llama desde `src/Pse/bootstrap.php` y desde el bundle: si no
 * fuese idempotente, tener los dos activos a la vez engancharía cada módulo dos
 * veces y cada `init` haría el trabajo por duplicado.
 */
class Test_Boot extends WP_UnitTestCase {

	/**
	 * How many callbacks hang off each hook the boot touches, across every priority.
	 *
	 * @return array<string, int>
	 */
	private function enganchadas(): array {
		global $wp_filter;

		$cuenta = array();
		foreach ( array( 'init' ) as $gancho ) {
			$total = 0;
			if ( isset( $wp_filter[ $gancho ] ) ) {
				foreach ( $wp_filter[ $gancho ]->callbacks as $prioridad ) {
					$total += count( $prioridad );
				}
			}
			$cuenta[ $gancho ] = $total;
		}
		return $cuenta;
	}

	/**
	 * Un segundo arranque no vuelve a enganchar nada.
	 */
	public function test_boot_is_idempotent() {
		$antes = $this->enganchadas();

		App::boot();
		App::boot();

		$this->assertSame( $antes, $this->enganchadas() );
	}

	/**
	 * Cada módulo cuelga de su gancho y en su prioridad: las capacidades
	 * después del tipo de contenido.
	 */
	public function test_boot_wired_every_module() {
		$this->assertSame( 10, has_action( 'init', array( Requests::class, 'register' ) ) );
		$this->assertSame( 11, has_action( 'init', array( Access::class, 'grant_caps_to_roles' ) ) );
		$this->assertSame( 10, has_action( 'pse_request_created', array( Mailer::class, 'notify' ) ) );
		$this->assertSame( 1, has_action( 'template_redirect', array( Screen::class, 'route' ) ) );
	}

	/**
	 * Y al terminar `init`, el tipo de contenido y sus campos están montados.
	 */
	public function test_boot_registered_the_post_type_and_its_meta() {
		// WordPress borra las metas registradas entre un test y otro.
		Requests::register();
		$this->assertTrue( post_type_exists( Requests::TYPE ) );
		$this->assertFalse( get_post_type_object( Requests::TYPE )->public );
		foreach ( Requests::keys() as $key ) {
			$this->assertTrue( registered_meta_key_exists( 'post', $key, Requests::TYPE ), $key );
		}
	}
}
