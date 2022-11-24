<?php

namespace SilverStripe\RealMe\Tests;

use ReflectionMethod;
use ReflectionProperty;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\Module;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\RealMe\RealMeService;
use SilverStripe\RealMe\Task\RealMeSetupTask;
use SilverStripe\View\ThemeResourceLoader;

/**
 * Class RealMeSetupTaskTest
 * Setup to unit test the Setup task to make sure metadata is being generated correctly.
 */
class RealMeSetupTaskTest extends SapphireTest
{
    /**
     * Valid entity id's tobe used for context.
     * @var array
     */
    private static $validEntityIDs = array(
        RealMeService::ENV_MTS => "https://dev.your-website.govt.nz/p-realm/s-name",
        RealMeService::ENV_ITE => 'https://uat.your-website.govt.nz/p-realm/s-name',
        RealMeService::ENV_PROD => 'https://www.your-website.govt.nz/p-realm/s-name'
    );

    private static $authnEnvContexts = array(
        RealMeService::ENV_MTS => 'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength',
        RealMeService::ENV_ITE => 'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength',
        RealMeService::ENV_PROD => 'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength'
    );

    private static $metadata_assertion_urls = array(
        RealMeService::ENV_MTS => "https://dev.your-website.govt.nz/",
        RealMeService::ENV_ITE => "https://staging.your-website.govt.nz/",
        RealMeService::ENV_PROD => "https://www.your-website.govt.nz/"
    );

    /**
     * We need to make sure that if an invalid environment, it raises the correct errors for correction.
     * - invalid environment
     * - no environment
     *
     * We must also not raise an error if we pass a correctly configured environment.
     */
    public function testEnvironmentValidation()
    {
        // Setup our objects for testing through reflection
        $realMeService = new RealMeService();
        $realMeSetupTask = new RealMeSetupTask();

        $errors = new ReflectionProperty($realMeSetupTask, 'errors');
        $errors->setAccessible(true);

        $service = new ReflectionProperty($realMeSetupTask, 'service');
        $service->setAccessible(true);
        $service->setValue($realMeSetupTask, $realMeService);

        // Make sure there's no errors to begin.
        $this->assertCount(0, $errors->getValue($realMeSetupTask));

        // Test: Make an error
        $invalidEnv = "wrong-environment";
        $validateEnvironments = new ReflectionMethod($realMeSetupTask, 'validateRealMeEnvironments');
        $validateEnvironments->setAccessible(true);
        $validateEnvironments->invoke($realMeSetupTask, $invalidEnv);
        $this->assertCount(1, $errors->getValue($realMeSetupTask), "An invalid environment should raise an error");

        // reset errors & Make sure there's no errors to begin.
        $errors->setValue($realMeSetupTask, array());
        $this->assertCount(0, $errors->getValue($realMeSetupTask));

        // Test: No environment passed
        $noEnvironment = null;
        $validateEnvironments = new ReflectionMethod($realMeSetupTask, 'validateRealMeEnvironments');
        $validateEnvironments->setAccessible(true);
        $validateEnvironments->invoke($realMeSetupTask, $noEnvironment);
        $this->assertCount(1, $errors->getValue($realMeSetupTask), "Missing environment should raise an error");

        // reset errors &&  Make sure there's no errors to begin.
        $errors->setValue($realMeSetupTask, array());
        $this->assertCount(0, $errors->getValue($realMeSetupTask));

        // Test: allowed environments pass without error.
        $reflectionMethod = new ReflectionMethod($realMeService, 'getAllowedRealMeEnvironments');
        $reflectionMethod->setAccessible(true);
        foreach ($reflectionMethod->invoke($realMeService) as $validEnvironment) {
            $validateEnvironments->invoke($realMeSetupTask, $validEnvironment);
        }

        // Make sure there's no errors, they should all be valid
        $this->assertCount(0, $errors->getValue($realMeSetupTask), "valid environments should not raise an error");
    }

