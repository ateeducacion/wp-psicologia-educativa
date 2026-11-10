<?php
/**
 * Tests for the front page: routing, screens and forms.
 *
 * @package Pse
 */

use Pse\Access\Access;
use Pse\Centre\CentreCatalogue;
use Pse\Centre\Zones;
use Pse\PostType\Requests;
use Pse\PublicFront\Actions;
use Pse\PublicFront\Screen;
use Pse\PublicFront\Views;

/**
 * La portada es el aplicativo: qué pestañas ve cada perfil, qué pinta cada
 * pantalla y qué hace cada formulario.
 */
class Test_Screens extends WP_UnitTestCase {

	/**
	 * Roles, an invented catalogue, two zones and a clean request.
	 */
	public function set_up() {
		parent::set_up();
		pse_register_roles();
		Access::grant_caps_to_roles();
		update_option(
			CentreCatalogue::OPTION_CATALOGUE,
			array(
				'90000001' => array(
					'code'         => '90000001',
					'name'         => 'IES Ejemplo Norte',
					'island'       => 'Isla 1',
					'municipality' => 'Municipio A',
					'active'       => true,
				),
			)
		);
		Zones::save( array( '90000001' => 'Zona 1' ), array( 'Zona 1' => array( 'uno@example.org' ) ) );
		delete_option( Requests::OPTION_SEQUENCE );
		$_GET  = array();
		$_POST = array();
		$this->go_to( home_url( '/' ) );
	}

