<?php
/**
 * Tests for the request, its zones, its permissions and its notice.
 *
 * @package Pse
 */

use Pse\Access\Access;
use Pse\Centre\CentreCatalogue;
use Pse\Centre\Zones;
use Pse\Domain\Stats;
use Pse\Domain\Vocabulary;
use Pse\Notifications\Mailer;
use Pse\PostType\Requests;

/**
 * La solicitud: qué guarda, quién la crea, quién la ve y a quién se avisa.
 */
class Test_Requests extends WP_UnitTestCase {

	/**
	 * Roles, an invented catalogue and two zones.
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
				'90000006' => array(
					'code'         => '90000006',
					'name'         => 'IES Ejemplo Costa',
					'island'       => 'Isla 2',
					'municipality' => 'Municipio D',
					'active'       => true,
				),
			)
		);
		Zones::save(
			array(
				'90000001' => 'Zona 1',
				'90000006' => 'Zona 3',
			),
			array(
				'Zona 1' => array( 'uno@example.org' ),
				'Zona 3' => array( 'tres@example.org' ),
			)
		);
		delete_option( Requests::OPTION_SEQUENCE );
		// El sitio de los tests no tiene un dominio con el que escribir un remitente válido.
		add_filter( 'wp_mail_from', array( $this, 'mail_from' ) );
		reset_phpmailer_instance();
	}

	/**
	 * A valid sender for the test site.
	 *
	 * @return string
	 */
	public function mail_from(): string {
		return 'aviso@example.org';
	}

	/**
	 * A user with a role, an email and maybe a centre.
	 *
	 * @param string $role   Role.
	 * @param string $centre Centre code.
	 * @param string $email  Email.
	 * @return int
	 */
	private function user( string $role, string $centre = '', string $email = '' ): int {
		$args = array( 'role' => $role );
		if ( '' !== $email ) {
			$args['user_email'] = $email;
		}
		$id = self::factory()->user->create( $args );
		if ( '' !== $centre ) {
			update_user_meta( $id, 'pse_centre_code', $centre );
		}
		return $id;
	}

	/**
	 * Los asuntos son una lista cerrada que el despliegue puede cambiar; una
	 * lista vacía o rota no deja al centro sin poder elegir.
	 */
	public function test_subjects_are_a_closed_list_the_deployment_can_replace() {
		$this->assertArrayHasKey( 'bullying', Vocabulary::subjects() );
		$this->assertSame( 'Acoso o ciberacoso escolar', Vocabulary::subject_label( 'bullying' ) );
		$this->assertSame( 'ya-no-esta', Vocabulary::subject_label( 'ya-no-esta' ) );

		$filter = static fn() => array(
			'Uno Dos' => ' Asunto uno ',
			'vacio'   => '  ',
		);
		add_filter( 'pse_subjects', $filter );
		$this->assertSame( array( 'unodos' => 'Asunto uno' ), Vocabulary::subjects() );
		remove_filter( 'pse_subjects', $filter );

		add_filter( 'pse_subjects', '__return_empty_array' );
		$this->assertArrayHasKey( 'other', Vocabulary::subjects() );
		remove_filter( 'pse_subjects', '__return_empty_array' );
	}

	/**
	 * Tres estados, con su rótulo y su plural; uno desconocido se lee como pendiente.
	 */
	public function test_states() {
		$this->assertSame( array( 'pending', 'in_progress', 'attended' ), array_keys( Vocabulary::states() ) );
		$this->assertSame( 'En atención', Vocabulary::state_label( 'in_progress' ) );
		$this->assertSame( 'Pendiente', Vocabulary::state_label( 'otro' ) );
		$this->assertSame( 'Atendidas', Vocabulary::state_plural( 'attended' ) );
		$this->assertSame( '', Vocabulary::state_plural( 'otro' ) );
	}

	/**
	 * Las listas pegadas: se quedan las líneas válidas, con «;», coma o tabulador.
	 */
	public function test_zone_lists_are_parsed_and_bad_lines_dropped() {
		$centres = Zones::parse_centres( "90000001; Zona 1\n90000002,Zona 1\n90000003\tZona 2\n123; Zona 9\nsin separador\n90000004;\n" );
		$this->assertSame(
			array(
				'90000001' => 'Zona 1',
				'90000002' => 'Zona 1',
				'90000003' => 'Zona 2',
			),
			$centres
		);

		$staff = Zones::parse_staff( "Zona 1; Uno@Example.org\nZona 1; uno@example.org\nZona 1; dos@example.org\nZona 2; no-es-un-correo\n" );
		$this->assertSame( array( 'Zona 1' => array( 'uno@example.org', 'dos@example.org' ) ), $staff );
	}

