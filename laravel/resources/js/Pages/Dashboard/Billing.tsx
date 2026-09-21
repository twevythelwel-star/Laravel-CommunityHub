import { useState } from 'react';
import { Head } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import dynamic from '@/lib/dynamic';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import {
  BillingSummary,
  type InvoiceRow,
  type Paginated,
} from '@/components/dashboard/billing-summary';
import {
  PaymentCenterHero,
  type PaymentCenterProps,
} from '@/components/dashboard/payment-center-hero';
import {
  CommunityWalletCard,
  type WalletData,
} from '@/components/dashboard/community-wallet-card';
import {
  PaymentLinksManager,
  type PaymentLinkItem,
} from '@/components/dashboard/payment-links-manager';
import {
  TransactionsLedger,
  type MasterTransaction,
} from '@/components/dashboard/transactions-ledger';
import {
  CreditCard,
  Receipt,
  Wallet,
  Link2,
  FileText,
  BarChart3,
  Sliders,
} from 'lucide-react';

type AdminBillingProps = {
  settings: { monthlyFee: number; currency: string; dueDayOfMonth: number };
  adminSummary: {
    totalOutstanding: number;
    overdueCount: number;
    collectedThisMonth: number;
    householdsPaidThisMonth: number;
    householdsOutstanding: number;
  };
  monthlyCollections: { month: string; total: number }[];
  invoices: Paginated<InvoiceRow & { homeowner: string | null; lot: string | null }>;
};

const AdminBilling = dynamic<AdminBillingProps>(
  () =>
    import('@/components/dashboard/admin-billing').then((module) => ({
      default: module.AdminBilling,
    })),
  { loading: () => <Skeleton className="h-96 w-full" /> },
);

const AdminFinancialDashboard = dynamic<any>(
  () =>
    import('@/components/dashboard/admin-financial-dashboard').then((module) => ({
      default: module.AdminFinancialDashboard,
    })),
  { loading: () => <Skeleton className="h-96 w-full" /> },
);

type Props = {
  settings: { monthlyFee: number; currency: string; dueDayOfMonth: number };
  myInvoices: Paginated<InvoiceRow>;
  summary: {
    outstanding: number;
    paidThisYear: number;
    paidCountThisYear: number;
    year: number;
  };
  adminSummary: {
    totalOutstanding: number;
    overdueCount: number;
    collectedThisMonth: number;
    householdsPaidThisMonth: number;
    householdsOutstanding: number;
  } | null;
  monthlyCollections: { month: string; total: number }[] | null;
  invoices: Paginated<InvoiceRow & { homeowner: string | null; lot: string | null }> | null;
  canManage: boolean;

  // New Community Payments & Revenue Engine props
  financialDashboard?: any;
  paymentCenter?: PaymentCenterProps;
  wallet?: WalletData;
  paymentLinks?: PaymentLinkItem[];
  transactions?: Paginated<MasterTransaction>;
  payouts?: any[];
  reconciliations?: any[];
};

