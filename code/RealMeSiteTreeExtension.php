<?php
class RealMeSiteTreeExtension extends DataExtension {
	private static $dependencies = array(
		'service' => '%$RealMeService'
	);

	/**
	 * @var RealMeService
	 */
	public $service;

	/**
	 *
	 */
	public function RealMeSessionData() {
		return $this->service->getUserData();
	}
}