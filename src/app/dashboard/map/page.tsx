
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import dynamic from 'next/dynamic';

const CommunityMap = dynamic(() => import('@/components/dashboard/community-map'), {
  ssr: false,
  loading: () => <p>Loading map...</p>,
});

export default function MapPage() {
  return (
    <div className="flex flex-col gap-8">
        <div>
            <h1 className="font-headline text-3xl font-bold">Community Map</h1>
            <p className="text-muted-foreground">An overview of the community layout.</p>
        </div>
        <Card>
            <CardHeader>
                <CardTitle>Interactive Map</CardTitle>
                <CardDescription>
                    The highlighted area represents the community boundaries.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="h-[60vh] w-full">
                    <CommunityMap />
                </div>
            </CardContent>
        </Card>
    </div>
  );
}
