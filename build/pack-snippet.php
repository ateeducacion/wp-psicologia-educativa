<?php
/**
 * Bundle src/Pse into a single Code Snippets-compatible PHP file.
 *
 * Usage (host):
 *   php build/pack-snippet.php
 *
 * Writes: snippets/pse-psicologia-app.bundle.php
 *
 * @package Pse
 */

$root    = dirname( __DIR__ );
$src_dir = $root . '/src/Pse';
$out     = $root . '/snippets/pse-psicologia-app.bundle.php';

// The changelog's top version heading is the single source of truth.
$changelog = (string) file_get_contents( $root . '/CHANGELOG.md' );
if ( ! preg_match( '/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $pse_version ) ) {
	fwrite( STDERR, "No version heading (## [X.Y.Z]) found in CHANGELOG.md\n" );
	exit( 1 );
}

$order = require $src_dir . '/load-order.php';

// Guard: every source file must be in the load order, or it silently would not ship.
$ignored = array( 'bootstrap.php', 'load-order.php' );
$files   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src_dir, FilesystemIterator::SKIP_DOTS ) );
foreach ( $files as $file ) {
	if ( 'php' !== $file->getExtension() ) {
		continue;
	}
	$rel = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $src_dir ) + 1 ) );
	if ( ! in_array( $rel, $ignored, true ) && ! in_array( $rel, $order, true ) ) {
		fwrite( STDERR, "Not in src/Pse/load-order.php: {$rel}\n" );
		exit( 1 );
	}
}

$header = <<<'HDR'
<?php
/**
 * Snippet Name: PSE — Solicitudes de intervención (CPT)
 * Description: Solicitudes de intervención de psicología educativa: los centros solicitan, cada zona atiende las suyas y la coordinación las sigue. Código generado desde src/Pse — no editar a mano; ejecutar php build/pack-snippet.php.
 * Scope: global
 * Priority: 15
 *
 * @package Pse
 * @version {{VERSION}}
 */

// phpcs:disable

HDR;

$header = str_replace( '{{VERSION}}', $pse_version[1], $header );

$body = '';
foreach ( $order as $rel ) {
	$path = $src_dir . '/' . $rel;
	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, "Missing: {$path}\n" );
		exit( 1 );
	}
	$code = file_get_contents( $path );
	if ( false === $code ) {
		fwrite( STDERR, "Cannot read {$path}\n" );
		exit( 1 );
	}
	// Strip opening PHP tag from each unit.
	$code = preg_replace( '/^\s*<\?php\s*/', '', $code );
	// Code Snippets re-evaluates an active snippet when it is saved. PHP wants
	// `namespace` first, so the guard goes right after the first one. It checks
	// a constant, not class_exists( App ): parentless classes are early-bound
	// while the eval()'d code compiles, so App would already exist on the first
	// pass and the bundle would return before booting anything.
	if ( $rel === $order[0] ) {
		$code = preg_replace(
			'/^namespace [^;]+;/m',
			"$0\n\n// La guarda de acceso directo va AQUÍ y no en la cabecera: antes de un\n// `namespace` no puede haber ninguna sentencia, y el bundle empieza por el\n// suyo. Code Snippets evalúa este código, no lo incluye, así que no hay\n// acceso directo que valga; va igual por si el fichero acaba servido.\ndefined( 'ABSPATH' ) || exit;\n\n// Code Snippets vuelve a evaluar un snippet activo al guardarlo: si el\n// bundle ya se cargó hay que salir, o el segundo eval() muere redeclarando\n// clases. Una constante y no class_exists(): las clases van cada una en su\n// propio class_exists(), y esta guarda es la que evita arrancar dos veces.\nif ( \\defined( 'PSE_BUNDLE_LOADED' ) ) {\n\treturn;\n}\n\\define( 'PSE_BUNDLE_LOADED', true );",
			(string) $code,
			1,
			$n
		);
		if ( 1 !== $n ) {
			fwrite( STDERR, "First unit must start with a namespace: {$rel}\n" );
			exit( 1 );
		}
	}
	// El validador de Code Snippets se salta el cuerpo de cada clase contando
	// llaves, y no reconoce el `{` de un `{$x}` dentro de una cadena, pero sí su
	// `}`: sale de la clase antes de tiempo y toma los métodos que siguen por
	// funciones repetidas. Dentro de una cadena, `$x` y `$wpdb->posts` valen
	// igual sin llaves.
	foreach ( token_get_all( '<?php ' . $code ) as $token ) {
		if ( is_array( $token ) && in_array( $token[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) {
			fwrite( STDERR, "src/Pse/{$rel}:{$token[2]}: interpolación con llaves («{\$…}»); escríbala sin llaves o concatenando, o Code Snippets no dejará guardar el bundle.\n" );
			exit( 1 );
		}
	}
	$body .= "\n// ---- src/Pse/{$rel} ----\n";
	$body .= trim( pse_guard_classes( (string) $code ) ) . "\n";
}

/**
 * Wrap every top-level class of a unit in its own `class_exists()` check.
 *
 * Code Snippets, desde la versión 4, valida el código al guardar **leyéndolo**,
 * sin ejecutarlo, y con el `namespace` en cuenta: ve `final class MetaTypes`
 * dentro de `Pse\Meta`, comprueba que `Pse\Meta\MetaTypes` ya existe —el
 * snippet está activo— y lo da por redeclarado. No ve la guarda
 * `PSE_BUNDLE_LOADED`, porque esa decide al ejecutar. Lo único que acepta es
 * un `class_exists( 'Nombre\Completo' )` escrito en el código: así sabe que la
 * redeclaración está controlada. Y es una guarda de verdad, no un adorno: la
 * clase se declara solo si no existe.
 *
 * Con el analizador léxico, como `pse_strip_comments()`: un `{` dentro de una
 * cadena no abre nada, y `Foo::class` o `new class` no son declaraciones.
 *
 * @param string $code One unit of src/Pse, without its opening tag.
 * @return string The same code, each class inside its guard.
 */
function pse_guard_classes( string $code ): string {
	$tokens    = token_get_all( '<?php ' . $code );
	$count     = count( $tokens );
	$out       = '';
	$namespace = '';
	$depth     = 0;
	$previous  = null;

	for ( $i = 1; $i < $count; $i++ ) {
		$token = $tokens[ $i ];
		if ( 0 === $depth && pse_token_is( $token, array( T_NAMESPACE ) ) ) {
			$namespace = pse_namespace_after( $tokens, $i );
		}
		if ( 0 === $depth && pse_starts_class( $token, $previous ) ) {
			list( $class, $name, $i ) = pse_read_class( $tokens, $i );
			$out                     .= "if ( ! class_exists( '" . ( '' !== $namespace ? $namespace . '\\' : '' ) . $name . "', false ) ) {\n" . $class . "\n}";
			$previous                 = '}';
			continue;
		}
		$depth += pse_brace_step( $token );
		$out   .= is_array( $token ) ? $token[1] : $token;
		if ( ! pse_token_is( $token, array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ) ) ) {
			$previous = $token;
		}
	}
	return $out;
}

