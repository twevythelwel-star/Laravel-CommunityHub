import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import dynamic from '@/lib/dynamic';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';
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
  type OfficePayment,
} from '@/components/dashboard/transactions-ledger';
import { InPersonPayments, type InPersonProps } from '@/components/dashboard/in-person-payments';
import {
  CreditCard,
  Receipt,
  Wallet,
  Link2,
  FileText,
  BarChart3,
  Sliders,
  AlertCircle,
  Calendar,
  RefreshCw,
  Download,
  RotateCcw,
  Scale,
  Cpu,
  CheckCircle2,
  XCircle,
  Clock,
  ShieldCheck,
  Send,
  Plus,
  Landmark,
  QrCode,
  Smartphone,
  Banknote,
  DollarSign,
  ArrowRight,
  ChevronRight,
  Layers,
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

export type OutstandingItem = {
  id: number;
  reference: string;
  homeowner: string;
  lot: string;
  amount: number;
  currency: string;
  dueOn: string;
  daysOverdue: number;
  status: string;
};

export type PaymentPlanItem = {
  id: number;
  invoiceId: number;
  invoiceReference: string;
  homeowner: string;
  lot: string;
  totalInstallments: number;
  remainingInstallments: number;
  installmentAmount: number;
  frequency: string;
  status: string;
};

export type ReceiptItem = {
  id: number;
  receiptNumber: string;
  reference: string;
  homeowner: string;
  lot: string;
  amount: number;
  currency: string;
  channel: string;
  date: string;
  receiptUrl: string;
};

export type RefundItem = {
  id: number;
  reference: string;
  homeowner: string;
  lot: string;
  amount: number;
  currency: string;
  channel: string;
  date: string;
  notes: string | null;
};

export type ChannelCheck = {
  name: string;
  passed: boolean;
  message: string;
};

export type ChannelReport = {
  channel_key: string;
  label: string;
  enabled: boolean;
  is_ready: boolean;
  status: 'ready' | 'needs_configuration';
  checks: ChannelCheck[];
  account_identifier?: string;
  instructions?: string;
  validated_at: string;
};

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

  // New Revenue Engine props
  financialDashboard?: any;
  paymentCenter?: PaymentCenterProps;
  wallet?: WalletData;
  paymentLinks?: PaymentLinkItem[];
  transactions?: Paginated<MasterTransaction>;
  /** Office payments in flight: every one for an administrator, a resident's own otherwise. */
  pendingPayments?: OfficePayment[];
  /** Administrators only: in-person card readers and what is on them. */
  inPerson?: InPersonProps | null;
  payouts?: any[];
  reconciliations?: any[];
  paymentEvents?: {
    id: number;
    eventId: string;
    type: string;
    status: string;
    errorMessage: string | null;
    date: string;
  }[];

  // 10 Views & Payment Orchestration Engine props
  outstanding?: OutstandingItem[];
  paymentPlans?: PaymentPlanItem[];
  receipts?: ReceiptItem[];
  refundsList?: RefundItem[];
  autoPayPortfolio?: {
    enrolledCount: number;
    cadenceBreakdown: Record<string, number>;
    nextRunDate: string;
  };
  paymentChannels?: ChannelReport[];
  triPartyReconciliation?: {
    currency: string;
    communityHubLedger: {
      total_minor: number;
      total: number;
      settled_transactions_count: number;
      status: string;
    };
    paymentProvider: {
      name: string;
      total_minor: number;
      total: number;
      status: string;
    };
    bank: {
      name: string;
      total_minor: number;
      total: number;
      statement_date: string;
      status: string;
    };
    variance: {
      amount_minor: number;
      amount: number;
      is_balanced: boolean;
      state: string;
    };
  } | null;
  chartOfAccounts?: Array<{
    id: number;
    code: string;
    name: string;
    type: string;
    currency: string;
    balance: number;
    entriesCount: number;
    description: string;
  }>;
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
  pendingPayments = [],
  inPerson = null,
  payouts = [],
  reconciliations = [],
  paymentEvents = [],
  outstanding = [],
  paymentPlans = [],
  receipts = [],
  refundsList = [],
  autoPayPortfolio,
  paymentChannels = [],
  triPartyReconciliation,
  chartOfAccounts = [],
}: Props) {
  const { toast } = useToast();
  const isAdmin = canManage && !!adminSummary && !!monthlyCollections && !!invoices;
  const [activeTab, setActiveTab] = useState(isAdmin ? 'dashboard' : 'invoices');

  const formatMoney = (amount: number, cur: string = 'JMD') => {
    return `${cur} ${Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  };

  // Create payment plan modal
  const [isPlanModalOpen, setIsPlanModalOpen] = useState(false);
  const [selectedInvoiceForPlan, setSelectedInvoiceForPlan] = useState<string>('');
  const [planInstallments, setPlanInstallments] = useState<number>(4);
  const [planFrequency, setPlanFrequency] = useState<string>('monthly');
  const [isValidatingChannel, setIsValidatingChannel] = useState<string | null>(null);

  // Bank Reconciliation modal
  const [isReconModalOpen, setIsReconModalOpen] = useState(false);
  const [reconDate, setReconDate] = useState<string>(new Date().toISOString().split('T')[0]);
  const [reconBalance, setReconBalance] = useState<string>('');
  const [reconNotes, setReconNotes] = useState<string>('');
  const [reconVerifyIds, setReconVerifyIds] = useState<number[]>([]);
  const [reconError, setReconError] = useState<string | null>(null);
  const receivedPayments = pendingPayments.filter((p) => p.state === 'received');
  const [isSubmittingRecon, setIsSubmittingRecon] = useState(false);

  const handleCreateReconSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!reconDate || !reconBalance) return;

    setIsSubmittingRecon(true);
    router.post(
      route('dashboard.billing.reconciliations.store'),
      {
        bank_statement_date: reconDate,
        statement_balance: parseFloat(reconBalance),
        notes: reconNotes,
        verify_payment_ids: reconVerifyIds,
      },
      {
        preserveScroll: true,
        onFinish: () => setIsSubmittingRecon(false),
        onSuccess: () => {
          setIsReconModalOpen(false);
          setReconBalance('');
          setReconNotes('');
          setReconVerifyIds([]);
          setReconError(null);
          toast({
            title: 'Bank Reconciliation Recorded',
            description: 'Variance verification against master transaction ledger completed.',
          });
        },
        onError: (errors) => {
          setReconError((Object.values(errors)[0] as string) ?? null);
          toast({
            variant: 'destructive',
            title: 'Reconciliation Failed',
            description: (Object.values(errors)[0] as string) || 'Could not record reconciliation.',
          });
        },
      }
    );
  };

  const handleValidateChannel = (channelKey: string) => {
    setIsValidatingChannel(channelKey);
    router.post(
      route('dashboard.billing.channels.validate', channelKey),
      {},
      {
        preserveScroll: true,
        onFinish: () => setIsValidatingChannel(null),
        onSuccess: () => {
          toast({
            title: 'Technical Integration Verified',
            description: `Channel [${channelKey}] has been audited against security and provider readiness standards.`,
          });
        },
      }
    );
  };

  const handleToggleChannel = (channelKey: string) => {
    router.post(
      route('dashboard.billing.channels.toggle', channelKey),
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          toast({
            title: 'Channel Status Updated',
            description: `Production availability for [${channelKey}] has been updated.`,
          });
        },
        onError: (errors) => {
          toast({
            variant: 'destructive',
            title: 'Channel Toggle Blocked',
            description: errors?.channel || 'Cannot enable channel without verified integration.',
          });
        },
      }
    );
  };

  const handleCreatePlanSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedInvoiceForPlan) return;

    router.post(
      route('dashboard.billing.payment-plans.store'),
      {
        invoice_id: selectedInvoiceForPlan,
        total_installments: planInstallments,
        frequency: planFrequency,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setIsPlanModalOpen(false);
          setSelectedInvoiceForPlan('');
          toast({
            title: 'Installment Plan Activated',
            description: `Created ${planInstallments}-part ${planFrequency} payment schedule.`,
          });
        },
      }
    );
  };

  // Helper icon for payment channels
  const getChannelIcon = (key: string) => {
    switch (key) {
      case 'card':
        return <CreditCard className="h-5 w-5 text-indigo-500" />;
      case 'apple_pay':
      case 'google_pay':
      case 'samsung_wallet':
        return <Smartphone className="h-5 w-5 text-blue-500" />;
      case 'nfc_pos':
        return <Cpu className="h-5 w-5 text-amber-500" />;
      case 'bank_wire':
        return <Landmark className="h-5 w-5 text-emerald-600" />;
      case 'cash_office':
        return <Banknote className="h-5 w-5 text-emerald-500" />;
      case 'cash_app':
      case 'zelle':
        return <DollarSign className="h-5 w-5 text-green-500" />;
      case 'qr_code':
        return <QrCode className="h-5 w-5 text-purple-500" />;
      default:
        return <CreditCard className="h-5 w-5 text-slate-500" />;
    }
  };

  return (
    <DashboardLayout>
      <Head title="Billing & Revenue Engine — Cypress Bay" />

      <div className="space-y-6">
        {/* Module Header */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="font-headline text-3xl font-bold tracking-tight text-foreground">
                Billing &amp; Treasury Engine
              </h1>
              <Badge variant="outline" className="font-mono text-xs border-emerald-500/40 text-emerald-600 bg-emerald-500/10">
                NON-CUSTODIAL LEDGER
              </Badge>
            </div>
            <p className="text-muted-foreground text-sm mt-1">
              Multi-channel payment orchestration, automated reconciliation, and resident accounts ledger.
            </p>
          </div>

          <div className="flex items-center gap-2 flex-wrap">
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                router.post('/dashboard/billing/portal', {}, {
                  preserveScroll: true,
                  onError: (err) => {
                    toast({
                      variant: 'destructive',
                      title: 'Customer Portal Unavailable',
                      description: (Object.values(err)[0] as string) || 'Unable to redirect to Stripe Customer Portal.',
                    });
                  },
                });
              }}
              className="gap-2 text-xs font-semibold border-emerald-500/30 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/40"
              title="Manage payment cards, billing address, and download past invoices via Stripe"
            >
              <CreditCard className="h-4 w-4" />
              <span>Stripe Customer Portal</span>
            </Button>

            <Button
              variant="outline"
              size="sm"
              onClick={() => setActiveTab('orchestration')}
              className="gap-2 text-xs font-semibold border-indigo-500/30 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/40"
            >
              <Cpu className="h-4 w-4" />
              <span>Payment Engine (10 Channels)</span>
            </Button>

            {isAdmin && (
              <Button
                variant="outline"
                size="sm"
                asChild
                className="gap-1.5 text-xs font-medium"
              >
                <a href={route('dashboard.billing.transactions.export')}>
                  <Download className="h-3.5 w-3.5" />
                  <span>Export Master Ledger</span>
                </a>
              </Button>
            )}
          </div>
        </div>

        {paymentCenter && (
          <PaymentCenterHero
            amountDue={paymentCenter.amountDue}
            currency={settings.currency}
            itemizedCharges={paymentCenter.itemizedCharges || []}
            walletBalance={wallet?.totalUsable || wallet?.available || 0}
            onNavigateTab={setActiveTab}
            availableChannels={paymentCenter.availableChannels}
            autoPay={paymentCenter.autoPay}
          />
        )}

        {/* 10 Views Tabbed Navigation */}
        <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
          <div className="overflow-x-auto pb-1">
            <TabsList className="h-11 bg-slate-100/90 dark:bg-slate-900/90 p-1 border border-slate-200 dark:border-slate-800 rounded-xl flex items-center w-max gap-1">
              {isAdmin && (
                <TabsTrigger value="dashboard" className="gap-1.5 text-xs font-semibold px-3 py-1.5">
                  <BarChart3 className="h-3.5 w-3.5 text-indigo-500" />
                  Dashboard
                </TabsTrigger>
              )}

              <TabsTrigger value="invoices" className="gap-1.5 text-xs font-semibold px-3 py-1.5">
                <FileText className="h-3.5 w-3.5 text-blue-500" />
                Invoices
              </TabsTrigger>

              <TabsTrigger value="outstanding" className="gap-1.5 text-xs font-semibold px-3 py-1.5">
                <AlertCircle className="h-3.5 w-3.5 text-amber-500" />
                Outstanding
              </TabsTrigger>

              <TabsTrigger value="payment-plans" className="gap-1.5 text-xs font-semibold px-3 py-1.5">
                <Calendar className="h-3.5 w-3.5 text-emerald-500" />
                Payment Plans
              </TabsTrigger>

              <TabsTrigger value="autopay" className="gap-1.5 text-xs font-semibold px-3 py-1.5">
                <RefreshCw className="h-3.5 w-3.5 text-cyan-500" />
                AutoPay
              </TabsTrigger>

              <TabsTrigger value="payment-links" className="gap-1.5 text-xs font-semibold px-3 py-1.5">
                <Link2 className="h-3.5 w-3.5 text-purple-500" />
                Payment Links
              </TabsTrigger>

              <TabsTrigger value="receipts" className="gap-1.5 text-xs font-semibold px-3 py-1.5">
                <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                Receipts
              </TabsTrigger>

              <TabsTrigger value="transactions" className="gap-1.5 text-xs font-semibold px-3 py-1.5">
                <Receipt className="h-3.5 w-3.5 text-slate-500" />
                Transactions
              </TabsTrigger>

              <TabsTrigger value="refunds" className="gap-1.5 text-xs font-semibold px-3 py-1.5">
                <RotateCcw className="h-3.5 w-3.5 text-rose-500" />
                Refunds
              </TabsTrigger>

              <TabsTrigger value="reconciliation" className="gap-1.5 text-xs font-semibold px-3 py-1.5">
                <Scale className="h-3.5 w-3.5 text-violet-500" />
                Reconciliation
              </TabsTrigger>

              <TabsTrigger value="orchestration" className="gap-1.5 text-xs font-semibold px-3 py-1.5 text-indigo-600 dark:text-indigo-400">
                <Cpu className="h-3.5 w-3.5" />
                Payment Engine
              </TabsTrigger>
            </TabsList>
          </div>

          {/* 1. Dashboard View */}
          {isAdmin && (
            <TabsContent value="dashboard" className="space-y-6 m-0">
              {financialDashboard ? (
                <AdminFinancialDashboard
                  financialDashboard={financialDashboard}
                  payouts={payouts}
                  reconciliations={reconciliations}
                  currency={settings.currency}
                />
              ) : (
                <div className="p-8 text-center border rounded-xl bg-card text-muted-foreground">
                  Financial Analytics Initializing...
                </div>
              )}
            </TabsContent>
          )}

          {/* 2. Invoices View */}
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

          {/* 3. Outstanding View */}
          <TabsContent value="outstanding" className="space-y-6 m-0">
            <Card className="border shadow-sm">
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between flex-wrap gap-2">
                  <div>
                    <CardTitle className="text-xl font-bold flex items-center gap-2">
                      <AlertCircle className="h-5 w-5 text-amber-500" />
                      <span>Outstanding Balances &amp; Delinquency Ledger</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      All unsettled assessment statements across residential lots with real-time aging calculation.
                    </CardDescription>
                  </div>
                  <Badge variant="outline" className="font-mono text-xs">
                    {outstanding.length} Delinquent Statements
                  </Badge>
                </div>
              </CardHeader>
              <CardContent>
                {outstanding.length === 0 ? (
                  <div className="p-12 text-center border border-dashed rounded-xl text-muted-foreground">
                    <CheckCircle2 className="h-8 w-8 mx-auto mb-2 text-emerald-500 opacity-60" />
                    <p className="font-semibold text-sm">All community accounts are settled in full.</p>
                    <p className="text-xs text-muted-foreground mt-1">No outstanding balances currently active.</p>
                  </div>
                ) : (
                  <div className="rounded-xl border overflow-hidden">
                    <table className="w-full text-left text-xs">
                      <thead className="bg-muted/60 text-muted-foreground font-semibold border-b">
                        <tr>
                          <th className="p-3">Statement Ref</th>
                          <th className="p-3">Homeowner</th>
                          <th className="p-3">Lot</th>
                          <th className="p-3">Amount Due</th>
                          <th className="p-3">Due Date</th>
                          <th className="p-3">Overdue Aging</th>
                          <th className="p-3 text-right">Actions</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y">
                        {outstanding.map((item) => (
                          <tr key={item.id} className="hover:bg-muted/30 transition-colors">
                            <td className="p-3 font-mono font-bold text-foreground">{item.reference}</td>
                            <td className="p-3 font-medium text-foreground">{item.homeowner}</td>
                            <td className="p-3 font-mono">Lot {item.lot}</td>
                            <td className="p-3 font-mono font-bold text-foreground">
                              {item.currency} {item.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                            </td>
                            <td className="p-3 font-mono text-muted-foreground">{item.dueOn}</td>
                            <td className="p-3">
                              <Badge
                                variant="outline"
                                className={`font-mono text-[10px] ${
                                  item.daysOverdue > 60
                                    ? 'border-red-500/40 text-red-600 bg-red-500/10'
                                    : item.daysOverdue > 30
                                    ? 'border-amber-500/40 text-amber-600 bg-amber-500/10'
                                    : 'border-slate-500/40 text-slate-600 bg-slate-500/10'
                                }`}
                              >
                                {item.daysOverdue > 0 ? `${item.daysOverdue} Days Overdue` : 'Current Due'}
                              </Badge>
                            </td>
                            <td className="p-3 text-right">
                              <div className="flex items-center justify-end gap-1.5">
                                {isAdmin && (
                                  <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => {
                                      setSelectedInvoiceForPlan(item.id.toString());
                                      setIsPlanModalOpen(true);
                                    }}
                                    className="h-7 text-xs font-semibold gap-1 text-emerald-600 border-emerald-500/30 hover:bg-emerald-50 dark:hover:bg-emerald-950/40"
                                  >
                                    <Calendar className="h-3 w-3" />
                                    <span>Payment Plan</span>
                                  </Button>
                                )}
                                <Button
                                  size="sm"
                                  variant="secondary"
                                  onClick={() => {
                                    toast({
                                      title: 'Digital Statement Sent',
                                      description: `SMS and email reminder sent to ${item.homeowner} (Lot ${item.lot}).`,
                                    });
                                  }}
                                  className="h-7 text-xs font-semibold gap-1"
                                >
                                  <Send className="h-3 w-3" />
                                  <span>Remind</span>
                                </Button>
                              </div>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          {/* 4. Payment Plans View */}
          <TabsContent value="payment-plans" className="space-y-6 m-0">
            <Card className="border shadow-sm">
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between flex-wrap gap-2">
                  <div>
                    <CardTitle className="text-xl font-bold flex items-center gap-2">
                      <Calendar className="h-5 w-5 text-emerald-500" />
                      <span>Structured Installment Payment Plans</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Formal installment agreements dividing assessments into manageable recurring payments.
                    </CardDescription>
                  </div>
                  {isAdmin && (
                    <Button
                      size="sm"
                      onClick={() => setIsPlanModalOpen(true)}
                      className="gap-1.5 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white"
                    >
                      <Plus className="h-4 w-4" />
                      <span>Create Payment Plan</span>
                    </Button>
                  )}
                </div>
              </CardHeader>
              <CardContent>
                {paymentPlans.length === 0 ? (
                  <div className="p-12 text-center border border-dashed rounded-xl text-muted-foreground">
                    <Calendar className="h-8 w-8 mx-auto mb-2 opacity-50" />
                    <p className="font-semibold text-sm">No active installment plans registered.</p>
                    <p className="text-xs text-muted-foreground mt-1">
                      Homeowners with arrears can be placed on approved installment schedules.
                    </p>
                  </div>
                ) : (
                  <div className="rounded-xl border overflow-hidden">
                    <table className="w-full text-left text-xs">
                      <thead className="bg-muted/60 text-muted-foreground font-semibold border-b">
                        <tr>
                          <th className="p-3">Homeowner</th>
                          <th className="p-3">Lot</th>
                          <th className="p-3">Statement Ref</th>
                          <th className="p-3">Installment Amount</th>
                          <th className="p-3">Progress</th>
                          <th className="p-3">Frequency</th>
                          <th className="p-3 text-right">Plan Status</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y">
                        {paymentPlans.map((plan) => (
                          <tr key={plan.id} className="hover:bg-muted/30 transition-colors">
                            <td className="p-3 font-medium text-foreground">{plan.homeowner}</td>
                            <td className="p-3 font-mono">Lot {plan.lot}</td>
                            <td className="p-3 font-mono font-bold">{plan.invoiceReference}</td>
                            <td className="p-3 font-mono font-bold text-foreground">
                              {settings.currency} {plan.installmentAmount.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                            </td>
                            <td className="p-3">
                              <span className="font-mono text-xs">
                                {plan.totalInstallments - plan.remainingInstallments} of {plan.totalInstallments} paid
                              </span>
                              <div className="w-24 h-1.5 bg-muted rounded-full overflow-hidden mt-1">
                                <div
                                  className="h-full bg-emerald-500"
                                  style={{
                                    width: `${((plan.totalInstallments - plan.remainingInstallments) / plan.totalInstallments) * 100}%`,
                                  }}
                                />
                              </div>
                            </td>
                            <td className="p-3 font-medium">{plan.frequency}</td>
                            <td className="p-3 text-right">
                              <Badge variant="outline" className="font-mono text-[10px] border-emerald-500/40 text-emerald-600 bg-emerald-500/10">
                                {plan.status}
                              </Badge>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          {/* 5. AutoPay View */}
          <TabsContent value="autopay" className="space-y-6 m-0">
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
              <div className="lg:col-span-6 space-y-6">
                <Card className="border shadow-sm">
                  <CardHeader>
                    <CardTitle className="text-lg font-bold flex items-center gap-2">
                      <RefreshCw className="h-5 w-5 text-cyan-500" />
                      <span>Resident AutoPay Preferences</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Configure automated assessment deductions to guarantee zero late fees and instant gate pass validity.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    {paymentCenter && (
                      <div className="p-4 rounded-xl border bg-muted/20 space-y-3">
                        <div className="flex items-center justify-between">
                          <span className="font-semibold text-xs text-foreground">AutoPay Deduction Status</span>
                          <Badge variant={paymentCenter.autoPay.isActive ? 'default' : 'secondary'} className="font-mono text-xs">
                            {paymentCenter.autoPay.isActive ? 'ACTIVE & ENROLLED' : 'PAUSED / OFF'}
                          </Badge>
                        </div>
                        <div className="grid grid-cols-2 gap-3 text-xs">
                          <div className="p-2.5 rounded-lg border bg-background">
                            <span className="text-muted-foreground block text-[10px] uppercase font-semibold">Deduction Cadence</span>
                            <strong className="text-foreground capitalize">{paymentCenter.autoPay.cadence}</strong>
                          </div>
                          <div className="p-2.5 rounded-lg border bg-background">
                            <span className="text-muted-foreground block text-[10px] uppercase font-semibold">Monthly Charge Day</span>
                            <strong className="text-foreground font-mono">Day {paymentCenter.autoPay.chargeDay} of month</strong>
                          </div>
                        </div>
                        <p className="text-[11px] text-muted-foreground">
                          Deduction is routed securely through non-custodial tokenized channels on file.
                        </p>
                      </div>
                    )}
                  </CardContent>
                </Card>
              </div>

              {isAdmin && autoPayPortfolio && (
                <div className="lg:col-span-6 space-y-6">
                  <Card className="border shadow-sm">
                    <CardHeader>
                      <CardTitle className="text-lg font-bold flex items-center gap-2">
                        <BarChart3 className="h-5 w-5 text-indigo-500" />
                        <span>Estate AutoPay Portfolio Metrics</span>
                      </CardTitle>
                      <CardDescription className="text-xs">
                        Automated recurring collection volume across registered community households.
                      </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                      <div className="grid grid-cols-2 gap-3">
                        <div className="p-3 rounded-xl border bg-emerald-500/10 border-emerald-500/20 text-center">
                          <span className="text-[10px] text-emerald-800 dark:text-emerald-300 font-semibold uppercase block">
                            Enrolled Households
                          </span>
                          <strong className="text-2xl font-mono font-bold text-emerald-700 dark:text-emerald-200">
                            {autoPayPortfolio.enrolledCount}
                          </strong>
                          <span className="text-[10px] text-muted-foreground block mt-0.5">Recurring Direct Dues</span>
                        </div>
                        <div className="p-3 rounded-xl border bg-indigo-500/10 border-indigo-500/20 text-center">
                          <span className="text-[10px] text-indigo-800 dark:text-indigo-300 font-semibold uppercase block">
                            Next Cycle Run
                          </span>
                          <strong className="text-lg font-mono font-bold text-indigo-700 dark:text-indigo-200">
                            {autoPayPortfolio.nextRunDate}
                          </strong>
                          <span className="text-[10px] text-muted-foreground block mt-0.5">Automated Ledger Settlement</span>
                        </div>
                      </div>

                      <div className="p-3 rounded-xl border bg-background space-y-2 text-xs">
                        <span className="text-[10px] font-semibold text-muted-foreground uppercase block">
                          Cadence Distribution
                        </span>
                        <div className="flex items-center justify-between">
                          <span>Monthly Cycles:</span>
                          <strong className="font-mono">{autoPayPortfolio.cadenceBreakdown.monthly || 0} accounts</strong>
                        </div>
                        <div className="flex items-center justify-between">
                          <span>Quarterly Cycles:</span>
                          <strong className="font-mono">{autoPayPortfolio.cadenceBreakdown.quarterly || 0} accounts</strong>
                        </div>
                        <div className="flex items-center justify-between">
                          <span>Annual Cycles:</span>
                          <strong className="font-mono">{autoPayPortfolio.cadenceBreakdown.annual || 0} accounts</strong>
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                </div>
              )}
            </div>
          </TabsContent>

          {/* 6. Payment Links View */}
          <TabsContent value="payment-links" className="space-y-6 m-0">
            <PaymentLinksManager
              links={paymentLinks}
              canManage={isAdmin}
              currency={settings.currency}
            />
          </TabsContent>

          {/* 7. Receipts View */}
          <TabsContent value="receipts" className="space-y-6 m-0">
            <Card className="border shadow-sm">
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between flex-wrap gap-2">
                  <div>
                    <CardTitle className="text-xl font-bold flex items-center gap-2">
                      <Receipt className="h-5 w-5 text-emerald-600" />
                      <span>Official Payment Receipts Ledger</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Formal cryptographically signed payment receipts confirming settlements to the Master Ledger.
                    </CardDescription>
                  </div>
                  <Badge variant="outline" className="font-mono text-xs">
                    {receipts.length} Official Receipts
                  </Badge>
                </div>
              </CardHeader>
              <CardContent>
                {receipts.length === 0 ? (
                  <div className="p-12 text-center border border-dashed rounded-xl text-muted-foreground">
                    <Receipt className="h-8 w-8 mx-auto mb-2 opacity-50" />
                    <p className="font-semibold text-sm">No payment receipts issued yet.</p>
                    <p className="text-xs text-muted-foreground mt-1">
                      Receipts are issued automatically upon transaction settlement.
                    </p>
                  </div>
                ) : (
                  <div className="rounded-xl border overflow-hidden">
                    <table className="w-full text-left text-xs">
                      <thead className="bg-muted/60 text-muted-foreground font-semibold border-b">
                        <tr>
                          <th className="p-3">Receipt Number</th>
                          <th className="p-3">Reference</th>
                          <th className="p-3">Payee</th>
                          <th className="p-3">Amount</th>
                          <th className="p-3">Channel</th>
                          <th className="p-3">Date</th>
                          <th className="p-3 text-right">Official Document</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y">
                        {receipts.map((rcpt) => (
                          <tr key={rcpt.id} className="hover:bg-muted/30 transition-colors">
                            <td className="p-3 font-mono font-bold text-foreground">{rcpt.receiptNumber}</td>
                            <td className="p-3 font-mono text-muted-foreground">{rcpt.reference}</td>
                            <td className="p-3 font-medium text-foreground">
                              {rcpt.homeowner} {rcpt.lot ? `(Lot ${rcpt.lot})` : ''}
                            </td>
                            <td className="p-3 font-mono font-bold text-foreground">
                              {rcpt.currency} {rcpt.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                            </td>
                            <td className="p-3 capitalize">{rcpt.channel}</td>
                            <td className="p-3 font-mono text-muted-foreground">{rcpt.date}</td>
                            <td className="p-3 text-right">
                              <Button
                                size="sm"
                                variant="outline"
                                asChild
                                className="h-7 text-xs font-semibold gap-1 text-emerald-600 border-emerald-500/30 hover:bg-emerald-50 dark:hover:bg-emerald-950/40"
                              >
                                <a href={rcpt.receiptUrl} download>
                                  <Download className="h-3 w-3" />
                                  <span>Download PDF</span>
                                </a>
                              </Button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          {/* 8. Transactions View */}
          <TabsContent value="transactions" className="space-y-6 m-0">
            {transactions ? (
              <TransactionsLedger
                transactions={transactions}
                pendingPayments={pendingPayments}
                currency={settings.currency}
              />
            ) : (
              <div className="py-12 text-center text-muted-foreground">
                No ledger records available.
              </div>
            )}

            {isAdmin && inPerson && (
              <InPersonPayments
                inPerson={inPerson}
                openInvoices={(invoices?.data ?? [])
                  .filter((i) => i.status === 'Unpaid' || i.status === 'Overdue')
                  .map((i) => ({ id: i.id, reference: i.reference, amount: i.amount, currency: i.currency, homeowner: i.homeowner }))}
              />
            )}
          </TabsContent>

          {/* 9. Refunds View */}
          <TabsContent value="refunds" className="space-y-6 m-0">
            <Card className="border shadow-sm">
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between flex-wrap gap-2">
                  <div>
                    <CardTitle className="text-xl font-bold flex items-center gap-2">
                      <RotateCcw className="h-5 w-5 text-rose-500" />
                      <span>Refunds &amp; Disputed Adjustments Ledger</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Complete audit log of returned funds and reversal transactions.
                    </CardDescription>
                  </div>
                  <Badge variant="outline" className="font-mono text-xs text-rose-600 border-rose-500/40 bg-rose-500/10">
                    {refundsList.length} Refund Events
                  </Badge>
                </div>
              </CardHeader>
              <CardContent>
                {refundsList.length === 0 ? (
                  <div className="p-12 text-center border border-dashed rounded-xl text-muted-foreground">
                    <RotateCcw className="h-8 w-8 mx-auto mb-2 opacity-50" />
                    <p className="font-semibold text-sm">No refunded transactions on record.</p>
                    <p className="text-xs text-muted-foreground mt-1">
                      Refunds are processed in compliance with Community HOA Financial Bylaws.
                    </p>
                  </div>
                ) : (
                  <div className="rounded-xl border overflow-hidden">
                    <table className="w-full text-left text-xs">
                      <thead className="bg-muted/60 text-muted-foreground font-semibold border-b">
                        <tr>
                          <th className="p-3">Reference</th>
                          <th className="p-3">Resident</th>
                          <th className="p-3">Amount Returned</th>
                          <th className="p-3">Channel</th>
                          <th className="p-3">Processed Date</th>
                          <th className="p-3">Audit Notes</th>
                          <th className="p-3 text-right">Status</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y">
                        {refundsList.map((refItem) => (
                          <tr key={refItem.id} className="hover:bg-muted/30 transition-colors">
                            <td className="p-3 font-mono font-bold">{refItem.reference}</td>
                            <td className="p-3 font-medium text-foreground">
                              {refItem.homeowner} {refItem.lot ? `(Lot ${refItem.lot})` : ''}
                            </td>
                            <td className="p-3 font-mono font-bold text-rose-600">
                              {refItem.currency} {refItem.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                            </td>
                            <td className="p-3 capitalize">{refItem.channel}</td>
                            <td className="p-3 font-mono text-muted-foreground">{refItem.date}</td>
                            <td className="p-3 text-muted-foreground">{refItem.notes || 'Reversal per bylaws'}</td>
                            <td className="p-3 text-right">
                              <Badge variant="outline" className="font-mono text-[10px] border-rose-500/40 text-rose-600 bg-rose-500/10">
                                REFUNDED
                              </Badge>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          {/* 10. Reconciliation View */}
          <TabsContent value="reconciliation" className="space-y-6 m-0">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2">
              <div>
                <h3 className="text-base font-semibold">Ledger &amp; Bank Settlement Engine</h3>
                <p className="text-xs text-muted-foreground">Automated variance verification, Stripe event audit logs, and operational disbursements.</p>
              </div>
              <Button
                size="sm"
                onClick={() => setIsReconModalOpen(true)}
                className="gap-2 bg-violet-600 hover:bg-violet-700 text-white font-medium text-xs shadow-sm self-start sm:self-auto"
              >
                <Plus className="h-4 w-4" />
                <span>New Reconciliation</span>
              </Button>
            </div>

            {/* Tri-Party Continuous Reconciliation Matrix: CommunityHub Ledger vs Provider vs Bank */}
            {triPartyReconciliation && (
              <div className="p-5 rounded-2xl border bg-gradient-to-br from-card via-card to-violet-500/5 shadow-sm space-y-4">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                  <div>
                    <div className="flex items-center gap-2">
                      <h4 className="font-bold text-sm tracking-tight text-foreground flex items-center gap-2">
                        <Scale className="h-4 w-4 text-violet-500" />
                        <span>Tri-Party Continuous Reconciliation Matrix</span>
                      </h4>
                      <Badge variant="outline" className="font-mono text-[10px] border-violet-500/40 text-violet-600 bg-violet-500/10">
                        {triPartyReconciliation.currency}
                      </Badge>
                    </div>
                    <p className="text-xs text-muted-foreground mt-0.5">
                      Triangulated verification auditing CommunityHub Master Ledger entries against Payment Gateway feeds and Bank Clearing deposits.
                    </p>
                  </div>
                  <Badge
                    variant="outline"
                    className={`font-mono text-xs px-2.5 py-1 ${
                      triPartyReconciliation.variance.is_balanced
                        ? 'border-emerald-500/40 text-emerald-600 bg-emerald-500/10'
                        : 'border-amber-500/40 text-amber-600 bg-amber-500/10'
                    }`}
                  >
                    {triPartyReconciliation.variance.is_balanced ? '✓ TRI-PARTY BALANCED' : 'VARIANCE DETECTED'}
                  </Badge>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  {/* Card 1: CommunityHub Ledger */}
                  <div className="p-4 rounded-xl border bg-background/60 shadow-xs space-y-2">
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                      <span className="font-medium">1. CommunityHub Ledger</span>
                      <Badge variant="secondary" className="text-[9px] font-mono">INTERNAL</Badge>
                    </div>
                    <div className="font-mono font-bold text-xl text-foreground">
                      {triPartyReconciliation.currency} {triPartyReconciliation.communityHubLedger.total.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                    </div>
                    <div className="text-[11px] text-muted-foreground flex items-center gap-1">
                      <CheckCircle2 className="h-3 w-3 text-emerald-500" />
                      <span>{triPartyReconciliation.communityHubLedger.settled_transactions_count} settled transactions audited</span>
                    </div>
                  </div>

                  {/* Card 2: Payment Provider (Stripe) */}
                  <div className="p-4 rounded-xl border bg-background/60 shadow-xs space-y-2">
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                      <span className="font-medium">2. Gateway ({triPartyReconciliation.paymentProvider.name})</span>
                      <Badge variant="secondary" className="text-[9px] font-mono">PROCESSOR</Badge>
                    </div>
                    <div className="font-mono font-bold text-xl text-foreground">
                      {triPartyReconciliation.currency} {triPartyReconciliation.paymentProvider.total.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                    </div>
                    <div className="text-[11px] text-muted-foreground flex items-center gap-1">
                      <ShieldCheck className="h-3 w-3 text-indigo-500" />
                      <span>HMAC-SHA256 verified webhooks</span>
                    </div>
                  </div>

                  {/* Card 3: Bank Operating Feed */}
                  <div className="p-4 rounded-xl border bg-background/60 shadow-xs space-y-2">
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                      <span className="font-medium">3. Bank Feed (NCB)</span>
                      <Badge variant="secondary" className="text-[9px] font-mono">EXTERNAL FEED</Badge>
                    </div>
                    <div className="font-mono font-bold text-xl text-foreground">
                      {triPartyReconciliation.currency} {triPartyReconciliation.bank.total.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                    </div>
                    <div className="text-[11px] text-muted-foreground flex items-center gap-1">
                      <Landmark className="h-3 w-3 text-emerald-500" />
                      <span>Statement as of {triPartyReconciliation.bank.statement_date}</span>
                    </div>
                  </div>
                </div>
              </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
              <div className="lg:col-span-7 space-y-6">
                <Card className="border shadow-sm">
                  <CardHeader>
                    <div className="flex items-center justify-between">
                      <div>
                        <CardTitle className="text-lg font-bold flex items-center gap-2">
                          <Scale className="h-5 w-5 text-violet-500" />
                          <span>Bank Statement vs. Master Ledger Reconciliation</span>
                        </CardTitle>
                        <CardDescription className="text-xs">
                          Continuous automated variance verification matching external bank feeds against internal ledger entries.
                        </CardDescription>
                      </div>
                      <Badge variant="outline" className="font-mono text-xs">
                        {reconciliations.length} Audits
                      </Badge>
                    </div>
                  </CardHeader>
                  <CardContent>
                    {reconciliations.length === 0 ? (
                      <div className="p-8 text-center border border-dashed rounded-xl text-muted-foreground">
                        <Scale className="h-7 w-7 mx-auto mb-2 opacity-50" />
                        <p className="font-semibold text-xs">Bank statement feeds fully balanced.</p>
                      </div>
                    ) : (
                      <div className="rounded-xl border overflow-hidden">
                        <table className="w-full text-left text-xs">
                          <thead className="bg-muted/60 text-muted-foreground font-semibold border-b">
                            <tr>
                              <th className="p-3">Statement Date</th>
                              <th className="p-3">Bank Balance</th>
                              <th className="p-3">Ledger Balance</th>
                              <th className="p-3">Variance</th>
                              <th className="p-3 text-right">Status</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y">
                            {reconciliations.map((rec) => (
                              <tr key={rec.id} className="hover:bg-muted/30">
                                <td className="p-3 font-mono font-medium">{rec.statementDate}</td>
                                <td className="p-3 font-mono">{settings.currency} {rec.statementBalance.toLocaleString()}</td>
                                <td className="p-3 font-mono">{settings.currency} {rec.ledgerBalance.toLocaleString()}</td>
                                <td className="p-3 font-mono font-bold">
                                  {rec.difference === 0 ? (
                                    <span className="text-emerald-600">J$0.00 (Balanced)</span>
                                  ) : (
                                    <span className="text-amber-600">J${rec.difference.toFixed(2)}</span>
                                  )}
                                </td>
                                <td className="p-3 text-right">
                                  <Badge variant="outline" className="font-mono text-[10px] border-emerald-500/40 text-emerald-600 bg-emerald-500/10">
                                    {rec.status}
                                  </Badge>
                                </td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    )}
                  </CardContent>
                </Card>
              </div>

              <div className="lg:col-span-5 space-y-6">
                <Card className="border shadow-sm">
                  <CardHeader>
                    <CardTitle className="text-lg font-bold flex items-center gap-2">
                      <Landmark className="h-5 w-5 text-indigo-500" />
                      <span>Vendor &amp; Operating Payouts</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Approved scheduled disbursements for community utilities, landscaping, and gate security.
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    {payouts.length === 0 ? (
                      <div className="p-8 text-center border border-dashed rounded-xl text-muted-foreground">
                        <Landmark className="h-7 w-7 mx-auto mb-2 opacity-50" />
                        <p className="font-semibold text-xs">No pending vendor payouts.</p>
                      </div>
                    ) : (
                      <div className="space-y-2">
                        {payouts.map((p) => (
                          <div key={p.id} className="p-3 rounded-xl border bg-muted/20 flex items-center justify-between text-xs">
                            <div>
                              <strong className="text-foreground block">{p.vendor}</strong>
                              <span className="text-muted-foreground text-[11px] block">{p.category} &bull; Scheduled {p.scheduledFor}</span>
                            </div>
                            <div className="text-right">
                              <span className="font-mono font-bold text-foreground block">
                                {p.currency} {p.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                              </span>
                              <Badge variant="outline" className="text-[9px] font-mono border-emerald-500/40 text-emerald-600 bg-emerald-500/10">
                                {p.status}
                              </Badge>
                            </div>
                          </div>
                        ))}
                      </div>
                    )}
                  </CardContent>
                </Card>
              </div>
            </div>

            {/* Verified Stripe Payment Events & Webhook Audit */}
            <Card className="border shadow-sm">
              <CardHeader>
                <div className="flex items-center justify-between">
                  <div>
                    <CardTitle className="text-lg font-bold flex items-center gap-2">
                      <ShieldCheck className="h-5 w-5 text-emerald-500" />
                      <span>Verified Payment Webhook Events &amp; Idempotency Ledger</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Cryptographically verified Stripe webhook events (HMAC-SHA256), idempotency tracking, settlement logs, and dispute lifecycle audits.
                    </CardDescription>
                  </div>
                  <Badge variant="outline" className="font-mono text-xs">
                    {paymentEvents.length} Events Logged
                  </Badge>
                </div>
              </CardHeader>
              <CardContent>
                {paymentEvents.length === 0 ? (
                  <div className="p-8 text-center border border-dashed rounded-xl text-muted-foreground">
                    <ShieldCheck className="h-7 w-7 mx-auto mb-2 opacity-50" />
                    <p className="font-semibold text-xs">No Stripe webhook events recorded yet.</p>
                    <p className="text-[11px] text-muted-foreground mt-1">Live webhook events will be audited here with verified signatures and idempotency status.</p>
                  </div>
                ) : (
                  <div className="rounded-xl border overflow-hidden">
                    <table className="w-full text-left text-xs">
                      <thead className="bg-muted/60 text-muted-foreground font-semibold border-b">
                        <tr>
                          <th className="p-3">Stripe Event ID</th>
                          <th className="p-3">Event Type</th>
                          <th className="p-3">Audit Status</th>
                          <th className="p-3">Details / Errors</th>
                          <th className="p-3 text-right">Received At</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y">
                        {paymentEvents.map((evt) => (
                          <tr key={evt.id} className="hover:bg-muted/30">
                            <td className="p-3 font-mono font-medium text-foreground">{evt.eventId}</td>
                            <td className="p-3">
                              <span className="font-mono font-semibold px-2 py-0.5 rounded bg-muted text-[11px]">
                                {evt.type}
                              </span>
                            </td>
                            <td className="p-3">
                              <Badge
                                variant="outline"
                                className={`font-mono text-[10px] ${
                                  evt.status === 'processed'
                                    ? 'border-emerald-500/40 text-emerald-600 bg-emerald-500/10'
                                    : evt.status === 'duplicate'
                                    ? 'border-amber-500/40 text-amber-600 bg-amber-500/10'
                                    : evt.status === 'failed'
                                    ? 'border-red-500/40 text-red-600 bg-red-500/10'
                                    : 'border-blue-500/40 text-blue-600 bg-blue-500/10'
                                }`}
                              >
                                {evt.status}
                              </Badge>
                            </td>
                            <td className="p-3 text-muted-foreground text-[11px] max-w-xs truncate">
                              {evt.errorMessage || '—'}
                            </td>
                            <td className="p-3 text-right font-mono text-muted-foreground">{evt.date}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Chart of Accounts (Double-Entry General Ledger) */}
            <Card className="border shadow-sm">
              <CardHeader>
                <div className="flex items-center justify-between flex-wrap gap-2">
                  <div>
                    <CardTitle className="text-lg font-bold flex items-center gap-2">
                      <Layers className="h-5 w-5 text-indigo-500" />
                      <span>Chart of Accounts &amp; General Ledger Sub-Accounts</span>
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Double-entry accounting structure with real-time balance calculations for assets, liabilities, revenues, and campaign funds.
                    </CardDescription>
                  </div>
                  <Badge variant="outline" className="font-mono text-xs">
                    {chartOfAccounts.length} Active Accounts
                  </Badge>
                </div>
              </CardHeader>
              <CardContent>
                {chartOfAccounts.length === 0 ? (
                  <div className="p-8 text-center border border-dashed rounded-xl text-muted-foreground">
                    <p className="font-semibold text-xs">No ledger accounts registered.</p>
                  </div>
                ) : (
                  <div className="rounded-xl border overflow-hidden">
                    <table className="w-full text-left text-xs">
                      <thead className="bg-muted/60 text-muted-foreground font-semibold border-b">
                        <tr>
                          <th className="p-3">Code</th>
                          <th className="p-3">Account Name</th>
                          <th className="p-3">Classification</th>
                          <th className="p-3">Currency</th>
                          <th className="p-3 text-right">Journal Entries</th>
                          <th className="p-3 text-right">Account Balance</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y">
                        {chartOfAccounts.map((acc) => (
                          <tr key={acc.id} className="hover:bg-muted/30 transition-colors">
                            <td className="p-3 font-mono font-bold text-foreground">{acc.code}</td>
                            <td className="p-3 font-medium text-foreground">
                              {acc.name}
                              {acc.description && (
                                <span className="block text-[10px] text-muted-foreground">{acc.description}</span>
                              )}
                            </td>
                            <td className="p-3">
                              <Badge
                                variant="outline"
                                className={`text-[10px] font-mono uppercase ${
                                  acc.type === 'asset'
                                    ? 'border-emerald-500/40 text-emerald-600 bg-emerald-500/10'
                                    : acc.type === 'liability'
                                    ? 'border-amber-500/40 text-amber-600 bg-amber-500/10'
                                    : acc.type === 'revenue'
                                    ? 'border-blue-500/40 text-blue-600 bg-blue-500/10'
                                    : 'border-slate-500/40 text-slate-600'
                                }`}
                              >
                                {acc.type}
                              </Badge>
                            </td>
                            <td className="p-3 font-mono text-muted-foreground">{acc.currency}</td>
                            <td className="p-3 text-right font-mono text-muted-foreground">{acc.entriesCount} entries</td>
                            <td className="p-3 text-right font-mono font-bold text-foreground">
                              {formatMoney(acc.balance, acc.currency)}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          {/* 11. Payment Orchestration Engine (The 10 Channels) */}
          <TabsContent value="orchestration" className="space-y-6 m-0">
            <Card className="border shadow-md bg-gradient-to-br from-card via-card to-primary/5">
              <CardHeader>
                <div className="flex items-center justify-between flex-wrap gap-2">
                  <div className="flex items-center gap-3">
                    <div className="p-2.5 rounded-xl bg-indigo-500/10 text-indigo-600 border border-indigo-500/20">
                      <Cpu className="h-6 w-6" />
                    </div>
                    <div>
                      <CardTitle className="text-xl font-bold flex items-center gap-2">
                        <span>Payment Orchestration &amp; Technical Integration Matrix</span>
                        <Badge variant="outline" className="font-mono text-xs border-indigo-500/40 text-indigo-600 bg-indigo-500/10">
                          10 CHANNELS
                        </Badge>
                      </CardTitle>
                      <CardDescription className="text-xs">
                        Provider availability and technical integration are validated individually before production enablement.
                      </CardDescription>
                    </div>
                  </div>

                  <div className="flex items-center gap-2">
                    <Badge variant="secondary" className="font-mono text-xs">
                      {paymentChannels.filter((c) => c.enabled).length} of 10 Enabled in Production
                    </Badge>
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {paymentChannels.map((channel) => (
                    <div
                      key={channel.channel_key}
                      className={`p-4 rounded-xl border transition-all ${
                        channel.enabled
                          ? 'border-emerald-500/30 bg-emerald-500/[0.02] shadow-sm'
                          : 'border-border bg-card/60 opacity-80'
                      }`}
                    >
                      <div className="flex items-start justify-between gap-3 mb-3">
                        <div className="flex items-center gap-2.5">
                          <div className="p-2 rounded-lg bg-muted/60 border shrink-0">
                            {getChannelIcon(channel.channel_key)}
                          </div>
                          <div>
                            <h4 className="font-bold text-sm text-foreground flex items-center gap-1.5">
                              {channel.label}
                              {channel.is_ready ? (
                                <Badge variant="outline" className="text-[9px] font-mono border-emerald-500/40 text-emerald-600 bg-emerald-500/10">
                                  ✓ Verified
                                </Badge>
                              ) : (
                                <Badge variant="outline" className="text-[9px] font-mono border-amber-500/40 text-amber-600 bg-amber-500/10">
                                  Needs Config
                                </Badge>
                              )}
                            </h4>
                            <span className="font-mono text-[10px] text-muted-foreground block">
                              Driver: {channel.channel_key} &bull; Validated: {channel.validated_at}
                            </span>
                          </div>
                        </div>

                        <Badge
                          variant={channel.enabled ? 'default' : 'secondary'}
                          className={`font-mono text-[10px] ${
                            channel.enabled ? 'bg-emerald-600 text-white' : ''
                          }`}
                        >
                          {channel.enabled ? 'PRODUCTION ACTIVE' : 'DISABLED'}
                        </Badge>
                      </div>

                      {/* Technical Checklist */}
                      <div className="space-y-1.5 mb-3 p-2.5 rounded-lg bg-muted/30 border text-xs">
                        <span className="text-[10px] font-semibold text-muted-foreground uppercase block">
                          Pre-Flight Technical Checks
                        </span>
                        {channel.checks.map((chk, idx) => (
                          <div key={idx} className="flex items-center justify-between text-[11px]">
                            <span className="flex items-center gap-1 text-foreground">
                              {chk.passed ? (
                                <CheckCircle2 className="h-3 w-3 text-emerald-600 shrink-0" />
                              ) : (
                                <XCircle className="h-3 w-3 text-rose-500 shrink-0" />
                              )}
                              <span>{chk.name}</span>
                            </span>
                            <span className="text-[10px] font-mono text-muted-foreground">{chk.message}</span>
                          </div>
                        ))}
                      </div>

                      {/* Action buttons */}
                      {isAdmin && (
                        <div className="flex items-center justify-between pt-2 border-t gap-2">
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={isValidatingChannel === channel.channel_key}
                            onClick={() => handleValidateChannel(channel.channel_key)}
                            className="h-7 text-xs font-semibold gap-1 text-primary"
                          >
                            <ShieldCheck className="h-3 w-3" />
                            <span>{isValidatingChannel === channel.channel_key ? 'Validating...' : 'Validate Integration'}</span>
                          </Button>

                          <Button
                            type="button"
                            size="sm"
                            variant={channel.enabled ? 'destructive' : 'default'}
                            onClick={() => handleToggleChannel(channel.channel_key)}
                            className={`h-7 text-xs font-semibold gap-1 ${
                              !channel.enabled ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : ''
                            }`}
                          >
                            <span>{channel.enabled ? 'Disable Channel' : 'Enable for Production'}</span>
                          </Button>
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>

      {/* Create Payment Plan Dialog */}
      <Dialog open={isPlanModalOpen} onOpenChange={setIsPlanModalOpen}>
        <DialogContent className="sm:max-w-md">
          <form onSubmit={handleCreatePlanSubmit}>
            <DialogHeader>
              <div className="flex items-center gap-2 mb-1">
                <span className="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-600">
                  <Calendar className="h-5 w-5" />
                </span>
                <DialogTitle className="text-lg font-bold">Configure Installment Payment Plan</DialogTitle>
              </div>
              <DialogDescription className="text-xs">
                Divide an outstanding assessment statement into structured, scheduled recurring installments.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-4 py-3">
              <div className="space-y-1.5">
                <Label className="text-xs font-semibold">Select Statement / Account</Label>
                <Select value={selectedInvoiceForPlan} onValueChange={setSelectedInvoiceForPlan}>
                  <SelectTrigger className="text-xs font-mono">
                    <SelectValue placeholder="Select outstanding invoice..." />
                  </SelectTrigger>
                  <SelectContent>
                    {outstanding.map((out) => (
                      <SelectItem key={out.id} value={out.id.toString()} className="text-xs font-mono">
                        {out.reference} &bull; {out.homeowner} (Lot {out.lot}) &bull; {out.currency} {out.amount.toFixed(2)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1.5">
                  <Label className="text-xs font-semibold">Total Installments</Label>
                  <Select
                    value={planInstallments.toString()}
                    onValueChange={(v) => setPlanInstallments(parseInt(v, 10))}
                  >
                    <SelectTrigger className="text-xs font-mono">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="2">2 Installments</SelectItem>
                      <SelectItem value="3">3 Installments</SelectItem>
                      <SelectItem value="4">4 Installments</SelectItem>
                      <SelectItem value="6">6 Installments</SelectItem>
                      <SelectItem value="12">12 Installments</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-1.5">
                  <Label className="text-xs font-semibold">Cadence / Frequency</Label>
                  <Select value={planFrequency} onValueChange={setPlanFrequency}>
                    <SelectTrigger className="text-xs font-mono">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="monthly">Monthly</SelectItem>
                      <SelectItem value="biweekly">Bi-Weekly</SelectItem>
                      <SelectItem value="weekly">Weekly</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
            </div>

            <DialogFooter className="gap-2 sm:gap-0">
              <Button type="button" variant="outline" size="sm" onClick={() => setIsPlanModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" size="sm" disabled={!selectedInvoiceForPlan} className="bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5">
                <CheckCircle2 className="h-4 w-4" />
                <span>Activate Schedule</span>
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Create Bank Reconciliation Dialog */}
      <Dialog open={isReconModalOpen} onOpenChange={setIsReconModalOpen}>
        <DialogContent className="sm:max-w-md">
          <form onSubmit={handleCreateReconSubmit}>
            <DialogHeader>
              <div className="flex items-center gap-2 mb-1">
                <span className="p-1.5 rounded-lg bg-violet-500/10 text-violet-600">
                  <Scale className="h-5 w-5" />
                </span>
                <DialogTitle className="text-lg font-bold">New Bank Statement Reconciliation</DialogTitle>
              </div>
              <DialogDescription className="text-xs">
                Compare external bank account ending balance against the general ledger to verify zero financial variance.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-4 py-3">
              <div className="space-y-1.5">
                <Label htmlFor="recon-date" className="text-xs font-semibold">Statement Cut-Off Date</Label>
                <Input
                  id="recon-date"
                  type="date"
                  value={reconDate}
                  onChange={(e) => setReconDate(e.target.value)}
                  className="text-xs font-mono"
                  required
                />
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="recon-balance" className="text-xs font-semibold">
                  Official Statement Ending Balance ({settings.currency})
                </Label>
                <Input
                  id="recon-balance"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="e.g. 18450.00"
                  value={reconBalance}
                  onChange={(e) => setReconBalance(e.target.value)}
                  className="text-xs font-mono"
                  required
                />
              </div>

              {receivedPayments.length > 0 && (
                <fieldset className="space-y-2 rounded-lg border border-border p-3">
                  <legend className="px-1 text-xs font-semibold">Verify office payments on this statement</legend>
                  <p className="text-[11px] text-muted-foreground">
                    Tick each payment you can see on the statement. It is applied to its statement or campaign, and
                    counted in this reconciliation. You cannot verify a payment you logged as received yourself.
                  </p>
                  <ul className="space-y-1.5">
                    {receivedPayments.map((p) => (
                      <li key={p.id}>
                        <label className={`flex items-start gap-2 text-xs ${p.canVerify ? '' : 'opacity-60'}`}>
                          <input
                            type="checkbox"
                            className="mt-0.5"
                            disabled={!p.canVerify}
                            checked={reconVerifyIds.includes(p.id)}
                            onChange={(e) =>
                              setReconVerifyIds((ids) => (e.target.checked ? [...ids, p.id] : ids.filter((id) => id !== p.id)))
                            }
                          />
                          <span>
                            <span className="font-mono font-semibold">{p.transactionId}</span>{' '}
                            {p.currency} {p.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })} · {p.paymentMethod} ·{' '}
                            {p.homeowner ?? 'Unknown payer'}
                            {p.bankReference ? ` · bank ref ${p.bankReference}` : ''}
                            <span className="block text-[10px] text-muted-foreground">
                              {p.canVerify
                                ? `Received by ${p.receivedBy ?? 'another administrator'} on ${p.receivedAt ?? '—'}`
                                : 'You logged this as received; another administrator must verify it.'}
                            </span>
                          </span>
                        </label>
                      </li>
                    ))}
                  </ul>
                </fieldset>
              )}

              {reconError && (
                <p role="alert" className="text-xs text-destructive">{reconError}</p>
              )}

              <div className="space-y-1.5">
                <Label htmlFor="recon-notes" className="text-xs font-semibold">Statement Reference / Audit Notes (Optional)</Label>
                <Input
                  id="recon-notes"
                  placeholder="e.g. NCB Monthly Operating Account #209485"
                  value={reconNotes}
                  onChange={(e) => setReconNotes(e.target.value)}
                  className="text-xs"
                />
              </div>
            </div>

            <DialogFooter className="gap-2 sm:gap-0">
              <Button type="button" variant="outline" size="sm" onClick={() => setIsReconModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" size="sm" disabled={isSubmittingRecon || !reconBalance} className="bg-violet-600 hover:bg-violet-700 text-white gap-1.5">
                <Scale className="h-4 w-4" />
                <span>{isSubmittingRecon ? 'Auditing Ledger...' : 'Run Audit'}</span>
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </DashboardLayout>
  );
}
