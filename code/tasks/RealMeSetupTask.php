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
 * - Output metadata XML that must be submitted to RealMe in order to integrate with ITE and Production environments
 */
class RealMeSetupTask extends BuildTask {
	/**
	 * @var RealMeService
	 */
	private $service;

    /**
     * A list of validation errors found while validating the realme configuration.
     *
     * @var string[]
     */
    private $errors = array();

	/**
	 * Run this setup task. See class phpdoc for the full description of what this does
	 *
	 * @param SS_HTTPRequest $request
	 */
	public function run($request) {
		try{
			$this->service = Injector::inst()->get('RealMeService');

			// Ensure we are running on the command-line, and not running in a browser
			if(false === Director::is_cli()) {
				throw new Exception(_t('RealMeSetupTask.ERR_NOT_CLI'));
			}

			// Validate all required values exist
			$forceRun = ($request->getVar('force') == 1);
			$forEnv = $request->getVar('forEnv');

            // Throws an exception if there was a problem with the config.
			$this->validateInputs($forceRun, $forEnv);

			$this->createConfigReadmeFromTemplate();

			$this->createConfigFromTemplate();

			$this->createAuthSourcesFromTemplate();

			$this->createMetadataFromTemplate();

			$this->outputMetadataXmlContent($forEnv);

			$this->message(PHP_EOL . _t('RealMeSetupTask.BUILD_FINISH', '', '', array('env' => $forEnv)));

		}catch(Exception $e){
			$this->message($e->getMessage() . PHP_EOL);
		}
	}

	/**
	 * Validate all inputs to this setup script. Ensures that all required values are available, where-ever they need to
	 * be loaded from (environment variables, Config API, or directly passed to this script via the cmd-line)
	 *
	 * @param bool           $forceRun Whether or not to force the setup (therefore skip checks around existing files)
	 * @param string         $forEnv   The environment that we want to output content for (mts, ite, or prod)
     *
     * @throws Exception if there were errors with the request or setup format.
	 */
	private function validateInputs($forceRun, $forEnv) {

		// Ensure we haven't already run before, or if we have, that force=1 is passed
        $this->validateRunOnce($forceRun);

		// Ensure that 'forEnv=' is specified on the cli, and ensure that it matches a RealMe environment
        $this->validateRealMeEnvironments($forEnv);

		// Ensure we have a config directory and that it's writeable by the web server
        $this->validateSimpleSamlConfig();

        // Ensure we have the necessary directory structures, and their visibility
        $this->validateDirectoryStructure();

        // Make sure we can create salts and passwords using the required libraries
        $this->validateCryptographicLibraries();

        // Ensure we have the certificates in the correct places.
        $this->validateCertificates();

        // Ensure the entityID is valid, and the privacy realm and service name are correct
        $this->validateEntityID();

		// Make sure we have an authncontext for each environment.
		$this->validateAuthnContext();

        // Ensure the consumer URL is correct
        $this->validateConsumerAssertionURL($forEnv);

        // Ensure data required for metadata XML output exists
        $this->validateMetadata();

		// Output validation errors, if any are found
		if(sizeof($this->errors) > 0) {
			$errorList = PHP_EOL . ' - ' . join(PHP_EOL . ' - ', $this->errors);

			throw new Exception(_t(
				'RealMeSetupTask.ERR_VALIDATION',
				'',
				'',
				array(
					'numissues' => sizeof($this->errors),
					'issues' => $errorList
				)
			));
		}

        $this->message(_t('RealMeSetupTask.VALIDATION_SUCCESS'));
	}

	private function createConfigReadmeFromTemplate() {
		// Create configuration files
		$this->message(sprintf(
			'Creating README file in %s from template dir %s',
			$this->service->getSimpleSamlConfigDir(),
			$this->getConfigurationTemplateDir()
		));

		$configDir = $this->getConfigurationTemplateDir();
		$templateFile = Controller::join_links($configDir, 'README.md');

		if(false === $this->isReadable($templateFile)) {
            throw new Exception(sprintf("Can't read README.md file at %s", $templateFile));
		}

		$this->writeConfigFile($templateFile, $this->getSimpleSAMLConfigReadmeFilePath());
	}