    /**
     * We need to make sure that there is an entity ID and that it's in the correct format for realme consumption
     * - It's present in the config
     * - it's not localhost
     * - it's not http (must be https)
     * - service name and privacy realm < 10 char.
     */
    public function testValidateEntityID()
    {
        $realMeService = new RealMeService();
        $realMeSetupTask = new RealMeSetupTask();

        $errors = new ReflectionProperty($realMeSetupTask, 'errors');
        $errors->setAccessible(true);

        $service = new ReflectionProperty($realMeSetupTask, 'service');
        $service->setAccessible(true);
        $service->setValue($realMeSetupTask, $realMeService);

        // Make sure there's no errors to begin.
        $this->assertCount(0, $errors->getValue($realMeSetupTask));

        // Test valid entityIds just in case they're different in this configuration.
        $config = Config::inst();
        $config->merge(RealMeService::class, 'sp_entity_ids', self::$validEntityIDs);

        // validate our list of valid entity IDs;
        $validateEntityId = new ReflectionMethod($realMeSetupTask, 'validateEntityID');
        $validateEntityId->setAccessible(true);
        $validateEntityId->invoke($realMeSetupTask, 'mts');

        // valid entityID's shouldn't have any issues
        $this->assertCount(0, $errors->getValue($realMeSetupTask));

        // TEST entityId missing.
        $entityIdList = self::$validEntityIDs;
        $entityIdList[RealMeService::ENV_MTS] = 'destroy-humans-with-incorrect-entity-ids';
        $config->merge(RealMeService::class, 'sp_entity_ids', $entityIdList);
        $validateEntityId->invoke($realMeSetupTask, 'mts');
        $this->assertCount(1, $errors->getValue($realMeSetupTask), 'validate entity id should fail for an invalid url');

        // reset errors &&  Make sure there's no errors to begin.
        $errors->setValue($realMeSetupTask, array());
        $this->assertCount(0, $errors->getValue($realMeSetupTask));

        // TEST entityId localhost.
        $entityIdList = self::$validEntityIDs;
        $entityIdList[RealMeService::ENV_MTS] = 'https://localhost/';
        $config->merge(RealMeService::class, 'sp_entity_ids', $entityIdList);
        $validateEntityId->invoke($realMeSetupTask, 'mts');
        $this->assertCount(1, $errors->getValue($realMeSetupTask), 'validate entity id should fail for localhost');

        $errors->setValue($realMeSetupTask, array());
        $this->assertCount(0, $errors->getValue($realMeSetupTask));

        // TEST entityId not http
        $entityIdList = self::$validEntityIDs;
        $entityIdList[RealMeService::ENV_MTS] = 'http://dev.realme-integration.govt.nz/p-realm/s-name';
        $config->merge(RealMeService::class, 'sp_entity_ids', $entityIdList);
        $validateEntityId->invoke($realMeSetupTask, 'mts');
        $this->assertCount(1, $errors->getValue($realMeSetupTask), 'validate entity id should fail for http');

        $errors->setValue($realMeSetupTask, array());
        $this->assertCount(0, $errors->getValue($realMeSetupTask));

        // TEST privacy realm /service name  missing
        $entityIdList = self::$validEntityIDs;
        $entityIdList[RealMeService::ENV_MTS] = 'https://dev.realme-integration.govt.nz/';
        $config->merge(RealMeService::class, 'sp_entity_ids', $entityIdList);
        $validateEntityId->invoke($realMeSetupTask, 'mts');
        $this->assertCount(
            2,
            $errors->getValue($realMeSetupTask),
            'validate entity id should fail for missing service name and privacy realm'
        );

        $errors->setValue($realMeSetupTask, array());
        $this->assertCount(0, $errors->getValue($realMeSetupTask));

        // TEST privacy realm
        // "https://www.domain.govt.nz/<privacy-realm>/<service-name>"
        $entityIdList = self::$validEntityIDs;
        $entityIdList[RealMeService::ENV_MTS] =
            'https://dev.realme-integration.govt.nz/s-name/privacy-realm-is-too-big';
        $config->merge(RealMeService::class, 'sp_entity_ids', $entityIdList);
        $validateEntityId->invoke($realMeSetupTask, 'mts');
        $this->assertCount(
            1,
            $errors->getValue($realMeSetupTask),
            'validate entity id should fail for privacy-realm-is-too-big'
        );

        $errors->setValue($realMeSetupTask, array());
        $this->assertCount(0, $errors->getValue($realMeSetupTask));

        // "https://www.domain.govt.nz/<privacy-realm>/<service-name>"
        $entityIdList = self::$validEntityIDs;
        $entityIdList[RealMeService::ENV_MTS] = 'https://dev.realme-integration.govt.nz/s-name';
        $config->merge(RealMeService::class, 'sp_entity_ids', $entityIdList);
        $validateEntityId->invoke($realMeSetupTask, 'mts');
        $this->assertCount(
            1,
            $errors->getValue($realMeSetupTask),
            'validate entity id should fail if privacy realm is missing'
        );

        $errors->setValue($realMeSetupTask, array());
        $this->assertCount(0, $errors->getValue($realMeSetupTask));
    }


