<?php
/**
 * The application lives on the site front page: routing and chrome.
 *
 * @package Pse
 */

namespace Pse\PublicFront;

use Pse\Access\Access;

/**
 * La portada del sitio es el aplicativo.
 *
 * Sin sesión, al acceso. Con sesión, un documento propio —no el del tema—,
 * con la cabecera y las pestañas de los aplicativos hermanos. Cada pantalla es
 * un parámetro de la portada (`?pse_tab=zone`): no hay páginas que crear ni
 * que se puedan borrar por error (ADR-0002).
 */
final class Screen {

	public const ARG_TAB    = 'pse_tab';
	public const ARG_NOTICE = 'pse_notice';
	public const ARG_CSV    = 'pse_csv';

	/**
	 * The application's name: header and page title.
	 */
	public const APP_NAME = 'Solicitudes de intervención';

	/**
	 * Tab and screen title of each screen.
	 */
	public const TITLES = array(
		'new'    => 'Solicitar intervención',
		'centre' => 'Solicitudes de mi centro',
		'zone'   => 'Solicitudes de mi zona',
		'all'    => 'Solicitudes',
		'stats'  => 'Estadísticas',
		'zones'  => 'Zonas',
	);

	/**
	 * Bootstrap from jsDelivr, pinned with SRI, same version as `package.json`.
	 */
	public const BOOTSTRAP = array(
		'css'     => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css',
		'css_sri' => 'sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB',
		'js'      => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js',
		'js_sri'  => 'sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI',
	);

