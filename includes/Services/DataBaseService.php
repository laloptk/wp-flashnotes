<?php
namespace WPFlashNotes\Services;

use WPFlashNotes\Core\ServiceInterface;

defined( 'ABSPATH' ) || exit;

/**
 * DatabaseService
 *
 * Handles all database schema creation at plugin activation.
 * Simplified version — no version checks or migrations.
 */
final class DatabaseService implements ServiceInterface {

	/**
	 * Register service hooks.
	 * No runtime hooks needed beyond activation.
	 */
	public function register(): void {
		// Intentionally empty — schema is only created on activation.
	}

	/**
	 * Static method for plugin activation.
	 * Called directly by register_activation_hook() in the main plugin file.
	 */
	public static function install_schema(): void {
		$instance = new self();
		$instance->run_schema_tasks();
	}

	/**
	 * Executes schema tasks in dependency order.
	 * Equivalent to the old run_schema_bootstrap() logic.
	 */
	private function run_schema_tasks(): void {
		require_once WPFN_PLUGIN_DIR . 'includes/DataBase/Schema/tasks.php';

		if ( ! function_exists( 'wpfn_schema_tasks' ) ) {
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

			// Run dependencies first
			foreach ( $task['deps'] as $dependency ) {
				$execute_task( $dependency );
			}

			// Execute this task
			if ( is_callable( $task['run'] ) ) {
				call_user_func( $task['run'] );
			}

			$completed_tasks[] = $slug;
		};

		foreach ( $schema_tasks as $task ) {
			$execute_task( $task['slug'] );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[WPFlashNotes] Database schema installed successfully.' );
		}
	}
}
