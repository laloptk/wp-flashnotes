<?php

use WPFlashNotes\Services\DatabaseService;
use WPFlashNotes\Services\PropagationService;

abstract class BaseTestCase extends WP_UnitTestCase {
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        $db_service = new DatabaseService();
        $db_service->install_schema();
        // Manually bootstrap the propagation service since 'init' already fired
		$propagation_service = new PropagationService();
		$propagation_service->bootstrap();
    }
}