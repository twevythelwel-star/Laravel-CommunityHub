import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

export default function DirectoryPage() {
  return (
    <div>
      <h1 className="font-headline text-3xl font-bold">Homeowner Directory</h1>
      <p className="text-muted-foreground">Search for homeowners and view contact information.</p>
       <Card className="mt-8">
        <CardHeader>
          <CardTitle>Directory</CardTitle>
          <CardDescription>
            This feature is under development.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <p>A searchable directory of all homeowners will be available here.</p>
        </CardContent>
      </Card>
    </div>
  );
}
