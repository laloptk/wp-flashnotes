<?php

namespace WPFlashNotes\CLI;

defined('ABSPATH') || exit;

use \WP_CLI;

use function WPFlashNotes\Dev\wpfn_crud_smoke_test;

if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('wpfn test:crud', function ($args, $assoc_args) {
        $entity = $assoc_args['entity'] ?? 'notes';
        $report = wpfn_crud_smoke_test($entity);
        \WP_CLI\Utils\format_items('table', [
            [
                'entity'      => $report['entity'],
                'inserted_id' => $report['inserted_id'] ?? 'null',
                'ok'          => $report['ok'] ? 'yes' : 'no',
            ]
        ], ['entity','inserted_id','ok']);
        \WP_CLI::log(print_r($report['steps'], true));
    });
}