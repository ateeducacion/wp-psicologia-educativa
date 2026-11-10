#!/usr/bin/env node
/*
 * Comprueba la propagación de errores de `make provision` sin tocar WordPress.
 *
 * `provision` encadena seis pasos y cada uno puede fallar. Lo que se comprueba
 * aquí es que **un fallo para la cadena** en vez de seguir a medias, y que el
 * único paso opcional —el idioma— avisa y continúa. Se hace con un ejecutable
 * de mentira en lugar de `wp-env`, así que no hace falta Docker ni un sitio.
 *
 * Node y sin dependencias: el stack de este repositorio es PHP y JavaScript, y
 * un tercer lenguaje es un requisito más que instalar en cada máquina y en CI.
 */

import { execFileSync, spawnSync } from 'node:child_process';
import { chmodSync, mkdtempSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import vm from 'node:vm';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' );

/**
 * Trocea una orden como lo haría una shell, respetando las comillas.
 *
 * Lo justo para leer un `run:` de un workflow: comillas simples y dobles, sin
 * expansión de variables ni escapes, que en un workflow no hacen falta.
 *
 * @param {string} order The command line.
 * @return {string[]} Its words.
 */
function shellSplit( order ) {
	const words = [];
	let current = '';
	let quote = '';
	let open = false;

	for ( const ch of order ) {
		if ( quote ) {
			if ( ch === quote ) {
				quote = '';
			} else {
				current += ch;
			}
			continue;
		}
		if ( '"' === ch || "'" === ch ) {
			quote = ch;
			open = true;
			continue;
		}
		if ( /\s/.test( ch ) ) {
			if ( current || open ) {
				words.push( current );
				current = '';
				open = false;
			}
			continue;
		}
		current += ch;
	}
	if ( current || open ) {
		words.push( current );
	}
	return words;
}

/**
 * Para con un mensaje que dice qué se esperaba.
 *
 * @param {boolean} ok      Whether the expectation held.
 * @param {string}  message What was expected.
 * @param {string}  detail  What happened instead.
 */
function expect( ok, message, detail = '' ) {
	if ( ok ) {
		return;
	}
	console.error( `check-provision: ${ message }` );
	if ( detail ) {
		console.error( detail );
	}
	process.exit( 1 );
}

/*
 * Paso a paso, el target `provision` del Makefile. Si allí cambia el orden o el
 * número de pasos, esta lista tiene que cambiar con él: es justo lo que hace
 * que la comprobación sirva de algo.
 */
const STEPS = [
	'language core',
	'bundle',
	'sync-snippets',
	'provision-roles.php',
	'seed-demo.php',
];

/**
 * Comprueba que el JavaScript de los workflows compila.
 *
 * `actions/github-script` lleva su código **dentro de una cadena YAML**, así
 * que nadie lo compila hasta que el job corre: tres minutos después de
 * empujar, y solo si el resto del job llegó hasta ahí. Un identificador
 * repetido o un paréntesis suelto se descubren en CI y no en local.
 *
 * El bloque se saca por indentación y no con un analizador de YAML, para que
 * este guion siga **sin dependencias**: lo único que hay que entender es
 * `script: |` seguido de líneas más indentadas, que es como está escrito en
 * este repositorio.
 *
 * **Lo que esto NO comprueba**, y conviene no confundirlo: que las expresiones
 * `${{ … }}` sean válidas. Un `if` que lea un contexto que ahí no existe
 * —`secrets`, por ejemplo— es YAML perfectamente válido y JavaScript que ni se
 * mira; GitHub lo rechaza al recibir el fichero y ninguna comprobación local lo
 * ve venir. Eso solo lo dice GitHub.
 *
 * @return {void}
 */
function comprobarWorkflows() {
	const dir = join( ROOT, '.github/workflows' );
	const ficheros = readdirSync( dir ).filter( ( f ) => f.endsWith( '.yml' ) || f.endsWith( '.yaml' ) );

	expect( ficheros.length > 0, 'no hay ningún workflow en .github/workflows' );

	let guiones = 0;

	for ( const nombre of ficheros ) {
		const lineas = readFileSync( join( dir, nombre ), 'utf8' ).split( '\n' );

		for ( let i = 0; i < lineas.length; i++ ) {
			const apertura = lineas[ i ].match( /^(\s*)script:\s*\|/ );
			if ( ! apertura ) {
				continue;
			}
			// El bloque son las líneas siguientes con más indentación que la
			// clave; una línea en blanco no lo corta.
			const sangria = apertura[ 1 ].length;
			const bloque = [];
			let j = i + 1;
			for ( ; j < lineas.length; j++ ) {
				const linea = lineas[ j ];
				if ( '' === linea.trim() ) {
					bloque.push( '' );
					continue;
				}
				if ( linea.search( /\S/ ) <= sangria ) {
					break;
				}
				bloque.push( linea );
			}
			i = j - 1;

			const corte = Math.min( ...bloque.filter( ( l ) => '' !== l ).map( ( l ) => l.search( /\S/ ) ) );
			const codigo = bloque.map( ( l ) => l.slice( corte ) ).join( '\n' );
			guiones += 1;

			try {
				// Como lo envuelve la propia acción: una función asíncrona.
				new vm.Script( `(async () => {\n${ codigo }\n})` );
			} catch ( error ) {
				expect( false, `${ nombre }, línea ${ apertura.index + i }: el script no compila`, error.message );
			}
		}
	}

	expect( guiones > 0, 'no se encontró ningún bloque script: | en los workflows' );
	console.log( `Workflows: ${ guiones } guion(es) de github-script compilan, en ${ ficheros.length } fichero(s).` );
}

const workdir = mkdtempSync( join( tmpdir(), 'pse-provision-' ) );

try {
	const stub = join( workdir, 'command' );
	const trace = join( workdir, 'trace' );

	// El doble de `wp-env` y de `make`: apunta lo que le piden y falla solo
	// cuando se le dice qué paso tiene que romper.
	writeFileSync(
		stub,
		'#!/bin/sh\n' +
			'echo "$*" >> "$PSE_PROVISION_TRACE"\n' +
			'[ -z "$PSE_FAIL_STEP" ] && exit 0\n' +
			'case "$*" in *"$PSE_FAIL_STEP"*) exit 1;; esac\n' +
			'exit 0\n'
	);
	chmodSync( stub, 0o700 );

	for ( const failing of [ '', ...STEPS ] ) {
		writeFileSync( trace, '' );

		const run = spawnSync( 'make', [ 'provision', `WP_ENV=${ stub }`, `MAKE=${ stub }` ], {
			cwd: ROOT,
			encoding: 'utf8',
			env: { ...process.env, PSE_FAIL_STEP: failing, PSE_PROVISION_TRACE: trace },
		} );

		const calls = readFileSync( trace, 'utf8' ).split( '\n' ).filter( Boolean );
		// El idioma es el único paso opcional: si falla, avisa y sigue.
		const optional = '' === failing || 'language core' === failing;
		const output = ( run.stdout || '' ) + ( run.stderr || '' );

		expect(
			( 0 === run.status ) === optional,
			`fallando «${ failing || 'nada' }», la provisión ${ optional ? 'tenía que seguir' : 'tenía que pararse' }`,
			output
		);

		const expected = optional ? STEPS : STEPS.slice( 0, STEPS.indexOf( failing ) + 1 );
		expect(
			calls.length === expected.length,
			`fallando «${ failing || 'nada' }» se esperaban ${ expected.length } pasos y se dieron ${ calls.length }`,
			calls.join( '\n' )
		);
		expect(
			expected.every( ( step, i ) => calls[ i ].includes( step ) ),
			`fallando «${ failing || 'nada' }», los pasos no salieron en el orden del Makefile`,
			calls.join( '\n' )
		);

		if ( 'language core' === failing ) {
			expect( output.includes( 'AVISO:' ), 'el idioma falla en silencio: tenía que avisar', output );
		}
	}

	console.log( 'Provisión: éxito y fallos de los seis pasos comprobados; solo el idioma es opcional.' );

	/*
	 * El workflow de PHPMD no ejecuta phpmd a mano: llama a `make phpmd`
	 * pasándole el formato y el fichero de informe. Si el target dejara de
	 * reenviarlos, la ejecución seguiría en verde escupiendo texto y el SARIF no
	 * se escribiría nunca, así que se comprueba que lo que ordena el workflow
	 * llega al comando.
	 */
	const workflow = readFileSync( join( ROOT, '.github/workflows/phpmd.yml' ), 'utf8' );
	const order = workflow
		.split( '\n' )
		.map( ( line ) => line.trim() )
		.find( ( line ) => line.includes( 'run:' ) && line.includes( 'make phpmd' ) );
	expect( Boolean( order ), 'el workflow de PHPMD ya no llama a `make phpmd`' );

	comprobarWorkflows();

	// Lo que va detrás de `run:`, sin la palabra `make`, es lo que se le pasa.
	// Se trocea como lo haría una shell y no por espacios: uno de los
	// argumentos viene entrecomillado y lleva espacios dentro.
	const args = shellSplit( order.split( 'run:' )[ 1 ].trim() ).slice( 1 );

	const rendered = execFileSync( 'make', [ '--dry-run', ...args ], {
		cwd: ROOT,
		encoding: 'utf8',
	} );
	expect(
		rendered.includes( 'phpmd.xml' ) && rendered.includes( 'sarif' ),
		'`make phpmd` no reenvía el formato que pide el workflow',
		rendered
	);
	expect(
		rendered.includes( '--reportfile-sarif phpmd-results.sarif' ),
		'`make phpmd` no reenvía el fichero de informe que pide el workflow',
		rendered
	);
	console.log( 'PHPMD: el target reenvía el formato y el fichero de informe que pide el workflow.' );
} finally {
	rmSync( workdir, { recursive: true, force: true } );
}
