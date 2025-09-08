
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

export default function BlockListPage() {
  return (
    <div>
      <h1 className="font-headline text-3xl font-bold">Block List</h1>
      <p className="text-muted-foreground">Manage blocked individuals.</p>
       <Card className="mt-8">
        <CardHeader>
          <CardTitle>Blocked Individuals</CardTitle>
          <CardDescription>
            This feature is under development.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <p>A section to manage blocked individuals will be available here.</p>
        </CardContent>
      </Card>
    </div>
  );
}