export default function BillingPage({
  settings,
  myInvoices,
  summary,
  adminSummary,
  monthlyCollections,
  invoices,
  canManage,
  financialDashboard,
  paymentCenter,
  wallet,
  paymentLinks = [],
  transactions,
  payouts = [],
  reconciliations = [],
}: Props) {
  const isAdmin = canManage && !!adminSummary && !!monthlyCollections && !!invoices;
  const [activeTab, setActiveTab] = useState(isAdmin ? 'analytics' : 'pay');

  return (
    <DashboardLayout>
      <Head title="Payments & Revenue Engine" />

      <div className="space-y-6">
        {/* Module Header */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 className="font-headline text-3xl font-bold tracking-tight text-foreground">
              Payments & Treasury
            </h1>
            <p className="text-muted-foreground text-sm mt-1">
              {isAdmin
                ? 'Estate-wide revenue management, payment recording and resident billing.'
                : 'Manage assessment dues, digital wallet balances, auto-pay preferences, and payment receipts.'}
            </p>
          </div>
        </div>

        {/* Tabbed Navigation */}
        <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
          <div className="overflow-x-auto pb-1">
            <TabsList className="h-11 bg-slate-100/90 dark:bg-slate-900/90 p-1 border border-slate-200 dark:border-slate-800 rounded-xl">
              {isAdmin && (
                <TabsTrigger value="analytics" className="gap-2 text-xs font-semibold px-4">
                  <BarChart3 className="h-4 w-4 text-indigo-500" />
                  Financial Dashboard
                </TabsTrigger>
              )}

              <TabsTrigger value="pay" className="gap-2 text-xs font-semibold px-4">
                <CreditCard className="h-4 w-4 text-emerald-500" />
                Payment Center
              </TabsTrigger>

              <TabsTrigger value="invoices" className="gap-2 text-xs font-semibold px-4">
                <FileText className="h-4 w-4 text-blue-500" />
                {isAdmin ? 'Estate Invoices' : 'My Invoices'}
              </TabsTrigger>

              <TabsTrigger value="wallet" className="gap-2 text-xs font-semibold px-4">
                <Wallet className="h-4 w-4 text-amber-500" />
                Community Wallet
              </TabsTrigger>

              <TabsTrigger value="links" className="gap-2 text-xs font-semibold px-4">
                <Link2 className="h-4 w-4 text-purple-500" />
                Payment Links & QR
              </TabsTrigger>

              <TabsTrigger value="transactions" className="gap-2 text-xs font-semibold px-4">
                <Receipt className="h-4 w-4 text-cyan-500" />
                Transactions Ledger
              </TabsTrigger>

              {isAdmin && (
                <TabsTrigger value="settings" className="gap-2 text-xs font-semibold px-4">
                  <Sliders className="h-4 w-4 text-slate-500" />
                  Rate Settings
                </TabsTrigger>
              )}
            </TabsList>
          </div>

          {/* Admin Executive Analytics Tab */}
          {isAdmin && financialDashboard && (
            <TabsContent value="analytics" className="space-y-6 m-0">
              <AdminFinancialDashboard
                financialDashboard={financialDashboard}
                payouts={payouts}
                reconciliations={reconciliations}
                currency={settings.currency}
              />
            </TabsContent>
          )}

          {/* Payment Center Tab */}
          <TabsContent value="pay" className="space-y-6 m-0">
            {paymentCenter && (
              <PaymentCenterHero
                amountDue={paymentCenter.amountDue}
                itemizedCharges={paymentCenter.itemizedCharges}
                availableChannels={paymentCenter.availableChannels}
                autoPay={paymentCenter.autoPay}
                walletBalance={wallet?.available ?? 0}
                currency={settings.currency}
              />
            )}
          </TabsContent>

          {/* Invoices Tab */}
          <TabsContent value="invoices" className="space-y-6 m-0">
            {isAdmin && adminSummary && monthlyCollections && invoices ? (
              <AdminBilling
                settings={settings}
                adminSummary={adminSummary}
                monthlyCollections={monthlyCollections}
                invoices={invoices}
              />
            ) : (
              <BillingSummary
                settings={settings}
                summary={summary}
                invoices={myInvoices}
              />
            )}
          </TabsContent>

          {/* Community Wallet Tab */}
          <TabsContent value="wallet" className="space-y-6 m-0">
            {wallet && <CommunityWalletCard wallet={wallet} />}
          </TabsContent>

          {/* Payment Links Tab */}
          <TabsContent value="links" className="space-y-6 m-0">
            <PaymentLinksManager
              links={paymentLinks}
              canManage={isAdmin}
              currency={settings.currency}
            />
          </TabsContent>

          {/* Master Transactions Ledger Tab */}
          <TabsContent value="transactions" className="space-y-6 m-0">
            {transactions ? (
              <TransactionsLedger
                transactions={transactions}
                currency={settings.currency}
              />
            ) : (
              <div className="py-12 text-center text-muted-foreground">
                No ledger records available.
              </div>
            )}
          </TabsContent>

          {/* Admin Settings Tab */}
          {isAdmin && adminSummary && monthlyCollections && invoices && (
            <TabsContent value="settings" className="space-y-6 m-0">
              <AdminBilling
                settings={settings}
                adminSummary={adminSummary}
                monthlyCollections={monthlyCollections}
                invoices={invoices}
              />
            </TabsContent>
          )}
        </Tabs>
      </div>
    </DashboardLayout>
  );
}
