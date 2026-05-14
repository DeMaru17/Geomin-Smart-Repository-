<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Integration tests for route registration and auth middleware.
 *
 * Validates: Requirements 8.1, 8.7, 8.8, 9.1, 9.6
 */
class RouteRegistrationAndAuthTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['mysql', 'mysql_hris'];

    protected function setUp(): void
    {
        parent::setUp();

        // Filament registers the 'login' named route via its panel provider,
        // but it uses deferred route registration that may not be available
        // during isolated test request handling. We add the route directly
        // to the router's route collection to ensure the auth middleware
        // can resolve it during unauthenticated request tests.
        $router = $this->app['router'];
        $router->get('/login', fn() => 'login page')
            ->name('login')
            ->middleware('web');

        // Force route collection refresh
        $router->getRoutes()->refreshNameLookups();
    }

    /**
     * Verify that the dokumen.aktif route is registered.
     */
    public function test_dokumen_aktif_route_exists(): void
    {
        $route = Route::getRoutes()->getByName('dokumen.aktif');

        $this->assertNotNull($route, 'Route dokumen.aktif should be registered');
        $this->assertStringContainsString('dokumen/aktif/{document_number}', $route->uri());
        $this->assertContains('GET', $route->methods());
    }

    /**
     * Verify that the validasi.cetak route is registered.
     */
    public function test_validasi_cetak_route_exists(): void
    {
        $route = Route::getRoutes()->getByName('validasi.cetak');

        $this->assertNotNull($route, 'Route validasi.cetak should be registered');
        $this->assertStringContainsString('validasi-cetak/{qr_token}', $route->uri());
        $this->assertContains('GET', $route->methods());
    }

    /**
     * Verify that the dokumen.aktif route has auth middleware applied.
     */
    public function test_dokumen_aktif_route_has_auth_middleware(): void
    {
        $route = Route::getRoutes()->getByName('dokumen.aktif');

        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();
        $this->assertTrue(
            in_array('auth', $middleware),
            'Route dokumen.aktif should have auth middleware'
        );
    }

    /**
     * Verify that the validasi.cetak route has auth middleware applied.
     */
    public function test_validasi_cetak_route_has_auth_middleware(): void
    {
        $route = Route::getRoutes()->getByName('validasi.cetak');

        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();
        $this->assertTrue(
            in_array('auth', $middleware),
            'Route validasi.cetak should have auth middleware'
        );
    }

    /**
     * Verify unauthenticated access to /dokumen/aktif/{doc} redirects to login.
     *
     * Validates: Requirement 8.7
     */
    public function test_dokumen_aktif_redirects_to_login_when_unauthenticated(): void
    {
        $response = $this->get('/dokumen/aktif/TEST-DOC-001');

        $response->assertRedirect('/login');
    }

    /**
     * Verify unauthenticated access to /validasi-cetak/{token} redirects to login.
     *
     * Validates: Requirement 9.6
     */
    public function test_validasi_cetak_redirects_to_login_when_unauthenticated(): void
    {
        $response = $this->get('/validasi-cetak/abc123token45678');

        $response->assertRedirect('/login');
    }

    /**
     * Verify the intended URL is preserved when redirecting to login for dokumen.aktif.
     *
     * Validates: Requirement 8.8
     */
    public function test_dokumen_aktif_preserves_intended_url_on_login_redirect(): void
    {
        $intendedUrl = '/dokumen/aktif/GSR-DOC-INTENDED';

        $response = $this->get($intendedUrl);

        $response->assertRedirect('/login');
        $this->assertEquals(url($intendedUrl), session('url.intended'));
    }

    /**
     * Verify the intended URL is preserved when redirecting to login for validasi.cetak.
     *
     * Validates: Requirement 9.6
     */
    public function test_validasi_cetak_preserves_intended_url_on_login_redirect(): void
    {
        $intendedUrl = '/validasi-cetak/tokenintended16c';

        $response = $this->get($intendedUrl);

        $response->assertRedirect('/login');
        $this->assertEquals(url($intendedUrl), session('url.intended'));
    }

    /**
     * Verify authenticated user can access /dokumen/aktif/{doc} without login redirect.
     * (Returns 404 since the document doesn't exist, but NOT a login redirect.)
     *
     * Validates: Requirement 8.1
     */
    public function test_dokumen_aktif_does_not_redirect_to_login_when_authenticated(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get('/dokumen/aktif/NONEXISTENT-DOC');

        // Should get 404 (document not found), not a login redirect
        $response->assertStatus(404);
    }

    /**
     * Verify authenticated user can access /validasi-cetak/{token} without login redirect.
     * (Returns 404 since the token doesn't exist, but NOT a login redirect.)
     *
     * Validates: Requirement 9.1
     */
    public function test_validasi_cetak_does_not_redirect_to_login_when_authenticated(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get('/validasi-cetak/nonexistenttoken1');

        // Should get 404 (token not found), not a login redirect
        $response->assertStatus(404);
    }
}
