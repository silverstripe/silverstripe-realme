<?php
class RealMeServiceTest extends SapphireTest
{
    private $pathForTempCertificate;

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
        $service = Injector::inst()->get('RealMeService');

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
        $service = Injector::inst()->get('RealMeService');
        $this->assertEquals('Redacted private key goes here', $service->getCertificateContents($path, 'key'));
        $this->assertEquals('Redacted certificate goes here', $service->getCertificateContents($path, 'certificate'));

        unlink($path);
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
