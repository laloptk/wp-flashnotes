<?php
namespace WPFlashNotes\Services;

use WPFlashNotes\Core\ServiceInterface;

class AssetsService implements ServiceInterface {

	public function register(): void {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	public function register_assets(): void {
		$asset_file = WPFN_PLUGIN_DIR . 'build/index.asset.php';
		$assets     = file_exists( $asset_file )
			? include $asset_file
			: array(
				'dependencies' => array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-api-fetch' ),
				'version'      => WPFN_VERSION,
			);

		wp_register_script(
			'wpfn-blocks',
			plugins_url( 'build/index.js', WPFN_PLUGIN_FILE ),
			$assets['dependencies'],
			$assets['version'],
			true
		);

		wp_localize_script(
			'wpfn-blocks',
			'WPFlashNotes',
			array(
				'apiNamespace' => WPFN_API_NAMESPACE,
				'restUrl'      => esc_url_raw( rest_url() ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	public function enqueue_editor_assets(): void {
		wp_enqueue_script(
			'wp-flashnotes-sidebar',
			WPFN_PLUGIN_URL . 'build/editor-sidebar.js',
			array( 'wp-plugins', 'wp-editor', 'wp-components', 'wp-element', 'wp-i18n' ),
			WPFN_VERSION,
			true
		);
	}
}
