<?php
/**
 * Application bootstrap.
 *
 * @package Pse
 */

namespace Pse;

use Pse\Access\Access;
use Pse\Centre\CentreCatalogueSync;
use Pse\Centre\CentreSettings;
use Pse\Notifications\Mailer;
use Pse\PostType\Requests;
use Pse\PublicFront\Screen;

/**
 * Wires hooks for the modular application.
 *
 * Se llama `App` y no `Plugin` a propósito: el validador de Code Snippets
 * compara los nombres declarados ignorando el namespace, así que un
 * `Pse\Plugin` chocaría con el `Code_Snippets\Plugin` del propio plugin y el
 * snippet se rechazaría con «code did not pass validation».
 */
final class App {

	/**
	 * Boot the application (idempotent).
	 *
	 * @return void
	 */
	public static function boot(): void {
		static $booted = false;
		if ( $booted ) {
			return;
		}
		$booted = true;

		// El catálogo de centros se copia de su fuente una vez al día.
		CentreCatalogueSync::register_cron();
		CentreSettings::register();
		add_action( 'init', array( Requests::class, 'register' ), 10 );
		add_action( 'init', array( Access::class, 'grant_caps_to_roles' ), 11 );
		Mailer::register();

		// La portada del sitio es el aplicativo: sin sesión, al acceso.
		Screen::register();
	}
}
