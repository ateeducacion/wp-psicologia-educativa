<?php
/**
 * Tests for the product roles snippet.
 *
 * @package Pse
 */

/**
 * El snippet suelto de roles.
 *
 * Es aditivo a propósito: crea lo que falta y no quita nada, para que lo que se
 * conceda a mano en el editor de roles siga ahí en la siguiente carga.
 */
class Test_Roles_And_Profiles extends WP_UnitTestCase {

	/**
	 * Los tres roles del contrato, en este orden.
	 */
	public function test_role_slugs_are_the_three_of_the_contract() {
		$this->assertSame( array( 'pse_school_head', 'pse_psychologist', 'pse_coordinator' ), pse_role_slugs() );
	}

	/**
	 * El registro crea los roles, y administrar el aplicativo es solo de administración.
	 */
	public function test_register_roles_creates_the_roles() {
		pse_register_roles();

		foreach ( pse_role_slugs() as $slug ) {
			$role = get_role( $slug );
			$this->assertNotNull( $role, $slug );
			$this->assertTrue( $role->has_cap( 'read' ), $slug );
			$this->assertFalse( $role->has_cap( 'pse_manage_app' ), $slug );
		}
		$this->assertTrue( get_role( 'administrator' )->has_cap( 'pse_manage_app' ) );
		$this->assertSame( array(), pse_roles_status() );
	}

	/**
	 * Es idempotente y no quita lo que se concedió a mano.
	 */
	public function test_register_roles_is_additive() {
		pse_register_roles();
		get_role( 'pse_psychologist' )->add_cap( 'moderate_comments' );

		pse_register_roles();

		$this->assertTrue( get_role( 'pse_psychologist' )->has_cap( 'moderate_comments' ) );
	}

	/**
	 * Si el código cambia el rótulo de un rol, el rol ya creado lo coge.
	 */
	public function test_a_renamed_role_takes_its_new_label() {
		pse_register_roles();
		$roles                                   = wp_roles();
		$roles->roles['pse_coordinator']['name'] = 'Coordinación de antes';
		$roles->role_names['pse_coordinator']    = 'Coordinación de antes';
		update_option( $roles->role_key, $roles->roles );

		pse_register_roles();

		$this->assertSame( 'Coordinación', wp_roles()->role_names['pse_coordinator'] );
		$this->assertSame( 'Coordinación', get_option( wp_roles()->role_key )['pse_coordinator']['name'], 'guardado, no solo en memoria' );
	}

	/**
	 * El despliegue pone el rótulo de cada perfil, y nada más (ADR-0002).
	 */
	public function test_the_deployment_renames_a_role_without_touching_its_caps() {
		$filter = static function ( $labels ) {
			$labels['pse_coordinator']  = 'Coordinación del área';
			$labels['pse_psychologist'] = '  ';
			return $labels;
		};
		add_filter( 'pse_role_labels', $filter );

		$definitions = pse_role_definitions();
		pse_register_roles();

		$this->assertSame( 'Coordinación del área', $definitions['pse_coordinator']['label'] );
		$this->assertSame( 'Coordinación del área', wp_roles()->role_names['pse_coordinator'] );
		$this->assertSame( 'Psicología educativa', $definitions['pse_psychologist']['label'], 'un rótulo en blanco no pisa el del código' );
		$this->assertSame( array( 'read' ), $definitions['pse_coordinator']['caps'], 'el filtro no reparte capacidades' );

		remove_filter( 'pse_role_labels', $filter );
	}

	/**
	 * Una capacidad prohibida concedida a mano se avisa, no se quita.
	 */
	public function test_a_forbidden_cap_granted_by_hand_is_reported() {
		pse_register_roles();
		get_role( 'pse_coordinator' )->add_cap( 'unfiltered_html' );

		$this->assertSame( array( 'pse_coordinator' => array( 'unfiltered_html' ) ), pse_roles_status() );

		get_role( 'pse_coordinator' )->remove_cap( 'unfiltered_html' );
	}
}
