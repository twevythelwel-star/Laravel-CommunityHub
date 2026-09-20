'use client';

import { useState } from 'react';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { 
  MoreHorizontal, 
  PlusCircle, 
  Search, 
  AlertTriangle,
  Scan,
  ShieldCheck,
  Filter,
  CheckCircle2,
  ExternalLink,
  Layers
} from "lucide-react";
import type { Staff, ManagedUser, UserRole } from "@/types";
import { ClientFormattedDate } from '@/components/client-formatted-date';
import { useAuth } from '@/context/auth-context';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { StaffGatePassDialog } from '@/components/dashboard/staff-gate-pass-dialog';
import { StaffForm } from '@/components/dashboard/staff-form';
import { useToast } from '@/hooks/use-toast';
import { Input } from '@/components/ui/input';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import { GatePassDisplay } from '@/components/dashboard/gate-pass-display';
import { GatePassCard } from '@/components/dashboard/gate-pass-card';
import { GateScannerDialog } from '@/components/dashboard/gate-scanner-dialog';
import { 
  PassCategory, 
  QRShape 
} from '@/lib/gate-pass-engine/types';
import { 
  CATEGORY_CONFIGS, 
  DEMO_PASS_DIRECTORY, 
  DemoPassProfile,
  generateDynamicGatePassToken 
} from '@/lib/gate-pass-engine/engine';
import { CategoryShapeIcon } from '@/lib/gate-pass-engine/shapes';

const mockStaff: Staff[] = [
  { 
    id: 'staff_1', 
    name: 'Maria Garcia', 
    job: 'Housekeeper', 
    idType: "National ID", 
    idNumber: "123456789", 
    idExpiry: new Date('2028-12-31'), 
    property: 'Lot 12, Main St', 
    addedBy: 'user-homeowner', 
    status: 'Active',
    photoUrl: 'https://picsum.photos/seed/maria/200'
  },
  { 
    id: 'staff_2', 
    name: 'David Kim', 
    job: 'Grounds & Maintenance', 
    idType: "Driver's License", 
    idNumber: "987654321", 
    idExpiry: new Date('2024-05-31'), 
    property: 'Estate Facility Hub', 
    addedBy: 'user-admin', 
    status: 'Active',
    photoUrl: 'https://picsum.photos/seed/david/200'
  },
  { 
    id: 'staff_3', 
    name: 'Chen Wei', 
    job: 'Nanny', 
    idType: "Passport", 
    idNumber: "G12345678", 
    idExpiry: new Date('2026-08-15'), 
    property: 'Lot 42, Royal Palm Dr', 
    addedBy: 'user-homeowner', 
    status: 'Inactive',
    photoUrl: 'https://picsum.photos/seed/chen/200'
  },
];

