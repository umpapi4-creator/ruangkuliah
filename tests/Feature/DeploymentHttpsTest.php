<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

class DeploymentHttpsTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_PROTO);
        parent::tearDown();
    }

    public function test_login_uses_https_behind_trusted_proxy(): void
    {
        config(['trustedproxy.proxies' => '*', 'session.secure' => true]);

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_HOST' => 'ruangkuliah.vercel.app',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->get('/login');

        $response->assertOk()
            ->assertSee('action="https://ruangkuliah.vercel.app/login"', false)
            ->assertSee('href="/css/app.css"', false);
        $this->assertTrue($response->getCookie(config('session.cookie'))->isSecure());
    }

    public function test_local_http_login_still_uses_http(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->get('http://localhost/login')->assertOk()
            ->assertSee('action="http://localhost/login"', false);
    }
}
