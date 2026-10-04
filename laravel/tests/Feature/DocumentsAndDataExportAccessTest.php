<?php

namespace Tests\Feature;

use App\Enums\TokenAbility;
use App\Enums\UserRole;
use App\Livewire\Features\FeatureFlagHub;
use App\Livewire\Pdf\PdfGenerationHub;
use App\Livewire\Spreadsheets\SpreadsheetOperationsHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Spreadsheet exports hand out the resident roster and the ledger, PDFs are
 * invoices and receipts in the estate's name, and feature flags change the
 * platform for everyone. All three were public; each now follows the roles
 * that already see the same records on the dashboard.
 */
class DocumentsAndDataExportAccessTest extends TestCase
{
    use RefreshDatabase;

    private function as(UserRole $role): User
    {
        return User::factory()->role($role)->create();
    }

    /**
     * Forgetting the guards matters when one test sends several tokens: the
     * test client otherwise keeps the first request's user for the rest.
     */
    private function bearer(User $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken('Device', TokenAbility::forUser($user))->plainTextToken];
    }

    // ── No token, no access ─────────────────────────────────────────

    /** @return array<string, array{string, string}> */
    public static function apiRoutes(): array
    {
        return [
            'export roster' => ['GET', '/api/v1/spreadsheets/export/residents'],
            'export ledger' => ['GET', '/api/v1/spreadsheets/export/transactions'],
            'export passes' => ['GET', '/api/v1/spreadsheets/export/gate_passes'],
            'queue export' => ['POST', '/api/v1/spreadsheets/export-queue/residents'],
            'import roster' => ['POST', '/api/v1/spreadsheets/import/residents'],
            'blueprints' => ['GET', '/api/v1/spreadsheets/blueprints'],
            'sample template' => ['GET', '/api/v1/spreadsheets/sample-template/residents'],
            'flags' => ['GET', '/api/v1/features'],
            'flag catalog' => ['GET', '/api/v1/features/catalog'],
            'flag check' => ['POST', '/api/v1/features/check'],
            'flag activate' => ['POST', '/api/v1/features/activate'],
            'flag deactivate' => ['POST', '/api/v1/features/deactivate'],
            'flag purge' => ['POST', '/api/v1/features/purge'],
            'flag simulate' => ['POST', '/api/v1/features/simulate'],
            'pdf catalog' => ['GET', '/api/v1/pdf/catalog'],
            'pdf drivers' => ['GET', '/api/v1/pdf/drivers'],
            'pdf preview' => ['POST', '/api/v1/pdf/preview/receipt'],
            'pdf download' => ['POST', '/api/v1/pdf/download/receipt'],
            'pdf generate' => ['POST', '/api/v1/pdf/generate'],
        ];
    }

    #[DataProvider('apiRoutes')]
    public function test_the_api_needs_a_token(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }

    // ── Spreadsheets ────────────────────────────────────────────────

    /** @return array<string, array{string, UserRole, UserRole}> */
    public static function exports(): array
    {
        // blueprint, a role refused, a role allowed
        return [
            'roster' => ['residents', UserRole::Security, UserRole::Admin],
            'benchmark (also users)' => ['large_dataset_benchmark', UserRole::Security, UserRole::Admin],
            'ledger' => ['transactions', UserRole::Security, UserRole::Admin],
            'passes' => ['gate_passes', UserRole::Homeowner, UserRole::Security],
        ];
    }

    #[DataProvider('exports')]
    public function test_each_export_follows_its_dashboard_gate(string $blueprint, UserRole $refused, UserRole $allowed): void
    {
        $this->get("/api/v1/spreadsheets/export/{$blueprint}?format=csv", $this->bearer($this->as(UserRole::Homeowner)))->assertForbidden();
        $this->get("/api/v1/spreadsheets/export/{$blueprint}?format=csv", $this->bearer($this->as($refused)))->assertForbidden();
        $this->get("/api/v1/spreadsheets/export/{$blueprint}?format=csv", $this->bearer($this->as($allowed)))->assertOk();
    }

    public function test_a_resident_cannot_import_the_roster(): void
    {
        $this->post('/api/v1/spreadsheets/import/residents', [
            'file' => UploadedFile::fake()->createWithContent('roster.csv', "full_legal_name,email_address,assigned_role\nMallory,m@example.com,admin\n"),
        ], $this->bearer($this->as(UserRole::Homeowner)) + ['Accept' => 'application/json'])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'm@example.com']);
    }

    public function test_a_queued_export_cannot_be_written_to_the_public_disk(): void
    {
        $this->postJson('/api/v1/spreadsheets/export-queue/residents', ['disk' => 'public'], $this->bearer($this->as(UserRole::Admin)))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('disk');
    }

    public function test_the_catalog_lists_only_usable_blueprints(): void
    {
        $response = $this->getJson('/api/v1/spreadsheets/blueprints', $this->bearer($this->as(UserRole::Security)))->assertOk();

        $this->assertSame(['gate_passes'], array_keys($response->json('data.export_blueprints')));
        $this->assertSame([], $response->json('data.import_blueprints'));
    }

    // ── Feature flags ───────────────────────────────────────────────

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function flagChanges(): array
    {
        return [
            'activate' => ['/api/v1/features/activate', ['feature' => 'beta-dashboard']],
            'deactivate' => ['/api/v1/features/deactivate', ['feature' => 'beta-dashboard']],
            'purge' => ['/api/v1/features/purge', []],
            'simulate' => ['/api/v1/features/simulate', ['feature' => 'beta-dashboard', 'scope_type' => 'role', 'scope_value' => 'Admin']],
        ];
    }

    #[DataProvider('flagChanges')]
    public function test_only_a_system_admin_can_change_flags(string $uri, array $body): void
    {
        $this->postJson($uri, $body, $this->bearer($this->as(UserRole::Admin)))->assertForbidden();
        $this->postJson($uri, $body, $this->bearer($this->as(UserRole::SystemAdmin)))->assertSuccessful();
    }

    public function test_anyone_signed_in_can_read_their_own_flags(): void
    {
        $resident = $this->as(UserRole::Homeowner);

        $this->getJson('/api/v1/features', $this->bearer($resident))->assertOk();
        $this->postJson('/api/v1/features/check', ['feature' => 'beta-dashboard'], $this->bearer($resident))->assertOk();
    }

    public function test_checking_someone_elses_flags_needs_a_system_admin(): void
    {
        $neighbour = $this->as(UserRole::Homeowner);

        $this->postJson('/api/v1/features/check', ['feature' => 'beta-dashboard', 'user_id' => $neighbour->id], $this->bearer($this->as(UserRole::Homeowner)))
            ->assertForbidden();
    }

    // ── PDF documents ───────────────────────────────────────────────

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function pdfRenders(): array
    {
        return [
            'preview' => ['/api/v1/pdf/preview/receipt', ['amount' => '1000000']],
            'download' => ['/api/v1/pdf/download/receipt', ['amount' => '1000000']],
            'generate' => ['/api/v1/pdf/generate', ['type' => 'receipt']],
        ];
    }

    #[DataProvider('pdfRenders')]
    public function test_residents_and_security_cannot_issue_documents(string $uri, array $body): void
    {
        foreach ([UserRole::Homeowner, UserRole::Security] as $role) {
            $this->postJson($uri, $body, $this->bearer($this->as($role)))->assertForbidden();
        }
    }

    public function test_an_administrator_can_preview_a_document(): void
    {
        $this->post('/api/v1/pdf/preview/receipt', [], $this->bearer($this->as(UserRole::Admin)))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    // ── Livewire pages ──────────────────────────────────────────────

    public function test_a_resident_cannot_open_any_of_the_pages(): void
    {
        $resident = $this->as(UserRole::Homeowner);

        foreach (['/operations/features', '/operations/pdf', '/operations/spreadsheets'] as $uri) {
            $this->actingAs($resident)->get($uri)->assertForbidden();
        }

        foreach ([FeatureFlagHub::class, PdfGenerationHub::class, SpreadsheetOperationsHub::class] as $component) {
            Livewire::actingAs($resident)->test($component)->assertForbidden();
        }
    }

    public function test_the_flag_page_is_the_system_admins(): void
    {
        $this->actingAs($this->as(UserRole::Admin))->get('/operations/features')->assertForbidden();
        $this->actingAs($this->as(UserRole::SystemAdmin))->get('/operations/features')->assertOk();
    }

    public function test_an_administrator_can_open_the_pdf_page(): void
    {
        $this->actingAs($this->as(UserRole::Admin))->get('/operations/pdf')->assertOk();
    }

    public function test_security_cannot_export_the_roster_by_switching_blueprint(): void
    {
        Livewire::actingAs($this->as(UserRole::Security))
            ->test(SpreadsheetOperationsHub::class)
            ->set('selectedBlueprint', 'residents')
            ->call('downloadExport')
            ->assertForbidden();
    }

    public function test_security_cannot_import_residents_from_the_page(): void
    {
        Livewire::actingAs($this->as(UserRole::Security))
            ->test(SpreadsheetOperationsHub::class)
            ->call('processImport')
            ->assertForbidden();
    }
}
