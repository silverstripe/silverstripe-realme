<?php

/**
 * Class RealMeSetupTask
 *
 * This class is intended to be run by a server administrator once the module is setup and configured via environment
 * variables, and YML fragments. The following tasks are done by this build task:
 *
 * - Check to ensure that the task is being run from the cmdline (not in the browser, it's too sensitive)
 * - Check to ensure that the task hasn't already been run, and if it has, fail unless `force=1` is passed to the script
 * - Validate all required values have been added in the appropriate place, and provide appropriate errors if not
 * - Create config.php file for simpleSAMLphp to consume, and write it in the appropriate place
 * - Create authsources.php file for simpleSAMLphp to consume, and write it to the appropriate place
 * - Create saml20-idp-remote.php file for simpleSAMLphp to consume, and write it to the appropriate place
 * - Symlink the RealMeService::$simplesaml_base_url_path from the webroot to vendor/simplesamlphp/simplesamlphp/www
 * - Output metadata XML that must be submitted to Real Me in order to integrate with ITE and Production environments
 */
class RealMeSetupTask extends BuildTask {
	/**
	 * @var RealMeService
	 */
	private $service;

	/**
	 * @config
	 * @var string Path (from the webroot, or an absolute path) to the directory that holds templates for config.php,
	 * authsources.php and saml20-idp-remote.php that will be used to create the configuration for SimpleSAMLphp.
	 * The default is to use the path to <module path>/templates/simplesaml-configuration
	 */
	private $config_template_dir = null;

	public function run($request) {
		$this->service = Injector::inst()->get('RealMeService');

		// Ensure we are running on the command-line, and not running in a browser
		if(!Director::is_cli()) {
			$this->halt(_t('RealMeSetupTask.ERRNOTCLI'));
		}

		// Validate all required values exist
		$forceRun = ($request->getVar('force') == 1);
		if($this->validateInputs($request, $forceRun)) {
			$this->halt();
		} else {
			$this->message("Validation succeeded, continuing with setup...");
		}

		$this->message(sprintf(
			'Creating config file in %s/config/ from config in template dir  %s',
			$this->getSimpleSAMLVendorPath(),
			$this->getConfigurationTemplateDir()
		));
		$this->createConfigFromTemplate();

		$this->message(sprintf(
			'Creating authsources file in %s/config/ from config in template dir %s',
			$this->getSimpleSAMLVendorPath(),
			$this->getConfigurationTemplateDir()
		));
		$this->createAuthSourcesFromTemplate();

		$this->message(sprintf(
			'Creating saml20-idp-remote file in %s/metadata/ from config in template dir %s',
			$this->getSimpleSAMLVendorPath(),
			$this->getConfigurationTemplateDir()));
		$this->createMetadataFromTemplate();

		$this->message(sprintf(
			'Symlinking SimpleSAMLphp\'s www folder from %s into %s',
			$this->getSimpleSAMLVendorPath(),
			$this->service->getSimpleSAMLSymlinkPath()
		));

		$this->symlinkSimpleSAMLIntoWebroot();

	}

