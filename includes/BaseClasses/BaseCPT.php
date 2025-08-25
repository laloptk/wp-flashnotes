<?php

namespace WPFlashNotes\BaseClasses;

abstract class BaseCPT {
	protected string $type;
	protected string $singular_label;
	protected string $plural_label;
	protected string $textdomain = 'wp-flashnotes';

	public function __construct() {
		$this->type           = $this->set_type();
		$this->singular_label = $this->set_singular();
		$this->plural_label   = $this->set_plural();
	}

	/** Llamar en 'init' */
	public function register(): void {
		$base_args = array(
			'labels'        => $this->labels(),
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'has_archive'   => false,
			'hierarchical'  => false,
			'menu_position' => 25,
			'supports'      => array( 'title', 'revisions' ),
			'map_meta_cap'  => true,
			'capabilities'  => $this->capabilities(),
			'show_in_rest'  => true,
			'rest_base'     => $this->type,
			'rewrite'       => array(
				'slug'       => $this->type,
				'with_front' => false,
			),
		);

		$final_args = array_replace_recursive( $base_args, $this->args() );
		register_post_type( $this->type, $final_args );
	}

	abstract protected function set_type(): string;
	abstract protected function set_singular(): string;
	abstract protected function set_plural(): string;

	/** Ajustes específicos del CPT concreto */
	protected function args(): array {
		return array();
	}

	/** Capacidades por defecto en base al $type */
	protected function capabilities(): array {
		$singular_key = $this->type;
		$plural_key   = $this->type . 's';

		return array(
			'edit_post'              => "edit_{$singular_key}",
			'read_post'              => "read_{$singular_key}",
			'delete_post'            => "delete_{$singular_key}",

			'edit_posts'             => "edit_{$plural_key}",
			'edit_others_posts'      => "edit_others_{$plural_key}",
			'publish_posts'          => "publish_{$plural_key}",
			'read_private_posts'     => "read_private_{$plural_key}",

			'delete_posts'           => "delete_{$plural_key}",
			'delete_private_posts'   => "delete_private_{$plural_key}",
			'delete_published_posts' => "delete_published_{$plural_key}",
			'delete_others_posts'    => "delete_others_{$plural_key}",

			'edit_private_posts'     => "edit_private_{$plural_key}",
			'edit_published_posts'   => "edit_published_{$plural_key}",
		);
	}

	/** Labels genéricos traducibles */
	protected function labels(): array {
		$singular = $this->singular_label;
		$plural   = $this->plural_label;

		return array(
			'name'               => _x( $plural, 'Post type general name', $this->textdomain ),
			'singular_name'      => _x( $singular, 'Post type singular name', $this->textdomain ),
			'menu_name'          => $plural,
			'name_admin_bar'     => $singular,
			'add_new'            => __( 'Add New', $this->textdomain ),
			'add_new_item'       => sprintf( __( 'Add New %s', $this->textdomain ), $singular ),
			'new_item'           => sprintf( __( 'New %s', $this->textdomain ), $singular ),
			'edit_item'          => sprintf( __( 'Edit %s', $this->textdomain ), $singular ),
			'view_item'          => sprintf( __( 'View %s', $this->textdomain ), $singular ),
			'all_items'          => sprintf( __( 'All %s', $this->textdomain ), $plural ),
			'search_items'       => sprintf( __( 'Search %s', $this->textdomain ), $plural ),
			'not_found'          => __( 'Not found', $this->textdomain ),
			'not_found_in_trash' => __( 'Not found in Trash', $this->textdomain ),
		);
	}

	/** Sembrar capacidades en un rol (usar en activation hook) */
	public function seedCapabilitiesToRole( string $role_name = 'administrator' ): void {
		$role_object = get_role( $role_name );
		if ( ! $role_object ) {
			return;
		}
		foreach ( $this->capabilities() as $capability ) {
			$role_object->add_cap( $capability );
		}
	}
}