    /**
     * We require an authn context for each environment to determine how secure to ask realme to validate.
     * - it should be present for each environment, and one of four pre-determined authncontexts.
     */
    public function testValidateAuthNContext()
    {
        $realMeService = new RealMeService();
        $realMeSetupTask = new RealMeSetupTask();

        $errors = new ReflectionProperty($realMeSetupTask, 'errors');
        $errors->setAccessible(true);

        $service = new ReflectionProperty($realMeSetupTask, 'service');
        $service->setAccessible(true);
        $service->setValue($realMeSetupTask, $realMeService);

        // Make sure there's no errors to begin.
        $this->assertCount(0, $errors->getValue($realMeSetupTask));

        // Test valid authnContexts just in case they're different in this configuration.
        $config = Config::inst();
        $config->merge(RealMeService::class, 'authn_contexts', self::$authnEnvContexts);

        // validate our list of valid entity IDs;
        $validateAuthNContext = new ReflectionMethod($realMeSetupTask, 'validateAuthNContext');
        $validateAuthNContext->setAccessible(true);
        $validateAuthNContext->invoke($realMeSetupTask);
        $this->assertCount(0, $errors->getValue($realMeSetupTask));

        $invalidAuthNContextList = self::$authnEnvContexts;
        $invalidAuthNContextList[RealMeService::ENV_MTS] = 'im-an-invalid-context';
        $config->merge(RealMeService::class, 'authn_contexts', $invalidAuthNContextList);

        $validateAuthNContext->invoke($realMeSetupTask);
        $this->assertCount(
            1,
            $errors->getValue($realMeSetupTask),
            "The authncontext validation should fail if invalid."
        );
    }

    /**
     * Ensure that setting the RealMeSetupTask template_config_dir config value
     * can adjust the path that the task looks in for its templates.
     * - ensure the default works
     * - ensure configuring a bad directory name falls back to the default
     * - ensure that configuring a good value uses the configured directory
     */
    public function testConfigurationTemplateDir()
    {
        /** @var Module $module */
        $module = ModuleLoader::inst()->getManifest()->getModule('realme');
        $fullPath = $module->getPath();
        $relativePath = $module->getRelativePath();

        $realMeSetupTask = new RealMeSetupTask();

        $getConfigurationTemplateDirMethod =
            new ReflectionMethod(RealMeSetupTask::class, 'getConfigurationTemplateDir');
        $getConfigurationTemplateDirMethod->setAccessible(true);

        $config = Config::inst();

        $config->set(RealMeSetupTask::class, 'template_config_dir', '');
        $this->assertEquals(
            $fullPath . '/templates/saml-conf',
            $getConfigurationTemplateDirMethod->invoke($realMeSetupTask),
            'Using no configuration for template_config_dir should use the default template directory.'
        );

        $config->set(RealMeSetupTask::class, 'template_config_dir', 'xyzzy');
        $this->assertEquals(
            $fullPath . '/templates/saml-conf',
            $getConfigurationTemplateDirMethod->invoke($realMeSetupTask),
            'Configuring a directory that does not exist should use the default template directory.'
        );

        $config->set(RealMeSetupTask::class, 'template_config_dir', $relativePath . '/tests');
        $this->assertEquals(
            $fullPath . '/tests', // doesn't contain templates, but does exist
            $getConfigurationTemplateDirMethod->invoke($realMeSetupTask),
            'Configuring a directory that exists should use the configured template directory.'
        );
    }
}
