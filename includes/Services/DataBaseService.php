<?php
namespace WPFlashNotes\Services;

use WPFlashNotes\Core\ServiceInterface;

defined( 'ABSPATH' ) || exit;

final class DatabaseService implements ServiceInterface {

	public function register(): void {
		// Install on activation.
		register_activation_hook(
			WPFN_PLUGIN_FILE,
			[ $this, 'install_schema' ]
		);

		// Check schema immediately when service is instantiated.
		$this->maybe_upgrade_schema();
	}

	public function install_schema(): void {
		$this->run_schema_tasks();
	}

	public function maybe_upgrade_schema(): void {
		if ( ! defined('WPFN_PLUGIN_FILE') ) {
			return; // safety guard if constants missing
		}

		$stored = get_option( 'wpfn_schema_version' );

		if ( $stored !== WPFN_VERSION ) {
			$this->run_schema_tasks();
			update_option( 'wpfn_schema_version', WPFN_VERSION );
		}
	}

	private function run_schema_tasks(): void {
		require_once WPFN_PLUGIN_DIR . 'includes/DataBase/Schema/tasks.php';

		if ( ! function_exists( 'wpfn_schema_tasks' ) ) {
			return;
		}

		$schema_tasks   = wpfn_schema_tasks();
		$tasks_by_slug  = [];
		$completed_tasks = [];

		foreach ( $schema_tasks as $task ) {
			$tasks_by_slug[ $task['slug'] ] = $task;
		}

		$execute_task = function ( string $slug ) use ( &$execute_task, &$completed_tasks, $tasks_by_slug ) {
			if ( in_array( $slug, $completed_tasks, true ) ) {
				return;
			}
			$task = $tasks_by_slug[ $slug ] ?? null;
			if ( ! $task ) {
				return;
			}

			foreach ( $task['deps'] as $dependency ) {
				$execute_task( $dependency );
			}

			if ( is_callable( $task['run'] ) ) {
				call_user_func( $task['run'] );
			}

			$completed_tasks[] = $slug;
		};

		foreach ( $schema_tasks as $task ) {
			$execute_task( $task['slug'] );
		}
	}
}