	/**
	 * Bootstrap Icons from jsDelivr, pinned with SRI, same version as `package.json`.
	 */
	public const BOOTSTRAP_ICONS = array(
		'css'     => 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.2/font/bootstrap-icons.min.css',
		'css_sri' => 'sha384-nWN3KqaaVcbEsCjLSV+2jMjh62Iny/+mN3srM8TZeM8N6aj7eDl5wwYggjAJ4/sJ',
	);

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'route' ), 1 );
		add_filter( 'login_redirect', array( self::class, 'login_redirect' ), 10, 2 );
		add_filter( 'show_admin_bar', array( self::class, 'show_admin_bar' ), 100 );
	}

	/**
	 * The WordPress admin bar: administration, and an administrator who switched into someone.
	 *
	 * Como en los aplicativos hermanos: la barra es por donde la administración
	 * vuelve al escritorio y, con WPFront User Role Editor, se cambia a otro
	 * usuario para probar el aplicativo con su perfil. A quien suplanta se le
	 * deja para que pueda volver a su cuenta. Solo decide si se ve; no concede
	 * nada al usuario suplantado.
	 *
	 * @param bool $show Whether WordPress would show it.
	 * @return bool
	 */
	public static function show_admin_bar( $show = false ): bool {
		unset( $show );
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$key = 'wpfront_ure_user_switching_stack_' . COOKIEHASH;
		if ( ! class_exists( '\\WPFront\\URE\\WPFront_User_Role_Editor_Utils' ) || ! isset( $_COOKIE[ $key ] ) || ! is_string( $_COOKIE[ $key ] ) ) {
			return false;
		}
		// La pila de WPFront: «COOKIEHASH-hora-id,id,…-recordar», cifrada; el primero es quien empezó.
		$stack = explode( '-', (string) \WPFront\URE\WPFront_User_Role_Editor_Utils::decrypt( sanitize_text_field( wp_unslash( $_COOKIE[ $key ] ) ) ), 4 );
		if ( count( $stack ) < 3 || COOKIEHASH !== $stack[0] || ! ctype_digit( $stack[1] ) ) {
			return false;
		}
		$age   = time() - (int) $stack[1];
		$users = explode( ',', $stack[2] );
		return $age >= 0 && $age <= 12 * HOUR_IN_SECONDS && ctype_digit( $users[0] ) && user_can( (int) $users[0], 'manage_options' );
	}

	/**
	 * After logging in without a destination, land on the application.
	 *
	 * @param string $redirect_to Destination.
	 * @param string $requested   Destination the login form asked for.
	 * @return string
	 */
	public static function login_redirect( $redirect_to, $requested ) {
		if ( '' === (string) $requested || admin_url() === (string) $requested ) {
			return home_url( '/' );
		}
		return (string) $redirect_to;
	}

	/**
	 * Tabs the current user has something to do in, in order.
	 *
	 * @return array<string, string> Tab => label.
	 */
	public static function tabs(): array {
		$tabs = array();
		$caps = array(
			'new'    => Access::CAP_REQUEST,
			'centre' => Access::CAP_REQUEST,
			'zone'   => Access::CAP_ATTEND,
			'all'    => Access::CAP_OVERSEE,
			'stats'  => Access::CAP_OVERSEE,
			'zones'  => Access::CAP_ZONES,
		);
		foreach ( $caps as $tab => $cap ) {
			// Solicitar es de quien tiene un centro: la administración lo prueba entrando como una dirección.
			$own = Access::CAP_REQUEST !== $cap || '' !== Access::centre_code();
			if ( $own && current_user_can( $cap ) ) {
				$tabs[ $tab ] = self::TITLES[ $tab ];
			}
		}
		return $tabs;
	}

	/**
	 * URL of a screen.
	 *
	 * @param array<string, scalar> $args Query args.
	 * @return string
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg( array_filter( $args, static fn( $v ) => '' !== $v && null !== $v ), home_url( '/' ) );
	}

	/**
	 * A sanitised query argument of the current screen.
	 *
	 * @param string $name Argument.
	 * @return string
	 */
	public static function arg( string $name ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navegación de solo lectura.
		return isset( $_GET[ $name ] ) && is_scalar( $_GET[ $name ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $name ] ) ) : '';
	}

	/**
	 * Route the front page.
	 *
	 * @return void
	 */
	public static function route(): void {
		if ( ! is_front_page() ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			self::leave( wp_login_url( home_url( '/' ) ) );
		}
		nocache_headers();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Actions::handle() verifies the nonce.
		if ( isset( $_POST[ Actions::FIELD ] ) ) {
			self::leave( Actions::handle() );
		}

		$tabs = self::tabs();
		$tab  = sanitize_key( self::arg( self::ARG_TAB ) );
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = (string) array_key_first( $tabs );
		}
		if ( 'all' === $tab && '' !== self::arg( self::ARG_CSV ) ) {
			Views::send_csv();
			exit;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- document() escapes, and every screen body is built escaped.
		echo self::document( Views::screen( $tab ) );
		exit;
	}

	/**
	 * What the deployment adds around the app: `head` (analytics, cookie
	 * notice), `brand` (logo and organisation, before the app name) and
	 * `footer` (legal links, the organisation's name). El pie reparte lo que
	 * llegue en `footer`: con la clase `pse-foot-legal`, un grupo de enlaces se
	 * va a la derecha; con `pse-foot-credit`, el crédito de quien desarrolla se
	 * pinta atenuado.
	 *
	 * Lo que dice de quién es el despliegue no se versiona (ADR-0002): llega por
	 * el filtro `pse_chrome`, vacío por defecto, desde un snippet de quien
	 * despliega. Es HTML de quien administra el código, así que no se escapa.
	 *
	 * @return array{head:string, brand:string, footer:string}
	 */
	public static function deployment_chrome(): array {
		$chrome = (array) apply_filters(
			'pse_chrome',
			array(
				'head'   => '',
				'brand'  => '',
				'footer' => '',
			)
		);
		return array(
			'head'   => (string) ( $chrome['head'] ?? '' ),
			'brand'  => (string) ( $chrome['brand'] ?? '' ),
			'footer' => (string) ( $chrome['footer'] ?? '' ),
		);
	}

	/**
	 * The whole HTML document around a screen.
	 *
	 * @param array{tab:string, title:string, subtitle?:string, actions?:string, body:string} $screen Screen.
	 * @return string
	 */
	public static function document( array $screen ): string {
		$chrome = self::deployment_chrome();
		$out    = '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
		$out   .= '<title>' . esc_html( $screen['title'] . ' · ' . self::APP_NAME ) . '</title>';
		// Bootstrap desde jsDelivr con SRI (ADR-0002).
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- documento propio, fuera del tema.
		$out .= '<link rel="stylesheet" href="' . esc_url( self::BOOTSTRAP['css'] ) . '" integrity="' . esc_attr( self::BOOTSTRAP['css_sri'] ) . '" crossorigin="anonymous">';
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- documento propio, fuera del tema.
		$out .= '<link rel="stylesheet" href="' . esc_url( self::BOOTSTRAP_ICONS['css'] ) . '" integrity="' . esc_attr( self::BOOTSTRAP_ICONS['css_sri'] ) . '" crossorigin="anonymous">';
		$bar  = self::admin_bar();
		$out .= '<style>' . Assets::css() . '</style>' . $bar['head'] . $chrome['head'] . '</head><body class="pse' . ( '' !== $bar['body'] ? ' admin-bar' : '' ) . '">' . $bar['body'];
		$out .= self::chrome( $screen['tab'], $chrome['brand'] );

		$out .= '<div class="pse-head"><div class="container d-flex flex-wrap align-items-start gap-3">';
		$out .= '<div class="me-auto"><h1 class="pse-h1">' . esc_html( $screen['title'] ) . '</h1>';
		if ( ! empty( $screen['subtitle'] ) ) {
			$out .= '<p class="pse-sub">' . esc_html( $screen['subtitle'] ) . '</p>';
		}
		$out .= '</div>' . ( $screen['actions'] ?? '' ) . '</div></div>';

		$out .= '<main class="container py-4">' . self::notice() . $screen['body'] . '</main>';
		// El nombre del aplicativo ya está arriba, en la cabecera: en el pie
		// solo va lo que pone quien despliega.
		$out .= '<footer class="pse-foot"><div class="container">' . $chrome['footer'] . '</div></footer>';
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- documento propio, fuera del tema.
		$out .= '<script src="' . esc_url( self::BOOTSTRAP['js'] ) . '" integrity="' . esc_attr( self::BOOTSTRAP['js_sri'] ) . '" crossorigin="anonymous"></script>';
		$out .= '<script>' . Assets::contents( 'js/pse-app.js' ) . '</script>';
		$out .= $bar['foot'] . '</body></html>';
		return $out;
	}

	/**
	 * The admin bar, printed by hand: this document skips `wp_head()` and
	 * `wp_footer()` so the theme stays out.
	 *
	 * WordPress ya la ha preparado en `template_redirect` (prioridad 0), antes
	 * que el aplicativo; aquí solo se pintan su hoja, el hueco de arriba y la
	 * propia barra con sus nodos —el «Cambiar a…» y el «Volver» de WPFront van
	 * dentro—.
	 *
	 * @return array{head:string, body:string, foot:string}
	 */
	private static function admin_bar(): array {
		global $wp_admin_bar;
		$none = array(
			'head' => '',
			'body' => '',
			'foot' => '',
		);
		if ( ! is_admin_bar_showing() || ! is_object( $wp_admin_bar ) ) {
			return $none;
		}
		ob_start();
		wp_print_styles( array( 'admin-bar' ) );
		$head = (string) ob_get_clean() . '<style>html{margin-top:32px!important}@media screen and (max-width:782px){html{margin-top:46px!important}}</style>';
		ob_start();
		wp_admin_bar_render();
		$body = (string) ob_get_clean();
		ob_start();
		wp_print_scripts( array( 'admin-bar' ) );
		return array(
			'head' => $head,
			'body' => $body,
			'foot' => (string) ob_get_clean(),
		);
	}

	/**
	 * Header and tabs of the application, as in the sibling apps.
	 *
	 * El menú de la cuenta es un `details` nativo: sin guion, y se cierra solo
	 * al navegar. Lo que dice de quién es el despliegue —logotipo, organización—
	 * llega en `$brand` por `pse_chrome` (ADR-0002).
	 *
	 * @param string $current Active tab.
	 * @param string $brand   Deployment HTML before the app name.
	 * @return string
	 */
	private static function chrome( string $current, string $brand = '' ): string {
		$user = wp_get_current_user();
		$name = (string) $user->display_name;
		$out  = '<header class="pse-top"><div class="container pse-top-in">' . $brand;
		$out .= '<a class="pse-brand" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( self::APP_NAME ) . '</a>';
		$out .= '<details class="pse-yo"><summary><span class="pse-avatar" aria-hidden="true">' . esc_html( strtoupper( mb_substr( $name, 0, 1 ) ) ) . '</span>';
		$out .= '<span class="pse-yo-txt"><strong>' . esc_html( $name ) . '</strong><small>' . esc_html( self::role_label( $user ) ) . '</small></span></summary><div class="pse-yo-menu">';
		if ( current_user_can( 'manage_options' ) ) {
			$out .= '<a href="' . esc_url( admin_url() ) . '">Escritorio</a>';
		}
		// Con WPFront, la administración prueba el aplicativo con el perfil de otro desde «Usuarios».
		if ( current_user_can( 'switch_users' ) ) {
			$out .= '<a href="' . esc_url( admin_url( 'users.php' ) ) . '">Ver como otro usuario</a>';
		}
		$out .= '<a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">Salir</a></div></details>';
		$out .= '</div></header>';

		$out .= '<nav class="pse-nav" aria-label="Secciones"><div class="container"><ul class="nav">';
		foreach ( self::tabs() as $key => $tab ) {
			$active = $key === $current;
			$out   .= '<li class="nav-item"><a class="nav-link' . ( $active ? ' active' : '' ) . '"' . ( $active ? ' aria-current="page"' : '' ) . ' href="' . esc_url( self::url( array( self::ARG_TAB => $key ) ) ) . '">' . esc_html( $tab ) . '</a></li>';
		}
		$out .= '</ul></div></nav>';
		return $out;
	}

	/**
	 * Label of the user's application role.
	 *
	 * @param \WP_User $user User.
	 * @return string
	 */
	public static function role_label( \WP_User $user ): string {
		$names = wp_roles()->role_names;
		foreach ( array( 'administrator', 'pse_coordinator', 'pse_psychologist', 'pse_school_head' ) as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return 'administrator' === $role ? 'Administración' : translate_user_role( $names[ $role ] ?? $role );
			}
		}
		return '';
	}

	/**
	 * The notice a redirect left behind.
	 *
	 * @return string
	 */
	public static function notice(): string {
		$texts = array(
			'saved'   => array( 'success', 'Guardado.' ),
			'denied'  => array( 'danger', 'No tiene permiso para hacer eso.' ),
			'subject' => array( 'warning', 'Elija un asunto de la lista.' ),
			'centre'  => array( 'warning', 'Su cuenta no tiene un centro asignado: no puede solicitar una intervención.' ),
		);
		$key   = sanitize_key( self::arg( self::ARG_NOTICE ) );
		return isset( $texts[ $key ] ) ? '<div class="alert alert-' . esc_attr( $texts[ $key ][0] ) . '" role="status">' . esc_html( $texts[ $key ][1] ) . '</div>' : '';
	}

	/**
	 * Leave to another screen and stop.
	 *
	 * @param string $url Destination.
	 * @return void
	 */
	public static function leave( string $url ): void {
		wp_safe_redirect( $url );
		exit;
	}
}
