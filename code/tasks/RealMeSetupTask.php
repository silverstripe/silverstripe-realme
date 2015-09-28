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
 * - Output metadata XML that must be submitted to RealMe in order to integrate with ITE and Production environments
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

	/**
	 * Run this setup task. See class phpdoc for the full description of what this does
	 *
	 * @param SS_HTTPRequest $request
	 */
	public function run($request) {
		$this->service = Injector::inst()->get('RealMeService');

		// Ensure we are running on the command-line, and not running in a browser
		if(!Director::is_cli()) {
			$this->halt(_t('RealMeSetupTask.ERRNOTCLI'));
		}

		// Validate all required values exist
		$forceRun = ($request->getVar('force') == 1);
		$forEnv = $request->getVar('forEnv');
		if($this->validateInputs($request, $forceRun, $forEnv)) {
			$this->halt();
		} else {
			$this->message("Validation succeeded, continuing with setup...");
		}

		// Create configuration files
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

		// Output metadata XML so that it can be sent to RealMe via the agency
		$this->message(sprintf(
			"Metadata XML is listed below for the '%s' RealMe environment, this should be sent to the agency so they "
				. "can pass it on to RealMe Operations staff" . PHP_EOL . PHP_EOL,
			$forEnv
		));

		$this->outputMetadataXmlContent($forEnv);

		$this->message(PHP_EOL . 'Done!');
	}

	/**
	 * Validate all inputs to this setup script. Ensures that all required values are available, where-ever they need to
	 * be loaded from (environment variables, Config API, or directly passed to this script via the cmd-line)
	 *
	 * @param SS_HTTPRequest $request  The request object for this cli process
	 * @param bool           $forceRun Whether or not to force the setup (therefore skip checks around existing files)
	 * @param string         $forEnv   The environment that we want to output content for (mts, ite, or prod)
	 * @return bool true if there were errors, false if there were none
	 */
	private function validateInputs($request, $forceRun, $forEnv) {
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

		// Ensure that the symlink location is valid
		$symlinkPath = $this->service->getSimpleSAMLSymlinkPath();
		if(!$this->validateSymlinkLocation($symlinkPath)) {
			$errors[] = _t('RealMeSetupTask.ERRBADSYMLINKPATH', '', '', array('path' => $symlinkPath));
		}

		// Ensure that 'forEnv=' is specified on the cli, and ensure that it matches a RealMe environment
		$allowedEnvs = $this->service->getAllowedRealMeEnvironments();
		if(!in_array($forEnv, $allowedEnvs)) {
			$errors[] = _t(
				'RealMeSetupTask.ERRENVNOTALLOWED',
				'',
				'',
				array(
					'env' => $forEnv,
					'allowedEnvs' => join(', ', $allowedEnvs)
				)
			);
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
		} elseif(is_null($this->service->getSigningCertContent())) {
			// Signing cert exists, but doesn't include BEGIN/END CERTIFICATE lines, or doesn't contain the cert
			$errors[] = _t(
				'RealMeSetupTask.ERRNOSIGNINGCERTCONTENT',
				'',
				'',
				array('file' => $this->service->getSigningCertPath())
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

		// Ensure the assertion consumer service location exists
		if(is_null($this->service->getAssertionConsumerServiceUrlForEnvironment($forEnv))) {
			$errors[] = _t('RealMeSetupTask.ERRNOASSERTIONSERVICEURL', '', '', array('env' => $forEnv));
		}

		// Ensure data required for metadata XML output exists
		if(is_null($this->service->getMetadataOrganisationName())) {
			$errors[] = _t('RealMeSetupTask.ERRNOORGANISATIONNAME');
		}

		if(is_null($this->service->getMetadataOrganisationDisplayName())) {
			$errors[] = _t('RealMeSetupTask.ERRNOORGANISATIONDISPLAYNAME');
		}

		if(is_null($this->service->getMetadataOrganisationUrl())) {
			$errors[] = _t('RealMeSetupTask.ERRNOORGANISATIONURL');
		}

		$contact = $this->service->getMetadataContactSupport();
		if(is_null($contact['company']) || is_null($contact['firstNames']) || is_null($contact['surname'])) {
			$errors[] = _t('RealMeSetupTask.ERRNOSUPPORTCONTACT');
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

	/**
	 * Create primary configuration file and place in SimpleSAMLphp configuration directory
	 */
	private function createConfigFromTemplate() {
		$configDir = $this->getConfigurationTemplateDir();
		$templateFile = Controller::join_links($configDir, 'config.php');

		if(!$this->isReadable($templateFile)) {
			$this->halt(sprintf("Can't read config.php file at %s", $templateFile));
		}

		$this->writeConfigFile(
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

	/**
	 * Create authentication sources configuration file and place in SimpleSAMLphp configuration directory
	 */
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
		$this->writeConfigFile(
			$templateFile,
			$this->getSimpleSAMLAuthSourcesFilePath(),
			array(
				'{{mts-entityID}}' => $this->service->getEntityIDForEnvironment('mts'),
				'{{mts-authncontext}}' => $this->service->getAuthnContextForEnvironment('mts'),
				'{{mts-privatepemfile-signing}}' => $this->service->getSigningCertPath(),
				'{{mts-privatepemfile-mutual}}' => $this->service->getMutualCertPath(),
				'{{mts-privatepemfile-signing-password}}' => $this->service->getSigningCertPassword(),
				'{{mts-privatepemfile-mutual-password}}' => $this->service->getMutualCertPassword(),
				'{{mts-backchannel-proxyhost}}' => $this->service->getProxyHostForEnvironment('mts'),
				'{{mts-backchannel-proxyport}}' => $this->service->getProxyPortForEnvironment('mts'),
				'{{ite-entityID}}' => $this->service->getEntityIDForEnvironment('ite'),
				'{{ite-authncontext}}' => $this->service->getAuthnContextForEnvironment('ite'),
				'{{ite-privatepemfile-signing}}' => $this->service->getSigningCertPath(),
				'{{ite-privatepemfile-mutual}}' => $this->service->getMutualCertPath(),
				'{{ite-privatepemfile-signing-password}}' => $this->service->getSigningCertPassword(),
				'{{ite-privatepemfile-mutual-password}}' => $this->service->getMutualCertPassword(),
				'{{ite-backchannel-proxyhost}}' => $this->service->getProxyHostForEnvironment('ite'),
				'{{ite-backchannel-proxyport}}' => $this->service->getProxyPortForEnvironment('ite'),
				'{{prod-entityID}}' => $this->service->getEntityIDForEnvironment('prod'),
				'{{prod-authncontext}}' => $this->service->getAuthnContextForEnvironment('prod'),
				'{{prod-privatepemfile-signing}}' => $this->service->getSigningCertPath(),
				'{{prod-privatepemfile-mutual}}' => $this->service->getMutualCertPath(),
				'{{prod-privatepemfile-signing-password}}' => $this->service->getSigningCertPassword(),
				'{{prod-privatepemfile-mutual-password}}' => $this->service->getMutualCertPassword(),
				'{{prod-backchannel-proxyhost}}' => $this->service->getProxyHostForEnvironment('prod'),
				'{{prod-backchannel-proxyport}}' => $this->service->getProxyPortForEnvironment('prod'),
			)
		);
	}

	/**
	 * Create metadata configuration file and place in SimpleSAMLphp configuration directory
	 */
	private function createMetadataFromTemplate() {
		$configDir = $this->getConfigurationTemplateDir();
		$templateFile = Controller::join_links($configDir, 'saml20-idp-remote.php');

		if(!$this->isReadable($templateFile)) {
			$this->halt(sprintf("Can't read saml20-idp-remote.php file at %s", $templateFile));
		}

		$this->writeConfigFile(
			$templateFile,
			$this->getSimpleSAMLMetadataFilePath()
		);
	}

	/**
	 * Ensures a symlink between the webroot (e.g. /path/to/webroot/simplesaml/) exists, and points to the correct
	 * vendor folder for SimpleSAMLphp's webroot (vendor/simplesamlphp/simplesamlphp/www/)
	 */
	private function symlinkSimpleSAMLIntoWebroot() {
		$simpleSamlWebroot = Controller::join_links($this->getSimpleSAMLVendorPath(), 'www');
		$symlinkLocation = $this->service->getSimpleSAMLSymlinkPath();
		if(substr($symlinkLocation, -1, 1) === '/') {
			$symlinkLocation = substr($symlinkLocation, 0, strlen($symlinkLocation) - 1);
		}

		// Double-ensure that we're not about to delete /, or the entire webroot, because $symlinkLocation is wrong
		if(!$this->validateSymlinkLocation($symlinkLocation)) {
			$this->halt(sprintf('Exiting because the symlink location (%s) is incorrect.', $symlinkLocation));
		}

		if(file_exists($symlinkLocation)) {
			unlink($symlinkLocation);
		}

		if(!symlink($simpleSamlWebroot, $symlinkLocation)) {
			$this->halt('Was not able to create symlink, symlink() call failed.');
		}
	}

	/**
	 * Outputs metadata template XML to console, so it can be sent to RealMe Operations team
	 *
	 * @param string $forEnv The RealMe environment to output metadata content for (e.g. mts, ite, prod).
	 */
	private function outputMetadataXmlContent($forEnv) {
		$configDir = $this->getConfigurationTemplateDir();
		$templateFile = Controller::join_links($configDir, 'metadata.xml');

		if(!$this->isReadable($templateFile)) {
			$this->halt(sprintf("Can't read metadata.xml file at %s", $templateFile));
		}

		$supportContact = $this->service->getMetadataContactSupport();

		$message = $this->replaceTemplateContents(
			$templateFile,
			array(
				'{{entityID}}' => $this->service->getEntityIDForEnvironment($forEnv),
				'{{certificate-data}}' => $this->service->getSigningCertContent(),
				'{{assertion-service-url}}' => $this->service->getAssertionConsumerServiceUrlForEnvironment($forEnv),
				'{{organisation-name}}' => $this->service->getMetadataOrganisationName(),
				'{{organisation-display-name}}' => $this->service->getMetadataOrganisationDisplayName(),
				'{{organisation-url}}' => $this->service->getMetadataOrganisationUrl(),
				'{{contact-support1-company}}' => $supportContact['company'],
				'{{contact-support1-firstnames}}' => $supportContact['firstNames'],
				'{{contact-support1-surname}}' => $supportContact['surname'],
			)
		);

		$this->message($message);
	}

	/**
	 * Writes configuration from a template file with {{variables}} to its final location for SimpleSAMLphp
	 *
	 * @param string $templatePath The path to the template file
	 * @param string $newFilePath The path where the new file will be written
	 * @param array|null $replacements An array of '{{variable}}' => 'value' replacements
	 */
	private function writeConfigFile($templatePath, $newFilePath, $replacements = null) {
		$configText = $this->replaceTemplateContents($templatePath, $replacements);

		if(file_put_contents($newFilePath, $configText) === false) {
			$this->halt(sprintf("Could not write template file '%s' to location '%s'", $templatePath, $newFilePath));
		}
	}

	/**
	 * Replace content in a template file with an array of replacements
	 *
	 * @param string $templatePath The path to the template file
	 * @param array|null $replacements An array of '{{variable}}' => 'value' replacements
	 * @return string The contents, with all {{variables}} replaced
	 */
	private function replaceTemplateContents($templatePath, $replacements = null) {
		$configText = file_get_contents($templatePath);

		if(is_array($replacements)) {
			$configText = str_replace(array_keys($replacements), array_values($replacements), $configText);
		}

		return $configText;
	}

	/**
	 * @return string The path within the SimpleSAMLphp root to the main configuration file
	 */
	private function getSimpleSAMLConfigFilePath() {
		return sprintf('%s/config/config.php', $this->getSimpleSAMLVendorPath());
	}

	/**
	 * @return string The path within the SimpleSAMLphp root to the authentication sources configuration file
	 */
	private function getSimpleSAMLAuthSourcesFilePath() {
		return sprintf('%s/config/authsources.php', $this->getSimpleSAMLVendorPath());
	}

	/**
	 * @return string The path within the SimpleSAMLphp root to the metadata configuration file
	 */
	private function getSimpleSAMLMetadataFilePath() {
		return sprintf('%s/metadata/saml20-idp-remote.php', $this->getSimpleSAMLVendorPath());
	}

	/**
	 * @return string The path from the server root to the physical location where SimpleSAMLphp is installed
	 */
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

	/**
	 * Ensure that a given symlink location is valid. Specifically, we check:
	 * - The given location is within the webroot of the site (inside BASE_PATH)
	 * - The given location is *not* the webroot itself (e.g. !== BASE_PATH)
	 *
	 * @param string $location The place where the symlink location will end up at
	 * @return bool true if validation passes, false if it fails
	 */
	private function validateSymlinkLocation($location) {
		// Strip any trailing slash off
		if(substr($location, -1, 1) === '/') {
			$location = substr($location, 0, (strlen($location) - 1));
		}

		$basePath = BASE_PATH;
		if(substr($basePath, -1, 1) === '/') {
			$basePath = substr($basePath, 0, (strlen($basePath) - 1));
		}

		if($location === $basePath) return false;

		// Ensure location is somewhere inside the SS BASE_PATH
		if(substr($location, 0, strlen($basePath)) !== $basePath) return false;

		return true;
	}
}