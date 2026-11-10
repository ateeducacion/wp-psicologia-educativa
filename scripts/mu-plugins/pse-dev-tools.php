<?php
/**
 * Plugin Name: PSE Dev Tools
 * Description: Herramientas de desarrollo del aplicativo de solicitudes de intervención: cambiar de usuario demo y mostrar las cuentas de prueba en wp-login.php. Solo para entornos de desarrollo, nunca se despliega.
 * Version: 1.1.0
 * Author: Equipo de desarrollo
 * License: GPL-2.0-or-later
 *
 * This mu-plugin is mounted by wp-env / Playground from scripts/mu-plugins.
 * User switching is delegated to WPFront User Role Editor.
 *
 * @package Pse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// wp-admin pide el avatar a secure.gravatar.com, y esperar al evento «load» de
// una página es esperar también a esa petición: cuando el runner de CI tarda en
// resolverla, un inicio de sesión de medio segundo se planta en los 30 s de
// Playwright y la comprobación se cae sin que falle nada del aplicativo. Es un
// filtro, no una opción: no toca la base de datos, así que sobrevive a
// `make destroy` y vale igual en Playground.
add_filter( 'pre_option_show_avatars', '__return_zero' );

if ( ! function_exists( 'pse_dev_demo_accounts' ) ) {
	/**
	 * Demo accounts used for role testing (see scripts/seed-demo.php).
	 *
	 * @return list<array{login:string,pass:string,label:string}>
	 */
	function pse_dev_demo_accounts(): array {
		return array(
			array(
				'login' => 'admin',
				'pass'  => 'password',
				'label' => 'Administración (lo ve todo)',
			),
			array(
				'login' => 'direccion',
				'pass'  => 'password',
				'label' => 'Dirección de centro (Zona 1): solicita y ve las de su centro',
			),
			array(
				'login' => 'direccion2',
				'pass'  => 'password',
				'label' => 'Dirección de centro 2 (Zona 3)',
			),
			array(
				'login' => 'psicologia',
				'pass'  => 'password',
				'label' => 'Psicología educativa (Zonas 1 y 2): atiende y cambia el estado',
			),
			array(
				'login' => 'psicologia2',
				'pass'  => 'password',
				'label' => 'Psicología educativa 2 (Zona 3)',
			),
			array(
				'login' => 'coordinacion',
				'pass'  => 'password',
				'label' => 'Coordinación: todas las solicitudes, estadísticas y zonas',
			),
		);
	}
}

if ( ! function_exists( 'pse_dev_demo_logins' ) ) {
	/**
	 * Demo logins for the admin-bar switcher.
	 *
	 * @return array<string, string> login => short label.
	 */
	function pse_dev_demo_logins(): array {
		$logins = array();
		foreach ( pse_dev_demo_accounts() as $account ) {
			$logins[ $account['login'] ] = $account['label'];
		}
		return $logins;
	}
}

if ( ! function_exists( 'pse_dev_wpfront_switching' ) ) {
	/**
	 * Whether WPFront User Role Editor's user switching is loaded.
	 *
	 * El mismo plugin que en producción: así el «Cambiar a…» de aquí hace lo
	 * que hace allí, y no hay un segundo mecanismo que mantener.
	 *
	 * @return bool
	 */
	function pse_dev_wpfront_switching(): bool {
		return class_exists( '\\WPFront\\URE\\User_Switching\\WPFront_User_Role_Editor_User_Switching' );
	}
}

if ( ! function_exists( 'pse_dev_ensure_switch_cap' ) ) {
	/**
	 * Make sure administration really holds `switch_users`.
	 *
	 * WPFront no concede esa capacidad al activarse: la añade al rol de
	 * administración a través del filtro
	 * `wpfront_ure_administrator_caps_to_process`, y ese filtro **solo corre
	 * cuando alguien entra en su interfaz del escritorio**. En un wp-env se
	 * entra tarde o temprano y por eso allí funciona; en **WordPress
	 * Playground**, que aterriza en el aplicativo y se aprovisiona sin abrir
	 * wp-admin, no entra nadie, la capacidad no llega nunca y el «Cambiar a…»
	 * responde «Permission denied» (su propio `wp_die`, 403).
	 *
	 * Así que se hace aquí lo mismo que haría el plugin: dársela al rol de
	 * administración, y a nadie más. Solo escribe cuando falta, así que en un
	 * entorno donde WPFront ya la puso no toca la base de datos.
	 *
	 * Esto es **solo desarrollo**: este mu-plugin no se despliega nunca.
	 *
	 * @return void
	 */
	function pse_dev_ensure_switch_cap(): void {
		if ( ! pse_dev_wpfront_switching() ) {
			return;
		}
		$rol = get_role( 'administrator' );
		if ( $rol instanceof WP_Role && ! $rol->has_cap( 'switch_users' ) ) {
			$rol->add_cap( 'switch_users' );
		}
	}
}