	/**
	 * Las zonas guardadas responden por centro, por correo y como texto.
	 */
	public function test_zones_answer_by_centre_and_by_email() {
		$this->assertSame( 'Zona 1', Zones::of_centre( '90000001' ) );
		$this->assertSame( '', Zones::of_centre( '90000099' ) );
		$this->assertSame( array( 'Zona 1', 'Zona 3' ), Zones::all() );
		$this->assertSame( array( 'tres@example.org' ), Zones::emails( 'Zona 3' ) );
		$this->assertSame( array(), Zones::emails( 'Zona 9' ) );
		$this->assertSame( array( 'Zona 1' ), Zones::of_email( ' UNO@example.org ' ) );
		$this->assertSame( array(), Zones::of_email( '' ) );
		$this->assertSame( "90000001; Zona 1\n90000006; Zona 3", Zones::centres_text() );
		$this->assertSame( "Zona 1; uno@example.org\nZona 3; tres@example.org", Zones::staff_text() );

		update_option( Zones::OPTION_CENTRES, 'roto' );
		update_option( Zones::OPTION_STAFF, 'roto' );
		$this->assertSame( array(), Zones::all() );
	}

	/**
	 * Cada rol recibe sus capacidades, y el despliegue puede dárselas a otro.
	 */
	public function test_roles_get_their_capabilities() {
		$this->assertTrue( get_role( 'pse_school_head' )->has_cap( Access::CAP_REQUEST ) );
		$this->assertFalse( get_role( 'pse_school_head' )->has_cap( Access::CAP_OVERSEE ) );
		$this->assertTrue( get_role( 'pse_psychologist' )->has_cap( Access::CAP_ATTEND ) );
		$this->assertTrue( get_role( 'pse_coordinator' )->has_cap( Access::CAP_ZONES ) );
		$this->assertFalse( get_role( 'pse_coordinator' )->has_cap( Access::CAP_APP ) );

		$filter = static function ( $map ) {
			$map['editor']    = array( Access::CAP_REQUEST, 'manage_options' );
			$map['no-existe'] = array( Access::CAP_REQUEST );
			return $map;
		};
		add_filter( 'pse_role_caps', $filter );
		Access::grant_caps_to_roles();
		remove_filter( 'pse_role_caps', $filter );

		$this->assertTrue( get_role( 'editor' )->has_cap( Access::CAP_REQUEST ) );
		$this->assertFalse( get_role( 'editor' )->has_cap( 'manage_options' ), 'solo reparte las capacidades del aplicativo' );
		get_role( 'editor' )->remove_cap( Access::CAP_REQUEST );
	}

	/**
	 * El centro sale de la cuenta, de la meta que diga el despliegue, y tiene
	 * que ser un código de ocho cifras.
	 */
	public function test_the_centre_comes_from_the_account() {
		$head = $this->user( 'pse_school_head', '90000001' );
		$this->assertSame( '90000001', Access::centre_code( $head ) );
		$this->assertSame( '', Access::centre_code( $this->user( 'pse_school_head', 'abc' ) ) );

		update_user_meta( $head, 'codigo', '90000006' );
		$filter = static fn() => 'codigo';
		add_filter( 'pse_centre_code_meta_key', $filter );
		$this->assertSame( '90000006', Access::centre_code( $head ) );
		remove_filter( 'pse_centre_code_meta_key', $filter );

		wp_set_current_user( $head );
		$this->assertSame( '90000001', Access::centre_code() );
	}

