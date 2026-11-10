#!/usr/bin/env node
/*
 * Comprueba que no se filtra información que no puede salir del repositorio.
 *
 * Este repositorio se publica como software libre. Eso significa que **nada de
 * lo que se versiona** puede decir de quién es el despliegue, dónde está, qué
 * infraestructura usa ni cómo era el sistema que se sustituye por dentro. Todo
 * eso vive en `.local/`, que está en el `.gitignore` (ADR-0002).
 *
 * No es una comprobación de estilo: es la que evita publicar la dirección del
 * servidor de analítica de una organización, el identificador de su sitio, o
 * el mapa de una instalación ajena.
 *
 * Node y sin dependencias, como el resto de las comprobaciones: el stack de
 * este repositorio es PHP y JavaScript, y un tercer lenguaje es un requisito
 * más que instalar en cada máquina y en CI.
 *
 *   node scripts/check-public.mjs           comprueba
 *   node scripts/check-public.mjs --list    además enseña cada línea
 */

import { execFileSync } from 'node:child_process';
import { readFileSync, statSync } from 'node:fs';
import { dirname, extname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' );

/*
 * Qué no puede aparecer, y por qué. El consejo se le enseña a quien lo rompa,
 * así que dice qué hacer, no solo que está mal.
 *
 * Aquí solo van las reglas genéricas. Las que nombran a una organización
 * —su marca, sus sitios, sus programas, sus repositorios privados— no pueden
 * versionarse: publicarían justo lo que buscan. Viven en
 * `.local/check-public.rules.json` (o en la variable `PSE_PUBLIC_RULES`, para
 * CI), con la misma forma: `[ { name, pattern, flags, advice } ]`.
 */
const RULES = [
	{
		name: 'detalle interno del sistema que se sustituye',
		pattern: /formulario \d+|Vista \d+|campo \d{3}\b|snippet \d+/i,
		advice:
			'Cómo era la instalación anterior por dentro —sus formularios, vistas, campos ' +
			'y fragmentos de código— es material de investigación: vive en `.local/`.',
	},
	{
		name: 'rutas locales de quien desarrolla',
		pattern: /\/Users\/|\/home\/[a-z]/,
		advice: 'Una ruta absoluta de un portátil no le sirve a nadie más y dice quién eres.',
	},
	...privateRules(),
];

/**
 * The deployment-specific rules, kept out of the repository.
 *
 * @return {Array<{name: string, pattern: RegExp, advice: string}>} Rules.
 */
function privateRules() {
	let json = process.env.PSE_PUBLIC_RULES || '';
	if ( ! json ) {
		try {
			json = readFileSync( join( ROOT, '.local/check-public.rules.json' ), 'utf8' );
		} catch {
			console.warn( 'Sin .local/check-public.rules.json: solo se aplican las reglas genéricas.' );
			return [];
		}
	}
	return JSON.parse( json ).map( ( r ) => ( { ...r, pattern: new RegExp( r.pattern, r.flags || '' ) } ) );
}

// Lo generado y lo que es, por definición, material de investigación.
const SKIP = [
	'.local/',
	'node_modules/',
	'vendor/',
	'snippets/pse-psicologia-app.bundle.php',
];
const EXTENSIONS = new Set( [ '.php', '.md', '.js', '.mjs', '.css', '.html', '.json', '.yml', '.yaml', '.xml', '.dist', '.txt', '.py', '.cjs', '.sh' ] );
const NO_EXTENSION = new Set( [ 'Makefile', 'Dockerfile' ] );

/**
 * Skills de terceros: van tal cual las trae `gh skill`, que les deja
 * `github-repo:` en el frontmatter. No se pueden corregir sin divergir de
 * upstream, y sus rutas de ejemplo (`/home/…`) no son de nadie. Las propias no
 * llevan esa marca y se siguen revisando.
 *
 * @param {string} f Ruta relativa a la raíz.
 * @return {boolean} Si cae dentro de una skill de terceros.
 */
function isThirdPartySkill( f ) {
	const m = f.match( /^\.(?:agents|claude)\/skills\/[^/]+\// );
	if ( ! m ) {
		return false;
	}
	try {
		return /^\s*github-repo:/m.test( readFileSync( join( ROOT, m[ 0 ], 'SKILL.md' ), 'utf8' ) );
	} catch {
		return false;
	}
}

/**
 * Lo que git incluiría: se le pregunta a él, que ya conoce el `.gitignore`.
 *
 * @return {string[]} Rutas relativas a la raíz del repositorio.
 */
function tracked() {
	let out = '';
	try {
		out = execFileSync(
			'git',
			[ 'ls-files', '--cached', '--others', '--exclude-standard' ],
			{ cwd: ROOT, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }
		);
	} catch {
		// Sin git no hay lista fiable de lo que se publicaría, y adivinarla
		// sería peor que decirlo: se para.
		console.error( 'No se ha podido preguntar a git qué ficheros se versionan.' );
		process.exit( 2 );
	}

	return out
		.split( '\n' )
		.filter( ( f ) => f && ! SKIP.some( ( x ) => f.includes( x ) ) )
		.filter( ( f ) => ! isThirdPartySkill( f ) )
		.filter( ( f ) => {
			const name = f.split( '/' ).pop();
			if ( ! EXTENSIONS.has( extname( f ) ) && ! NO_EXTENSION.has( name ) ) {
				return false;
			}
			try {
				return statSync( join( ROOT, f ) ).isFile();
			} catch {
				return false;
			}
		} );
}

const verbose = process.argv.includes( '--list' );
const found = new Map(); // nombre de la regla → apariciones

for ( const file of tracked() ) {
	let text;
	try {
		text = readFileSync( join( ROOT, file ), 'utf8' );
	} catch {
		continue;
	}
	const lines = text.split( '\n' );
	for ( let i = 0; i < lines.length; i++ ) {
		for ( const rule of RULES ) {
			if ( rule.pattern.test( lines[ i ] ) ) {
				if ( ! found.has( rule.name ) ) {
					found.set( rule.name, [] );
				}
				found.get( rule.name ).push( {
					file,
					line: i + 1,
					text: lines[ i ].trim().slice( 0, 120 ),
				} );
			}
		}
	}
}

if ( found.size === 0 ) {
	console.log( 'Publicación: nada que no pueda salir del repositorio.' );
	process.exit( 0 );
}

const total = [ ...found.values() ].reduce( ( n, v ) => n + v.length, 0 );
console.log( `Publicación: ${ total } aparición(es) que no pueden ir a un repositorio público.\n` );

for ( const rule of RULES ) {
	const hits = found.get( rule.name );
	if ( ! hits ) {
		continue;
	}
	const files = [ ...new Set( hits.map( ( c ) => c.file ) ) ].sort();
	console.log( `  ${ rule.name }: ${ hits.length } en ${ files.length } fichero(s)` );
	console.log( `    → ${ rule.advice }` );
	for ( const f of files.slice( 0, 10 ) ) {
		const count = hits.filter( ( c ) => c.file === f ).length;
		console.log( `      ${ String( count ).padStart( 4 ) }  ${ f }` );
	}
	if ( files.length > 10 ) {
		console.log( `      … y ${ files.length - 10 } fichero(s) más` );
	}
	if ( verbose ) {
		for ( const c of hits ) {
			console.log( `        ${ c.file }:${ c.line }: ${ c.text }` );
		}
	}
	console.log();
}

console.log( 'Con `--list` se ve cada línea. Lo que sea material de investigación va a `.local/`.' );
process.exit( 1 );