// Después de que WPFront se haya cargado —él engancha su propio `init` con
// prioridad 1— y antes de que ninguna pantalla pregunte por la capacidad.
add_action( 'init', 'pse_dev_ensure_switch_cap', 5 );

if ( ! function_exists( 'pse_dev_switch_to_user_url' ) ) {
	/**
	 * Build the WPFront switch-to-user URL, or null when the plugin is not there.
	 *
	 * Los mismos parámetros y el mismo nonce que pone WPFront en la lista de
	 * usuarios (`ure_switch_action=switch_to`); los procesa él en `init`.
	 *
	 * @param WP_User $user   Target user.
	 * @param string  $volver Where to land after the switch; '' for the default.
	 * @return string|null
	 */
	function pse_dev_switch_to_user_url( WP_User $user, string $volver = '' ): ?string {
		if ( ! pse_dev_wpfront_switching() ) {
			return null;
		}
		$args = array(
			'ure_switch_action' => 'switch_to',
			'user_id'           => $user->ID,
		);
		if ( '' !== $volver ) {
			$args['pse_back'] = rawurlencode( $volver );
		}
		return wp_nonce_url(
			add_query_arg( $args, admin_url( 'users.php' ) ),
			"switch_to_user_{$user->ID}"
		);
	}
}

if ( ! function_exists( 'pse_dev_front_url' ) ) {
	/**
	 * Where a switch lands when there is nowhere to go back to.
	 *
	 * El aplicativo es la portada del sitio, así que el destino es la portada
	 * y no el escritorio de WordPress: cambiar de perfil es para ver el
	 * aplicativo con otros ojos, y el escritorio no es el aplicativo.
	 *
	 * @return string
	 */
	function pse_dev_front_url(): string {
		return home_url( '/' );
	}
}

if ( ! function_exists( 'pse_dev_switch_destination' ) ) {
	/**
	 * Where this switch has to land.
	 *
	 * **La pantalla en la que se estaba**, que es lo que se quiere al cambiar
	 * de perfil: se mira una solicitud, se cambia a otro perfil y se sigue
	 * mirando la misma acción con sus permisos. Rebotar a la lista obliga a
	 * volver a buscarlo, y es justo cuando se pierde lo que se iba a comprobar.
	 *
	 * La dirección se valida contra este sitio: viene de la petición, y un
	 * destino de fuera convertiría el cambio de usuario en un salto a cualquier
	 * parte.
	 *
	 * @return string
	 */
	function pse_dev_switch_destination(): string {
		$defecto = pse_dev_front_url();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- el nonce del cambio se comprobó en pse_dev_prepare_switch_redirect().
		$pedido = isset( $_GET['pse_back'] ) ? rawurldecode( sanitize_text_field( wp_unslash( $_GET['pse_back'] ) ) ) : '';
		if ( '' === $pedido ) {
			return $defecto;
		}
		return wp_validate_redirect( $pedido, $defecto );
	}
}

if ( ! function_exists( 'pse_dev_prepare_switch_redirect' ) ) {
	/**
	 * Keep WPFront's authenticated user switches inside the development app.
	 *
	 * @return void
	 */
	function pse_dev_prepare_switch_redirect(): void {
		$action = isset( $_GET['ure_switch_action'] ) ? sanitize_key( wp_unslash( $_GET['ure_switch_action'] ) ) : '';
		if ( ! pse_dev_wpfront_switching() || ! in_array( $action, array( 'switch_to', 'switch_back', 'clear' ), true ) ) {
			return;
		}
		$target = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		$nonce  = 'switch_to' === $action ? 'switch_to_user_' . $target : 'switch_back_user_' . get_current_user_id();
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), $nonce ) ) {
			return;
		}
		// WPFront sigue comprobando permisos y cambiando la sesión en init:1.
		// Solo sustituimos su destino final; el nonce pertenece al usuario inicial.
		add_filter(
			'wp_redirect',
			static function ( $location ) {
				return in_array( $location, array( admin_url(), home_url() ), true ) ? pse_dev_switch_destination() : $location;
			}
		);
	}
}
add_action( 'init', 'pse_dev_prepare_switch_redirect', 0 );