function AdminSecurityView({ onOpenScannerWithToken }: { onOpenScannerWithToken: (token?: string) => void }) {
  const { user } = useAuth();
  const { toast } = useToast();
  const [passDirectory] = useState<DemoPassProfile[]>(DEMO_PASS_DIRECTORY);
  const [staffList, setStaffList] = useState<Staff[]>(mockStaff);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategoryFilter, setSelectedCategoryFilter] = useState<string>('ALL');
  
  // Pass modal view states
  const [viewingPass, setViewingPass] = useState<DemoPassProfile | null>(null);
  const [selectedStaff, setSelectedStaff] = useState<Staff | null>(null);
  const [staffToEdit, setStaffToEdit] = useState<Staff | undefined>(undefined);
  const [isFormOpen, setFormOpen] = useState(false);

  const isAdmin = user?.role === 'Admin' || user?.role === 'System Admin';

  const filteredDirectory = passDirectory.filter(item => {
    const matchesSearch = item.userName.toLowerCase().includes(searchTerm.toLowerCase()) ||
                          item.property.toLowerCase().includes(searchTerm.toLowerCase()) ||
                          item.passId.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesCat = selectedCategoryFilter === 'ALL' || item.category === selectedCategoryFilter;
    return matchesSearch && matchesCat;
  });

  const handleOpenForm = (staff?: Staff) => {
    setStaffToEdit(staff);
    setFormOpen(true);
  };

  const handleSaveStaff = (staffData: Omit<Staff, 'id' | 'addedBy'>, id?: string) => {
    if (id) {
      setStaffList(staffList.map(s => s.id === id ? { ...s, ...staffData } : s));
      toast({ title: "Staff Updated", description: `${staffData.name}'s details updated.` });
    } else {
      const newStaff: Staff = {
        ...staffData,
        id: `staff_${Date.now()}`,
        addedBy: user?.uid || 'user-admin',
      };
      setStaffList([...staffList, newStaff]);
      toast({ title: "Staff Added", description: `${staffData.name} registered for gate pass.` });
    }
  };

  const handleRevokeAccess = (id: string) => {
    setStaffList(staffList.map(s => s.id === id ? { ...s, status: 'Inactive' } : s));
    toast({
      variant: "destructive",
      title: "Access Revoked",
      description: "Staff access status changed to inactive in central database."
    });
  };

  const getStatusVariant = (status: Staff['status']) => {
    switch (status) {
      case 'Active': return 'default';
      case 'Inactive': return 'secondary';
      case 'Expired ID': return 'destructive';
      default: return 'outline';
    }
  };

  return (
    <>
      {/* Dynamic Digital Gate Pass Inspector Modal */}
      {viewingPass && (
        <Dialog open={!!viewingPass} onOpenChange={(open) => !open && setViewingPass(null)}>
          <DialogContent className="sm:max-w-md p-0 overflow-hidden border-0 bg-transparent shadow-none">
            <GatePassCard
              category={viewingPass.category}
              userName={viewingPass.userName}
              property={viewingPass.property}
              passId={viewingPass.passId}
              userId={viewingPass.id}
              gate={viewingPass.gate}
              status={viewingPass.status === 'REVOKED' ? 'REVOKED' : 'ACTIVE'}
              initialColorVariant={viewingPass.colorVariant}
              onScanInVerifier={(token) => {
                setViewingPass(null);
                onOpenScannerWithToken(token);
              }}
            />
          </DialogContent>
        </Dialog>
      )}

      {selectedStaff && (
        <StaffGatePassDialog
          staff={selectedStaff}
          open={!!selectedStaff}
          onOpenChange={() => setSelectedStaff(null)}
        />
      )}

      <Tabs defaultValue="directory" className="w-full space-y-6">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <TabsList className="grid w-full sm:w-80 grid-cols-2">
            <TabsTrigger value="directory">Identity Directory</TabsTrigger>
            <TabsTrigger value="staff">Staff Personnel</TabsTrigger>
          </TabsList>

          {/* Direct Scanner Quick Launch */}
          <Button 
            onClick={() => onOpenScannerWithToken()} 
            className="gap-2 bg-primary text-primary-foreground font-bold shadow-md hover:shadow-lg transition-all"
          >
            <Scan className="w-4 h-4 text-emerald-400" />
            <span>Open Gate Security Scanner</span>
          </Button>
        </div>

        {/* ── Tab 1: Identity Directory (All 6 Categories with Shapes & Colors) ── */}
        <TabsContent value="directory" className="space-y-4">
          <Card>
            <CardHeader className="pb-3">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                  <CardTitle className="text-xl font-bold flex items-center gap-2">
                    <Layers className="w-5 h-5 text-primary" />
                    Gate Pass Identity Directory
                  </CardTitle>
                  <CardDescription className="text-xs">
                    Cryptographically generated credentials categorized by shape, clearance policy, and visual treatment.
                  </CardDescription>
                </div>
              </div>

              {/* Category Filter Badges */}
              <div className="flex items-center gap-1.5 flex-wrap pt-3 border-t">
                <span className="text-[11px] font-mono font-bold text-muted-foreground mr-1 flex items-center gap-1">
                  <Filter className="w-3 h-3" />
                  CATEGORY:
                </span>
                <button
                  type="button"
                  onClick={() => setSelectedCategoryFilter('ALL')}
                  className={cn(
                    "px-2.5 py-1 rounded-full text-xs font-semibold transition-colors border",
                    selectedCategoryFilter === 'ALL'
                      ? "bg-primary text-primary-foreground border-primary"
                      : "bg-muted text-muted-foreground border-transparent hover:text-foreground"
                  )}
                >
                  All (7 Categories)
                </button>

                {(['SYSADMIN', 'ADMIN', 'HOMEOWNER', 'RENTER', 'STAFF', 'SECURITY', 'HOMEOWNER_STAFF'] as PassCategory[]).map(cat => {
                  const conf = CATEGORY_CONFIGS[cat];
                  const isSelected = selectedCategoryFilter === cat;
                  return (
                    <button
                      key={cat}
                      type="button"
                      onClick={() => setSelectedCategoryFilter(cat)}
                      className={cn(
                        "px-2.5 py-1 rounded-full text-xs font-semibold transition-colors border flex items-center gap-1.5",
                        isSelected 
                          ? "bg-primary text-primary-foreground border-primary shadow-xs" 
                          : "bg-muted/40 border-border text-muted-foreground hover:text-foreground hover:bg-muted"
                      )}
                    >
                      <CategoryShapeIcon shape={conf.shape} className="w-3 h-3" color={isSelected ? 'white' : conf.themeColor} />
                      <span>{conf.shapeLabel} • {cat.replace('_', ' ')}</span>
                    </button>
                  );
                })}
              </div>
            </CardHeader>

            <CardContent className="space-y-4">
              <div className="relative">
                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input 
                  placeholder="Search by name, property (e.g. 'Lot 42'), or Pass ID..." 
                  className="pl-8 text-xs bg-muted/20" 
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                />
              </div>

              {/* Grid of Registered Passes */}
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {filteredDirectory.map((profile) => {
                  const conf = CATEGORY_CONFIGS[profile.category];
                  const activeColor = profile.colorVariant?.hex || conf.themeColor;
                  const colorName = profile.colorVariant?.name.split(' ')[0] || 'Approved';
                  return (
                    <Card 
                      key={profile.id}
                      className={cn(
                        "overflow-hidden border hover:border-primary/50 transition-all duration-200 shadow-sm flex flex-col justify-between",
                        profile.status === 'REVOKED' && "opacity-60 bg-red-500/5 border-red-500/30"
                      )}
                    >
                      <div className="p-4 space-y-3">
                        <div className="flex items-center justify-between">
                          <div className="flex items-center gap-2">
                            <div 
                              className="w-7 h-7 rounded-lg flex items-center justify-center text-white shadow-xs border border-white/20"
                              style={{ backgroundColor: activeColor }}
                            >
                              <CategoryShapeIcon shape={conf.shape} className="w-4 h-4" />
                            </div>
                            <div>
                              <span className="font-mono text-[10px] uppercase font-bold text-muted-foreground block leading-tight">
                                {conf.shape} • {profile.category.replace('_', ' ')}
                              </span>
                              <span className="font-mono text-xs font-bold text-foreground">
                                {profile.passId}
                              </span>
                            </div>
                          </div>

                          <Badge 
                            variant="outline" 
                            className={cn(
                              "text-[10px] font-bold",
                              profile.status === 'ACTIVE' 
                                ? "border-emerald-500/40 text-emerald-600 bg-emerald-500/10" 
                                : "border-red-500/40 text-red-600 bg-red-500/10"
                            )}
                          >
                            {profile.status}
                          </Badge>
                        </div>

                        <div>
                          <h4 className="font-bold text-sm text-foreground">{profile.userName}</h4>
                          <p className="text-xs text-muted-foreground mt-0.5">{profile.role}</p>
                          <p className="text-xs font-medium text-foreground/80 mt-1">{profile.property}</p>

                          {/* Visual Identity Color Swatch & WCAG status */}
                          <div className="flex items-center gap-1.5 mt-2.5 pt-2 border-t text-[11px] font-mono text-muted-foreground">
                            <span 
                              className="w-2.5 h-2.5 rounded-full inline-block border border-black/10 shrink-0 shadow-xs" 
                              style={{ backgroundColor: activeColor }} 
                            />
                            <span className="font-semibold text-foreground">{profile.colorVariant?.name || colorName}</span>
                            <span>•</span>
                            <span className="text-emerald-600 font-medium">WCAG {profile.colorVariant?.contrastRatio || 6.2}:1</span>
                          </div>
                        </div>
                      </div>

                      <div className="p-3 pt-0 flex items-center gap-2 border-t bg-muted/15">
                        <Button 
                          variant="outline" 
                          size="sm" 
                          onClick={() => setViewingPass(profile)}
                          className="flex-1 text-xs h-8 font-semibold"
                        >
                          View Dynamic Pass
                        </Button>
                        <Button 
                          size="sm" 
                          onClick={() => {
                            const token = generateDynamicGatePassToken({
                              passId: profile.passId,
                              category: profile.category,
                              userId: profile.id,
                              userName: profile.userName,
                              property: profile.property,
                              gate: profile.gate,
                              colorVariant: profile.colorVariant?.name,
                            });
                            onOpenScannerWithToken(token);
                          }}
                          className="h-8 text-xs font-semibold gap-1 bg-muted hover:bg-muted/80 text-foreground border"
                          title="Test scan in 3-layer security engine"
                        >
                          <Scan className="w-3.5 h-3.5 text-primary" />
                          <span>Scan</span>
                        </Button>
                      </div>
                    </Card>
                  );
                })}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* ── Tab 2: Registered Staff Personnel ── */}
        <TabsContent value="staff">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <div>
                <CardTitle className="text-xl font-bold">Registered Staff Personnel</CardTitle>
                <CardDescription className="text-xs">
                  Housekeeping, maintenance, and domestic staff with Diamond or Hexagon QR identity tokens.
                </CardDescription>
              </div>
              {isAdmin && (
                <StaffForm
                  open={isFormOpen}
                  onOpenChange={setFormOpen}
                  onSave={handleSaveStaff}
                  staff={staffToEdit}
                >
                  <Button size="sm" className="gap-1.5" onClick={() => handleOpenForm()}>
                    <PlusCircle className="h-4 w-4" />
                    <span>Add Staff Member</span>
                  </Button>
                </StaffForm>
              )}
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Staff Member</TableHead>
                    <TableHead>Role & Category</TableHead>
                    <TableHead>Assigned Property</TableHead>
                    <TableHead>ID Expiry</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {staffList.map((staff) => {
                    const isDomestic = staff.addedBy?.includes('homeowner') || ['Housekeeper', 'Nanny'].includes(staff.job);
                    const cat: PassCategory = isDomestic ? 'HOMEOWNER_STAFF' : 'STAFF';
                    const conf = CATEGORY_CONFIGS[cat];

                    return (
                      <TableRow key={staff.id}>
                        <TableCell className="font-bold text-foreground">{staff.name}</TableCell>
                        <TableCell>
                          <div className="flex items-center gap-1.5">
                            <CategoryShapeIcon shape={conf.shape} className="w-3.5 h-3.5" color={conf.themeColor} />
                            <span className="text-xs font-semibold">{staff.job}</span>
                            <span className="text-[10px] text-muted-foreground font-mono">({conf.shape})</span>
                          </div>
                        </TableCell>
                        <TableCell className="text-xs text-muted-foreground">{staff.property}</TableCell>
                        <TableCell className="text-xs font-mono">
                          <ClientFormattedDate date={staff.idExpiry} formatString="MMM d, yyyy" />
                        </TableCell>
                        <TableCell>
                          <Badge variant={getStatusVariant(staff.status)}>
                            {staff.status}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-right">
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button size="icon" variant="ghost" className="h-8 w-8">
                                <MoreHorizontal className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                              <DropdownMenuLabel>Actions</DropdownMenuLabel>
                              <DropdownMenuItem onClick={() => setSelectedStaff(staff)}>
                                View Pass
                              </DropdownMenuItem>
                              {isAdmin && (
                                <>
                                  <DropdownMenuItem onClick={() => handleOpenForm(staff)}>Edit Details</DropdownMenuItem>
                                  <DropdownMenuSeparator />
                                  <DropdownMenuItem className="text-destructive" onClick={() => handleRevokeAccess(staff.id)}>
                                    Revoke Access
                                  </DropdownMenuItem>
                                </>
                              )}
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </>
  );
}

export default function GatePassPage() {
  const { user } = useAuth();
  const [scannerOpen, setScannerOpen] = useState(false);
  const [scannerInitialToken, setScannerInitialToken] = useState<string | undefined>(undefined);

  const isManager = user?.role === 'System Admin' || user?.role === 'Admin' || user?.role === 'Security';

  const handleOpenScannerWithToken = (token?: string) => {
    setScannerInitialToken(token);
    setScannerOpen(true);
  };

  return (
    <div className="grid gap-8 pb-12">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="font-headline text-3xl font-bold tracking-tight">Digital Gate Pass Visual Identity Engine</h1>
          <p className="text-muted-foreground text-sm">
            {isManager 
              ? 'Profile Shape + Controlled Random Color credential system with cryptographic HMAC ingress scanner.' 
              : 'Your secure dynamic gate pass for community entrance and amenity clearance.'}
          </p>
        </div>

        {/* Global Scanner Launcher Button */}
        {isManager && (
          <Button 
            onClick={() => handleOpenScannerWithToken()} 
            className="gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-md self-start sm:self-auto"
          >
            <Scan className="w-4 h-4" />
            <span>Gate Scanner Console</span>
          </Button>
        )}
      </div>
      
      {isManager ? (
        <div className="grid gap-8">
          <div className="flex justify-center">
            <div className="w-full max-w-sm">
              <GatePassDisplay />
            </div>
          </div>
          <AdminSecurityView onOpenScannerWithToken={handleOpenScannerWithToken} />
        </div>
      ) : (
        <div className="flex justify-center">
          <div className="w-full max-w-sm">
            <GatePassDisplay />
          </div>
        </div>
      )}

      {/* Linked Gate Security Scanner Verification Modal */}
      <GateScannerDialog
        open={scannerOpen}
        onOpenChange={setScannerOpen}
        initialToken={scannerInitialToken}
      />
    </div>
  );
}
