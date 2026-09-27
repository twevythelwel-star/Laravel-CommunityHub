<?php

namespace Tests;

use App\Models\PaymentChannelSetting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         | resources/views/app.blade.php calls @vite, which reads
         | public/build/manifest.json. Any test that renders the page rather
         | than asking for the Inertia JSON would otherwise fail on a machine
         | that has not run `npm run build` — which is a fact about the asset
         | pipeline, not about the code under test.
         */
        $this->withoutVite();
    }

    /**
     * Bank, Zelle and Cash App are offered only once the estate has entered the
     * account payers send money to. Tests that pay through them set one here.
     */
    protected function configureAccountChannels(): void
    {
        foreach ([
            'bank_wire' => 'Test Bank 000-TEST',
            'zelle' => 'zelle@example.test',
            'cash_app' => '$ExampleTest',
        ] as $channel => $account) {
            PaymentChannelSetting::updateOrCreate(
                ['channel_key' => $channel],
                ['enabled' => true, 'account_identifier' => $account, 'display_label' => ucfirst(str_replace('_', ' ', $channel))],
            );
        }
    }
}