	/**
	 * Crear una solicitud copia el centro, el municipio y la zona, la numera y
	 * la deja pendiente.
	 */
	public function test_a_request_is_created_with_the_data_of_its_centre() {
		$head = $this->user( 'pse_school_head', '90000001' );
		$id   = Requests::create( $head, 'bullying' );

		$this->assertIsInt( $id );
		$row = Requests::row( get_post( $id ) );
		$this->assertSame( gmdate( 'Y' ) . '-0001', $row['number'] );
		$this->assertSame( '90000001', $row['centre_code'] );
		$this->assertSame( 'IES Ejemplo Norte', $row['centre_name'] );
		$this->assertSame( 'Municipio A', $row['municipality'] );
		$this->assertSame( 'Isla 1', $row['island'] );
		$this->assertSame( 'Zona 1', $row['zone'] );
		$this->assertSame( 'bullying', $row['subject'] );
		$this->assertSame( 'pending', $row['status'] );
		$this->assertSame( $row['number'], get_the_title( $id ) );

		$this->assertSame( gmdate( 'Y' ) . '-0002', Requests::row( get_post( Requests::create( $head, 'other' ) ) )['number'] );
	}

	/**
	 * Sin permiso, sin centro o con un asunto que no es de la lista, no se crea.
	 */
	public function test_a_request_needs_permission_centre_and_subject() {
		$this->assertSame( 'pse_denied', Requests::create( $this->user( 'pse_psychologist' ), 'bullying' )->get_error_code() );
		$this->assertSame( 'pse_no_centre', Requests::create( $this->user( 'pse_school_head' ), 'bullying' )->get_error_code() );
		$this->assertSame( 'pse_subject', Requests::create( $this->user( 'pse_school_head', '90000001' ), 'inventado' )->get_error_code() );
		$this->assertSame( 0, Requests::query()->found_posts );
	}

	/**
	 * Cada uno ve lo suyo: el centro, las de su centro; psicología, las de su
	 * zona; la coordinación, todas. Y solo quien atiende cambia el estado.
	 */
	public function test_who_sees_and_who_changes_a_request() {
		$head   = $this->user( 'pse_school_head', '90000001' );
		$other  = $this->user( 'pse_school_head', '90000006' );
		$psy    = $this->user( 'pse_psychologist', '', 'uno@example.org' );
		$psy3   = $this->user( 'pse_psychologist', '', 'tres@example.org' );
		$coord  = $this->user( 'pse_coordinator' );
		$admin  = $this->user( 'administrator' );
		$nobody = $this->user( 'subscriber' );
		$post   = get_post( Requests::create( $head, 'distress' ) );

		$this->assertTrue( Access::can_view( $post, $head ) );
		$this->assertFalse( Access::can_view( $post, $other ) );
		$this->assertTrue( Access::can_view( $post, $psy ) );
		$this->assertFalse( Access::can_view( $post, $psy3 ) );
		$this->assertTrue( Access::can_view( $post, $coord ) );
		$this->assertFalse( Access::can_view( $post, $nobody ) );
		$this->assertFalse( Access::can_view( get_post( self::factory()->post->create() ), $coord ), 'solo solicitudes' );

		$this->assertTrue( Access::can_set_status( $post, $psy ) );
		$this->assertTrue( Access::can_set_status( $post, $admin ) );
		$this->assertFalse( Access::can_set_status( $post, $psy3 ) );
		$this->assertFalse( Access::can_set_status( $post, $coord ), 'la coordinación sigue, no atiende' );
		$this->assertFalse( Access::can_set_status( $post, $head ) );
		wp_set_current_user( 0 );
		$this->assertFalse( Access::can_view( $post ) );
		$this->assertFalse( Access::can_set_status( $post ) );

		$this->assertFalse( Requests::set_status( $post->ID, 'attended', $coord ) );
		$this->assertFalse( Requests::set_status( $post->ID, 'inventado', $psy ) );
		$this->assertFalse( Requests::set_status( 0, 'attended', $psy ) );
		$this->assertTrue( Requests::set_status( $post->ID, 'attended', $psy ) );
		$this->assertSame( 'attended', Requests::row( get_post( $post->ID ) )['status'] );
	}

