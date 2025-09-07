import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

export default function UsersPage() {
  return (
    <div>
      <h1 className="font-headline text-3xl font-bold">User Management</h1>
      <p className="text-muted-foreground">Manage users and their roles.</p>
       <Card className="mt-8">
        <CardHeader>
          <CardTitle>Users</CardTitle>
          <CardDescription>
            This feature is under development.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <p>A section to manage community users will be available here.</p>
        </CardContent>
      </Card>
    </div>
  );
}
