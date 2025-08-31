<?php
namespace WpMigrate\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WpMigrate\Rest\Api;
use WpMigrate\Security\HmacAuth;

class ApiDiagTest extends TestCase
{
    public function testDiagSettingsReturnsMaskedSettings(): void
    {
        /** @var HmacAuth|\PHPUnit\Framework\MockObject\MockObject $auth */
        $auth = $this->getMockBuilder(HmacAuth::class)->disableOriginalConstructor()->getMock();
        $auth->method('get_masked_settings')->willReturn([
            'peer_url' => 'https://staging.example.com',
            'key_fingerprint' => 'abc123def456',
        ]);

        $api = new Api($auth);
        $req = new \WP_REST_Request();
        $req->set_method('GET');
        $req->set_route('/migrate/v1/diag/settings');

        $resp = $api->diag_settings($req);
        $data = $resp->get_data();

        $this->assertTrue($data['ok']);
        $this->assertEquals('https://staging.example.com', $data['settings']['peer_url']);
        $this->assertEquals('abc123def456', $data['settings']['key_fingerprint']);
    }

    public function testDiagHeadersEchoesHeadersAndBodyHash(): void
    {
        /** @var HmacAuth|\PHPUnit\Framework\MockObject\MockObject $auth */
        $auth = $this->getMockBuilder(HmacAuth::class)->disableOriginalConstructor()->getMock();
        $api = new Api($auth);

        $req = new \WP_REST_Request();
        $req->set_method('POST');
        $req->set_route('/migrate/v1/diag/headers');
        $req->set_headers([
            'x-custom-header' => ['Value123'],
            'x_mig_test' => ['underscore'],
        ]);
        $req->set_body('hello world');

        $resp = $api->diag_headers($req);
        $data = $resp->get_data();

        $this->assertTrue($data['ok']);
        $this->assertEquals('POST', $data['method']);
        $this->assertEquals('/migrate/v1/diag/headers', $data['route']);
        $this->assertArrayHasKey('received_headers', $data);
        $this->assertArrayHasKey('server_http', $data);
        $this->assertEquals(hash('sha256', 'hello world'), $data['body_sha256']);

        // Ensure our headers made it through
        $this->assertArrayHasKey('x-custom-header', $data['received_headers']);
        $this->assertEquals(['Value123'], $data['received_headers']['x-custom-header']);
        $this->assertArrayHasKey('x_mig_test', $data['received_headers']);
        $this->assertEquals(['underscore'], $data['received_headers']['x_mig_test']);
    }
}


