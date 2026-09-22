import { useState } from 'react';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import {
  Receipt,
  Download,
  Search,
  Filter,
  CreditCard,
  Building,
  Smartphone,
  CheckCircle2,
  Clock,
  RotateCcw,
  AlertCircle,
  FileSpreadsheet,
} from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import { submit } from '@/lib/submit';
import { money, type Paginated } from '@/components/dashboard/billing-summary';

export type MasterTransaction = {
  id: number;
  reference: string;
  receiptNumber: string;
  homeowner: string;
  lot: string;
  amount: number;
  currency: string;
  channel: string;
  status: 'completed' | 'pending' | 'refunded' | 'failed' | string;
  notes?: string | null;
  date: string;
  /** Present only for an administrator, on a Stripe payment with money left to return. */
  refundableAmount?: number | null;
  refundUrl?: string | null;
};

type Props = {
  transactions: Paginated<MasterTransaction>;
  currency?: string;
};

export function TransactionsLedger({ transactions, currency = 'JMD' }: Props) {
  const [searchTerm, setSearchTerm] = useState('');
  const [channelFilter, setChannelFilter] = useState('all');
  const [statusFilter, setStatusFilter] = useState('all');
  const [currencyFilter, setCurrencyFilter] = useState('all');
  const { toast } = useToast();
  const [refunding, setRefunding] = useState<MasterTransaction | null>(null);
  const [refundAmount, setRefundAmount] = useState('');
  const [refundNote, setRefundNote] = useState('');
  const [refundError, setRefundError] = useState<string | null>(null);
  const [refundSaving, setRefundSaving] = useState(false);

  const openRefund = (tx: MasterTransaction) => {
    setRefunding(tx);
    setRefundAmount(String(tx.refundableAmount ?? ''));
    setRefundNote('');
    setRefundError(null);
  };

  const issueRefund = async () => {
    if (!refunding?.refundUrl) return;
    setRefundSaving(true);
    setRefundError(null);
    try {
      await submit('post', refunding.refundUrl, {
        amount: Number(refundAmount),
        note: refundNote.trim() === '' ? null : refundNote,
      });
      toast({
        title: 'Refund issued',
        description: `${money(Number(refundAmount), refunding.currency || currency)} is on its way back through Stripe.`,
      });
      setRefunding(null);
    } catch (message) {
      setRefundError(typeof message === 'string' ? message : 'The refund could not be issued. Please try again.');
    } finally {
      setRefundSaving(false);
    }
  };

  const filteredData = transactions.data.filter((tx) => {
    const matchesSearch =
      tx.reference.toLowerCase().includes(searchTerm.toLowerCase()) ||
      tx.receiptNumber.toLowerCase().includes(searchTerm.toLowerCase()) ||
      tx.homeowner.toLowerCase().includes(searchTerm.toLowerCase()) ||
      tx.lot.toLowerCase().includes(searchTerm.toLowerCase());

    const matchesChannel =
      channelFilter === 'all' || tx.channel.toLowerCase() === channelFilter.toLowerCase();
    const matchesStatus =
      statusFilter === 'all' || tx.status.toLowerCase() === statusFilter.toLowerCase();
    const matchesCurrency =
      currencyFilter === 'all' || (tx.currency || 'JMD').toUpperCase() === currencyFilter.toUpperCase();

    return matchesSearch && matchesChannel && matchesStatus && matchesCurrency;
  });

  const getChannelIcon = (channel: string) => {
    switch (channel.toLowerCase()) {
      case 'apple_pay':
      case 'google_pay':
      case 'samsung_wallet':
      case 'nfc':
        return <Smartphone className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />;
      case 'bank_transfer':
      case 'bank_wire':
        return <Building className="h-4 w-4 text-blue-600 dark:text-blue-400" />;
      default:
        return <CreditCard className="h-4 w-4 text-amber-600 dark:text-amber-400" />;
    }
  };

  const getStatusBadge = (status: string) => {
    switch (status.toLowerCase()) {
      case 'completed':
        return (
          <Badge className="bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800 gap-1">
            <CheckCircle2 className="h-3 w-3" /> Completed
          </Badge>
        );
      case 'pending':
        return (
          <Badge className="bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-300 dark:border-amber-800 gap-1">
            <Clock className="h-3 w-3" /> Pending
          </Badge>
        );
      case 'refunded':
        return (
          <Badge className="bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 border-purple-300 dark:border-purple-800 gap-1">
            <RotateCcw className="h-3 w-3" /> Refunded
          </Badge>
        );
      default:
        return (
          <Badge variant="destructive" className="gap-1">
            <AlertCircle className="h-3 w-3" /> {status}
          </Badge>
        );
    }
  };

  return (
    <Card className="shadow-sm border-slate-200 dark:border-slate-800">
      <CardHeader className="pb-4">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <CardTitle className="text-xl font-bold flex items-center gap-2">
              <Receipt className="h-5 w-5 text-primary" />
              Master Transactions Ledger
            </CardTitle>
            <CardDescription>
              A record of dues, donations and credits entered against this community.
            </CardDescription>
          </div>
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              className="gap-2 text-xs font-semibold"
              onClick={() => {
                window.location.href = '/dashboard/billing/export-transactions';
              }}
            >
              <FileSpreadsheet className="h-4 w-4 text-emerald-600" />
              Export CSV
            </Button>
          </div>
        </div>

        {/* Search & Filters */}
        <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
          <div className="relative">
            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search reference, receipt, resident..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="pl-9 text-sm"
            />
          </div>

          <Select value={currencyFilter} onValueChange={setCurrencyFilter}>
            <SelectTrigger className="text-sm">
              <SelectValue placeholder="All Currencies" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Currencies</SelectItem>
              <SelectItem value="JMD">🇯🇲 JMD (J$)</SelectItem>
              <SelectItem value="USD">🇺🇸 USD ($)</SelectItem>
              <SelectItem value="CAD">🇨🇦 CAD (CA$)</SelectItem>
              <SelectItem value="GBP">🇬🇧 GBP (£)</SelectItem>
              <SelectItem value="EUR">🇪🇺 EUR (€)</SelectItem>
            </SelectContent>
          </Select>

          <Select value={channelFilter} onValueChange={setChannelFilter}>
            <SelectTrigger className="text-sm">
              <SelectValue placeholder="All Channels" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Channels</SelectItem>
              <SelectItem value="card">Credit/Debit Card</SelectItem>
              <SelectItem value="apple_pay">Apple Pay</SelectItem>
              <SelectItem value="google_pay">Google Pay</SelectItem>
              <SelectItem value="bank_transfer">Bank Transfer</SelectItem>
              <SelectItem value="nfc">NFC Contactless</SelectItem>
              <SelectItem value="wallet">Community Wallet</SelectItem>
              <SelectItem value="cash">Cash Desk</SelectItem>
            </SelectContent>
          </Select>

          <Select value={statusFilter} onValueChange={setStatusFilter}>
            <SelectTrigger className="text-sm">
              <SelectValue placeholder="All Statuses" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Statuses</SelectItem>
              <SelectItem value="completed">Completed</SelectItem>
              <SelectItem value="pending">Pending</SelectItem>
              <SelectItem value="refunded">Refunded</SelectItem>
              <SelectItem value="failed">Failed</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </CardHeader>

      <CardContent>
        {filteredData.length === 0 ? (
          <div className="py-12 text-center text-muted-foreground">
            <Receipt className="h-10 w-10 mx-auto mb-3 opacity-30" />
            <p className="font-medium text-sm">No transactions match the selected criteria.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow className="bg-slate-50 dark:bg-slate-900/50">
                  <TableHead className="w-[180px]">Date & Time</TableHead>
                  <TableHead>Reference / Receipt</TableHead>
                  <TableHead>Member / Lot</TableHead>
                  <TableHead>Channel</TableHead>
                  <TableHead className="text-right">Amount & Currency</TableHead>
                  <TableHead className="text-center">Status</TableHead>
                  <TableHead className="text-right">Receipt</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filteredData.map((tx) => (
                  <TableRow key={tx.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-900/30">
                    <TableCell className="text-xs text-muted-foreground font-mono">
                      {tx.date}
                    </TableCell>
                    <TableCell>
                      <div className="font-semibold text-xs font-mono text-foreground">
                        {tx.reference}
                      </div>
                      <div className="text-[11px] text-muted-foreground">{tx.receiptNumber}</div>
                    </TableCell>
                    <TableCell>
                      <div className="text-xs font-medium text-foreground">{tx.homeowner}</div>
                      <div className="text-[11px] text-muted-foreground">{tx.lot}</div>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-1.5 text-xs capitalize text-muted-foreground">
                        {getChannelIcon(tx.channel)}
                        {tx.channel.replace(/_/g, ' ')}
                      </div>
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="font-bold text-sm text-foreground">
                        {money(tx.amount, tx.currency || currency)}
                      </div>
                      <span className="inline-block text-[10px] font-mono font-bold text-indigo-600 dark:text-indigo-400 uppercase">
                        {tx.currency || 'JMD'}
                      </span>
                    </TableCell>
                    <TableCell className="text-center">{getStatusBadge(tx.status)}</TableCell>
                    <TableCell className="text-right">
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 px-2 text-xs gap-1 text-primary"
                        onClick={() => {
                          window.open(`/dashboard/billing/receipt/${tx.id}`, '_blank');
                        }}
                      >
                        <Download className="h-3.5 w-3.5" />
                        PDF
                      </Button>
                      {tx.refundUrl && (
                        <Button
                          variant="ghost"
                          size="sm"
                          className="h-8 px-2 text-xs gap-1 text-destructive"
                          onClick={() => openRefund(tx)}
                        >
                          <RotateCcw className="h-3.5 w-3.5" />
                          Refund
                        </Button>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
      </CardContent>

      <Dialog open={refunding !== null} onOpenChange={(open) => !open && setRefunding(null)}>
        <DialogContent className="sm:max-w-md">
          {refunding && (
            <>
              <DialogHeader>
                <DialogTitle>Refund Stripe payment</DialogTitle>
                <DialogDescription>
                  {refunding.reference} from {refunding.homeowner}. Up to{' '}
                  {money(refunding.refundableAmount ?? 0, refunding.currency || currency)} can be returned to the card.
                  If this payment settled the invoice, the invoice goes back to Unpaid or Partially Paid.
                </DialogDescription>
              </DialogHeader>
              <div className="space-y-4">
                <div className="grid gap-2">
                  <Label htmlFor="refund-amount">Amount ({refunding.currency || currency})</Label>
                  <Input
                    id="refund-amount"
                    type="number"
                    inputMode="decimal"
                    min={0.01}
                    step={0.01}
                    max={refunding.refundableAmount ?? undefined}
                    value={refundAmount}
                    onChange={(e) => setRefundAmount(e.target.value)}
                    aria-invalid={refundError !== null}
                  />
                  {refundError && <p className="text-sm text-destructive">{refundError}</p>}
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="refund-note">Note (optional)</Label>
                  <Textarea
                    id="refund-note"
                    maxLength={500}
                    placeholder="e.g., Duplicate payment; already paid at the office"
                    value={refundNote}
                    onChange={(e) => setRefundNote(e.target.value)}
                  />
                </div>
              </div>
              <DialogFooter>
                <Button type="button" variant="outline" onClick={() => setRefunding(null)}>Cancel</Button>
                <Button
                  type="button"
                  variant="destructive"
                  disabled={refundSaving || !(Number(refundAmount) > 0)}
                  onClick={issueRefund}
                >
                  {refundSaving ? 'Refunding…' : 'Issue Refund'}
                </Button>
              </DialogFooter>
            </>
          )}
        </DialogContent>
      </Dialog>
    </Card>
  );
}