	/**
	 * Create primary configuration file and place in SimpleSAMLphp configuration directory
	 */
	private function createConfigFromTemplate() {
		$this->message(sprintf(
			'Creating config file in %s/config/ from config in template dir %s',
			$this->service->getSimpleSamlConfigDir(),
			$this->getConfigurationTemplateDir()
		));

		$configDir = $this->getConfigurationTemplateDir();
		$templateFile = Controller::join_links($configDir, 'config.php');

		if(false === $this->isReadable($templateFile)) {
			throw new Exception(sprintf("Can't read config.php file at %s", $templateFile));
		}

		$this->writeConfigFile(
			$templateFile,
			$this->getSimpleSAMLConfigFilePath(),
			array(
				'{{baseurlpath}}' => $this->service->getSimpleSamlBaseUrlPath(),
				'{{certdir}}' => $this->service->getCertDir(),
				'{{loggingdir}}' => $this->service->getLoggingDir(),
				'{{tempdir}}' => $this->service->getTempDir(),
				'{{metadatadir}}' => $this->service->getSimpleSamlMetadataDir(),
				'{{adminpassword}}' => $this->service->findOrMakeSimpleSAMLPassword(),
				'{{secretsalt}}' => $this->service->generateSimpleSAMLSalt(),
			)
		);
	}

