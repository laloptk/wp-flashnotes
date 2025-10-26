<?php
namespace WPFlashNotes\Core;

final class ServiceRegistrar {

	/** @var ServiceInterface[] */
	private array $services = [];

	public function add( ServiceInterface $service ): void {
		$this->services[] = $service;
	}

	public function register_all(): void {
		foreach ( $this->services as $service ) {
			$service->register();
		}
	}
}
