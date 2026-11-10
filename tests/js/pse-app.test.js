import { afterEach, describe, expect, it, vi } from 'vitest';

async function load() {
	vi.resetModules();
	await import( '../../assets/js/pse-app.js' );
}

function page( html ) {
	document.body.innerHTML = html;
}

afterEach( () => {
	if ( window.pseApp ) {
		document.removeEventListener( 'change', window.pseApp.onChange );
	}
	vi.restoreAllMocks();
} );

describe( 'cambiar el estado sin pulsar «Guardar»', () => {
	const FORM = '<form><select data-pse-autosubmit><option>a</option><option>b</option></select><button type="submit">Guardar</button></form>';

	it( 'esconde el botón, que con el guion sobra', async () => {
		page( FORM );
		await load();
		expect( document.querySelector( 'button' ).classList.contains( 'visually-hidden' ) ).toBe( true );
	} );

	it( 'envía el formulario al elegir', async () => {
		page( FORM );
		await load();
		const form = document.querySelector( 'form' );
		form.requestSubmit = vi.fn();
		document.querySelector( 'select' ).dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( form.requestSubmit ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'usa submit() donde no hay requestSubmit()', async () => {
		page( FORM );
		await load();
		const form = document.querySelector( 'form' );
		form.requestSubmit = undefined;
		form.submit = vi.fn();
		document.querySelector( 'select' ).dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( form.submit ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'no toca los demás desplegables ni los que no tienen formulario', async () => {
		page( '<form><select id="otro"><option>a</option></select></form><select id="suelto" data-pse-autosubmit></select>' );
		await load();
		const form = document.querySelector( 'form' );
		form.requestSubmit = vi.fn();
		document.querySelector( '#otro' ).dispatchEvent( new Event( 'change', { bubbles: true } ) );
		document.querySelector( '#suelto' ).dispatchEvent( new Event( 'change', { bubbles: true } ) );
		document.dispatchEvent( new Event( 'change' ) );
		expect( form.requestSubmit ).not.toHaveBeenCalled();
	} );
} );