/**
 * Whether a token is one of some kinds.
 *
 * @param array|string $token Token.
 * @param int[]        $kinds Token kinds.
 * @return bool
 */
function pse_token_is( $token, array $kinds ): bool {
	return is_array( $token ) && in_array( $token[0], $kinds, true );
}

/**
 * How a token moves the brace depth: +1, -1 or 0. `{$x}` in a string opens too.
 *
 * @param array|string $token Token.
 * @return int
 */
function pse_brace_step( $token ): int {
	if ( '{' === $token || pse_token_is( $token, array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ) ) ) {
		return 1;
	}
	return '}' === $token ? -1 : 0;
}

/**
 * Whether a top-level token starts a class declaration (not `Foo::class` nor `new class`).
 *
 * @param array|string      $token    Token.
 * @param array|string|null $previous Previous meaningful token.
 * @return bool
 */
function pse_starts_class( $token, $previous ): bool {
	return pse_token_is( $token, array( T_FINAL, T_ABSTRACT, T_CLASS ) ) && ! pse_token_is( $previous, array( T_DOUBLE_COLON, T_NEW ) );
}

/**
 * The name a `namespace` token declares.
 *
 * @param array<int, array|string> $tokens Tokens.
 * @param int                      $i      Index of the `namespace` token.
 * @return string
 */
function pse_namespace_after( array $tokens, int $i ): string {
	$name = '';
	for ( $j = $i + 1; isset( $tokens[ $j ] ) && ';' !== $tokens[ $j ] && '{' !== $tokens[ $j ]; $j++ ) {
		$name .= is_array( $tokens[ $j ] ) && T_WHITESPACE !== $tokens[ $j ][0] ? $tokens[ $j ][1] : '';
	}
	return trim( $name, '\\' );
}

/**
 * A whole class declaration, from its modifier to its closing brace.
 *
 * @param array<int, array|string> $tokens Tokens.
 * @param int                      $i      Index where the declaration starts.
 * @return array{0:string, 1:string, 2:int} Code, class name, index of the closing brace.
 */
function pse_read_class( array $tokens, int $i ): array {
	$class = '';
	$name  = '';
	$open  = 0;
	for ( $j = $i; isset( $tokens[ $j ] ); $j++ ) {
		$part   = $tokens[ $j ];
		$class .= is_array( $part ) ? $part[1] : $part;
		if ( '' === $name && pse_token_is( $part, array( T_STRING ) ) ) {
			$name = $part[1];
		}
		$open += pse_brace_step( $part );
		if ( '}' === $part && 0 === $open ) {
			break;
		}
	}
	return array( $class, $name, $j );
}

