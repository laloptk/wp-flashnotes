<?php
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/tasks.php';
function run_schema_bootstrap() {
	$schema_tasks = wpfn_schema_tasks();

	$tasks_by_slug = [];
	foreach ( $schema_tasks as $task ) {
		$tasks_by_slug[ $task['slug'] ] = $task;
	}

	$completed_tasks = [];

	$execute_task = function ( $slug ) use ( &$execute_task, &$completed_tasks, $tasks_by_slug ) {
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
		( $task['run'] )();

		// Mark as completed
		$completed_tasks[] = $slug;
	};

	// Run all tasks
	foreach ( $schema_tasks as $task ) {
		$execute_task( $task['slug'] );
	}
}

run_schema_bootstrap();
