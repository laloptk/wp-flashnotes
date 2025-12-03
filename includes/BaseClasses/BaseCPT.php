<?php

namespace WPFlashNotes\BaseClasses;

/**
 * BaseCPT
 *
 * Abstract base class for registering custom post types with
 * reusable capability and labels logic.
 */
abstract class BaseCPT {

	/**
	 * CPT slug (non-translated).
	 *
	 * @var string
	 */
	protected string $type;

	/**
	 * Singular label (already translated in concrete class).
	 *
	 * @var string
	 */
	protected string $singular_label;

	/**
	 * Plural label (already translated in concrete class).
	 *
	 * @var string
	 */
	protected string $plural_label;

	/**
	 * BaseCPT constructor.
	 *
	 * Concrete classes must implement set_type(), set_singular(), set_plural()
	 * and should call register() on 'init'.
	 */
	public function __construct() {
		$this->type           = $this->set_type();
		$this->singular_label = $this->set_singular();
		$this->plural_label   = $this->set_plural();
	}

	/**
	 * Register the post type.
	 *
	 * Should be called on the 'init' hook.
	 */
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

	/**
	 * Return the CPT slug (non-translated).
	 *
	 * Example: 'studyset'.
	 *
	 * @return string
	 */
	abstract protected function set_type(): string;

	/**
	 * Return the singular label (already translated).
	 *
	 * Example: return __( 'Study Set', 'wp-flashnotes' );
	 *
	 * @return string
	 */
	abstract protected function set_singular(): string;

	/**
	 * Return the plural label (already translated).
	 *
	 * Example: return __( 'Study Sets', 'wp-flashnotes' );
	 *
	 * @return string
	 */
	abstract protected function set_plural(): string;

	/**
	 * Per-CPT specific args merged into the base args.
	 *
	 * @return array
	 */
	protected function args(): array {
		return array();
	}

	/**
	 * Capabilities derived from the CPT type.
	 *
	 * @return array
	 */
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

	/**
	 * Generic labels.
	 *
	 * The singular and plural labels are already translated by the concrete class.
	 *
	 * @return array
	 */
	protected function labels(): array {
		$singular = $this->singular_label;
		$plural   = $this->plural_label;

		return array(
			// Singular/plural already translated in concrete class.
			'name'           => $plural,
			'singular_name'  => $singular,
			'menu_name'      => $plural,
			'name_admin_bar' => $singular,

			// Generic literals with placeholders and translator comments.
			'add_new' => __( 'Add New', 'wp-flashnotes' ),

			'add_new_item' => sprintf(
				/* translators: %s: post type singular label. */
				__( 'Add New %s', 'wp-flashnotes' ),
				$singular
			),
			'new_item' => sprintf(
				/* translators: %s: post type singular label. */
				__( 'New %s', 'wp-flashnotes' ),
				$singular
			),
			'edit_item' => sprintf(
				/* translators: %s: post type singular label. */
				__( 'Edit %s', 'wp-flashnotes' ),
				$singular
			),
			'view_item' => sprintf(
				/* translators: %s: post type singular label. */
				__( 'View %s', 'wp-flashnotes' ),
				$singular
			),
			'all_items' => sprintf(
				/* translators: %s: post type plural label. */
				__( 'All %s', 'wp-flashnotes' ),
				$plural
			),
			'search_items' => sprintf(
				/* translators: %s: post type plural label. */
				__( 'Search %s', 'wp-flashnotes' ),
				$plural
			),

			'not_found'          => __( 'Not found', 'wp-flashnotes' ),
			'not_found_in_trash' => __( 'Not found in Trash', 'wp-flashnotes' ),
		);
	}

	/**
	 * Seed capabilities into a given role.
	 *
	 * Intended for use on plugin activation.
	 *
	 * @param string $role_name Role name. Default 'administrator'.
	 */
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