	/**
	 * Validate all inputs to this setup script. Ensures that all required values are available, where-ever they need to
	 * be loaded from (environment variables, Config API, or directly passed to this script via the cmd-line)
	 *
	 * @param SS_HTTPRequest $request The request object for this cli process
	 * @param bool $forceRun Whether or not to force the setup (therefore skip checks around existing files)
	 * @return bool true if there were errors, false if there were none
	 */
	private function validateInputs($request, $forceRun) {
		$errors = array();

		// Ensure we haven't already run before, or if we have, that force=1 is passed
		$existingFiles = array(
			$this->getSimpleSAMLConfigFilePath(),
			$this->getSimpleSAMLAuthSourcesFilePath(),
			$this->getSimpleSAMLMetadataFilePath(),
			$this->service->getSimpleSAMLSymlinkPath()
		);

		foreach($existingFiles as $filePath) {
			if(file_exists($filePath) && !$forceRun) {
				$errors[] = _t('RealMeSetupTask.ERRALREADYRUN', '', '', array('path' => $filePath));
			}
		}

		if(is_null($this->service->getSimpleSamlBaseUrlPath())) {
			$errors[] = _t('RealMeSetupTask.ERRNOBASEDIR');
		}

		if(is_null($this->service->getCertDir())) {
			$errors[] = _t('RealMeSetupTask.ERRNOCERTDIR');
		} elseif(!$this->isReadable($this->service->getCertDir())) {
			$errors[] = _t(
				'RealMeSetupTask.ERRCERTDIRNOTREADABLE',
				'',
				'',
				array('dir' => $this->service->getCertDir())
			);
		}

		if(is_null($this->service->getLoggingDir())) {
			$errors[] = _t('RealMeSetupTask.ERRNOLOGDIR');
		} elseif(!$this->isWriteable($this->service->getLoggingDir())) {
			$errors[] = _t(
				'RealMeSetupTask.ERRLOGDIRNOTWRITEABLE',
				'',
				'',
				array('dir' => $this->service->getLoggingDir())
			);
		}

		if(is_null($this->service->getTempDir())) {
			$errors[] = _t('RealMeSetupTask.ERRNOTEMPDIR');
		} elseif(!$this->isWriteable($this->service->getTempDir()) && !$this->isWriteable(dirname($this->service->getTempDir()))) {
			$errors[] = _t(
				'RealMeSetupTask.ERRTEMPDIRNOTWRITEABLE',
				'',
				'',
				array('dir' => $this->service->getTempDir())
			);
		}

		if(is_null($this->service->findOrMakeSimpleSAMLPassword())) {
			$errors[] = _t('RealMeSetupTask.ERRNOADMPASS');
		}

		if(is_null($this->service->generateSimpleSAMLSalt())) {
			$errors[] = _t('RealMeSetupTask.ERRNOSALT');
		}

		$signingCertFile = $this->service->getSigningCertPath();
		if(is_null($signingCertFile) || !$this->isReadable($signingCertFile)) {
			$errors[] = _t(
				'RealMeSetupTask.ERRNOSIGNINGCERT',
				'',
				'',
				array(
					'file' => $signingCertFile,
					'const' => 'REALME_SIGNING_CERT_FILENAME'
				)
			);
		}

		$mutualCertFile = $this->service->getMutualCertPath();
		if(is_null($mutualCertFile) || !$this->isReadable($mutualCertFile)) {
			$errors[] = _t(
				'RealMeSetupTask.ERRNOMUTUALCERT',
				'',
				'',
				array(
					'file' => $mutualCertFile,
					'const' => 'REALME_MUTUAL_CERT_FILENAME'
				)
			);
		}

		foreach(array('mts', 'ite', 'prod') as $env) {
			if(is_null($this->service->getEntityIDForEnvironment($env))) {
				$errors[] = _t('RealMeSetupTask.ERRNOENTITYID', '', '', array('env' => $env));
			}

			if(is_null($this->service->getAuthnContextForEnvironment($env))) {
				$errors[] = _t('RealMeSetupTask.ERRNOAUTHNCONTEXT', '', '', array('env' => $env));
			}
		}

		// Output validation errors, if any are found
		if(sizeof($errors) > 0) {
			$errorList = PHP_EOL . ' - ' . join(PHP_EOL . ' - ', $errors);

			$this->message(_t(
				'RealMeSetupTask.ERRVALIDATION',
				'',
				'',
				array(
					'numissues' => sizeof($errors),
					'issues' => $errorList
				)
			));
		}

		return sizeof($errors) > 0;
	}

	private function createConfigFromTemplate() {
		$configDir = $this->getConfigurationTemplateDir();
		$templateFile = Controller::join_links($configDir, 'config.php');

		if(!$this->isReadable($templateFile)) {
			$this->halt(sprintf("Can't read config.php file at %s", $templateFile));
		}

		$this->createConfigFile(
			$templateFile,
			$this->getSimpleSAMLConfigFilePath(),
			array(
				'{{baseurlpath}}' => $this->service->getSimpleSamlBaseUrlPath(),
				'{{certdir}}' => $this->service->getCertDir(),
				'{{loggingdir}}' => $this->service->getLoggingDir(),
				'{{tempdir}}' => $this->service->getTempDir(),
				'{{adminpassword}}' => $this->service->findOrMakeSimpleSAMLPassword(),
				'{{secretsalt}}' => $this->service->generateSimpleSAMLSalt(),
			)
		);
	}

