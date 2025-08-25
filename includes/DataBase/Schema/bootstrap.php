<?php
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/tasks.php';

register_activation_hook(
	WPFN_PLUGIN_FILE,
	function () {
		$tasks = wpfn_schema_tasks();

		$bySlug = array();
		foreach ( $tasks as $t ) {
			$bySlug[ $t['slug'] ] = $t; }

		$done = array();
		$run  = function ( $slug ) use ( &$run, &$done, $bySlug ) {
			if ( in_array( $slug, $done, true ) ) {
				return;
			}
			$t = $bySlug[ $slug ] ?? null;
			if ( ! $t ) {
				return;
			}
			foreach ( $t['deps'] as $dep ) {
				$run( $dep ); }
			( $t['run'] )();
			$done[] = $slug;
		};

		foreach ( $tasks as $t ) {
			$run( $t['slug'] ); }
	}
);
