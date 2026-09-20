import { Head } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import dynamic from '@/lib/dynamic';
import { Skeleton } from '@/components/ui/skeleton';
import {
  BillingSummary,
  type InvoiceRow,
  type Paginated,
} from '@/components/dashboard/billing-summary';

/**
 * Billing.
 *
 * The resident half of this page used to be a card-payment form: a "Name on
 * Card" input, four identical generic icons standing in for card brands, a
 * "Remember this card" checkbox, a recurring-payment date picker, and the line
 * "Card information is securely collected by Stripe (PCI-DSS Level 1
 * Compliant)". None of it was connected to anything — there is no Stripe
 * integration in this codebase and never was — and the Pay button had no
 * handler. A resident could fill it in, click Pay, and reasonably believe their
 * dues were settled.
 *
 * All of it is gone. What replaces it is the truth: your dues, what you owe,
 * your invoice history, and a note that payment is recorded by an administrator.
 * See the README for what adding real payments would involve.
 *
 * The admin/resident split is kept from the original, so an administrator sees
 * the estate view rather than their own invoices.
 */

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

/*
 * Split out of the page bundle. AdminBilling pulls in recharts for the
 * collections chart, which is ~390 kB of the Billing chunk — and only an
 * administrator ever renders it. Residents were downloading the whole charting
 * library to look at a list of their own invoices, which matters more than
 * usual here because this app also ships as a Capacitor mobile shell.
 */
const AdminBilling = dynamic<AdminBillingProps>(
  () =>
    import('@/components/dashboard/admin-billing').then((module) => ({
      default: module.AdminBilling,
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
};

export default function BillingPage({
  settings,
  myInvoices,
  summary,
  adminSummary,
  monthlyCollections,
  invoices,
  canManage,
}: Props) {
  /*
   * `canManage` comes from the `manageBilling` gate rather than the
   * `role === 'Admin' || role === 'System Admin'` check the page used to do
   * inline, so the view and the write routes agree on who is an administrator.
   */
  if (canManage && adminSummary && monthlyCollections && invoices) {
    return (
      <DashboardLayout>
        <Head title="Billing Overview" />
        <AdminBilling
          settings={settings}
          adminSummary={adminSummary}
          monthlyCollections={monthlyCollections}
          invoices={invoices}
        />
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout>
      <Head title="Billing" />

      <div className="grid gap-8">
        <div>
          <h1 className="font-headline text-3xl font-bold">Billing</h1>
          <p className="text-muted-foreground">Your community dues and invoice history.</p>
        </div>

        <BillingSummary settings={settings} summary={summary} invoices={myInvoices} />
      </div>
    </DashboardLayout>
  );
}
