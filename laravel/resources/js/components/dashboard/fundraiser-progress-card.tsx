import { useState } from 'react';
import { router } from '@inertiajs/react';
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Badge } from '@/components/ui/badge';
import { ClientFormattedDate } from '@/components/client-formatted-date';
import {
  HeartHandshake,
  Sparkles,
  Download,
  Building,
  Layers,
  FileCheck2,
} from 'lucide-react';
import { DonateForm } from './donate-form';
import type { PaymentChannel } from '@/lib/payment-channels';
import { useToast } from '@/hooks/use-toast';

export type FundraiserRow = {
  id: number;
  title: string;
  description: string;
  goal: number;
  raised: number;
  progress: number;
  donorCount: number;
  currency: string;
  startDate: string;
  endDate: string;
  status: 'Active' | 'Completed' | 'Upcoming' | 'Canceled';
  isOpen?: boolean;
  beneficiary?: string | null;
  matchingSponsor?: string | null;
  fundAllocation?: Record<string, number> | null;
  updates?: {
    id: number;
    title: string;
    content: string;
    date: string;
  }[];
  recentDonations?: {
    id?: number;
    donorName: string;
    amount: number;
    currency: string;
    timestamp: string;
    receiptUrl?: string;
  }[];
};

type FundraiserProgressCardProps = {
  fundraiser: FundraiserRow;
  canManage?: boolean;
  /** The estate's enabled payment methods, passed through to the donate form. */
  channels?: PaymentChannel[];
};

const JMD_EQUIVALENTS = {
  USD: 0.0064,
  GBP: 0.0051,
  EUR: 0.006,
  CAD: 0.0088,
} as const;

const LOCALES: Record<string, string> = {
  USD: 'en-US',
  GBP: 'en-GB',
  EUR: 'de-DE',
  CAD: 'en-CA',
  JMD: 'en-JM',
};

function money(amount: number, currency: string): string {
  return amount.toLocaleString(LOCALES[currency] ?? undefined, {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  });
}

function statusVariant(status: FundraiserRow['status']) {
  switch (status) {
    case 'Active':
      return 'default' as const;
    case 'Completed':
      return 'secondary' as const;
    case 'Upcoming':
      return 'outline' as const;
    default:
      return 'destructive' as const;
  }
}

