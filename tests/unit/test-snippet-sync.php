<?php
/**
 * Tests for the content-aware snippet synchronizer.
 *
 * @package Pse
 */

/**
 * `pse_sync_snippets_from_dir()` no puede volver a guardar un snippet
 * idéntico: guardarlo reescribe la fila, actualiza `modified`, revalida el
 * código —lo que puede llegar a ejecutarlo— y limpia cachés por un cambio que
 * no existe (ADR-0002). Estos tests cubren las cuatro salidas —created,
 * updated, unchanged, error—, que un `updated` conserva los campos que el
 * repositorio no gestiona, y que el fingerprint SHA-256 del estado
 * gestionado, no la versión PSE, es lo único que decide cuál toca.
 */
class Test_Snippet_Sync extends WP_UnitTestCase {

	/**
	 * Directorio temporal con los ficheros de la sincronización de cada test.
	 *
	 * @var string
	 */
	private string $dir = '';

	/**
	 * IDs de snippet que hay que borrar al terminar el test.
	 *
	 * @var int[]
	 */
	private array $ids = array();

	/**
	 * Si ya se ha activado Code Snippets para este proceso de tests.
	 *
	 * El wp-env de tests instala el plugin pero no lo activa —nada, hasta
	 * este test, llamaba a su API en PHPUnit—, así que hace falta activarlo
	 * aquí para que exista la tabla `wp_snippets` y sus funciones. Se activa
	 * una sola vez por proceso: `activate_plugin()` es idempotente y el resto
	 * de la suite no toca Code Snippets, así que no hay nada que aislar.
	 *
	 * @var bool
	 */
	private static bool $code_snippets_activated = false;

	/**
	 * Con Code Snippets activo y un directorio vacío para los ficheros del test.
	 */
	public function set_up() {
		parent::set_up();

		require_once dirname( __DIR__, 2 ) . '/scripts/lib/snippet-sync.php';

		if ( ! self::$code_snippets_activated ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			// wp-env nombra el directorio del plugin a partir del zip
			// («code-snippets.latest-stable»), no del slug, así que se busca
			// en vez de suponer la ruta.
			$plugin_files = (array) glob( WP_PLUGIN_DIR . '/code-snippets*/code-snippets.php' );
			$this->assertNotEmpty( $plugin_files, 'no se encuentra el plugin Code Snippets en wp-content/plugins' );
			activate_plugin( plugin_basename( $plugin_files[0] ) );
			self::$code_snippets_activated = true;
		}

		$this->dir = sys_get_temp_dir() . '/pse-snippet-sync-' . uniqid();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- directorio temporal del test, fuera de WP_Filesystem.
		mkdir( $this->dir );
	}