if ( ! function_exists( 'pse_dev_admin_bar_node' ) ) {
	/**
	 * Add the quick demo account switcher to the admin bar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 * @return void
	 */
	function pse_dev_admin_bar_node( $wp_admin_bar ) {
		$can_manage = current_user_can( 'manage_options' );
		$can_switch = pse_dev_wpfront_switching() && current_user_can( 'switch_users' );

		if ( ! $can_switch && ! $can_manage ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'pse-switch-user',
				'title' => 'PSE: Cambiar a…',
				'href'  => false,
			)
		);

		$current_login = wp_get_current_user()->user_login;
		// Dónde se está ahora, para volver aquí con el otro perfil.
		$aqui = '';
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$aqui = home_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		}

		foreach ( pse_dev_demo_logins() as $login => $label ) {
			$user = get_user_by( 'login', $login );
			if ( ! $user instanceof WP_User ) {
				$wp_admin_bar->add_node(
					array(
						'id'     => 'pse-switch-' . sanitize_key( $login ),
						'parent' => 'pse-switch-user',
						'title'  => $label . ' (no creado — make seed-demo)',
						'href'   => false,
						'meta'   => array( 'class' => 'pse-switch-missing' ),
					)
				);
				continue;
			}

			if ( $login === $current_login ) {
				$wp_admin_bar->add_node(
					array(
						'id'     => 'pse-switch-' . sanitize_key( $login ),
						'parent' => 'pse-switch-user',
						'title'  => '✓ ' . $label,
						'href'   => false,
					)
				);
				continue;
			}

			$url = pse_dev_switch_to_user_url( $user, $aqui );
			if ( null === $url ) {
				// Plugin not loaded yet: point to users list as last resort.
				$url = admin_url( 'users.php?s=' . rawurlencode( $login ) );
			}

			$wp_admin_bar->add_node(
				array(
					'id'     => 'pse-switch-' . sanitize_key( $login ),
					'parent' => 'pse-switch-user',
					'title'  => $label,
					'href'   => $url,
				)
			);
		}
	}
}
add_action( 'admin_bar_menu', 'pse_dev_admin_bar_node', 100 );

if ( ! function_exists( 'pse_dev_login_form_accounts' ) ) {
	/**
	 * Print the demo account list under the login submit button.
	 *
	 * Hooked on `login_form` (after the password field). CSS `order` moves the
	 * box below «Acceder» without leaving the form.
	 *
	 * @return void
	 */
	function pse_dev_login_form_accounts() {
		echo '<div class="pse-dev-login-accounts">';
		echo '<p class="pse-dev-login-accounts__title">Cuentas de prueba</p>';
		echo '<ul class="pse-dev-login-accounts__list">';

		foreach ( pse_dev_demo_accounts() as $account ) {
			echo '<li>';
			echo '<button type="button" class="pse-dev-fill-login" data-login="' . esc_attr( $account['login'] ) . '" data-pass="' . esc_attr( $account['pass'] ) . '">';
			echo esc_html( $account['login'] );
			echo '</button>';
			echo ' / <code>' . esc_html( $account['pass'] ) . '</code>';
			echo '<span class="pse-dev-login-accounts__role">' . esc_html( $account['label'] ) . '</span>';
			echo '</li>';
		}

		echo '</ul>';
		echo '<p class="pse-dev-login-accounts__hint">Clic en el usuario para rellenar el formulario.</p>';
		echo '</div>';
	}
}
add_action( 'login_form', 'pse_dev_login_form_accounts' );

if ( ! function_exists( 'pse_dev_login_assets' ) ) {
	/**
	 * Styles and click-to-fill script for the demo account list on wp-login.php.
	 *
	 * @return void
	 */
	function pse_dev_login_assets() {
		global $action;

		if ( ! isset( $action ) || 'login' !== $action ) {
			return;
		}

		$css = <<<'CSS'
#loginform {
	display: flex;
	flex-direction: column;
}
.pse-dev-login-accounts {
	order: 20;
	margin-block-start: 1.25em;
	padding: 12px 14px;
	border: 1px solid #c3c4c7;
	background: #f6f7f7;
	box-sizing: border-box;
	font-size: 13px;
	line-height: 1.4;
}
.pse-dev-login-accounts__title {
	margin: 0 0 8px;
	font-weight: 600;
}
.pse-dev-login-accounts__list {
	margin: 0;
	padding: 0;
	list-style: none;
}
.pse-dev-login-accounts__list li + li {
	margin-block-start: 8px;
}
.pse-dev-fill-login {
	margin: 0;
	padding: 0;
	border: 0;
	background: none;
	color: #2271b1;
	cursor: pointer;
	font: inherit;
	font-family: Consolas, Monaco, monospace;
	text-decoration: underline;
}
.pse-dev-fill-login:focus-visible {
	outline: 2px solid #2271b1;
	outline-offset: 2px;
}
.pse-dev-login-accounts__role {
	display: block;
	color: #50575e;
}
.pse-dev-login-accounts__hint {
	margin: 8px 0 0;
	color: #646970;
}
CSS;

		wp_register_style( 'pse-dev-login', false, array(), '1.0.0' );
		wp_enqueue_style( 'pse-dev-login' );
		wp_add_inline_style( 'pse-dev-login', $css );

		$js = <<<'JS'
(function () {
	var box = document.querySelector(".pse-dev-login-accounts");
	var submit = document.querySelector("#loginform p.submit");
	if (box && submit && submit.parentNode) {
		submit.parentNode.insertBefore(box, submit.nextSibling);
	}
	document.querySelectorAll(".pse-dev-fill-login").forEach(function (button) {
		button.addEventListener("click", function () {
			var login = document.getElementById("user_login");
			var pass = document.getElementById("user_pass");
			if (login) {
				login.value = button.getAttribute("data-login") || "";
			}
			if (pass) {
				pass.value = button.getAttribute("data-pass") || "";
			}
			if (login) {
				login.focus();
			}
		});
	});
})();
JS;

		wp_register_script( 'pse-dev-login', false, array(), '1.0.0', true );
		wp_enqueue_script( 'pse-dev-login' );
		wp_add_inline_script( 'pse-dev-login', $js );
	}
}
add_action( 'login_enqueue_scripts', 'pse_dev_login_assets' );