	private function createAuthSourcesFromTemplate() {
		$configDir = $this->getConfigurationTemplateDir();
		$templateFile = Controller::join_links($configDir, 'authsources.php');

		if(!$this->isReadable($templateFile)) {
			$this->halt(sprintf("Can't read authsources.php file at %s", $templateFile));
		}

		/**
		 * @todo Determine what to do with multiple certificates.
		 *
		 * This currently uses the same signing and mutual certificate paths and password for all 3 environments. This
		 * means that you can't test e.g. connectivity with ITE on the production server environment. However, the
		 * alternative is that all certificates and passwords must be present on all servers, which is sub-optimal.
		 *
		 * See realme/templates/simplesaml-configuration/authsources.php
		 */
		$this->createConfigFile(
			$templateFile,
			$this->getSimpleSAMLAuthSourcesFilePath(),
			array(
				'{{mts-entityID}}' => $this->service->getEntityIDForEnvironment('mts'),
				'{{mts-authncontext}}' => $this->service->getAuthnContextForEnvironment('mts'),
				'{{mts-privatepemfile-signing}}' => $this->service->getSigningCertPath(),
				'{{mts-privatepemfile-mutual}}' => $this->service->getMutualCertPath(),
				'{{mts-privatepemfile-signing-password}}' => $this->service->getSigningCertPassword(),
				'{{mts-privatepemfile-mutual-password}}' => $this->service->getMutualCertPassword(),
				'{{ite-entityID}}' => $this->service->getEntityIDForEnvironment('ite'),
				'{{ite-authncontext}}' => $this->service->getAuthnContextForEnvironment('ite'),
				'{{ite-privatepemfile-signing}}' => $this->service->getSigningCertPath(),
				'{{ite-privatepemfile-mutual}}' => $this->service->getMutualCertPath(),
				'{{ite-privatepemfile-signing-password}}' => $this->service->getSigningCertPassword(),
				'{{ite-privatepemfile-mutual-password}}' => $this->service->getMutualCertPassword(),
				'{{prod-entityID}}' => $this->service->getEntityIDForEnvironment('prod'),
				'{{prod-authncontext}}' => $this->service->getAuthnContextForEnvironment('prod'),
				'{{prod-privatepemfile-signing}}' => $this->service->getSigningCertPath(),
				'{{prod-privatepemfile-mutual}}' => $this->service->getMutualCertPath(),
				'{{prod-privatepemfile-signing-password}}' => $this->service->getSigningCertPassword(),
				'{{prod-privatepemfile-mutual-password}}' => $this->service->getMutualCertPassword(),
			)
		);
	}

	private function createMetadataFromTemplate() {
		$configDir = $this->getConfigurationTemplateDir();
		$templateFile = Controller::join_links($configDir, 'saml20-idp-remote.php');

		if(!$this->isReadable($templateFile)) {
			$this->halt(sprintf("Can't read saml20-idp-remote.php file at %s", $templateFile));
		}

		// We don't currently need to replace any {{variables}} in this file.
		$this->createConfigFile(
			$templateFile,
			$this->getSimpleSAMLMetadataFilePath()
		);
	}

	private function symlinkSimpleSAMLIntoWebroot() {
		$this->message('TODO: Unimplemented');
	}

	private function createConfigFile($templatePath, $newFilePath, $replacements = null) {
		$configText = file_get_contents($templatePath);

		if(is_array($replacements)) {
			$configText = str_replace(array_keys($replacements), array_values($replacements), $configText);
		}

		file_put_contents($newFilePath, $configText);
	}

	private function getSimpleSAMLConfigFilePath() {
		return sprintf('%s/config/config.php', $this->getSimpleSAMLVendorPath());
	}

	private function getSimpleSAMLAuthSourcesFilePath() {
		return sprintf('%s/config/authsources.php', $this->getSimpleSAMLVendorPath());
	}

	private function getSimpleSAMLMetadataFilePath() {
		return sprintf('%s/metadata/saml20-idp-remote.php', $this->getSimpleSAMLVendorPath());
	}

	private function getSimpleSAMLVendorPath() {
		return sprintf('%s/vendor/simplesamlphp/simplesamlphp', BASE_PATH);
	}

	/**
	 * @return string The full path to RealMe configuration
	 */
	private function getConfigurationTemplateDir() {
		$dir = $this->config()->template_config_dir;

		if(!$dir || !$this->isReadable($dir)) {
			$dir = REALME_MODULE_PATH . '/templates/simplesaml-configuration';
		}

		return Controller::join_links(BASE_PATH, $dir);
	}

	/**
	 * Immediately halt execution of the script, with a required error message.
	 *
	 * @param string $message
	 * @return void This method never returns
	 */
	private function halt($message = "") {
		$this->message($message . PHP_EOL);
		die();
	}

	/**
	 * Output a message to the console
	 * @param string $message
	 * @return void
	 */
	private function message($message) {
		echo $message . PHP_EOL;
	}

	/**
	 * Thin wrapper around is_readable(), used mainly so we can test this class completely
	 *
	 * @param string $filename The filename or directory to test
	 * @return bool true if the file/dir is readable, false if not
	 */
	private function isReadable($filename) {
		return is_readable($filename);
	}

	/**
	 * Thin wrapper around is_writeable(), used mainly so we can test this class completely
	 *
	 * @param string $filename The filename or directory to test
	 * @return bool true if the file/dir is writeable, false if not
	 */
	private function isWriteable($filename) {
		return is_writeable($filename);
	}
}