	/**
	 * Create authentication sources configuration file and place in SimpleSAMLphp configuration directory
	 */
	private function createAuthSourcesFromTemplate() {
		$this->message(sprintf(
			'Creating authsources file in %s/config/ from config in template dir %s',
			$this->service->getSimpleSamlConfigDir(),
			$this->getConfigurationTemplateDir()
		));

		$configDir = $this->getConfigurationTemplateDir();

		$templateFile = Controller::join_links($configDir, 'authsources.php');

		if(false === $this->isReadable($templateFile)) {
			throw new Exception(sprintf("Can't read authsources.php file at %s", $templateFile));
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
		$this->message(sprintf(
			'Creating saml20-idp-remote file in %s/metadata/ from config in template dir %s',
			$this->service->getSimpleSamlConfigDir(),
			$this->getConfigurationTemplateDir())
		);

		$configDir = $this->getConfigurationTemplateDir();
		$templateFile = Controller::join_links($configDir, 'saml20-idp-remote.php');

		if(false === $this->isReadable($templateFile)) {
			throw new Exception(sprintf("Can't read saml20-idp-remote.php file at %s", $templateFile));
		}

		$this->writeConfigFile(
			$templateFile,
			$this->getSimpleSAMLMetadataFilePath()
		);
	}

	/**
	 * Outputs metadata template XML to console, so it can be sent to RealMe Operations team
	 *
	 * @param string $forEnv The RealMe environment to output metadata content for (e.g. mts, ite, prod).
	 */
	private function outputMetadataXmlContent($forEnv) {
		// Output metadata XML so that it can be sent to RealMe via the agency
		$this->message(sprintf(
			"Metadata XML is listed below for the '%s' RealMe environment, this should be sent to the agency so they "
				. "can pass it on to RealMe Operations staff" . PHP_EOL . PHP_EOL,
			$forEnv
		));

		$configDir = $this->getConfigurationTemplateDir();
		$templateFile = Controller::join_links($configDir, 'metadata.xml');

		if(false === $this->isReadable($templateFile)) {
			throw new Exception(sprintf("Can't read metadata.xml file at %s", $templateFile));
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

		// If the parent folder of $newFilePath doesn't already exist, then create it
		// Specifically only look one level higher, we already validate that everything else exists and can be written
		$fileParentDir = dirname($newFilePath);
		if(false === is_dir($fileParentDir)) {
			mkdir($fileParentDir, 0744);
		}

		if(false === file_put_contents($newFilePath, $configText)) {
            throw new Exception(
                sprintf("Could not write template file '%s' to location '%s'", $templatePath, $newFilePath)
            );
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

		if(true === is_array($replacements)) {
			$configText = str_replace(array_keys($replacements), array_values($replacements), $configText);
		}

		return $configText;
	}

	/**
	 * @return string The path to the README file we create to help identify this configuration directory
	 */
	private function getSimpleSAMLConfigReadmeFilePath() {
		return sprintf('%s/README.md', $this->service->getSimpleSamlConfigDir());
	}

	/**
	 * @return string The path to the main SimpleSAMLphp configuration file, once written
	 */
	private function getSimpleSAMLConfigFilePath() {
		return sprintf('%s/config.php', $this->service->getSimpleSamlConfigDir());
	}

	/**
	 * @return string The path to the authentication sources configuration file, once written
	 */
	private function getSimpleSAMLAuthSourcesFilePath() {
		return sprintf('%s/authsources.php', $this->service->getSimpleSamlConfigDir());
	}

	/**
	 * @return string The path to the metadata configuration file, once written
	 */
	private function getSimpleSAMLMetadataFilePath() {
		return sprintf('%s/metadata/saml20-idp-remote.php', $this->service->getSimpleSamlConfigDir());
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

		if(!$dir || false === $this->isReadable($dir)) {
			$dir = REALME_MODULE_PATH . '/templates/simplesaml-configuration';
		}

		return Controller::join_links(BASE_PATH, $dir);
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
     * The entity ID will pass validation, but raise an exception if the format of the service name and privacy realm
     * are in the incorrect format.
     * The service name and privacy realm need to be under 10 chars eg.
     * http://hostname.domain/serviceName/privacyRealm
     *
     * @return void
     */
    private function validateEntityID () {
        foreach ($this->service->getAllowedRealMeEnvironments() as $env) {
            $entityId = $this->service->getEntityIDForEnvironment($env);

            if (true === is_null($entityId)) {
                $this->errors[] = _t('RealMeSetupTask.ERR_CONFIG_NO_ENTITYID', '', '', array('env' => $env));
            }

			// check it's not localhost and HTTPS.
			$urlParts = parse_url($entityId);
			if('localhost' === $urlParts['host'] || 'http' === $urlParts['scheme']){
				$this->errors[] = _t('RealMeSetupTask.ERR_CONFIG_ENTITYID', '', '',
                    array(
                        'env' => $env,
                        'entityId' => $entityId
                    )
                );
			}

			$path = ltrim($urlParts['path']);
			$urlParts = preg_split("/\\//", $path);

            // Validate Service Name
            $serviceName = array_pop($urlParts);
            if (mb_strlen($serviceName) > 10 || 0 === mb_strlen($serviceName) ) {
                $this->errors[] = _t('RealMeSetupTask.ERR_CONFIG_ENTITYID_SERVICE_NAME', '', '',
                    array(
                        'env' => $env,
                        'serviceName' => $serviceName,
                        'entityId' => $entityId
                    )
                );
            }

            // Validate Privacy Realm
            $privacyRealm = array_pop($urlParts);
            if (mb_strlen($privacyRealm) > 10 || 0 === mb_strlen($privacyRealm) ) {
                $this->errors[] = _t('RealMeSetupTask.ERR_CONFIG_ENTITYID_PRIVACY_REALM', '', '',
                    array(
                        'env' => $env,
                        'privacyRealm' => $privacyRealm ,
                        'entityId' => $entityId
                    )
                );
            }
        }
    }

	/**
	 * Ensure we have an authncontext (how secure auth we require for each environment)
	 *
	 * e.g. urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength
	 */
	private function validateAuthNContext(){
		foreach ($this->service->getAllowedRealMeEnvironments() as $env) {
			if (true === is_null($this->service->getAuthnContextForEnvironment($env))) {
				$this->errors[] = _t('RealMeSetupTask.ERR_CONFIG_NO_AUTHNCONTEXT', '', '', array('env' => $env));
			}
		}
	}

    /**
     * Validate that this script has only been run once. It must be deliberate to overwrite the configuration settings
     * as this could potentially change the privacy realms and the associated FLT. You will loose context and the
     * matching of users to FLTs if this is the case.
     *
     * @param $forceRun boolean
     */
    private function validateRunOnce ($forceRun) {

        $existingFiles = array(
            $this->getSimpleSAMLConfigFilePath(),
            $this->getSimpleSAMLAuthSourcesFilePath(),
            $this->getSimpleSAMLMetadataFilePath()
        );

        foreach ($existingFiles as $filePath) {
            if (true === file_exists($filePath) && false === $forceRun) {
                $this->errors[] = _t('RealMeSetupTask.ERR_ALREADY_RUN', '', '', array('path' => $filePath));
            }
        }
    }

    /**
     * Ensure's the environment we're building the setup for exists.
     *
     * @param $forEnv string
     */
    private function validateRealMeEnvironments ($forEnv) {
        $allowedEnvs = $this->service->getAllowedRealMeEnvironments();
        if(0 === mb_strlen($forEnv)){
            $this->errors[] = _t(
                'RealMeSetupTask.ERR_ENV_NOT_SPECIFIED',
                '',
                '',
                array(
                    'allowedEnvs' => join(', ', $allowedEnvs)
                )
            );
            return;
        }

        if (false === in_array($forEnv, $allowedEnvs)) {
            $this->errors[] = _t(
                'RealMeSetupTask.ERR_ENV_NOT_ALLOWED',
                '',
                '',
                array(
                    'env' => $forEnv,
                    'allowedEnvs' => join(', ', $allowedEnvs)
                )
            );
        }
    }

    /**
     * Validates the SimpleSaml Config directories and ensures this script can write to them. Note: it's important that
     * this script is run by the web user as this will be the user accessing the files, and writing to the log.
     *
     * @return array
     */
    private function validateSimpleSamlConfig () {
        if (true === is_null($this->service->getSimpleSamlConfigDir())) {
            $this->errors[] = _t('RealMeSetupTask.ERR_SIMPLE_SAML_CONFIG_DIR_MISSING');
        } elseif (false === $this->isWriteable($this->service->getSimpleSamlConfigDir())) {
            $this->errors[] = _t(
                'RealMeSetupTask.ERR_SIMPLE_SAML_CONFIG_DIR_NOT_WRITEABLE',
                '',
                '',
                array('dir' => $this->service->getSimpleSamlConfigDir())
            );
        }

        if (true === is_null($this->service->getSimpleSamlBaseUrlPath())) {
            $this->errors[] = _t('RealMeSetupTask.ERR_BASE_DIR_MISSING');
        }
    }

    /**
     * Ensures that the directory structure is correct and the necessary directories are writable.
     */
    private function validateDirectoryStructure () {
        if (true === is_null($this->service->getCertDir())) {
            $this->errors[] = _t('RealMeSetupTask.ERR_CERT_DIR_MISSING');
        } elseif (false === $this->isReadable($this->service->getCertDir())) {
            $this->errors[] = _t(
                'RealMeSetupTask.ERR_CERT_DIR_NOT_READABLE',
                '',
                '',
                array('dir' => $this->service->getCertDir())
            );
        }

        if (true === is_null($this->service->getLoggingDir())) {
            $this->errors[] = _t('RealMeSetupTask.ERR_LOG_DIR_MISSING');
        } elseif (false === $this->isWriteable($this->service->getLoggingDir())) {
            $this->errors[] = _t(
                'RealMeSetupTask.ERR_LOG_DIR_NOT_WRITEABLE',
                '',
                '',
                array('dir' => $this->service->getLoggingDir())
            );
        }

        if (true === is_null($this->service->getTempDir())) {
            $this->errors[] = _t('RealMeSetupTask.ERR_TEMP_DIR_MISSING');
        } elseif (
            false === $this->isWriteable($this->service->getTempDir())
            && false === $this->isWriteable(dirname($this->service->getTempDir()))
        ) {
            $this->errors[] = _t(
                'RealMeSetupTask.ERR_TEMP_DIR_NOT_WRITEABLE',
                '',
                '',
                array('dir' => $this->service->getTempDir())
            );
        }
    }

    /**
     * Ensures that the required metadata is filled out correctly in the realme configuration.
     */
    private function validateMetadata () {
        if (true === is_null($this->service->getMetadataOrganisationName())) {
            $this->errors[] = _t('RealMeSetupTask.ERR_CONFIG_NO_ORGANISATION_NAME');
        }

        if (true === is_null($this->service->getMetadataOrganisationDisplayName())) {
            $this->errors[] = _t('RealMeSetupTask.ERR_CONFIG_NO_ORGANISATION_DISPLAY_NAME');
        }

        if (true === is_null($this->service->getMetadataOrganisationUrl())) {
            $this->errors[] = _t('RealMeSetupTask.ERR_CONFIG_NO_ORGANISATION_URL');
        }

        $contact = $this->service->getMetadataContactSupport();
        if (true === is_null($contact['company']) || true === is_null($contact['firstNames']) || is_null($contact['surname'])) {
            $this->errors[] = _t('RealMeSetupTask.ERR_CONFIG_NO_SUPPORT_CONTACT');
        }
    }

    /**
     * Ensures the certificates are readable and that the service can sign and unencrypt using them
     */
    private function validateCertificates () {
        $signingCertFile = $this->service->getSigningCertPath();
        if (true === is_null($signingCertFile) || false === $this->isReadable($signingCertFile)) {
            $this->errors[] = _t(
                'RealMeSetupTask.ERR_CERT_NO_SIGNING_CERT',
                '',
                '',
                array(
                    'const' => 'REALME_SIGNING_CERT_FILENAME'
                )
            );
        } elseif (true === is_null($this->service->getSigningCertContent())) {
            // Signing cert exists, but doesn't include BEGIN/END CERTIFICATE lines, or doesn't contain the cert
            $this->errors[] = _t(
                'RealMeSetupTask.ERR_CERT_SIGNING_CERT_CONTENT',
                '',
                '',
                array('file' => $this->service->getSigningCertPath())
            );
        }

        $mutualCertFile = $this->service->getMutualCertPath();
        if (true === is_null($mutualCertFile) || false === $this->isReadable($mutualCertFile)) {
            $this->errors[] = _t(
                'RealMeSetupTask.ERR_CERT_NO_MUTUAL_CERT',
                '',
                '',
                array(
                    'const' => 'REALME_MUTUAL_CERT_FILENAME'
                )
            );
        }
    }

    /**
     * Ensures the server has the correct cryptographic libraries installed by trying to generate salts and passwords
     * using these libraries
     */
    private function validateCryptographicLibraries () {
        if (true === is_null($this->service->findOrMakeSimpleSAMLPassword())) {
            $this->errors[] = _t('RealMeSetupTask.ERR_SIMPLE_SAML_NO_ADMIN_PASS');
        }

        if (true === is_null($this->service->generateSimpleSAMLSalt())) {
            $this->errors[] = _t('RealMeSetupTask.ERR_SIMPLE_SAML_NO_SALT');
        }
    }

    /**
     * Ensure the consumerAssertionUrl is correct for this environment
     *
     * @param $forEnv
     */
    private function validateConsumerAssertionURL ($forEnv) {
        // Ensure the assertion consumer service location exists
        if (true === is_null($this->service->getAssertionConsumerServiceUrlForEnvironment($forEnv))) {
            $this->errors[] = _t('RealMeSetupTask.ERR_CONFIG_NO_ASSERTION_SERVICE_URL', '', '', array('env' => $forEnv));
        }
    }
}