// Lo que se encola desde jsDelivr mete la red en mitad de cada prueba: si el CDN
// tarda o el DNS parpadea, la página se dibuja sin estilos y la comprobación se
// cae señalando a un sitio que no tiene nada que ver. Aquí se sirve de la copia
// que `npm install` deja en node_modules, con la versión clavada: la misma que
// pide la URL, o no se toca nada (ADR-0002).
//
// Solo alcanza a lo encolado con `wp_enqueue_*`. El documento propio del
// aplicativo (`Screen::document()`) escribe sus etiquetas a mano, así que en
// desarrollo esas siguen viniendo del CDN.
//
// Solo desarrollo: este mu-plugin no se despliega. En producción siguen viniendo
// del CDN, con su SRI, que se añade mirando el `src` y por tanto deja de ponerse
// solo cuando la URL ya no es la del CDN.
if ( ! function_exists( 'pse_dev_local_cdn_src' ) ) {
	/**
	 * Serve a pinned jsDelivr asset from node_modules when it is installed.
	 *
	 * @param string $src Asset URL.
	 * @return string
	 */
	function pse_dev_local_cdn_src( $src ) {
		if ( ! is_string( $src ) || 0 !== strpos( $src, 'https://cdn.jsdelivr.net/npm/' ) ) {
			return $src;
		}

		// WordPress ya le ha pegado el `?ver=`; se aparta y se devuelve al final.
		$consulta = '';
		$posicion = strpos( $src, '?' );
		if ( false !== $posicion ) {
			$consulta = substr( $src, $posicion );
			$src      = substr( $src, 0, $posicion );
		}

		$patron = '~^https://cdn\.jsdelivr\.net/npm/((?:@[^/@]+/)?[^/@]+)@([^/]+)/(.+)$~';
		if ( ! preg_match( $patron, $src, $partes ) ) {
			return $src . $consulta;
		}
		list( , $paquete, $version, $fichero ) = $partes;

		// 1) El paquete está instalado.
		$base = WP_CONTENT_DIR . '/pse-dev/node_modules/' . $paquete;
		$meta = $base . '/package.json';
		if ( ! is_readable( $meta ) ) {
			return $src . $consulta;
		}

		// 2) Y su versión es exactamente la que pide la URL: otra probaría algo
		// distinto de lo que se despliega. Mejor seguir yendo al CDN y que se note.
		$datos = wp_json_file_decode( $meta, array( 'associative' => true ) );
		if ( ! is_array( $datos ) || ( $datos['version'] ?? '' ) !== $version ) {
			return $src . $consulta;
		}

		// 3) Y el fichero existe. jsDelivr minifica al vuelo, así que hay `.min`
		// que el paquete no trae: entonces vale el original.
		$candidatos = array( $fichero, (string) preg_replace( '~\.min\.(js|css)$~', '.$1', $fichero ) );
		foreach ( array_unique( $candidatos ) as $candidato ) {
			if ( is_readable( $base . '/' . $candidato ) ) {
				return content_url( '/pse-dev/node_modules/' . $paquete . '/' . $candidato ) . $consulta;
			}
		}
		return $src . $consulta;
	}
}

add_filter( 'script_loader_src', 'pse_dev_local_cdn_src' );
add_filter( 'style_loader_src', 'pse_dev_local_cdn_src' );
