<?php

namespace SilverStripe\RealMe\Tests;

use OneLogin\Saml2\Auth;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\TempFolder;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\RealMe\RealMeService;

class RealMeServiceTest extends SapphireTest
{
    private static $pathForTempCertificate;

    /**
     * @var RealMeService
     */
    private $service;

    public function testGetCertificateContents()
    {
        self::$pathForTempCertificate = TempFolder::getTempFolder(BASE_PATH) . '/tmpcert.pem';

        /**
         * Test standard certificate
         */

        $contents = file_get_contents(__DIR__ . '/certs/standard_cert.pem');

        // Strip carriage returns
        $contents = str_replace("\r", '', $contents);

        $path = self::$pathForTempCertificate;
        file_put_contents($path, $contents);

        /** @var RealMeService $service */
        $service = Injector::inst()->get(RealMeService::class);

        $this->assertEquals('Redacted private key goes here', $service->getCertificateContents($path, 'key'));
        $this->assertEquals('Redacted certificate goes here', $service->getCertificateContents($path, 'certificate'));

        unlink($path);

        /**
         * Test certificate with RSA private key
         */

        $contents = file_get_contents(__DIR__ . '/certs/rsa_cert.pem');

        // Strip carriage returns
        $contents = str_replace("\r", '', $contents);

        $path = self::$pathForTempCertificate;
        file_put_contents($path, $contents);

        /** @var RealMeService $service */
        $service = Injector::inst()->get(RealMeService::class);
        $this->assertEquals('Redacted private key goes here', $service->getCertificateContents($path, 'key'));
        $this->assertEquals('Redacted certificate goes here', $service->getCertificateContents($path, 'certificate'));

        unlink($path);
    }

    public function testGetAuth()
    {
        $auth = $this->service->getAuth(new NullHTTPRequest());
        $this->assertTrue(get_class($auth) === Auth::class);

        // Service Provider settings
        $spData = $auth->getSettings()->getSPData();
        $this->assertSame('https://example.com/realm/service', $spData['entityId']);
        $this->assertSame('https://example.com/Security/login/RealMe/acs', $spData['assertionConsumerService']['url']);
        $this->assertSame('urn:oasis:names:tc:SAML:2.0:nameid-format:persistent', $spData['NameIDFormat']);

        // Identity Provider settings
        $idpData = $auth->getSettings()->getIdPData();
        $this->assertSame('https://mts.realme.govt.nz/saml2', $idpData['entityId']);
        $this->assertSame('https://mts.realme.govt.nz/logon-mts/mtsEntryPoint', $idpData['singleSignOnService']['url']);

        // Security settings
        $securityData = $auth->getSettings()->getSecurityData();
        $this->assertSame(
            'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength',
            $securityData['requestedAuthnContext'][0]
        );
    }

    public function testGetAuthCustomSPEntityId()
    {
        Config::modify()->set(
            RealMeService::class,
            'sp_entity_ids',
            ['mts' => 'https://example.com/custom-realm/custom-service']
        );
        $spData = $this->service->getAuth(new NullHTTPRequest())->getSettings()->getSPData();
        $this->assertSame('https://example.com/custom-realm/custom-service', $spData['entityId']);
    }

    public function testGetAuthCustomIdPEntityId()
    {
        Config::modify()->set(
            RealMeService::class,
            'idp_entity_ids',
            ['mts' => ['login' => 'https://example.com/idp-entry']]
        );
        $idpData = $this->service->getAuth(new NullHTTPRequest())->getSettings()->getIdPData();
        $this->assertSame('https://example.com/idp-entry', $idpData['entityId']);
    }

    public function testGetAuthCustomAuthnContext()
    {
        Config::modify()->set(
            RealMeService::class,
            'authn_contexts',
            ['mts' => 'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength::OTP:Mobile:SMS']
        );
        $securityData = $this->service->getAuth(new NullHTTPRequest())->getSettings()->getSecurityData();
        $this->assertSame(
            'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength::OTP:Mobile:SMS',
            $securityData['requestedAuthnContext'][0]
        );
    }

    public static function setUpBeforeClass()
    {
        Environment::putEnv('REALME_CERT_DIR=' . __DIR__ . '/certs');
        Environment::putEnv('REALME_SIGNING_CERT_FILENAME=' . 'standard_cert.pem');

        parent::setUpBeforeClass();
    }

    protected function setUp()
    {
        parent::setUp();
        $this->service = Injector::inst()->create(RealMeService::class);

        // Configure for login integration and mts by default
        Config::modify()->set(RealMeService::class, 'sp_entity_ids', ['mts' => 'https://example.com/realm/service']);
        Config::modify()->set(
            RealMeService::class,
            'metadata_assertion_service_domains',
            ['mts' => 'https://example.com']
        );
        Config::modify()->set(
            RealMeService::class,
            'authn_contexts',
            ['mts' => 'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength']
        );
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();

        // Ensure self::$pathForTempCertificate is unlink'd (otherwise it won't get unlinked if the test fails)
        if (file_exists(self::$pathForTempCertificate)) {
            unlink(self::$pathForTempCertificate);
        }
    }
}
