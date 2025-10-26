<?php
namespace WPFlashNotes\Services;

use WPFlashNotes\Core\ServiceInterface;

defined( 'ABSPATH' ) || exit;

/**
 * DatabaseService
 *
 * Handles all database schema creation at plugin activation.
 * Works without relying on plugin constants.
 */
final class DatabaseService implements ServiceInterface {

	public function register(): void {
		// Intentionally empty — schema only installed at activation.
	}

	/**
	 * Called directly by register_activation_hook() in main plugin file.
	 * This method must not depend on constants like WPFN_PLUGIN_DIR.
	 */
	public static function install_schema(): void {
		$instance = new self();
		$instance->run_schema_tasks();
	}

	/**
	 * Executes schema tasks using direct paths (no constants).
	 */
	private function run_schema_tasks(): void {
		$tasks_path = dirname(__DIR__, 1) . '/DataBase/Schema/tasks.php';

		if ( ! file_exists( $tasks_path ) ) {
			error_log('[WPFlashNotes] tasks.php not found: ' . $tasks_path);
			return;
		}

		require_once $tasks_path;

		if ( ! function_exists( 'wpfn_schema_tasks' ) ) {
			error_log('[WPFlashNotes] wpfn_schema_tasks() missing after require.');
			return;
		}

		$schema_tasks    = wpfn_schema_tasks();
		$tasks_by_slug   = [];
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

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log('[WPFlashNotes] Database schema installed successfully.');
		}
	}
}