	/**
	 * La consulta: el alcance —centro o zonas— y los filtros del listado.
	 */
	public function test_requests_are_queried_by_scope_and_filters() {
		$north = $this->user( 'pse_school_head', '90000001' );
		$coast = $this->user( 'pse_school_head', '90000006' );
		$a     = Requests::create( $north, 'bullying' );
		$b     = Requests::create( $north, 'distress' );
		$c     = Requests::create( $coast, 'bullying' );
		update_post_meta( $b, Requests::STATUS, 'attended' );

		$ids = static fn( array $args ) => wp_list_pluck( Requests::query( $args )->posts, 'ID' );
		$this->assertEqualsCanonicalizing( array( $a, $b, $c ), $ids( array() ) );
		$this->assertEqualsCanonicalizing( array( $a, $b ), $ids( array( 'centre' => '90000001' ) ) );
		$this->assertSame( array(), $ids( array( 'centre' => '' ) ), 'sin centro no se ve nada' );
		$this->assertSame( array( $c ), $ids( array( 'zones' => array( 'Zona 3' ) ) ) );
		$this->assertSame( array(), $ids( array( 'zones' => array() ) ), 'sin zonas no se ve nada' );
		$this->assertSame( array( $b ), $ids( array( 'status' => 'attended' ) ) );
		$this->assertEqualsCanonicalizing( array( $a, $c ), $ids( array( 'subject' => 'bullying' ) ) );
		$this->assertSame( array( $c ), $ids( array( 'municipality' => 'Municipio D' ) ) );
		$this->assertSame( array( $c ), $ids( array( 'zone' => 'Zona 3' ) ) );
		$this->assertSame( array( $c ), $ids( array( 'search' => 'Costa' ) ) );
		$this->assertSame( array( $c ), $ids( array( 'search' => '90000006' ) ) );
		$this->assertSame( array( $b ), $ids( array( 'search' => '-0002' ) ) );
		$this->assertCount( 3, Requests::rows() );
	}

	/**
	 * La estadística cuenta por cada dimensión, y lo que no tiene valor va a «—».
	 */
	public function test_stats_count_by_each_dimension() {
		$north = $this->user( 'pse_school_head', '90000001' );
		Requests::create( $north, 'bullying' );
		Requests::create( $north, 'bullying' );
		$c = Requests::create( $this->user( 'pse_school_head', '90000006' ), 'distress' );
		update_post_meta( $c, Requests::STATUS, 'attended' );
		update_post_meta( $c, Requests::ZONE, '' );

		$stats = Stats::of( Requests::rows() );
		$this->assertSame( 3, $stats['total'] );
		$this->assertSame(
			array(
				'pending'     => 2,
				'in_progress' => 0,
				'attended'    => 1,
			),
			$stats['status']
		);
		$this->assertSame(
			array(
				'bullying' => 2,
				'distress' => 1,
			),
			$stats['subject']
		);
		$this->assertSame(
			array(
				'Zona 1' => 2,
				'—'      => 1,
			),
			$stats['zone']
		);
		$this->assertSame( array( gmdate( 'Y-m' ) => 3 ), $stats['month'] );
		$this->assertSame( 0, Stats::of( array() )['total'] );
	}

	/**
	 * El aviso va a quien atiende la zona y no dice el asunto.
	 */
	public function test_the_notice_goes_to_the_zone_without_the_subject() {
		$id   = Requests::create( $this->user( 'pse_school_head', '90000001' ), 'suicidal_behaviour' );
		$sent = tests_retrieve_phpmailer_instance()->get_sent();

		$this->assertNotFalse( $sent, 'crear la solicitud envía el aviso' );
		$this->assertSame( 'uno@example.org', $sent->to[0][0] );
		$this->assertStringContainsString( gmdate( 'Y' ) . '-0001', $sent->subject );
		$this->assertStringContainsString( 'IES Ejemplo Norte (90000001)', $sent->body );
		$this->assertStringNotContainsString( 'suicida', $sent->body );
		$this->assertStringNotContainsString( 'suicida', $sent->subject );

		$this->assertTrue( Mailer::notify( $id ) );
		$this->assertNull( Mailer::message( 0 ) );
		$this->assertNull( Mailer::message( self::factory()->post->create() ) );
	}

	/**
	 * Una zona sin nadie no envía nada.
	 */
	public function test_a_zone_without_staff_sends_nothing() {
		Zones::save( array( '90000001' => 'Zona 1' ), array() );
		reset_phpmailer_instance();

		$id = Requests::create( $this->user( 'pse_school_head', '90000001' ), 'other' );

		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
		$this->assertFalse( Mailer::notify( $id ) );
	}
}
