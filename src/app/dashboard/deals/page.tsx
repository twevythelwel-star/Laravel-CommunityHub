
'use client';

import { useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import Image from "next/image";
import type { Business, FoodApp, Voucher } from "@/types";
import { useToast } from "@/hooks/use-toast";
import { placeholderData } from "@/lib/placeholder-images.json";
import { useAuth } from "@/context/auth-context";
import { PlusCircle, Trash2 } from "lucide-react";
import { FoodAppForm } from "@/components/dashboard/food-app-form";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { VoucherForm } from "@/components/dashboard/voucher-form";


const initialBusinesses: Business[] = placeholderData.businesses;
const initialVouchers: Voucher[] = placeholderData.vouchers;
const initialFoodApps: FoodApp[] = placeholderData.foodApps;

function VoucherCard({ voucher, business, canManage, onDelete }: { voucher: Voucher, business?: Business, canManage: boolean, onDelete: (id: string) => void }) {
  const { toast } = useToast();

  const handleClip = () => {
    toast({
      title: "Voucher Clipped!",
      description: `"${voucher.title}" from ${business?.name} has been saved to your profile.`,
    });
  };

  return (
    <Card className="flex flex-col">
      <CardHeader>
        <div className="flex items-start justify-between gap-4">
            <div className="flex items-center gap-4">
                {business && (
                    <Image 
                    src={business.logoUrl} 
                    alt={`${business.name} logo`} 
                    width={40} 
                    height={40} 
                    className="rounded-full"
                    data-ai-hint={business.aiHint}
                    />
                )}
                <div>
                    <CardTitle className="text-lg">{voucher.title}</CardTitle>
                    <CardDescription>{business?.name}</CardDescription>
                </div>
            </div>
             {canManage && (
                <AlertDialog>
                    <AlertDialogTrigger asChild>
                        <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-destructive">
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                        <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This action cannot be undone. This will permanently remove the voucher "{voucher.title}".
                        </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={() => onDelete(voucher.id)}>
                            Yes, remove
                        </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            )}
        </div>
      </CardHeader>
      <CardContent className="flex-grow">
        <p className="text-sm text-muted-foreground">{voucher.description}</p>
      </CardContent>
      <CardContent>
        <Button onClick={handleClip} className="w-full">Clip Voucher</Button>
      </CardContent>
    </Card>
  );
}

function FoodAppCard({ app, onRemove, canManage }: { app: FoodApp, onRemove: (id: string) => void, canManage: boolean }) {
  return (
    <Card>
      <CardContent className="pt-6 flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Image 
            src={app.logoUrl}
            alt={`${app.name} logo`}
            width={48}
            height={48}
            className="rounded-lg"
            data-ai-hint={app.aiHint}
          />
          <div>
            <p className="font-semibold">{app.name}</p>
             {app.couponPercentage && (
              <Badge variant="destructive" className="mt-1">
                {app.couponPercentage}% OFF
              </Badge>
            )}
          </div>
        </div>
        <div className="flex items-center gap-2">
            <a href={app.websiteUrl} target="_blank" rel="noopener noreferrer">
              <Button variant="outline">Order Now</Button>
            </a>
             {canManage && (
                <AlertDialog>
                    <AlertDialogTrigger asChild>
                        <Button variant="destructive" size="icon">
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                        <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This action cannot be undone. This will permanently remove the "{app.name}" delivery option.
                        </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={() => onRemove(app.id)}>
                            Yes, remove
                        </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
             )}
        </div>
      </CardContent>
    </Card>
  );
}

export default function DealsPage() {
  const { toast } = useToast();
  const { user } = useAuth();
  const [businesses] = useState<Business[]>(initialBusinesses);
  const [vouchers, setVouchers] = useState<Voucher[]>(initialVouchers);
  const [foodApps, setFoodApps] = useState<FoodApp[]>(initialFoodApps);
  const [isAddAppOpen, setIsAddAppOpen] = useState(false);
  const [isVoucherFormOpen, setVoucherFormOpen] = useState(false);

  const canManage = user?.role === 'System Admin' || user?.role === 'Admin';

  const handleAddFoodApp = (newApp: Omit<FoodApp, 'id'>) => {
    const appToAdd: FoodApp = {
        ...newApp,
        id: `fa_${Date.now()}`
    };
    setFoodApps(prev => [appToAdd, ...prev]);
    toast({
        title: 'Service Added',
        description: `${appToAdd.name} has been added to the food delivery options.`,
    });
  }

  const handleRemoveFoodApp = (id: string) => {
    const appToRemove = foodApps.find(app => app.id === id);
    setFoodApps(prev => prev.filter(app => app.id !== id));
     toast({
        title: 'Service Removed',
        description: `${appToRemove?.name} has been removed.`,
    });
  }

  const handleSaveVoucher = (newVoucherData: Omit<Voucher, 'id'>) => {
    const newVoucher: Voucher = {
        id: `v_${Date.now()}`,
        ...newVoucherData,
    };
    setVouchers(prev => [newVoucher, ...prev]);
    toast({
        title: 'Voucher Added',
        description: `The voucher "${newVoucher.title}" has been successfully created.`,
    });
  };

  const handleDeleteVoucher = (id: string) => {
    const voucherToDelete = vouchers.find(v => v.id === id);
     if (voucherToDelete) {
        setVouchers(prev => prev.filter(v => v.id !== id));
        toast({
            title: 'Voucher Removed',
            description: `The voucher "${voucherToDelete.title}" has been removed.`,
        });
    }
  };


  return (
    <div className="grid gap-8">
      <div>
        <h1 className="font-headline text-3xl font-bold">Deals & Promotions</h1>
        <p className="text-muted-foreground">
          Exclusive offers from local businesses and easy access to food delivery.
        </p>
      </div>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
            <div>
                <CardTitle>Community Vouchers</CardTitle>
                <CardDescription>
                    Exclusive discounts for residents from our partner businesses. Click to clip and save!
                </CardDescription>
            </div>
            {canManage && (
                 <VoucherForm 
                    open={isVoucherFormOpen}
                    onOpenChange={setVoucherFormOpen}
                    onSave={handleSaveVoucher}
                    businesses={businesses}
                 >
                    <Button size="sm" className="gap-1">
                        <PlusCircle className="h-3.5 w-3.5" />
                        <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                            Add Voucher
                        </span>
                    </Button>
                </VoucherForm>
            )}
        </CardHeader>
        <CardContent className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {vouchers.map((voucher) => {
            const business = businesses.find(b => b.id === voucher.businessId);
            return (
                <VoucherCard 
                    key={voucher.id} 
                    voucher={voucher} 
                    business={business} 
                    canManage={canManage}
                    onDelete={handleDeleteVoucher}
                />
            );
          })}
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
            <div>
                <CardTitle>Food Delivery</CardTitle>
                <CardDescription>
                    Order from your favorite local restaurants through these popular services.
                </CardDescription>
            </div>
             {canManage && (
                <FoodAppForm onOpenChange={setIsAddAppOpen} open={isAddAppOpen} onSave={handleAddFoodApp}>
                    <Button size="sm" className="gap-1">
                        <PlusCircle className="h-3.5 w-3.5" />
                        <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                            Add Service
                        </span>
                    </Button>
                </FoodAppForm>
            )}
        </CardHeader>
        <CardContent className="grid gap-4">
          {foodApps.map((app) => (
            <FoodAppCard key={app.id} app={app} onRemove={handleRemoveFoodApp} canManage={canManage} />
          ))}
        </CardContent>
      </Card>
    </div>
  );
}
