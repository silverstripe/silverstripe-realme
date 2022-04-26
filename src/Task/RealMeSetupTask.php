<?php

namespace SilverStripe\RealMe\Task;

use Exception;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\RealMe\RealMeService;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Dev\BuildTask;

/**
 * Class RealMeSetupTask
 *
 * This class is intended to be run by a server administrator once the module is setup and configured via environment
 * variables, and YML fragments. The following tasks are done by this build task:
 *
 * - Check to ensure that the task is being run from the cmdline (not in the browser, it's too sensitive)
 * - Check to ensure that the task hasn't already been run, and if it has, fail unless `force=1` is passed to the script
 * - Validate all required values have been added in the appropriate place, and provide appropriate errors if not
 * - Output metadata XML that must be submitted to RealMe in order to integrate with ITE and Production environments
 */
class RealMeSetupTask extends BuildTask
{
    private static $segment = 'RealMeSetupTask';

    private static $dependencies = [
        'Service' => '%$' . RealMeService::class,
    ];

    protected $title = "RealMe Setup Task";

    protected $description = 'Validates a realme configuration & creates the resources needed to integrate with realme';

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
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        try {
            // Ensure we are running on the command-line, and not running in a browser
            if (false === Director::is_cli()) {
                throw new Exception(_t(
                    self::class . '.ERR_NOT_CLI',
                    'This task can only be run from the command-line, not in your browser.'
                ));
            }

            // Validate all required values exist
            $forEnv = $request->getVar('forEnv');

            // Throws an exception if there was a problem with the config.
            $this->validateInputs($forEnv);

            $this->outputMetadataXmlContent($forEnv);

            $this->message(PHP_EOL . _t(
                self::class . '.BUILD_FINISH',
                'RealMe setup complete. Please copy the XML into a file for upload to the {env} environment or DIA ' .
                'to complete the integration',
                array('env' => $forEnv)
            ));
        } catch (Exception $e) {
            $this->message($e->getMessage() . PHP_EOL);
        }
    }

    /**
     * @param RealMeService $service
     * @return $this
     */
    public function setService($service)
    {
        $this->service = $service;

        return $this;
    }

    /**
     * Validate all inputs to this setup script. Ensures that all required values are available, where-ever they need to
     * be loaded from (environment variables, Config API, or directly passed to this script via the cmd-line)
     *
     * @param string $forEnv The environment that we want to output content for (mts, ite, or prod)
     *
     * @throws Exception if there were errors with the request or setup format.
     */
    private function validateInputs($forEnv)
    {
        // Ensure that 'forEnv=' is specified on the cli, and ensure that it matches a RealMe environment
        $this->validateRealMeEnvironments($forEnv);

        // Ensure we have the necessary directory structures, and their visibility
        $this->validateDirectoryStructure();

        // Ensure we have the certificates in the correct places.
        $this->validateCertificates();

        // Ensure the entityID is valid, and the privacy realm and service name are correct
        $this->validateEntityID($forEnv);

        // Make sure we have an authncontext for each environment.
        $this->validateAuthNContext();

        // Ensure data required for metadata XML output exists
        $this->validateMetadata();

        // Output validation errors, if any are found
        if (sizeof($this->errors ?? []) > 0) {
            $errorList = PHP_EOL . ' - ' . join(PHP_EOL . ' - ', $this->errors);

            throw new Exception(_t(
                self::class . '.ERR_VALIDATION',
                'There were {numissues} issue(s) found during validation that must be fixed prior to setup: {issues}',
                array(
                    'numissues' => sizeof($this->errors ?? []),
                    'issues' => $errorList
                )
            ));
        }

        $this->message(_t(
            self::class . '.VALIDATION_SUCCESS',
            'Validation succeeded, continuing with setup...'
        ));
    }

    /**
     * Outputs metadata template XML to console, so it can be sent to RealMe Operations team
     *
     * @param string $forEnv The RealMe environment to output metadata content for (e.g. mts, ite, prod).
     */
    private function outputMetadataXmlContent($forEnv)
    {
        // Output metadata XML so that it can be sent to RealMe via the agency
        $this->message(_t(
            self::class . '.OUPUT_PREFIX',
            'Metadata XML is listed below for the \'{env}\' RealMe environment, this should be sent to the agency so ' .
                'they can pass it on to RealMe Operations staff',
            ['env' => $forEnv]
        ) . PHP_EOL . PHP_EOL);

        $configDir = $this->getConfigurationTemplateDir();
        $templateFile = Controller::join_links($configDir, 'metadata.xml');

        if (false === $this->isReadable($templateFile)) {
            throw new Exception(sprintf("Can't read metadata.xml file at %s", $templateFile));
        }

        $supportContact = $this->service->getMetadataContactSupport();

        $message = $this->replaceTemplateContents(
            $templateFile,
            array(
                '{{entityID}}' => $this->service->getSPEntityID(),
                '{{certificate-data}}' => $this->service->getSPCertContent(),
                '{{nameidformat}}' => $this->service->getNameIdFormat(),
                '{{acs-url}}' => $this->service->getAssertionConsumerServiceUrlForEnvironment($forEnv),
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
     * Replace content in a template file with an array of replacements
     *
     * @param string $templatePath The path to the template file
     * @param array|null $replacements An array of '{{variable}}' => 'value' replacements
     * @return string The contents, with all {{variables}} replaced
     */
    private function replaceTemplateContents($templatePath, $replacements = null)
    {
        $configText = file_get_contents($templatePath ?? '');

        if (true === is_array($replacements)) {
            $configText = str_replace(
                array_keys($replacements ?? []),
                array_values($replacements ?? []),
                $configText ?? ''
            );
        }

        return $configText;
    }

    /**
     * @return string The full path to RealMe configuration
     */
    private function getConfigurationTemplateDir()
    {
        $dir = $this->config()->template_config_dir;
        $path = Controller::join_links(BASE_PATH, $dir);

        if ($dir && false !== $this->isReadable($path)) {
            return $path;
        }

        $path = ModuleLoader::inst()->getManifest()->getModule('realme')->getPath();

        return $path . '/templates/saml-conf';
    }

    /**
     * Output a message to the console
     * @param string $message
     * @return void
     */
    private function message($message)
    {
        echo $message . PHP_EOL;
    }

    /**
     * Thin wrapper around is_readable(), used mainly so we can test this class completely
     *
     * @param string $filename The filename or directory to test
     * @return bool true if the file/dir is readable, false if not
     */
    private function isReadable($filename)
    {
        return is_readable($filename ?? '');
    }

    /**
     * The entity ID will pass validation, but raise an exception if the format of the service name and privacy realm
     * are in the incorrect format.
     * The service name and privacy realm need to be under 10 chars eg.
     * http://hostname.domain/serviceName/privacyRealm
     *
     * @param string $forEnv
     * @return void
     */
    private function validateEntityID($forEnv)
    {
        $entityId = $this->service->getSPEntityID();

        if (is_null($entityId)) {
            $this->errors[] = _t(
                self::class . '.ERR_CONFIG_NO_ENTITYID',
                'No entityID specified for environment \'{env}\'. Specify this in your YML configuration, see the ' .
                    'module documentation for more details',
                array('env' => $forEnv)
            );
        }

        // make sure the entityID is a valid URL
        $entityId = filter_var($entityId, FILTER_VALIDATE_URL);
        if ($entityId === false) {
            $this->errors[] = _t(
                self::class . '.ERR_CONFIG_ENTITYID',
                'The Entity ID (\'{entityId}\') must be https, not be \'localhost\', and must contain a valid ' .
                    'service name and privacy realm e.g. https://my-realme-integration.govt.nz/p-realm/s-name',
                array(
                    'entityId' => $entityId
                )
            );

            // invalid entity id, no point continuing.
            return;
        }

        // check it's not localhost and HTTPS. and make sure we have a host / scheme
        $urlParts = parse_url($entityId ?? '');
        if ($urlParts['host'] === 'localhost' || $urlParts['scheme'] === 'http') {
            $this->errors[] = _t(
                self::class . '.ERR_CONFIG_ENTITYID',
                'The Entity ID (\'{entityId}\') must be https, not be \'localhost\', and must contain a valid ' .
                    'service name and privacy realm e.g. https://my-realme-integration.govt.nz/p-realm/s-name',
                array(
                    'entityId' => $entityId
                )
            );

            // if there's this much wrong, we want them to fix it first.
            return;
        }

        $path = ltrim($urlParts['path'] ?? '');
        $urlParts = preg_split("/\\//", $path ?? '');


        // A valid Entity ID is in the form of "https://www.domain.govt.nz/<privacy-realm>/<service-name>"
        // Validate Service Name
        $serviceName = array_pop($urlParts);
        if (mb_strlen($serviceName ?? '') > 20 || 0 === mb_strlen($serviceName ?? '')) {
            $this->errors[] = _t(
                self::class . '.ERR_CONFIG_ENTITYID_SERVICE_NAME',
                'The service name \'{serviceName}\' must be a maximum of 20 characters and not blank for entityID ' .
                    '\'{entityId}\'',
                array(
                    'serviceName' => $serviceName,
                    'entityId' => $entityId
                )
            );
        }

        // Validate Privacy Realm
        $privacyRealm = array_pop($urlParts);
        if (null === $privacyRealm || 0 === mb_strlen($privacyRealm ?? '')) {
            $this->errors[] = _t(
                self::class . '.ERR_CONFIG_ENTITYID_PRIVACY_REALM',
                'The privacy realm \'{privacyRealm}\' must not be blank for entityID \'{entityId}\'',
                array(
                    'privacyRealm' => $privacyRealm,
                    'entityId' => $entityId
                )
            );
        }
    }

    /**
     * Ensure we have an authncontext (how secure auth we require for each environment)
     *
     * e.g. urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength
     */
    private function validateAuthNContext()
    {
        foreach ($this->service->getAllowedRealMeEnvironments() as $env) {
            $context = $this->service->getAuthnContextForEnvironment($env);
            if (is_null($context)) {
                $this->errors[] = _t(
                    self::class . '.ERR_CONFIG_NO_AUTHNCONTEXT',
                    'No AuthnContext specified for environment \'{env}\'. Specify this in your YML configuration, ' .
                        'see the module documentation for more details',
                    array('env' => $env)
                );
            }

            if (!in_array($context, $this->service->getAllowedAuthNContextList() ?? [])) {
                $this->errors[] = _t(
                    self::class . '.ERR_CONFIG_INVALID_AUTHNCONTEXT',
                    'The AuthnContext specified for environment \'{env}\' is invalid, please check your configuration',
                    array('env' => $env)
                );
            }
        }
    }

    /**
     * Ensure's the environment we're building the setup for exists.
     *
     * @param string $forEnv The environment that we're going to configure with this run.
     */
    private function validateRealMeEnvironments($forEnv)
    {
        $allowedEnvs = $this->service->getAllowedRealMeEnvironments();
        if (0 === mb_strlen($forEnv ?? '')) {
            $this->errors[] = _t(
                self::class . '.ERR_ENV_NOT_SPECIFIED',
                'The RealMe environment was not specified on the cli It must be one of: {allowedEnvs} ' .
                    'e.g. vendor/bin/sake dev/tasks/RealMeSetupTask forEnv=mts',
                array(
                    'allowedEnvs' => join(', ', $allowedEnvs)
                )
            );
            return;
        }

        if (false === in_array($forEnv, $allowedEnvs ?? [])) {
            $this->errors[] = _t(
                self::class . '.ERR_ENV_NOT_ALLOWED',
                'The RealMe environment specified on the cli (\'{env}\') is not allowed. ' .
                    'It must be one of: {allowedEnvs}',
                array(
                    'env' => $forEnv,
                    'allowedEnvs' => join(', ', $allowedEnvs)
                )
            );
        }
    }

    /**
     * Ensures that the directory structure is correct and the necessary directories are writable.
     */
    private function validateDirectoryStructure()
    {
        if (is_null($this->service->getCertDir())) {
            $this->errors[] = _t(
                self::class . '.ERR_CERT_DIR_MISSING',
                'No certificate dir is specified. Define the REALME_CERT_DIR environment variable in your .env file'
            );
        } elseif (!$this->isReadable($this->service->getCertDir())) {
            $this->errors[] = _t(
                self::class . '.ERR_CERT_DIR_NOT_READABLE',
                'Certificate dir specified (\'{dir}\') must be created and be readable. Ensure permissions are set ' .
                    'correctly and the directory is absolute',
                array('dir' => $this->service->getCertDir())
            );
        }
    }

    /**
     * Ensures that the required metadata is filled out correctly in the realme configuration.
     */
    private function validateMetadata()
    {
        if (is_null($this->service->getMetadataOrganisationName())) {
            $this->errors[] = _t(
                self::class . '.ERR_CONFIG_NO_ORGANISATION_NAME',
                'No organisation name is specified in YML configuration. Ensure the \'metadata_organisation_name\' ' .
                    'value is defined in your YML configuration'
            );
        }

        if (is_null($this->service->getMetadataOrganisationDisplayName())) {
            $this->errors[] = _t(
                self::class . '.ERR_CONFIG_NO_ORGANISATION_DISPLAY_NAME',
                'No organisation display name is specified in YML configuration. Ensure the ' .
                    '\'metadata_organisation_display_name\' value is defined in your YML configuration'
            );
        }

        if (is_null($this->service->getMetadataOrganisationUrl())) {
            $this->errors[] = _t(
                self::class . '.ERR_CONFIG_NO_ORGANISATION_URL',
                'No organisation URL is specified in YML configuration. Ensure the \'metadata_organisation_url\' ' .
                    'value is defined in your YML configuration'
            );
        }

        $contact = $this->service->getMetadataContactSupport();
        if (is_null($contact['company']) || is_null($contact['firstNames']) || is_null($contact['surname'])) {
            $this->errors[] = _t(
                self::class . '.ERR_CONFIG_NO_SUPPORT_CONTACT',
                'Support contact detail is missing from YML configuration. Ensure the following values are defined ' .
                    'in the YML configuration: metadata_contact_support_company, metadata_contact_support_firstnames,' .
                    ' metadata_contact_support_surname'
            );
        }
    }

    /**
     * Ensures the certificates are readable and that the service can sign and unencrypt using them
     */
    private function validateCertificates()
    {
        $signingCertFile = $this->service->getSigningCertPath();
        if (is_null($signingCertFile) || !$this->isReadable($signingCertFile)) {
            $this->errors[] = _t(
                self::class . '.ERR_CERT_NO_SIGNING_CERT',
                'No SAML signing PEM certificate defined, or the file can\'t be read. Define the {const} environment ' .
                    'variable in your .env file, and ensure the file exists in the certificate directory',
                array(
                    'const' => 'REALME_SIGNING_CERT_FILENAME'
                )
            );
        } elseif (is_null($this->service->getSPCertContent())) {
            // Signing cert exists, but doesn't include BEGIN/END CERTIFICATE lines, or doesn't contain the cert
            $this->errors[] = _t(
                self::class . '.ERR_CERT_SIGNING_CERT_CONTENT',
                'The file specified for the signing certificate ({file}) does not contain a valid certificate ' .
                    '(beginning with -----BEGIN CERTIFICATE-----). Check this file to ensure it contains the ' .
                    'certificate and private key',
                array('file' => $this->service->getSigningCertPath())
            );
        }
    }
}
