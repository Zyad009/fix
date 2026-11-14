<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The browser log viewer exists only with configured credentials and opens only with them.
 */
class LogViewerTest extends TestCase
{
    public function test_the_page_does_not_exist_without_configured_credentials(): void
    {
        config(['services.log_viewer.username' => null, 'services.log_viewer.password' => null]);

        $this->get('/logs')->assertNotFound();
        $this->withBasicAuth('', '')->get('/logs')->assertNotFound();
    }

    public function test_wrong_or_missing_credentials_are_challenged(): void
    {
        config(['services.log_viewer.username' => 'ops', 'services.log_viewer.password' => 'secret']);

        $this->get('/logs')->assertUnauthorized()->assertHeader('WWW-Authenticate', 'Basic realm="Logs"');
        $this->withBasicAuth('ops', 'wrong')->get('/logs')->assertUnauthorized();
        $this->withBasicAuth('other', 'secret')->get('/logs')->assertUnauthorized();
    }

    public function test_the_right_credentials_open_the_viewer(): void
    {
        config(['services.log_viewer.username' => 'ops', 'services.log_viewer.password' => 'secret']);

        $this->withBasicAuth('ops', 'secret')->get('/logs')->assertOk();
    }
}