	/**
	 * Leave the superglobals as they were.
	 */
	public function tear_down() {
		$_GET  = array();
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Log in as a role.
	 *
	 * @param string $role   Role.
	 * @param string $centre Centre code.
	 * @param string $email  Email.
	 * @return int
	 */
	private function as_role( string $role, string $centre = '', string $email = '' ): int {
		$args = array( 'role' => $role );
		if ( '' !== $email ) {
			$args['user_email'] = $email;
		}
		$id = self::factory()->user->create( $args );
		if ( '' !== $centre ) {
			update_user_meta( $id, 'pse_centre_code', $centre );
		}
		wp_set_current_user( $id );
		return $id;
	}

	/**
	 * Route the front page and say how it ended.
	 *
	 * @return string `redirect:URL`, `document:TITLE`, `csv` or `returned`.
	 */
	private function route(): string {
		$redirect = static function ( $location ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- lo lee el test, no se pinta.
			throw new RuntimeException( 'redirect:' . $location );
		};
		$document = static function ( $safe ) {
			$tail = ' · ' . Screen::APP_NAME;
			if ( str_ends_with( (string) $safe, $tail ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- lo lee el test, no se pinta.
				throw new RuntimeException( 'document:' . substr( $safe, 0, -strlen( $tail ) ) );
			}
			return $safe;
		};
		add_filter( 'wp_redirect', $redirect );
		add_filter( 'esc_html', $document );
		try {
			Screen::route();
			$stop = 'returned';
		} catch ( PHPUnit\Framework\Error\Warning $e ) {
			// En los tests la salida ya ha empezado: el primer `header()` de la
			// descarga en CSV avisa, y es justo ahí donde se para.
			$stop = str_starts_with( $e->getMessage(), 'Cannot modify header information' ) ? 'csv' : 'warning:' . $e->getMessage();
		} catch ( RuntimeException $e ) {
			$stop = $e->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $redirect );
			remove_filter( 'esc_html', $document );
		}
		return $stop;
	}

	/**
	 * Post a form of the application.
	 *
	 * @param string                $action Action.
	 * @param array<string, string> $fields Fields.
	 * @return string How the route ended.
	 */
	private function post( string $action, array $fields = array() ): string {
		$_POST = $fields + array(
			Actions::FIELD => $action,
			Actions::NONCE => wp_create_nonce( 'pse_' . $action ),
		);
		return $this->route();
	}

	/**
	 * Cada perfil ve sus pestañas; una dirección sin centro, ninguna.
	 */
	public function test_each_profile_sees_its_tabs() {
		$this->as_role( 'pse_school_head', '90000001' );
		$this->assertSame( array( 'new', 'centre' ), array_keys( Screen::tabs() ) );

		$this->as_role( 'pse_school_head' );
		$this->assertSame( array(), Screen::tabs() );

		$this->as_role( 'pse_psychologist' );
		$this->assertSame( array( 'zone' ), array_keys( Screen::tabs() ) );

		$this->as_role( 'pse_coordinator' );
		$this->assertSame( array( 'all', 'stats', 'zones' ), array_keys( Screen::tabs() ) );

		$this->as_role( 'administrator' );
		$this->assertSame( array( 'zone', 'all', 'stats', 'zones' ), array_keys( Screen::tabs() ) );
	}

	/**
	 * Sin sesión, al acceso; con sesión, la primera pestaña del perfil; y una
	 * pestaña ajena cae en la propia.
	 */
	public function test_the_front_page_routes_by_session_and_profile() {
		wp_set_current_user( 0 );
		$this->assertStringStartsWith( 'redirect:' . wp_login_url(), $this->route() );

		$this->as_role( 'pse_school_head', '90000001' );
		$this->assertSame( 'document:Solicitar intervención', $this->route() );

		$_GET[ Screen::ARG_TAB ] = 'stats';
		$this->assertSame( 'document:Solicitar intervención', $this->route(), 'la estadística no es de la dirección' );

		$this->as_role( 'pse_coordinator' );
		$this->assertSame( 'document:Estadísticas', $this->route() );

		$this->as_role( 'subscriber' );
		$this->assertSame( 'document:Sin acceso', $this->route() );

		$this->go_to( home_url( '/?p=' . self::factory()->post->create() ) );
		$this->assertSame( 'returned', $this->route(), 'solo la portada' );
	}

	/**
	 * Tras entrar sin destino, a la portada; con destino, a él.
	 */
	public function test_login_lands_on_the_application() {
		$this->assertSame( home_url( '/' ), Screen::login_redirect( admin_url(), '' ) );
		$this->assertSame( home_url( '/' ), Screen::login_redirect( admin_url(), admin_url() ) );
		$this->assertSame( 'https://example.org/x', Screen::login_redirect( 'https://example.org/x', 'https://example.org/x' ) );
	}

	/**
	 * La barra de administración, solo para la administración.
	 */
	public function test_the_admin_bar_is_for_the_administration() {
		wp_set_current_user( 0 );
		$this->assertFalse( Screen::show_admin_bar( true ) );
		$this->as_role( 'pse_coordinator' );
		$this->assertFalse( Screen::show_admin_bar( true ) );
		$this->as_role( 'administrator' );
		$this->assertTrue( Screen::show_admin_bar( false ) );
	}

	/**
	 * El documento lleva la cabecera, las pestañas, lo que pone el despliegue
	 * y el aviso que dejó la redirección.
	 */
	public function test_the_document_wraps_a_screen() {
		$this->as_role( 'pse_coordinator' );
		$chrome                     = static fn() => array(
			'head'   => '<meta name="x-prueba" content="1">',
			'brand'  => '<span class="marca">Organización de ejemplo</span>',
			'footer' => '<span class="pie">Pie de ejemplo</span>',
		);
		add_filter( 'pse_chrome', $chrome );
		$_GET[ Screen::ARG_NOTICE ] = 'saved';
		$html                       = Screen::document( Views::screen( 'stats' ) );
		remove_filter( 'pse_chrome', $chrome );

		$this->assertStringStartsWith( '<!doctype html><html lang="es">', $html );
		$this->assertStringContainsString( '<title>Estadísticas · Solicitudes de intervención</title>', $html );
		$this->assertStringContainsString( '<meta name="x-prueba" content="1">', $html );
		$this->assertStringContainsString( 'Organización de ejemplo', $html );
		$this->assertStringContainsString( 'Pie de ejemplo', $html );
		$this->assertStringContainsString( 'aria-current="page"', $html );
		$this->assertStringContainsString( 'Coordinación', $html );
		$this->assertStringContainsString( 'role="status">Guardado.</div>', $html );
		$this->assertStringContainsString( 'integrity="sha384-', $html );
		$this->assertStringNotContainsString( 'Escritorio', $html, 'el escritorio es de la administración' );

		$this->as_role( 'administrator' );
		$this->assertStringContainsString( 'Escritorio', Screen::document( Views::screen( 'stats' ) ) );
		$this->assertSame( 'Administración', Screen::role_label( wp_get_current_user() ) );
		$this->assertSame( '', Screen::role_label( new WP_User( self::factory()->user->create( array( 'role' => 'subscriber' ) ) ) ) );
	}

	/**
	 * El formulario de solicitud: el centro de la cuenta, de solo lectura, la
	 * lista de asuntos y ningún campo de texto libre.
	 */
	public function test_the_request_form_only_asks_for_the_subject() {
		$this->as_role( 'pse_school_head', '90000001' );
		$body = Views::screen( 'new' )['body'];

		$this->assertStringContainsString( 'value="IES Ejemplo Norte · 90000001" readonly', $body );
		$this->assertStringContainsString( '<option value="bullying">Acoso o ciberacoso escolar</option>', $body );
		$this->assertStringContainsString( 'No escriba datos del alumnado.', $body );
		$this->assertStringNotContainsString( '<textarea', $body );
		$this->assertStringNotContainsString( 'type="text"', $body );
		$this->assertStringContainsString( 'name="' . Actions::NONCE . '"', $body );
	}

	/**
	 * Solicitar: crea la solicitud y vuelve al listado del centro, que la anuncia.
	 */
	public function test_asking_creates_the_request_and_shows_it() {
		$this->as_role( 'pse_school_head', '90000001' );

		$stop = $this->post( 'create', array( 'pse_subject' => 'bullying' ) );

		$this->assertSame( 1, Requests::query()->found_posts );
		$id = Requests::query()->posts[0]->ID;
		$this->assertSame( 'redirect:' . Screen::url( array( Screen::ARG_TAB => 'centre' ) ) . '&pse_created=' . $id, $stop );

		$_POST = array();
		$_GET  = array(
			Screen::ARG_TAB => 'centre',
			'pse_created'   => (string) $id,
		);
		$body  = Views::screen( 'centre' )['body'];
		$this->assertStringContainsString( 'Solicitud n.º ' . gmdate( 'Y' ) . '-0001 registrada.', $body );
		$this->assertStringContainsString( 'Se ha avisado por correo', $body );
		$this->assertStringContainsString( 'Acoso o ciberacoso escolar', $body );
		$this->assertStringContainsString( 'pse-state-pending', $body );
		$this->assertStringNotContainsString( 'Cambiar estado', $body );
	}

	/**
	 * Un asunto que no es de la lista, o un formulario sin su nonce, no crea nada.
	 */
	public function test_a_bad_form_creates_nothing() {
		$this->as_role( 'pse_school_head', '90000001' );

		$this->assertStringContainsString( 'pse_notice=subject', $this->post( 'create', array( 'pse_subject' => 'inventado' ) ) );
		$_POST = array(
			Actions::FIELD => 'create',
			Actions::NONCE => 'malo',
			'pse_subject'  => 'bullying',
		);
		$this->assertStringContainsString( 'pse_notice=denied', $this->route() );
		$this->assertStringContainsString( 'pse_notice=denied', $this->post( 'no-existe' ) );

		$this->as_role( 'pse_psychologist' );
		$this->assertStringContainsString( 'pse_notice=denied', $this->post( 'create', array( 'pse_subject' => 'bullying' ) ) );
		$this->as_role( 'pse_school_head' );
		update_user_meta( get_current_user_id(), 'pse_centre_code', '' );
		$this->assertStringContainsString( 'pse_notice=centre', $this->post( 'create', array( 'pse_subject' => 'bullying' ) ) );

		$this->assertSame( 0, Requests::query()->found_posts );
	}

	/**
	 * Psicología ve las de su zona, con el formulario de estado, y lo cambia.
	 */
	public function test_psychology_sees_its_zone_and_changes_the_state() {
		$head = $this->as_role( 'pse_school_head', '90000001' );
		$id   = Requests::create( $head, 'distress' );

		$this->as_role( 'pse_psychologist', '', 'uno@example.org' );
		$screen = Views::screen( 'zone' );
		$this->assertStringContainsString( 'Zona 1.', $screen['subtitle'] );
		$this->assertStringContainsString( 'IES Ejemplo Norte', $screen['body'] );
		$this->assertStringContainsString( 'name="pse_status" data-pse-autosubmit', $screen['body'] );
		$this->assertStringContainsString( 'Pendientes <b>1</b>', $screen['body'] );

		$stop = $this->post(
			'status',
			array(
				'pse_id'     => (string) $id,
				'pse_status' => 'in_progress',
				'pse_back'   => 'pending',
			)
		);
		$this->assertStringContainsString( 'pse_tab=zone', $stop );
		$this->assertStringContainsString( 'pse_f_status=pending', $stop );
		$this->assertStringContainsString( 'pse_notice=saved', $stop );
		$this->assertSame( 'in_progress', get_post_meta( $id, Requests::STATUS, true ) );

		// Otra zona: ni la ve ni la cambia.
		$this->as_role( 'pse_psychologist', '', 'otra@example.org' );
		$_POST  = array();
		$screen = Views::screen( 'zone' );
		$this->assertStringContainsString( 'Su correo no figura en ninguna zona', $screen['body'] );
		$this->assertStringNotContainsString( 'IES Ejemplo Norte', $screen['body'] );
		$this->assertStringContainsString(
			'pse_notice=denied',
			$this->post(
				'status',
				array(
					'pse_id'     => (string) $id,
					'pse_status' => 'attended',
				)
			)
		);
		$this->assertSame( 'in_progress', get_post_meta( $id, Requests::STATUS, true ) );
	}

	/**
	 * La coordinación ve todas, filtra, pagina y exporta, pero no cambia estados.
	 */
	public function test_the_coordination_lists_filters_and_exports() {
		$head = $this->as_role( 'pse_school_head', '90000001' );
		for ( $i = 0; $i < Requests::PER_PAGE + 1; $i++ ) {
			Requests::create( $head, 0 === $i ? 'bullying' : 'distress' );
		}

		$this->as_role( 'pse_coordinator' );
		$body = Views::screen( 'all' )['body'];
		$this->assertStringContainsString( 'Todas <b>26</b>', $body );
		$this->assertStringContainsString( 'Página 1 de 2', $body );
		$this->assertStringContainsString( 'Siguiente', $body );
		$this->assertStringContainsString( '<option value="Municipio A">Municipio A</option>', $body );
		$this->assertStringContainsString( 'pse_csv=1', $body );
		$this->assertStringNotContainsString( 'name="pse_status"', $body );

		$_GET = array(
			Screen::ARG_TAB => 'all',
			'pse_p'         => '2',
		);
		$this->assertStringContainsString( 'Anterior', Views::screen( 'all' )['body'] );

		$_GET = array(
			Screen::ARG_TAB      => 'all',
			'pse_f_subject'      => 'bullying',
			'pse_f_noexiste'     => 'x',
			'pse_f_search'       => array( 'no', 'escalar' ),
			'pse_f_zone'         => 'Zona 1',
			'pse_f_status'       => 'pending',
			'pse_f_municipality' => 'Municipio A',
		);
		$body = Views::screen( 'all' )['body'];
		$this->assertStringContainsString( 'Pendientes <b>1</b>', $body );
		$this->assertSame( 1, substr_count( $body, '<tr><td>' ) );
		$this->assertStringContainsString( '<option value="bullying" selected=\'selected\'>', $body );

		$_GET[ Screen::ARG_CSV ] = '1';
		$this->assertSame( 'csv', $this->route() );

		$_GET = array( 'pse_f_status' => 'attended' );
		$this->assertStringContainsString( 'No hay solicitudes que mostrar.', Views::screen( 'all' )['body'] );
	}

	/**
	 * El CSV: cabecera, una línea por solicitud, y nada que una hoja de cálculo ejecute.
	 */
	public function test_the_csv_is_quoted_and_safe() {
		$csv   = Views::csv(
			array(
				array(
					'number'       => '2026-0001',
					'date'         => '2026-11-10',
					'centre_code'  => '90000001',
					'centre_name'  => '=HIPERVINCULO("x")',
					'municipality' => 'Municipio "A"',
					'island'       => 'Isla 1',
					'zone'         => 'Zona 1',
					'subject'      => 'bullying',
					'status'       => 'attended',
				),
			)
		);
		$lines = explode( "\r\n", trim( $csv ) );

		$this->assertCount( 2, $lines );
		$this->assertStringStartsWith( '"Número";"Fecha";"Código de centro"', $lines[0] );
		$this->assertStringContainsString( '"\'=HIPERVINCULO(""x"")"', $lines[1] );
		$this->assertStringContainsString( '"Municipio ""A"""', $lines[1] );
		$this->assertStringContainsString( '"Acoso o ciberacoso escolar";"Atendida"', $lines[1] );

		$this->as_role( 'pse_school_head', '90000001' );
		ob_start();
		Views::send_csv();
		$this->assertSame( '', ob_get_clean(), 'sin permiso no se exporta nada' );
	}

	/**
	 * La estadística pinta las cifras y las barras; vacía, lo dice.
	 */
	public function test_the_statistics_screen() {
		$this->as_role( 'pse_coordinator' );
		$this->assertStringContainsString( 'Sin datos todavía.', Views::screen( 'stats' )['body'] );

		Requests::create( $this->as_role( 'pse_school_head', '90000001' ), 'bullying' );
		$this->as_role( 'pse_coordinator' );
		$body = Views::screen( 'stats' )['body'];
		$this->assertStringContainsString( '<b>1</b><span>Solicitudes</span>', $body );
		$this->assertStringContainsString( '<dt>Acoso o ciberacoso escolar</dt>', $body );
		$this->assertStringContainsString( '<dt>Municipio A</dt>', $body );
		$this->assertStringContainsString( 'style="width:100%"', $body );
		$this->assertStringContainsString( 'no alumnado', $body );
	}

	/**
	 * La coordinación pega las dos listas de zonas; otro perfil, no.
	 */
	public function test_the_coordination_saves_the_zones() {
		$this->as_role( 'pse_coordinator' );
		$body = Views::screen( 'zones' )['body'];
		$this->assertStringContainsString( '>90000001; Zona 1</textarea>', $body );
		$this->assertStringContainsString( '>Zona 1; uno@example.org</textarea>', $body );

		$fields = array(
			'pse_centres' => "90000001; Zona Norte\n90000002; Zona Sur",
			'pse_staff'   => 'Zona Norte; norte@example.org',
		);
		$this->assertStringContainsString( 'pse_notice=saved', $this->post( 'zones', $fields ) );
		$this->assertSame( 'Zona Sur', Zones::of_centre( '90000002' ) );
		$this->assertSame( array( 'norte@example.org' ), Zones::emails( 'Zona Norte' ) );

		$this->as_role( 'pse_psychologist' );
		$this->assertStringContainsString( 'pse_notice=denied', $this->post( 'zones', array( 'pse_centres' => '90000003; Otra' ) ) );
		$this->assertSame( '', Zones::of_centre( '90000003' ) );
	}

	/**
	 * Una solicitud de una zona sin nadie se registra, y la pantalla no promete un aviso.
	 */
	public function test_a_request_without_staff_does_not_promise_a_notice() {
		Zones::save( array(), array() );
		$id   = Requests::create( $this->as_role( 'pse_school_head', '90000001' ), 'other' );
		$_GET = array( 'pse_created' => (string) $id );

		$body = Views::screen( 'centre' )['body'];

		$this->assertStringContainsString( 'Queda registrada y a la vista de la coordinación.', $body );
		$this->assertStringNotContainsString( 'Se ha avisado', $body );
	}
}
