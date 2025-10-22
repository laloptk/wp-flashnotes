<?php
namespace WPFlashNotes;

defined( 'ABSPATH' ) || exit;

class Plugin {

	public function init(): void {
		$this->define_constants();
		$this->check_composer_autoload();
		$this->bootstrap_components();
		$this->setup_i18n();
		$this->setup_assets();
	}

	private function define_constants(): void {
		if ( ! defined( 'WPFN_PLUGIN_FILE' ) ) {
			define( 'WPFN_PLUGIN_FILE', dirname( __DIR__ ) . '/wp-flashnotes.php' );
		}
		if ( ! defined( 'WPFN_PLUGIN_DIR' ) ) {
			define( 'WPFN_PLUGIN_DIR', plugin_dir_path( WPFN_PLUGIN_FILE ) );
		}
		if ( ! defined( 'WPFN_PLUGIN_URL' ) ) {
			define( 'WPFN_PLUGIN_URL', plugin_dir_url( WPFN_PLUGIN_FILE ) );
		}
		if ( ! defined( 'WPFN_PLUGIN_BASENAME' ) ) {
			define( 'WPFN_PLUGIN_BASENAME', plugin_basename( WPFN_PLUGIN_FILE ) );
		}
		if ( ! defined( 'WPFN_VERSION' ) ) {
			define( 'WPFN_VERSION', '1.0.0' );
		}

		define( 'WPFN_API_VERSION', 1 );
		define( 'WPFN_API_NAMESPACE', 'wpfn/v' . WPFN_API_VERSION );
	}

	private function check_composer_autoload(): void {
		$composer = WPFN_PLUGIN_DIR . 'vendor/autoload.php';

		if ( ! file_exists( $composer ) ) {
			if ( is_admin() ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';

				add_action(
					'admin_notices',
					function () use ( $composer ) {
						echo '<div class="notice notice-error"><p><strong>WP FlashNotes</strong> can’t run because the Composer autoloader is missing:<br><code>'
						. esc_html( $composer ) .
						'</code><br>Please run <code>composer install</code> in the plugin root directory.</p></div>';
					}
				);

				add_action(
					'admin_init',
					function () {
						deactivate_plugins( WPFN_PLUGIN_BASENAME );
					}
				);
			}
			return;
		}

		require_once $composer;
	}

	private function bootstrap_components(): void {
		require_once WPFN_PLUGIN_DIR . 'includes/REST/bootstrap.php';
		require_once WPFN_PLUGIN_DIR . 'includes/CPT/bootstrap.php';
		require_once WPFN_PLUGIN_DIR . 'includes/Blocks/bootstrap.php';
		// require_once WPFN_PLUGIN_DIR . 'includes/Events/bootstrap.php';
		// require_once WPFN_PLUGIN_DIR . 'includes/Managers/bootstrap.php';
		require_once WPFN_PLUGIN_DIR . 'includes/DataBase/bootstrap.php';
	}

	private function setup_i18n(): void {
		add_action(
			'init',
			function () {
				load_plugin_textdomain(
					'wp-flashnotes',
					false,
					dirname( WPFN_PLUGIN_BASENAME ) . '/languages'
				);
			}
		);
	}

	private function setup_assets(): void {
		add_action(
			'init',
			function () {
				$asset_file = WPFN_PLUGIN_DIR . 'build/index.asset.php';
				$assets     = file_exists( $asset_file ) ? include $asset_file : array(
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

				// Localize global object for JS
				wp_localize_script(
					'wpfn-blocks',
					'WPFlashNotes',
					array(
						'apiNamespace' => WPFN_API_NAMESPACE,
						'restUrl'      => esc_url_raw( rest_url() ),
						'nonce'        => wp_create_nonce( 'wp_rest' ),
					)
				);

				wp_set_script_translations( 'wpfn-blocks', 'wp-flashnotes' );
			}
		);
	}
}
