<?php
namespace WPFlashNotes\Managers;

use WPFlashNotes\Repos\SetsRepository;
use WPFlashNotes\Managers\ContextLinkManager;
use WPFlashNotes\Managers\TemplateLockManager;

defined( 'ABSPATH' ) || exit;

$sets_repo = new SetsRepository();

$template_lock_manager = new TemplateLockManager( $sets_repo );
$template_lock_manager->register_hooks();

$context_link_manager = new ContextLinkManager( $sets_repo );
$context_link_manager->register_hooks();
