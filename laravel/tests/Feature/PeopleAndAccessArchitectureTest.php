<?php

namespace Tests\Feature;

use App\Actions\GatePass\ScanGatePassAction;
use App\Enums\AuthorizedPersonRole;
use App\Enums\GateId;
use App\Enums\OwnershipEntityType;
use App\Enums\ScanDecision;
use App\Enums\UserRole;
use App\Models\AuthorizedPerson;
use App\Models\HouseholdMember;
use App\Models\Occupant;
use App\Models\OwnershipEntity;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Staff;
use App\Models\User;
use App\Models\Visitor;
use App\Services\GatePassEngine;
use App\Services\HouseholdManagementService;
use App\Services\People\PropertyPeopleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * People and access, on the one people model the app uses.
 *
 * The people allowed on a property are occupants (renters, household
 * members, household staff in the `occupants` table), plus delegates and a
 * company's authorized representatives in their own tables. Owners are
 * users and properties; guests are visitors.
 *
 * A parallel copy of all of this (people, property_relationships,
 * access_authorizations) was made once by a migration and never kept in step;
 * it has been removed. These tests hold the same guarantees against the real
 * records: one identity across properties, ownership apart from occupancy,
 * every kind of person queryable per property, access that lapses and can be
 * suspended, access scoped to one property, and the gate enforcing it.
 */
class PeopleAndAccessArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private User $guard;

    private User $owner;

    private Property $unit14;

    private Property $unit15;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guard = User::factory()->role(UserRole::Security)->create(['name' => 'Officer Guard']);
        $this->owner = User::factory()->role(UserRole::Homeowner)->create(['name' => 'Olivia Owner', 'lot' => '14']);

        $this->unit14 = Property::create([
            'property_code' => 'UNIT-14', 'lot_number' => '14', 'street_address' => '14 Royal Palm Way',
            'status' => 'occupied', 'owner_user_id' => $this->owner->id,
        ]);
        $this->unit15 = Property::create([
            'property_code' => 'UNIT-15', 'lot_number' => '15', 'street_address' => '15 Royal Palm Way',
            'status' => 'occupied',
        ]);
    }

    private function renter(Property $property, array $attributes = []): Renter
    {
        return Renter::create([
            'homeowner_id' => $this->owner->id, 'property_id' => $property->id, 'name' => 'Bob Renter',
            'stay_type' => 'Long-term (Renter)', 'status' => 'Active',
            'lease_start' => now()->subMonth()->toDateString(), 'lease_end' => now()->addMonths(11)->toDateString(),
            ...$attributes,
        ]);
    }

    private function scan(HouseholdMember $member): array
    {
        $token = app(GatePassEngine::class)->issueToken($member->gatePass->fresh(), GateId::Gate01)['token'];

        return app(ScanGatePassAction::class)->execute($token, 'GATE_01', $this->guard);
    }

    // ── 1. One identity across properties ───────────────────────────────

    public function test_one_person_keeps_one_identity_across_properties(): void
    {
        $person = User::factory()->create(['name' => 'John Sterling', 'email' => 'john.sterling@example.com']);

        // Renting Unit 14, and family at Unit 15: two relationships, one account.
        $this->renter($this->unit14, ['user_id' => $person->id, 'name' => 'John Sterling']);
        HouseholdMember::create([
            'user_id' => $person->id, 'property_id' => $this->unit15->id, 'name' => 'John Sterling',
            'role_in_household' => 'spouse', 'relationship_label' => 'Spouse',
        ]);

        $roles = Occupant::everyone()->where('user_id', $person->id)->get();

        $this->assertCount(2, $roles, 'two relationships, one person');
        $this->assertEqualsCanonicalizing([$this->unit14->id, $this->unit15->id], $roles->pluck('property_id')->all());
        $this->assertEqualsCanonicalizing(['renter', 'household_member'], $roles->map->personType()->all());
    }

    // ── 2. Ownership is separate from occupancy ─────────────────────────

    public function test_ownership_is_strictly_separated_from_occupancy(): void
    {
        $company = OwnershipEntity::create([
            'legal_name' => 'ABC Holdings Ltd', 'entity_type' => OwnershipEntityType::Company,
            'registration_number' => 'REG-ABC-991', 'status' => 'active',
        ]);
        $this->unit14->update(['ownership_entity_id' => $company->id, 'owner_user_id' => null]);
        AuthorizedPerson::create([
            'ownership_entity_id' => $company->id, 'user_id' => User::factory()->create()->id,
            'full_name' => 'Victoria Vance', 'role' => AuthorizedPersonRole::Director, 'status' => 'active',
        ]);
        $tenant = $this->renter($this->unit14, ['name' => 'Marcus Cole']);

        $this->unit14->refresh();

        $this->assertSame('ABC Holdings Ltd', $this->unit14->ownerName());
        $this->assertSame('Company', $this->unit14->ownershipType());
        $this->assertFalse($this->unit14->is_owner_occupied);
        $this->assertSame('Marcus Cole', $this->unit14->occupants()->sole()->personName());
        $this->assertSame($tenant->id, $this->unit14->occupants()->sole()->id);
    }

    // ── 3. Every kind of person, queryable per property ─────────────────

    public function test_every_kind_of_person_at_a_property_can_be_queried(): void
    {
        $household = app(HouseholdManagementService::class)->getOrCreateHousehold($this->owner);
        app(HouseholdManagementService::class)->addMember($household, ['name' => 'Charlie Child', 'role_in_household' => 'child']);
        HouseholdMember::query()->update(['property_id' => $this->unit14->id]);
        $this->renter($this->unit14);
        Staff::create([
            'property_id' => $this->unit14->id, 'name' => 'Diana Gardener', 'job' => 'Head Gardener',
            'id_type' => 'Passport', 'id_number' => 'B456', 'id_expiry' => now()->addYear()->toDateString(),
            'property_label' => 'Lot 14', 'added_by' => $this->owner->id, 'status' => 'Active',
        ]);
        $guest = Visitor::create([
            'name' => 'Evan Guest', 'type' => 'One-time', 'status' => 'Expected', 'expected_at' => now()->addDay(),
            'homeowner_id' => $this->owner->id, 'homeowner_name' => $this->owner->display_name, 'property_id' => $this->unit14->id,
        ]);

        $people = app(PropertyPeopleService::class)->everyoneAt($this->unit14);

        $this->assertEqualsCanonicalizing(
            ['household_member', 'household_member', 'renter', 'staff'],
            $people->map->personType()->all(),
            'the owner (as head of household), their child, the renter and the gardener',
        );
        $this->assertTrue($people->every->hasCurrentAccess());
        $this->assertSame($this->owner->id, $this->unit14->owner_user_id, 'the resident owner is the property\'s owner');
        $this->assertSame($this->unit14->id, $guest->property_id, 'guests are visitors to the property');
    }

    // ── 4. Access lapses, and can be suspended ──────────────────────────

    public function test_access_lapses_at_its_end_and_when_suspended(): void
    {
        $renter = $this->renter($this->unit14, ['lease_end' => now()->addDays(7)->toDateString()]);
        $this->assertTrue($renter->hasCurrentAccess());

        $this->travel(8)->days();
        $this->assertFalse($renter->fresh()->hasCurrentAccess(), 'the lease has ended');
        $this->travelBack();

        $renter->update(['status' => 'Inactive']);
        $this->assertFalse($renter->fresh()->hasCurrentAccess(), 'suspended');

        $staff = Staff::create([
            'property_id' => $this->unit14->id, 'name' => 'Frank Contractor', 'job' => 'Plumber',
            'id_type' => 'Passport', 'id_number' => 'C789', 'id_expiry' => now()->subDay()->toDateString(),
            'property_label' => 'Lot 14', 'added_by' => $this->owner->id, 'status' => 'Active',
        ]);
        $this->assertFalse($staff->hasCurrentAccess(), 'staff with an expired ID');

        $lease = $renter->lease_end->toDateString();
        $renter->hasCurrentAccess();
        $this->assertSame($lease, $renter->lease_end->toDateString(), 'checking access does not move the lease date');
    }

    // ── 5. Access is scoped to one property ─────────────────────────────

    public function test_a_person_is_only_on_the_property_they_belong_to(): void
    {
        $this->renter($this->unit14, ['name' => 'George Unit14']);

        $this->assertSame(['George Unit14'], $this->unit14->occupants()->pluck('name')->all());
        $this->assertSame([], $this->unit15->occupants()->pluck('name')->all());
        $this->assertSame([], app(PropertyPeopleService::class)->everyoneAt($this->unit15)->all());
    }

    // ── 6–7. The gate enforces it ───────────────────────────────────────

    public function test_an_occupants_pass_works_while_their_access_is_current(): void
    {
        $household = app(HouseholdManagementService::class)->getOrCreateHousehold($this->owner);
        $member = app(HouseholdManagementService::class)->addMember($household, ['name' => 'Helen Spouse', 'role_in_household' => 'spouse']);

        $scan = $this->scan($member);

        $this->assertSame(ScanDecision::CheckIn->value, $scan['decision']);
        $this->assertSame($member->gatePass->pass_id, $scan['report']['passId']);
    }

    public function test_the_gate_refuses_a_pass_whose_occupancy_has_ended(): void
    {
        $household = app(HouseholdManagementService::class)->getOrCreateHousehold($this->owner);
        $member = app(HouseholdManagementService::class)->addMember($household, ['name' => 'Ian Spouse', 'role_in_household' => 'spouse']);

        // The member's record ends, but the pass itself was never revoked:
        // the gate must still refuse it.
        $member->update(['status' => 'suspended']);
        $this->assertTrue($member->gatePass->fresh()->isActive());

        $scan = $this->scan($member);

        $this->assertSame(ScanDecision::Reject->value, $scan['decision']);
        $this->assertSame('The occupancy behind this pass has ended or is suspended', $scan['report']['primaryReason']);
    }

    public function test_the_parallel_people_tables_are_gone(): void
    {
        foreach (['people', 'property_relationships', 'access_authorizations'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} should be gone");
        }
        foreach (['person_id', 'access_authorization_id', 'property_id'] as $column) {
            $this->assertFalse(Schema::hasColumn('gate_passes', $column), "gate_passes.{$column} should be gone");
        }
    }
}
