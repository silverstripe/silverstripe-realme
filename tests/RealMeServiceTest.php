<?php

namespace SilverStripe\RealMe\Tests;

use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\RealMe\RealMeService;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

class RealMeServiceTest extends SapphireTest
{
    private $pathForTempCertificate;

    /**
     * @var RealMeService
     */
    private $service;

    public function testGetCertificateContents()
    {
        $this->pathForTempCertificate = ASSETS_PATH . '/tmpcert.pem';

        /**
         * Test standard certificate
         */

        $contents = file_get_contents(BASE_PATH . '/realme/tests/certs/standard_cert.pem');

        // Strip carriage returns
        $contents = str_replace("\r", '', $contents);

        $path = $this->pathForTempCertificate;
        file_put_contents($path, $contents);

        /** @var RealMeService $service */
        $service = Injector::inst()->get(RealMeService::class);

        $this->assertEquals('Redacted private key goes here', $service->getCertificateContents($path, 'key'));
        $this->assertEquals('Redacted certificate goes here', $service->getCertificateContents($path, 'certificate'));

        unlink($path);

        /**
         * Test certificate with RSA private key
         */

        $contents = file_get_contents(BASE_PATH . '/realme/tests/certs/rsa_cert.pem');

        // Strip carriage returns
        $contents = str_replace("\r", '', $contents);

        $path = $this->pathForTempCertificate;
        file_put_contents($path, $contents);

        /** @var RealMeService $service */
        $service = Injector::inst()->get(RealMeService::class);
        $this->assertEquals('Redacted private key goes here', $service->getCertificateContents($path, 'key'));
        $this->assertEquals('Redacted certificate goes here', $service->getCertificateContents($path, 'certificate'));

        unlink($path);
    }

    public function testGetAuth()
    {
        $auth = $this->service->getAuth();
        $this->assertTrue(get_class($auth) === 'OneLogin_Saml2_Auth');

        // Service Provider settings
        $spData = $auth->getSettings()->getSPData();
        $this->assertSame('https://example.com/realm/service', $spData['entityId']);
        $this->assertSame('https://example.com/Security/realme/acs', $spData['assertionConsumerService']['url']);
        $this->assertSame('urn:oasis:names:tc:SAML:2.0:nameid-format:persistent', $spData['NameIDFormat']);

        // Identity Provider settings
        $idpData = $auth->getSettings()->getIdPData();
        $this->assertSame('https://mts.realme.govt.nz/saml2', $idpData['entityId']);
        $this->assertSame('https://mts.realme.govt.nz/logon-mts/mtsEntryPoint', $idpData['singleSignOnService']['url']);

        // Security settings
        $securityData = $auth->getSettings()->getSecurityData();
        $this->assertSame('urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength', $securityData['requestedAuthnContext'][0]);
    }

    public function testGetAuthCustomSPEntityId()
    {
        Config::inst()->update(RealMeService::class, 'sp_entity_ids', ['mts' => 'https://example.com/custom-realm/custom-service']);
        $spData = $this->service->getAuth()->getSettings()->getSPData();
        $this->assertSame('https://example.com/custom-realm/custom-service', $spData['entityId']);
    }

    public function testGetAuthCustomIdPEntityId()
    {
        Config::inst()->update(RealMeService::class, 'idp_entity_ids', ['mts' => [ 'login' => 'https://example.com/idp-entry']]);
        $idpData = $this->service->getAuth()->getSettings()->getIdPData();
        $this->assertSame('https://example.com/idp-entry', $idpData['entityId']);
    }

    public function testGetAuthCustomAuthnContext()
    {
        Config::inst()->update(RealMeService::class, 'authn_contexts', ['mts' => 'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength::OTP:Mobile:SMS']);
        $securityData = $this->service->getAuth()->getSettings()->getSecurityData();
        $this->assertSame('urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength::OTP:Mobile:SMS', $securityData['requestedAuthnContext'][0]);
    }

    public function setUpOnce()
    {
        parent::setUpOnce();

        Environment::putEnv('REALME_CERT_DIR', BASE_PATH . '/realme/tests/certs');
        Environment::putEnv('REALME_SIGNING_CERT_FILENAME', 'standard_cert.pem');
    }

    public function setUp()
    {
        parent::setUp();
        $this->service = Injector::inst()->get(RealMeService::class);

        // Configure for login integration and mts by default
        Config::inst()->update(RealMeService::class, 'sp_entity_ids', ['mts' => 'https://example.com/realm/service']);
        Config::inst()->update(RealMeService::class, 'metadata_assertion_service_domains', ['mts' => 'https://example.com']);
        Config::inst()->update(RealMeService::class, 'authn_contexts', ['mts' => 'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength']);
    }

    public function tearDownOnce()
    {
        parent::tearDownOnce();

        // Ensure $this->pathForTempCertificate is unlink'd (otherwise it won't get unlinked if the test fails)
        if(file_exists($this->pathForTempCertificate)) {
            unlink($this->pathForTempCertificate);
        }
    }
}
