

'use client';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Logo } from '@/components/logo';
import { useAuth } from '@/context/auth-context';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertTriangle } from 'lucide-react';
import { WelcomeAnimation } from '@/components/welcome-animation';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  DialogClose,
} from "@/components/ui/dialog";
import { useToast } from '@/hooks/use-toast';


const testUsers = [
    { role: 'System Admin', username: 'user-sysadmin' },
    { role: 'Admin', username: 'user-admin' },
    { role: 'Homeowner', username: 'user-homeowner' },
    { role: 'Temporary Homeowner', username: 'user-renter' },
    { role: 'Security', username: 'user-security' },
    { role: 'Staff', username: 'user-staff' },
]

export default function LoginPage() {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [isLoggedIn, setIsLoggedIn] = useState(false);
  const [resetContact, setResetContact] = useState('');
  const { login, user } = useAuth();
  const router = useRouter();
  const { toast } = useToast();

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (password.length < 6) {
      setError('Password must be at least 6 characters.');
      return;
    }

    setIsLoading(true);
    const success = await login(username, password);
    setIsLoading(false);

    if (success) {
      setIsLoggedIn(true);
      setTimeout(() => {
        router.push('/dashboard');
      }, 1500); // Wait for 1.5-second cinematic animation to complete
    } else {
      setError('Invalid username. Please use one of the test usernames shown below.');
    }
  };

  const handlePasswordReset = (e: React.FormEvent) => {
    e.preventDefault();
    // In a real app, this would call a backend service.
    // For this mock, we just show a confirmation.
    toast({
      title: 'Password Reset Requested',
      description: 'If an account exists for that email or phone, a reset link has been sent.',
    });
  };

  if (isLoggedIn && user) {
    return <WelcomeAnimation username={user.name} />;
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-background p-4">
      <Card className="mx-auto w-full max-w-sm">
        <CardHeader className="text-center">
          <div className="mb-4 flex justify-center">
            <Logo />
          </div>
          <CardTitle className="text-2xl font-headline">Welcome Back</CardTitle>
          <CardDescription>Enter your credentials to access your account</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleLogin}>
            <div className="grid gap-4">
              {error && (
                <Alert variant="destructive">
                  <AlertTriangle className="h-4 w-4" />
                  <AlertTitle>Login Failed</AlertTitle>
                  <AlertDescription>{error}</AlertDescription>
                </Alert>
              )}
              <div className="grid gap-2">
                <Label htmlFor="username">Username</Label>
                <Input
                  id="username"
                  type="text"
                  placeholder="e.g., user-sysadmin"
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  required
                  disabled={isLoading}
                />
              </div>
              <div className="grid gap-2">
                <div className="flex items-center">
                    <Label htmlFor="password">Password</Label>
                     <Dialog>
                        <DialogTrigger asChild>
                            <Button variant="link" className="ml-auto inline-block px-1 py-0 h-auto text-xs">
                                Forgot password?
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-[425px]">
                            <form onSubmit={handlePasswordReset}>
                                <DialogHeader>
                                <DialogTitle>Reset Password</DialogTitle>
                                <DialogDescription>
                                    Enter your email address or phone number to receive a password reset link.
                                </DialogDescription>
                                </DialogHeader>
                                <div className="grid gap-4 py-4">
                                <div className="grid grid-cols-4 items-center gap-4">
                                    <Label htmlFor="reset-contact" className="text-right">
                                        Email/Phone
                                    </Label>
                                    <Input
                                        id="reset-contact"
                                        className="col-span-3"
                                        value={resetContact}
                                        onChange={(e) => setResetContact(e.target.value)}
                                        placeholder="user@example.com"
                                    />
                                </div>
                                </div>
                                <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="submit">Send Reset Link</Button>
                                </DialogClose>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
                <Input
                  id="password"
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                  disabled={isLoading}
                />
              </div>
              <Button type="submit" className="w-full" disabled={isLoading}>
                {isLoading ? 'Logging in...' : 'Login'}
              </Button>
            </div>
          </form>
        </CardContent>
         <CardFooter className="flex-col items-start text-sm">
             <Accordion type="single" collapsible className="w-full">
              <AccordionItem value="item-1">
                <AccordionTrigger className="py-2 text-muted-foreground">View Test Credentials</AccordionTrigger>
                <AccordionContent>
                  <Alert>
                    <AlertDescription className="space-y-3">
                       <p>Click any account below to <strong>Instant Demo</strong> or use password <code>password123</code>:</p>
                       <div className="grid grid-cols-2 gap-2 pt-1">
                        {testUsers.map(u => (
                            <Button 
                              key={u.role} 
                              variant="outline" 
                              size="sm" 
                              className="text-xs justify-start h-8"
                              onClick={async () => {
                                setUsername(u.username);
                                setPassword('password123');
                                setIsLoading(true);
                                const success = await login(u.username, 'password123');
                                setIsLoading(false);
                                if (success) {
                                  setIsLoggedIn(true);
                                  setTimeout(() => router.push('/dashboard'), 1500);
                                }
                              }}
                            >
                              ⚡ {u.role}
                            </Button>
                        ))}
                       </div>
                    </AlertDescription>
                  </Alert>
                </AccordionContent>
              </AccordionItem>
            </Accordion>
        </CardFooter>
      </Card>
    </div>
  );
}