// Los assets viajan dentro del bundle: en producción no hay repositorio en
// disco ni URL de plugin, así que un `src` registrado daría 404 y el aplicativo
// se vería sin hoja de estilos. Véase src/Pse/PublicFront/Assets.php.
// La lista sale del directorio y no de aquí: escrita a mano, un fichero nuevo se
// olvida, `Assets::contents()` devuelve cadena vacía y el guion sencillamente no
// sale en la página, sin error que lo delate. El orden de este mapa da igual: es
// una búsqueda por ruta, y quien decide en qué orden se cargan es
// `Assets::register_assets()`.
/**
 * Strip every comment from the bundled body, keeping its line structure.
 *
 * El bundle es un artefacto: se pega en Code Snippets, no se lee ni se edita
 * —AGENTS.md lo prohíbe— y `src/Pse/` conserva los comentarios enteros, que es
 * donde se leen. Quitarlos aquí hace el snippet bastante más pequeño y más
 * manejable en el editor del plugin, que es lo que se pidió.
 *
 * Se hace con el analizador léxico de PHP y **no con expresiones regulares**:
 * un `//` dentro de una cadena o una URL no es un comentario, y un reemplazo a
 * ciegas se llevaría por delante medio fichero. `token_get_all()` sabe la
 * diferencia.
 *
 * Cada comentario se sustituye por sus propios saltos de línea, no por nada:
 * así el número de línea del bundle sigue significando algo cuando PHP informa
 * de un error, y la guarda del empaquetador —que el primer fichero abra con su
 * `namespace`— se sigue viendo donde estaba.
 *
 * **La cabecera no pasa por aquí.** Es un docblock y la leen dos cosas:
 * `pse_parse_snippet_header()` al sincronizar —de ahí salen el nombre, el
 * ámbito y la prioridad del snippet— y `make release`, que busca su
 * `@version`. Sin ella el snippet no se puede publicar.
 *
 * @param string $code Concatenated body of the bundle.
 * @return string The same code without comments.
 */
function pse_strip_comments( string $code ): string {
	// El `<?php` de entrada no es decorativo: sin él `token_get_all()` empieza
	// en modo HTML y devuelve el fichero entero como un solo T_INLINE_HTML…
	// hasta que tropieza con un `<?php` metido dentro de una cadena, y a
	// partir de ahí tokeniza desincronizado. Se le da la apertura y se le quita
	// después el token que corresponde.
	$tokens = token_get_all( '<?php ' . $code );
	$out    = '';
	$abrio  = false;

	foreach ( $tokens as $token ) {
		if ( ! $abrio && is_array( $token ) && T_OPEN_TAG === $token[0] ) {
			$abrio = true;
			continue;
		}
		if ( is_string( $token ) ) {
			$out .= $token;
			continue;
		}
		if ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
			// Los saltos que tuviera dentro se conservan; el texto no.
			$out .= str_repeat( "\n", substr_count( $token[1], "\n" ) );
			continue;
		}
		$out .= $token[1];
	}

	// Un comentario que ocupaba su línea deja la sangría suelta detrás. Se
	// recoge, pero **solo eso**: colapsar líneas en blanco tocaría también las
	// de dentro de las cadenas del CSS y el JavaScript inlineados, que son
	// contenido y no formato.
	$out = (string) preg_replace( '/^[ \t]+$/m', '', $out );

	return $out;
}

$assets = array();
foreach ( array( 'css', 'js' ) as $tipo ) {
	$encontrados = is_dir( $root . '/assets/' . $tipo ) ? glob( $root . '/assets/' . $tipo . '/*.' . $tipo ) : array();
	if ( false === $encontrados ) {
		fwrite( STDERR, "Cannot list assets/{$tipo}\n" );
		exit( 1 );
	}
	foreach ( $encontrados as $path ) {
		$assets[] = $tipo . '/' . basename( $path );
	}
}
sort( $assets );

$inlined = array();
foreach ( $assets as $rel ) {
	$path = $root . '/assets/' . $rel;
	$data = is_readable( $path ) ? file_get_contents( $path ) : false;
	if ( false === $data ) {
		fwrite( STDERR, "Cannot read asset: {$path}\n" );
		exit( 1 );
	}
	$inlined[ $rel ] = $data;
}

// Sin assets todavía no hay a quién dárselos: la clase que los sirve llega con
// la primera pantalla propia.
if ( array() !== $inlined ) {
	$body .= "\n// ---- assets/ (inlined) ----\n";
	$body .= '\Pse\PublicFront\Assets::set_inline( ' . var_export( $inlined, true ) . " );\n";
}

$footer = <<<'FTR'

if ( \class_exists( \Pse\App::class ) ) {
	\Pse\App::boot();
}

// phpcs:enable

FTR;

$body = pse_strip_comments( $body );

if ( false === file_put_contents( $out, $header . $body . $footer ) ) {
	fwrite( STDERR, "Cannot write {$out}\n" );
	exit( 1 );
}

// The test suite loads the modular sources, never the bundle: a per-file legal
// `declare(strict_types=1)` becomes a fatal once concatenated. Lint the result.
$lint   = array();
$status = 0;
// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Build script, host-only.
exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $out ) . ' 2>&1', $lint, $status );
if ( 0 !== $status ) {
	fwrite( STDERR, implode( "\n", $lint ) . "\nBundle is not valid PHP; fix the source and rebuild.\n" );
	exit( 1 );
}

fwrite( STDOUT, "Wrote {$out} (" . filesize( $out ) . " bytes, syntax OK)\n" );
