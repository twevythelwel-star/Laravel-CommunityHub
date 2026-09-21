<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Fundraiser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Covers fundraising: the page loading at all on SQLite, donations being
 * recorded in the fundraiser's own currency so the total means something,
 * anonymity, and opening an Upcoming fundraiser.
 */
class FundraisingPageTest extends TestCase
{
    use RefreshDatabase;

    private function fundraiser(array $overrides = []): Fundraiser
    {
        return Fundraiser::create(array_merge([
            'title' => 'New Playground Equipment',
            'description' => 'A modern playground for the community children.',
            'goal_minor' => 150000000,        // JMD 1,500,000.00
            'goal_currency' => 'JMD',
            'start_date' => now()->subWeek(),
            'end_date' => now()->addMonth(),
            'status' => 'Active',
        ], $overrides));
    }

    private function resident(array $attributes = []): User
    {
        return User::factory()->role(UserRole::Homeowner)->create($attributes);
    }

    private function admin(): User
    {
        return User::factory()->role(UserRole::Admin)->create();
    }

    // ── Loading ──────────────────────────────────────────────────────

    public function test_the_page_loads(): void
    {
        /*
         | This is not a formality. The listing ordered with
         | `FIELD(status, ...)`, which is a MySQL function — on SQLite, which is
         | what this suite and the quick-start setup both use, the page threw.
         | No test covered it, so nothing caught it.
         */
        $this->fundraiser();

        $this->actingAs($this->resident())
            ->get('/dashboard/fundraising')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Fundraising')
                ->has('fundraisers', 1)
                ->where('canManage', false)
            );
    }

    public function test_active_fundraisers_are_listed_before_upcoming_and_completed(): void
    {
        $this->fundraiser(['title' => 'Done', 'status' => 'Completed', 'end_date' => now()->subWeek()]);
        $this->fundraiser(['title' => 'Later', 'status' => 'Upcoming', 'start_date' => now()->addWeek()]);
        $this->fundraiser(['title' => 'Now', 'status' => 'Active']);

        $this->actingAs($this->resident())
            ->get('/dashboard/fundraising')
            ->assertInertia(fn (Assert $page) => $page
                ->where('fundraisers.0.title', 'Now')
                ->where('fundraisers.1.title', 'Later')
                ->where('fundraisers.2.title', 'Done')
            );
    }

    public function test_only_fundraiser_managers_are_offered_management(): void
    {
        foreach ([UserRole::Admin, UserRole::SystemAdmin] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/dashboard/fundraising')
                ->assertInertia(fn (Assert $page) => $page->where('canManage', true));
        }

        foreach ([UserRole::Homeowner, UserRole::TemporaryHomeowner] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/dashboard/fundraising')
                ->assertInertia(fn (Assert $page) => $page->where('canManage', false));
        }
    }

    /**
     * Fundraising is resident community life. Security and Staff are employed
     * by the estate rather than living in it, and the sidebar has never
     * offered them the page — but the route was open, so both could reach it
     * by URL and donate. `accessCommunityLife` now closes it.
     *
     * This previously asserted that Security saw the page with
     * `canManage => false`, which described the hole rather than the rule.
     */
    public function test_the_estates_employees_are_not_admitted_to_fundraising(): void
    {
        foreach ([UserRole::Security, UserRole::Staff] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/dashboard/fundraising')
                ->assertForbidden();
        }
    }

    // ── Donations ────────────────────────────────────────────────────

    public function test_a_donation_is_recorded_in_the_fundraisers_currency(): void
    {
        /*
         | The form offered five currencies and defaulted to USD, while
         | raisedMinor() sums amount_minor with no conversion. USD 50 against a
         | JMD goal counted as JMD 50 — about 1/155th of what was given. The
         | currency now comes from the fundraiser and the request cannot set it.
         */
        $fundraiser = $this->fundraiser();
        $donor = $this->resident();

        $this->actingAs($donor)
            ->post("/dashboard/fundraising/{$fundraiser->id}/donate", [
                'amount' => 50,
                'currency' => 'USD',          // ignored
                'donor_name' => 'Marcus V.',
                'is_anonymous' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $donation = $fundraiser->donations()->firstOrFail();

        $this->assertSame('JMD', $donation->currency);
        $this->assertSame(5000, $donation->amount_minor);
    }

    public function test_the_total_is_summed_from_minor_units(): void
    {
        $fundraiser = $this->fundraiser(['goal_minor' => 1000000]);   // 10,000.00

        foreach ([3333, 3333, 3334, 50] as $minor) {
            $fundraiser->donations()->create([
                'user_id' => $this->resident()->id,
                'amount_minor' => $minor,
                'currency' => 'JMD',
                'is_anonymous' => false,
                'donated_at' => now(),
            ]);
        }

        $this->actingAs($this->resident())
            ->get('/dashboard/fundraising')
            ->assertInertia(fn (Assert $page) => $page
                ->where('fundraisers.0.raised', fn ($v) => (float) $v === 100.50)
                ->where('fundraisers.0.donorCount', 4)
            );
    }

    public function test_an_anonymous_donor_is_never_named_in_the_payload(): void
    {
        $fundraiser = $this->fundraiser();

        $this->actingAs($this->resident())
            ->post("/dashboard/fundraising/{$fundraiser->id}/donate", [
                'amount' => 100,
                'donor_name' => 'Should Not Appear',
                'is_anonymous' => true,
            ])
            ->assertRedirect();

        $response = $this->actingAs($this->resident())->get('/dashboard/fundraising');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('fundraisers.0.recentDonations.0.donorName', 'Anonymous')
        );

        $response->assertDontSee('Should Not Appear');
    }

    public function test_a_closed_fundraiser_refuses_donations(): void
    {
        $fundraiser = $this->fundraiser(['status' => 'Upcoming', 'start_date' => now()->addWeek()]);

        $this->actingAs($this->resident())
            ->post("/dashboard/fundraising/{$fundraiser->id}/donate", ['amount' => 100])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('donations', 0);
    }

    public function test_an_expired_fundraiser_refuses_donations(): void
    {
        // Active, but past its end date — isOpen() checks both.
        $fundraiser = $this->fundraiser(['end_date' => now()->subDay()]);

        $this->actingAs($this->resident())
            ->post("/dashboard/fundraising/{$fundraiser->id}/donate", ['amount' => 100])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('donations', 0);
    }

    public function test_the_amount_must_be_positive(): void
    {
        $fundraiser = $this->fundraiser();

        $this->actingAs($this->resident())
            ->post("/dashboard/fundraising/{$fundraiser->id}/donate", ['amount' => 0])
            ->assertSessionHasErrors('amount');
    }

    // ── Creating and opening ─────────────────────────────────────────

    public function test_an_administrator_creates_a_fundraiser(): void
    {
        $this->actingAs($this->admin())
            ->post('/dashboard/fundraising', [
                'title' => 'Community Garden Expansion',
                'description' => 'Ten new plots and an irrigation system.',
                'goal' => 400000,
                'start_date' => now()->addWeek()->toDateString(),
                'end_date' => now()->addMonths(2)->toDateString(),
                'status' => 'Upcoming',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('fundraisers', [
            'title' => 'Community Garden Expansion',
            'goal_minor' => 40000000,
            'status' => 'Upcoming',
        ]);
    }

    public function test_a_resident_cannot_create_a_fundraiser(): void
    {
        $this->actingAs($this->resident())
            ->post('/dashboard/fundraising', [
                'title' => 'Unauthorised',
                'description' => 'Should not be created.',
                'goal' => 1000,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
                'status' => 'Active',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('fundraisers', 0);
    }

    public function test_enable_now_opens_an_upcoming_fundraiser(): void
    {
        // The button existed with no handler and no endpoint, so a scheduled
        // fundraiser could never be opened.
        $fundraiser = $this->fundraiser(['status' => 'Upcoming', 'start_date' => now()->addDay()]);

        $this->actingAs($this->admin())
            ->patch("/dashboard/fundraising/{$fundraiser->id}", ['status' => 'Active'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('Active', $fundraiser->fresh()->status);
        $this->assertTrue($fundraiser->fresh()->isOpen());
    }

    public function test_an_expired_fundraiser_cannot_be_opened(): void
    {
        /*
         | isOpen() is Active AND not past its end date, so activating an
         | expired fundraiser produces one that looks open and refuses every
         | donation.
         */
        $fundraiser = $this->fundraiser(['status' => 'Upcoming', 'end_date' => now()->subDay()]);

        $this->actingAs($this->admin())
            ->patch("/dashboard/fundraising/{$fundraiser->id}", ['status' => 'Active'])
            ->assertSessionHasErrors('status');

        $this->assertSame('Upcoming', $fundraiser->fresh()->status);
    }

    public function test_a_resident_cannot_open_a_fundraiser(): void
    {
        $fundraiser = $this->fundraiser(['status' => 'Upcoming']);

        $this->actingAs($this->resident())
            ->patch("/dashboard/fundraising/{$fundraiser->id}", ['status' => 'Active'])
            ->assertForbidden();

        $this->assertSame('Upcoming', $fundraiser->fresh()->status);
    }
}
