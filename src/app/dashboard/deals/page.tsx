
'use client';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import Image from "next/image";
import type { Business, FoodApp, Voucher } from "@/types";
import { useToast } from "@/hooks/use-toast";
import { placeholderData } from "@/lib/placeholder-images.json";

const { businesses, vouchers, foodApps } = placeholderData;

function VoucherCard({ voucher, business }: { voucher: Voucher, business?: Business }) {
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

function FoodAppCard({ app }: { app: FoodApp }) {
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
          </div>
        </div>
        <a href={app.websiteUrl} target="_blank" rel="noopener noreferrer">
          <Button variant="outline">Order Now</Button>
        </a>
      </CardContent>
    </Card>
  );
}

export default function DealsPage() {
  return (
    <div className="grid gap-8">
      <div>
        <h1 className="font-headline text-3xl font-bold">Deals & Promotions</h1>
        <p className="text-muted-foreground">
          Exclusive offers from local businesses and easy access to food delivery.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Community Vouchers</CardTitle>
          <CardDescription>
            Exclusive discounts for residents from our partner businesses. Click to clip and save!
          </CardDescription>
        </CardHeader>
        <CardContent className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {vouchers.map((voucher) => {
            const business = businesses.find(b => b.id === voucher.businessId);
            return <VoucherCard key={voucher.id} voucher={voucher} business={business} />;
          })}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Food Delivery</CardTitle>
          <CardDescription>
            Order from your favorite local restaurants through these popular services.
          </CardDescription>
        </CardHeader>
        <CardContent className="grid gap-4">
          {foodApps.map((app) => (
            <FoodAppCard key={app.id} app={app} />
          ))}
        </CardContent>
      </Card>
    </div>
  );
}