	/**
	 * Borra los ficheros temporales y los snippets que haya creado el test.
	 */
	public function tear_down() {
		foreach ( (array) glob( $this->dir . '/*.php' ) as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- fichero temporal del test, fuera de WP_Filesystem.
			unlink( $file );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- directorio temporal del test, fuera de WP_Filesystem.
		rmdir( $this->dir );

		foreach ( $this->ids as $id ) {
			// Code Snippets se niega a borrar un snippet bloqueado, así que se
			// quita el candado antes: si no, los tests de `locked` dejarían
			// filas vivas para los siguientes.
			\Code_Snippets\set_snippet_locked( $id, false );
			\Code_Snippets\delete_snippet( $id );
		}
		$this->ids = array();

		parent::tear_down();
	}

	/**
	 * Escribe un fichero de snippet en el directorio temporal del test.
	 *
	 * @param string $basename Nombre del fichero.
	 * @param string $name     Cabecera «Snippet Name:».
	 * @param string $body     Cuerpo del snippet, ya en PHP.
	 * @param string $desc     Cabecera «Description:».
	 * @param string $scope    Cabecera «Scope:».
	 * @param int    $priority Cabecera «Priority:».
	 * @return void
	 */
	private function write( string $basename, string $name, string $body = '// no-op', string $desc = 'Descripción de prueba.', string $scope = 'global', int $priority = 10 ): void {
		$code = "<?php\n/**\n * Snippet Name: {$name}\n * Description: {$desc}\n * Scope: {$scope}\n * Priority: {$priority}\n */\n\n{$body}\n";
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fichero temporal del test, fuera de WP_Filesystem.
		file_put_contents( $this->dir . '/' . $basename, $code );
	}

	/**
	 * Sincroniza el directorio temporal y recuerda los IDs para limpiarlos.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function sync(): array {
		$results = pse_sync_snippets_from_dir( $this->dir );
		foreach ( $results as $result ) {
			if ( ! empty( $result['id'] ) ) {
				$this->ids[] = (int) $result['id'];
			}
		}
		$this->ids = array_unique( $this->ids );
		return $results;
	}

	/**
	 * 1. Un snippet que no existe se crea y se activa.
	 */
	public function test_a_new_snippet_is_created_and_activated() {
		$this->write( 'a.php', 'PSE TEST — uno' );

		$results = $this->sync();

		$this->assertSame( 'created', $results['a.php']['status'] );
		$this->assertTrue( $results['a.php']['active'] );
		$this->assertGreaterThan( 0, $results['a.php']['id'] );
	}

	/**
	 * 2. Código distinto → updated.
	 */
	public function test_different_code_is_updated() {
		$this->write( 'a.php', 'PSE TEST — código', '// version uno' );
		$this->sync();

		$this->write( 'a.php', 'PSE TEST — código', '// version dos' );
		$results = $this->sync();

		$this->assertSame( 'updated', $results['a.php']['status'] );
	}

	/**
	 * 3. Descripción distinta → updated.
	 */
	public function test_different_description_is_updated() {
		$this->write( 'a.php', 'PSE TEST — desc', '// no-op', 'Primera descripción.' );
		$this->sync();

		$this->write( 'a.php', 'PSE TEST — desc', '// no-op', 'Segunda descripción.' );
		$results = $this->sync();

		$this->assertSame( 'updated', $results['a.php']['status'] );
	}

	/**
	 * 4. Ámbito distinto → updated.
	 */
	public function test_different_scope_is_updated() {
		$this->write( 'a.php', 'PSE TEST — scope', '// no-op', 'Descripción de prueba.', 'global' );
		$this->sync();

		$this->write( 'a.php', 'PSE TEST — scope', '// no-op', 'Descripción de prueba.', 'admin' );
		$results = $this->sync();

		$this->assertSame( 'updated', $results['a.php']['status'] );
	}

	/**
	 * 5. Prioridad distinta → updated.
	 */
	public function test_different_priority_is_updated() {
		$this->write( 'a.php', 'PSE TEST — priority', '// no-op', 'Descripción de prueba.', 'global', 10 );
		$this->sync();

		$this->write( 'a.php', 'PSE TEST — priority', '// no-op', 'Descripción de prueba.', 'global', 20 );
		$results = $this->sync();

		$this->assertSame( 'updated', $results['a.php']['status'] );
	}

	/**
	 * 6. Etiquetas distintas → updated.
	 *
	 * La cabecera no declara etiquetas: las pone la sincronización
	 * (`array('pse')`). Se simula un snippet que ya tenía otras —tocado a
	 * mano desde el escritorio de Code Snippets, por ejemplo— y se comprueba
	 * que la sincronización lo detecta como cambio y las deja como debe.
	 */
	public function test_different_tags_is_updated() {
		$this->write( 'a.php', 'PSE TEST — tags' );
		$created = $this->sync();
		$id      = (int) $created['a.php']['id'];

		$snippet       = \Code_Snippets\get_snippet( $id );
		$snippet->tags = array( 'otra-etiqueta' );
		\Code_Snippets\save_snippet( $snippet );

		$results = $this->sync();

		$this->assertSame( 'updated', $results['a.php']['status'] );
		$this->assertSame( array( 'pse' ), \Code_Snippets\get_snippet( $id )->tags );
	}

	/**
	 * 7. Snippet idéntico y activo → unchanged, sin reactivar.
	 */
	public function test_unchanged_and_active_is_left_alone() {
		$this->write( 'a.php', 'PSE TEST — unchanged' );
		$this->sync();

		$results = $this->sync();

		$this->assertSame( 'unchanged', $results['a.php']['status'] );
		$this->assertTrue( $results['a.php']['active'] );
		$this->assertFalse( $results['a.php']['reactivated'] );
	}

	/**
	 * 8. Snippet idéntico e inactivo → unchanged, reactivado.
	 */
	public function test_unchanged_and_inactive_is_reactivated_only() {
		$this->write( 'a.php', 'PSE TEST — inactive' );
		$created = $this->sync();
		\Code_Snippets\deactivate_snippet( (int) $created['a.php']['id'] );

		$results = $this->sync();

		$this->assertSame( 'unchanged', $results['a.php']['status'] );
		$this->assertTrue( $results['a.php']['active'] );
		$this->assertTrue( $results['a.php']['reactivated'] );
	}

	/**
	 * 9. Un `unchanged` no llama a `save_snippet()`.
	 *
	 * `save_snippet()` dispara siempre `code_snippets/update_snippet` al
	 * actualizar una fila existente; si no se llama, el gancho no se dispara.
	 */
	public function test_unchanged_does_not_call_save_snippet() {
		$this->write( 'a.php', 'PSE TEST — no-save' );
		$this->sync();

		$updates = 0;
		add_action(
			'code_snippets/update_snippet',
			function () use ( &$updates ) {
				++$updates;
			}
		);

		$results = $this->sync();

		$this->assertSame( 'unchanged', $results['a.php']['status'] );
		$this->assertSame( 0, $updates );
	}

	/**
	 * 10. Un `unchanged` activo no llama a `activate_snippet()`.
	 */
	public function test_unchanged_active_does_not_call_activate_snippet() {
		$this->write( 'a.php', 'PSE TEST — no-activate' );
		$this->sync();

		$activations = 0;
		add_action(
			'code_snippets/activate_snippet',
			function () use ( &$activations ) {
				++$activations;
			}
		);

		$results = $this->sync();

		$this->assertSame( 'unchanged', $results['a.php']['status'] );
		$this->assertFalse( $results['a.php']['reactivated'] );
		$this->assertSame( 0, $activations );
	}

	/**
	 * 11. Dos ejecuciones seguidas sin cambios dejan todo `unchanged`.
	 */
	public function test_two_runs_with_no_changes_stay_unchanged() {
		$this->write( 'a.php', 'PSE TEST — repetible uno' );
		$this->write( 'b.php', 'PSE TEST — repetible dos' );
		$this->sync();

		foreach ( array( $this->sync(), $this->sync() ) as $results ) {
			$this->assertSame( 'unchanged', $results['a.php']['status'] );
			$this->assertSame( 'unchanged', $results['b.php']['status'] );
		}
	}

	/**
	 * 12. El fingerprint es estable para entradas idénticas.
	 */
	public function test_fingerprint_is_stable_for_identical_input() {
		$state = pse_snippet_managed_state( "<?php\necho 1;\n", 'Descripción.', 'global', 10, array( 'pse' ) );

		$this->assertSame( pse_snippet_fingerprint( $state ), pse_snippet_fingerprint( $state ) );
	}

	/**
	 * 13. El fingerprint cambia si cambia cualquier campo gestionado.
	 */
	public function test_fingerprint_changes_with_any_managed_field() {
		$base = pse_snippet_fingerprint(
			pse_snippet_managed_state( "<?php\necho 1;\n", 'Descripción.', 'global', 10, array( 'pse' ) )
		);

		$variantes = array(
			pse_snippet_managed_state( "<?php\necho 2;\n", 'Descripción.', 'global', 10, array( 'pse' ) ),
			pse_snippet_managed_state( "<?php\necho 1;\n", 'Otra.', 'global', 10, array( 'pse' ) ),
			pse_snippet_managed_state( "<?php\necho 1;\n", 'Descripción.', 'admin', 10, array( 'pse' ) ),
			pse_snippet_managed_state( "<?php\necho 1;\n", 'Descripción.', 'global', 20, array( 'pse' ) ),
			pse_snippet_managed_state( "<?php\necho 1;\n", 'Descripción.', 'global', 10, array( 'otra' ) ),
		);

		foreach ( $variantes as $variante ) {
			$this->assertNotSame( $base, pse_snippet_fingerprint( $variante ) );
		}
	}

	/**
	 * 14. El orden de las etiquetas no importa.
	 */
	public function test_tag_order_does_not_affect_the_fingerprint() {
		$uno = pse_snippet_fingerprint(
			pse_snippet_managed_state( "<?php\necho 1;\n", 'Descripción.', 'global', 10, array( 'b', 'a' ) )
		);
		$dos = pse_snippet_fingerprint(
			pse_snippet_managed_state( "<?php\necho 1;\n", 'Descripción.', 'global', 10, array( 'a', 'b' ) )
		);

		$this->assertSame( $uno, $dos );
	}

	/**
	 * 15. Actualizar contenido gestionado conserva los campos no gestionados.
	 *
	 * `pse_sync_snippets_from_dir()` solo gestiona code/desc/scope/priority/
	 * tags. Un campo ajeno —aquí `condition_id`— fijado a mano tiene que
	 * sobrevivir a un `updated` en vez de volver al valor por defecto de
	 * `Snippet`, que es lo que pasaba al construir siempre un objeto nuevo.
	 */
	public function test_updating_managed_content_preserves_unmanaged_fields() {
		$this->write( 'a.php', 'PSE TEST — unmanaged', '// version uno' );
		$created = $this->sync();
		$id      = (int) $created['a.php']['id'];

		$snippet               = \Code_Snippets\get_snippet( $id );
		$snippet->condition_id = 42;
		\Code_Snippets\save_snippet( $snippet );

		$this->write( 'a.php', 'PSE TEST — unmanaged', '// version dos' );
		$results = $this->sync();

		$this->assertSame( 'updated', $results['a.php']['status'] );
		$this->assertSame( 42, \Code_Snippets\get_snippet( $id )->condition_id );
	}

	/**
	 * 16. `<?php`, cierre PHP y salto final no producen falsos `updated`.
	 *
	 * Es lo que quita `pse_strip_php_tags()`, y el fingerprint se calcula
	 * sobre el código ya normalizado.
	 */
	public function test_tag_and_trailing_newline_differences_do_not_change_the_fingerprint() {
		$sin_normalizar = pse_snippet_fingerprint(
			pse_snippet_managed_state( "<?php\necho 1;\n?>\n\n\n", 'Descripción.', 'global', 10, array( 'pse' ) )
		);
		$normalizado    = pse_snippet_fingerprint(
			pse_snippet_managed_state( 'echo 1;', 'Descripción.', 'global', 10, array( 'pse' ) )
		);

		$this->assertSame( $normalizado, $sin_normalizar );
	}

	/**
	 * 17. Un `updated` no modifica in situ la instancia cacheada.
	 *
	 * `get_snippets()` deja sus objetos en la caché `all_snippets_<tabla>` y
	 * `get_snippet()` devuelve esa misma instancia sin releer la tabla, así
	 * que modificar el objeto existente le cambiaría a `save_snippet()` el
	 * «estado anterior» que él mismo relee y pasa al gancho
	 * `code_snippets/update_snippet` como tercer argumento. Se comprueba
	 * contra el plugin real: lo que Code Snippets considera el estado
	 * anterior tiene que seguir siendo el código viejo.
	 */
	public function test_an_update_does_not_mutate_the_cached_existing_snippet_in_place() {
		$this->write( 'a.php', 'PSE TEST — caché', '// version uno' );
		$this->sync();

		$existing_code = null;
		$saved_code    = null;
		add_action(
			'code_snippets/update_snippet',
			function ( $updated, $table, $existing, $snippet ) use ( &$existing_code, &$saved_code ) {
				$existing_code = (string) $existing->code;
				$saved_code    = (string) $snippet->code;
			},
			10,
			4
		);

		$this->write( 'a.php', 'PSE TEST — caché', '// version dos' );
		$results = $this->sync();

		$this->assertSame( 'updated', $results['a.php']['status'] );
		$this->assertNotNull( $existing_code, 'el gancho code_snippets/update_snippet no llegó a dispararse' );
		$this->assertStringContainsString( '// version uno', $existing_code );
		$this->assertStringNotContainsString( '// version dos', $existing_code );
		$this->assertStringContainsString( '// version dos', $saved_code );
	}

	/**
	 * 18. Un `updated` que sigue activo no se vuelve a activar.
	 *
	 * `activate_snippet()` activa con un `UPDATE ... SET active = 1` y da por
	 * fallida la llamada que no cambia ninguna fila, así que activar un
	 * snippet que ya está activo devuelve «Could not activate snippet.» y
	 * acababa saliendo por pantalla como un aviso de algo que no había
	 * pasado. Lo que lo demuestra es `error` vacío; el contador del gancho
	 * comprueba además que ni siquiera se intenta.
	 */
	public function test_updated_and_still_active_is_not_activated_again() {
		$this->write( 'a.php', 'PSE TEST — sigue activo', '// version uno' );
		$created = $this->sync();
		$this->assertTrue( $created['a.php']['active'] );

		$activations = 0;
		add_action(
			'code_snippets/activate_snippet',
			function () use ( &$activations ) {
				++$activations;
			}
		);

		$this->write( 'a.php', 'PSE TEST — sigue activo', '// version dos' );
		$results = $this->sync();

		$this->assertSame( 'updated', $results['a.php']['status'] );
		$this->assertTrue( $results['a.php']['active'] );
		$this->assertSame( '', $results['a.php']['error'] );
		$this->assertSame( 0, $activations );
	}

	/**
	 * 19. Un `updated` que el guardado deja inactivo se recupera.
	 *
	 * `save_snippet()` revalida el código de un snippet activo
	 * (`test_snippet_code()`) y lo desactiva si encuentra `code_error`. Aquí
	 * se provoca de verdad, sin dobles: el `Validator` de Code Snippets
	 * rechaza un snippet que redeclare una función ya definida —compara
	 * contra `get_defined_functions()`—, así que se declara aquí mismo una
	 * con un nombre exclusivo de este test y el snippet pasa a redeclararla.
	 * La cadena que se comprueba es activo → el guardado lo desactiva → la
	 * sincronización lo recupera con `pse_activate_snippet_with_fallback()` →
	 * vuelve a quedar activo.
	 *
	 * El código del snippet no llega a ejecutarse en ningún momento: el
	 * `Validator` corta antes de `execute_snippet()`, y `activate_snippet()`
	 * vuelve a validar antes de tocar la tabla.
	 */
	public function test_updated_and_deactivated_by_save_is_recovered() {
		if ( ! function_exists( 'pse_test_snippet_sync_identifier_clash' ) ) {
			/**
			 * Nombre que el snippet del test redeclara para no validar.
			 */
			function pse_test_snippet_sync_identifier_clash() {
			}
		}

		$this->write( 'a.php', 'PSE TEST — recuperar', '// version uno' );
		$created = $this->sync();
		$id      = (int) $created['a.php']['id'];
		$this->assertTrue( $created['a.php']['active'] );

		$this->write( 'a.php', 'PSE TEST — recuperar', "function pse_test_snippet_sync_identifier_clash() {\n}" );
		$results = $this->sync();

		$this->assertSame( 'updated', $results['a.php']['status'] );
		$this->assertTrue( $results['a.php']['active'] );
		// La recuperación deja dicho por qué hizo falta saltarse el validador.
		$this->assertNotSame( '', $results['a.php']['error'] );
		$this->assertTrue( (bool) \Code_Snippets\get_snippet( $id )->active );
	}

	/**
	 * 20. Un snippet bloqueado con código distinto es un error.
	 *
	 * `save_snippet()` restaura el código de la fila cuando el snippet estaba
	 * bloqueado y sigue estándolo, así que guardar diría «actualizado» sin
	 * haber actualizado nada. Ni se guarda, ni se desbloquea, ni se aplica la
	 * metadatos a medias, y la siguiente pasada vuelve a avisar.
	 */
	public function test_locked_snippet_with_different_code_is_an_error() {
		$this->write( 'a.php', 'PSE TEST — locked código', '// version uno' );
		$created = $this->sync();
		$id      = (int) $created['a.php']['id'];

		\Code_Snippets\set_snippet_locked( $id, true );

		$this->write( 'a.php', 'PSE TEST — locked código', '// version dos' );
		$results = $this->sync();

		$this->assertSame( 'error', $results['a.php']['status'] );

		$snippet = \Code_Snippets\get_snippet( $id );
		$this->assertTrue( $snippet->locked );
		$this->assertStringContainsString( '// version uno', $snippet->code );
		$this->assertStringNotContainsString( '// version dos', $snippet->code );
		$this->assertTrue( (bool) $snippet->active );

		$otra_vez = $this->sync();
		$this->assertSame( 'error', $otra_vez['a.php']['status'] );
	}

	/**
	 * 21. Un snippet bloqueado con el mismo código sí actualiza su metadatos.
	 *
	 * El candado de Code Snippets protege el código y el nombre, no la
	 * descripción, el ámbito, la prioridad ni las etiquetas: `locked` no
	 * convierte el snippet en inmutable y la sincronización no lo trata como
	 * si lo hiciera.
	 *
	 * La divergencia tiene que venir de la tabla, y no del fichero, porque en
	 * este repositorio la descripción, el ámbito y la prioridad se declaran en
	 * la cabecera del propio fichero: cambiarlas ahí cambia también el código,
	 * que es justo lo que el candado no deja tocar. El caso real es el de
	 * quien edita la descripción desde el escritorio de Code Snippets.
	 */
	public function test_locked_snippet_with_identical_code_updates_its_metadata() {
		$this->write( 'a.php', 'PSE TEST — locked metadatos', '// no-op', 'La del repositorio.' );
		$created = $this->sync();
		$id      = (int) $created['a.php']['id'];

		$snippet       = \Code_Snippets\get_snippet( $id );
		$snippet->desc = 'La de alguien, cambiada a mano.';
		\Code_Snippets\save_snippet( $snippet );
		\Code_Snippets\set_snippet_locked( $id, true );

		$results = $this->sync();

		$this->assertSame( 'updated', $results['a.php']['status'] );

		$snippet = \Code_Snippets\get_snippet( $id );
		$this->assertSame( 'La del repositorio.', $snippet->desc );
		$this->assertTrue( $snippet->locked );
		$this->assertStringContainsString( '// no-op', $snippet->code );
	}
}
