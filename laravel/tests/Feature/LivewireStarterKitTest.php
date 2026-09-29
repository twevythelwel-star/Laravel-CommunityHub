<?php

namespace Tests\Feature;

use App\Enums\PassStatus;
use App\Livewire\EstateStatusWidget;
use App\Models\GatePass;
use App\Models\User;
use App\Models\Warning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireStarterKitTest extends TestCase
{
    use RefreshDatabase;

    public function test_livewire_component_renders_and_responds_to_actions(): void
    {
        $user = User::factory()->create();

        GatePass::factory()->create([
            'user_id' => $user->id,
            'status' => PassStatus::Active,
        ]);

        Warning::create([
            'author_id' => $user->id,
            'author_name' => $user->name,
            'title' => 'Gate 1 Power Glitch',
            'description' => 'Power supply flickering on lane 1 scanner.',
            'category' => 'security',
            'severity' => 'medium',
            'issued_at' => now(),
        ]);

        Livewire::test(EstateStatusWidget::class)
            ->assertSee('Cypress Bay Estate')
            ->assertSeeHtml('Active Passes')
            ->assertSet('activePassesCount', 1)
            ->assertSet('activeAlertsCount', 1)
            ->call('refreshStats')
            ->assertSet('isRefreshed', true);
    }
}
