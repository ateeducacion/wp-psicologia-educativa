<?php
/**
 * Tests for the implementation plans and their index.
 *
 * @package Pse
 */

/**
 * Los planes se indexan y sus enlaces llevan a alguna parte.
 *
 * Es el mismo trato que reciben las ADR en `test-adr-registry.php`, y por el
 * mismo motivo: un plan que no entra en `README.md` no lo encuentra nadie, y un
 * plan que enlaza a un fichero que no existe manda a un 404 a quien lo lea
 * dentro de un año. Los planes enlazan sobre todo **fuera** de su directorio
 * —a `../adr/` y a `../sdd/`—, así que es justo donde más fácil es equivocarse.
 */
class Test_Plan_Registry extends WP_UnitTestCase {

	/**
	 * Directory holding the plans.
	 *
	 * @return string
	 */
	private function dir(): string {
		return dirname( __DIR__, 2 ) . '/docs/plan/';
	}

	/**
	 * Plan files, by ID.
	 *
	 * @return array<string, string> ID => file name.
	 */
	private function plans(): array {
		$out = array();
		foreach ( (array) glob( $this->dir() . 'PLAN-*.md' ) as $ruta ) {
			$nombre                         = basename( (string) $ruta );
			$out[ substr( $nombre, 0, 9 ) ] = $nombre;
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Contents of a file under docs/plan/.
	 *
	 * @param string $nombre File name.
	 * @return string
	 */
	private function texto( string $nombre ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions -- se lee la documentación del propio repositorio.
		return (string) file_get_contents( $this->dir() . $nombre );
	}

	/**
	 * Todo plan del directorio está en la tabla del índice, con su enlace.
	 */
	public function test_every_plan_is_in_the_index() {
		$indice = $this->texto( 'README.md' );
		$planes = $this->plans();

		$this->assertNotEmpty( $planes, 'no hay ningún plan: algo va mal con la ruta' );
		foreach ( $planes as $id => $nombre ) {
			$this->assertStringContainsString( '(' . $nombre . ')', $indice, $id . ' no está enlazado en README.md' );
			$this->assertStringContainsString( '| [' . $id . ']', $indice, $id . ' no tiene fila en la tabla de README.md' );
		}
	}

	/**
	 * Cada plan abre con su frontmatter completo, y el `id` es el del nombre.
	 */
	public function test_every_plan_has_complete_frontmatter() {
		foreach ( $this->plans() as $id => $nombre ) {
			$texto = $this->texto( $nombre );
			$this->assertStringStartsWith( "---\n", $texto, $nombre . ' no abre con frontmatter' );

			foreach ( array( 'title:', 'status:', 'date:', 'supersedes:', 'superseded_by:', 'ai_assistance:' ) as $clave ) {
				$this->assertStringContainsString( "\n" . $clave, $texto, $nombre . ' no declara ' . $clave );
			}
			$this->assertStringContainsString( "\nid: " . $id . "\n", $texto, $nombre . ' declara un id que no es el de su nombre' );
			$this->assertMatchesRegularExpression( '/\ndate: \d{4}-\d{2}-\d{2}\n/', $texto, $nombre . ' no lleva fecha AAAA-MM-DD' );
			$this->assertMatchesRegularExpression( '/\n  tool: "[^"]+"\n/', $texto, $nombre . ' no declara la herramienta de IA' );
			$this->assertMatchesRegularExpression( '/\n  model: "[^"]+"\n/', $texto, $nombre . ' no declara el modelo de IA' );
		}
	}

	/**
	 * Ningún enlace relativo de un plan —ni del índice— apunta a un fichero que
	 * no está. Es lo que más se rompe: casi todos salen del directorio.
	 */
	public function test_no_plan_links_to_a_missing_file() {
		$ficheros   = $this->plans();
		$ficheros[] = 'README.md';

		foreach ( $ficheros as $nombre ) {
			preg_match_all( '/\]\(((?:\.\.\/)*[A-Za-z0-9._\/-]+\.md)(?:#[^)]*)?\)/', $this->texto( $nombre ), $m );
			foreach ( array_unique( $m[1] ) as $rel ) {
				$this->assertFileExists( $this->dir() . $rel, $nombre . ' enlaza a ' . $rel . ', que no existe' );
			}
		}
	}
}
