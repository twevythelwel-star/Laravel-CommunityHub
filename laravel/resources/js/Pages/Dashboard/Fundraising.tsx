import { useState } from 'react';
import { Head } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/components/ui/button';
import { PlusCircle } from 'lucide-react';
import { CreateFundraiserForm } from '@/components/dashboard/create-fundraiser-form';
import {
  FundraiserProgressCard,
  type FundraiserRow,
} from '@/components/dashboard/fundraiser-progress-card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { PaymentChannel } from '@/lib/payment-channels';

/**
 * Community fundraising.
 *
 * Was four hardcoded fundraisers and eight hardcoded donations, with "Create
 * Fundraiser" and "Donate" both pushing onto local arrays. `canManage` was a
 * `role === 'Admin'` check in JSX rather than the `manageFundraisers` gate the
 * route enforces.
 */

type Props = {
  fundraisers: FundraiserRow[];
  canManage: boolean;
  /**
   * The estate's enabled payment methods, from the same
   * PaymentOrchestratorService call the Billing page uses — so giving to a
   * fundraiser offers exactly the methods paying dues does.
   */
  availableChannels?: PaymentChannel[];
};

const TABS = [
  { value: 'active', status: 'Active', empty: 'No active fundraisers.' },
  { value: 'upcoming', status: 'Upcoming', empty: 'No upcoming fundraisers.' },
  { value: 'completed', status: 'Completed', empty: 'No completed fundraisers.' },
] as const;

export default function FundraisingPage({
  fundraisers,
  canManage,
  availableChannels = [],
}: Props) {
  const [isCreateOpen, setCreateOpen] = useState(false);

  return (
    <DashboardLayout>
      <Head title="Community Fundraising" />

      <div className="grid gap-8">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="font-headline text-3xl font-bold">Community Fundraising</h1>
            <p className="text-muted-foreground">Support community projects through donations.</p>
          </div>

          {canManage && (
            <CreateFundraiserForm open={isCreateOpen} onOpenChange={setCreateOpen}>
              <Button size="sm" className="gap-1" onClick={() => setCreateOpen(true)}>
                <PlusCircle className="h-3.5 w-3.5" />
                <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                  Create Fundraiser
                </span>
              </Button>
            </CreateFundraiserForm>
          )}
        </div>

        <Tabs defaultValue="active" className="w-full">
          <TabsList className="grid w-full grid-cols-3">
            {TABS.map((tab) => (
              <TabsTrigger key={tab.value} value={tab.value} className="capitalize">
                {tab.value}
              </TabsTrigger>
            ))}
          </TabsList>

          {TABS.map((tab) => {
            const shown = fundraisers.filter((f) => f.status === tab.status);

            return (
              <TabsContent
                key={tab.value}
                value={tab.value}
                className="mt-4 grid gap-6 md:grid-cols-2"
              >
                {shown.length > 0 ? (
                  shown.map((fundraiser) => (
                    <FundraiserProgressCard
                      key={fundraiser.id}
                      fundraiser={fundraiser}
                      canManage={canManage}
                      channels={availableChannels}
                    />
                  ))
                ) : (
                  <p className="text-muted-foreground col-span-2 text-center py-8">{tab.empty}</p>
                )}
              </TabsContent>
            );
          })}
        </Tabs>
      </div>
    </DashboardLayout>
  );
}