export function FundraiserProgressCard({
  fundraiser,
  canManage,
  channels = [],
}: FundraiserProgressCardProps) {
  const { toast } = useToast();
  const [isDonateOpen, setDonateOpen] = useState(false);
  const [enabling, setEnabling] = useState(false);

  const reachedGoal = fundraiser.progress >= 100;

  const enable = () => {
    setEnabling(true);

    router.patch(
      `/dashboard/fundraising/${fundraiser.id}`,
      { status: 'Active' },
      {
        preserveScroll: true,
        onSuccess: () =>
          toast({
            title: 'Fundraiser opened',
            description: `"${fundraiser.title}" is now accepting donations.`,
          }),
        onFinish: () => setEnabling(false),
      },
    );
  };

  return (
    <Card className="flex flex-col h-full overflow-hidden border-slate-200 dark:border-slate-800 shadow-sm hover:shadow transition-shadow">
      <CardHeader>
        <div className="flex justify-between items-start gap-2">
          <div>
            <CardTitle className="text-xl font-bold tracking-tight">
              {fundraiser.title}
            </CardTitle>
            {fundraiser.beneficiary && (
              <p className="text-xs text-muted-foreground flex items-center gap-1 mt-0.5">
                <Building className="h-3 w-3" /> Beneficiary: {fundraiser.beneficiary}
              </p>
            )}
          </div>
          <Badge variant={statusVariant(fundraiser.status)}>
            {reachedGoal && fundraiser.status !== 'Upcoming' ? 'Goal Reached!' : fundraiser.status}
          </Badge>
        </div>

        {/* Corporate Matching Sponsor Banner */}
        {fundraiser.matchingSponsor && (
          <div className="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-[11px] font-semibold text-emerald-800 dark:text-emerald-300">
            <Sparkles className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
            <span>1:1 Matching Gift by {fundraiser.matchingSponsor}</span>
          </div>
        )}

        <CardDescription className="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
          {fundraiser.description}
        </CardDescription>
      </CardHeader>

      <CardContent className="flex-grow space-y-4">
        <div>
          <div className="flex items-baseline justify-between mb-2">
            <span className="text-2xl font-bold tracking-tight text-primary">
              {money(fundraiser.raised, fundraiser.currency)}
            </span>
            <span className="text-sm font-medium text-muted-foreground">
              Goal: {money(fundraiser.goal, fundraiser.currency)}
            </span>
          </div>

          <Progress value={fundraiser.progress} className="h-2.5 bg-slate-100 dark:bg-slate-800" />

          <div className="flex justify-between items-center mt-2 gap-2">
            {fundraiser.currency === 'JMD' ? (
              <p className="text-xs text-muted-foreground">
                Goal is roughly{' '}
                {Object.entries(JMD_EQUIVALENTS)
                  .map(([code, rate]) => money(fundraiser.goal * rate, code))
                  .join(' / ')}
              </p>
            ) : (
              <span />
            )}
            <p className="text-xs text-muted-foreground font-semibold whitespace-nowrap">
              {fundraiser.progress}% &bull; {fundraiser.donorCount}{' '}
              {fundraiser.donorCount === 1 ? 'supporter' : 'supporters'}
            </p>
          </div>
        </div>

        {/* Fund Allocation Pill Breakdown */}
        {fundraiser.fundAllocation && Object.keys(fundraiser.fundAllocation).length > 0 && (
          <div className="border-t border-slate-100 dark:border-slate-800/80 pt-3 space-y-1.5">
            <div className="flex items-center gap-1 text-xs font-semibold text-muted-foreground">
              <Layers className="h-3 w-3" /> Budget Allocation
            </div>
            <div className="flex flex-wrap gap-1.5">
              {Object.entries(fundraiser.fundAllocation).map(([cat, pct]) => (
                <span
                  key={cat}
                  className="inline-flex items-center text-[10px] bg-slate-100 dark:bg-slate-800/80 px-2 py-0.5 rounded text-foreground font-medium"
                >
                  {cat}: <strong className="ml-1 text-primary">{pct}%</strong>
                </span>
              ))}
            </div>
          </div>
        )}

        {/*
          "Official Tax Receipts" until now. A tax receipt asserts the recipient
          is a registered charity and the gift is deductible — neither is
          established anywhere in this application, and saying so could lead a
          donor to make a claim they are not entitled to. It is a record of the
          donation, so that is what it says.
        */}
        {fundraiser.recentDonations && fundraiser.recentDonations.length > 0 && (
          <div className="space-y-2 border-t border-slate-100 dark:border-slate-800/80 pt-3">
            <div className="flex items-center justify-between">
              <p className="text-xs font-semibold text-muted-foreground">Recent Supporters</p>
              <span className="text-[10px] text-emerald-600 dark:text-emerald-400 flex items-center gap-0.5">
                <FileCheck2 className="h-3 w-3" /> Donation receipts
              </span>
            </div>

            <div className="space-y-1.5">
              {fundraiser.recentDonations.slice(0, 5).map((donation, index) => (
                <div
                  key={index}
                  className="flex items-center justify-between text-xs p-1.5 rounded bg-slate-50/60 dark:bg-slate-900/30"
                >
                  <span className="truncate font-medium text-foreground">{donation.donorName}</span>
                  <div className="flex items-center gap-2 shrink-0">
                    <span className="tabular-nums font-bold text-foreground">
                      {money(donation.amount, donation.currency)}
                    </span>
                    {donation.receiptUrl && (
                      <Button
                        variant="ghost"
                        size="icon"
                        className="h-6 w-6 text-primary hover:text-primary/80"
                        title="Download donation receipt (PDF)"
                        onClick={() => window.open(donation.receiptUrl, '_blank')}
                      >
                        <Download className="h-3 w-3" />
                      </Button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </CardContent>

      <CardFooter className="flex flex-wrap justify-between items-center gap-2 bg-muted/40 py-3 px-6 mt-auto border-t border-slate-100 dark:border-slate-800">
        <div className="text-xs text-muted-foreground">
          {fundraiser.status === 'Upcoming' && (
            <span>
              Starts: <ClientFormattedDate date={fundraiser.startDate} formatString="MMM d, yyyy" />
            </span>
          )}
          {fundraiser.status !== 'Upcoming' && (
            <span>
              {fundraiser.status === 'Active' ? 'Ends' : 'Ended'}:{' '}
              <ClientFormattedDate date={fundraiser.endDate} formatString="MMM d, yyyy" />
            </span>
          )}
        </div>

        {fundraiser.isOpen && (
          <DonateForm
            open={isDonateOpen}
            onOpenChange={setDonateOpen}
            fundraiser={fundraiser}
            channels={channels}
          >
            <Button className="w-full sm:w-auto shadow-sm gap-2" onClick={() => setDonateOpen(true)}>
              <HeartHandshake className="h-4 w-4" />
              Donate Now
            </Button>
          </DonateForm>
        )}

        {fundraiser.status === 'Upcoming' && canManage && (
          <Button variant="outline" size="sm" disabled={enabling} onClick={enable}>
            {enabling ? 'Opening…' : 'Enable Now'}
          </Button>
        )}
      </CardFooter>
    </Card>
  );
}
