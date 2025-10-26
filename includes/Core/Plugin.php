<?php
namespace WPFlashNotes\Core;

use WPFlashNotes\Services\{
	AssetsService,
	I18nService,
	RestService,
	CptService,
	BlocksService,
	DatabaseService,
    PropagationService,
};

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;

	private function __construct() {}

	public static function instance(): Plugin {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		$this->define_constants();
		$this->check_composer_autoload();

		$registrar = new ServiceRegistrar();

		$registrar->add( new I18nService() );
		$registrar->add( new AssetsService() );
		$registrar->add( new DatabaseService() );
		$registrar->add( new RestService() );
		$registrar->add( new CptService() );
		$registrar->add( new BlocksService() );
        $registrar->add( new PropagationService());

		$registrar->register_all();
	}

	private function define_constants(): void {
		define( 'WPFN_PLUGIN_FILE', dirname( __DIR__, 2 ) . '/wp-flashnotes.php' );
		define( 'WPFN_PLUGIN_DIR', plugin_dir_path( WPFN_PLUGIN_FILE ) );
		define( 'WPFN_PLUGIN_URL', plugin_dir_url( WPFN_PLUGIN_FILE ) );
		define( 'WPFN_PLUGIN_BASENAME', plugin_basename( WPFN_PLUGIN_FILE ) );
		define( 'WPFN_VERSION', '1.0.0' );
		define( 'WPFN_API_VERSION', 1 );
		define( 'WPFN_API_NAMESPACE', 'wpfn/v' . WPFN_API_VERSION );
	}

	private function check_composer_autoload(): void {
		$composer = WPFN_PLUGIN_DIR . 'vendor/autoload.php';
		if ( ! file_exists( $composer ) ) {
			if ( is_admin() ) {
				add_action(
					'admin_notices',
					function () use ( $composer ) {
						echo '<div class="notice notice-error"><p><strong>WP FlashNotes</strong> cannot run — missing autoloader:<br><code>' .
						esc_html( $composer ) .
						'</code><br>Run <code>composer install</code>.</p></div>';
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
}
