/*
 * Lo único que hace el guion: al elegir un estado, enviar su formulario.
 *
 * Sin JavaScript el formulario funciona igual, con su botón «Guardar»; con
 * él, el botón sobra y se esconde.
 */
( function () {
	function onChange( event ) {
		const select = event.target;
		if ( ! select || ! select.matches || ! select.matches( 'select[data-pse-autosubmit]' ) || ! select.form ) {
			return;
		}
		if ( typeof select.form.requestSubmit === 'function' ) {
			select.form.requestSubmit();
		} else {
			select.form.submit();
		}
	}

	function hideButtons() {
		document.querySelectorAll( 'select[data-pse-autosubmit]' ).forEach( function ( select ) {
			const button = select.form ? select.form.querySelector( 'button[type="submit"]' ) : null;
			if ( button ) {
				button.classList.add( 'visually-hidden' );
			}
		} );
	}

	document.addEventListener( 'change', onChange );
	hideButtons();
	window.pseApp = { onChange, hideButtons };
}() );
