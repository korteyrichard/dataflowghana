import React from "react";
import { Link, router } from "@inertiajs/react";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import { Button } from "@/components/ui/button";
import { PanelLeft, LogOut, Settings } from "lucide-react";
import { Icon } from "@/components/ui/icon";
import { icons } from "lucide-react";
import { Toaster } from "@/components/ui/toaster";
import { ThemeToggle } from "@/components/theme-toggle";

type IconName = keyof typeof icons;

interface NavigationItem {
  name: string;
  href: string;
  icon: IconName;
  current: boolean;
}

// Update props interface to include user and header
interface AdminLayoutProps {
  children: React.ReactNode;
  user: {
    id: number;
    name: string;
    email: string;
    role: string;
  };
  header?: React.ReactNode;
}

const adminNavigation: NavigationItem[] = [
  { name: "Admin Dashboard", href: route("admin.dashboard"), icon: "Shield", current: route().current("admin.dashboard") },
  { name: "Users", href: route("admin.users"), icon: "Users", current: route().current("admin.users") },
  { name: "Products", href: route("admin.products"), icon: "Box", current: route().current("admin.products") },
  { name: "AFA Products", href: route("admin.afa-products"), icon: "Package2", current: route().current("admin.afa-products") },
  { name: "Orders", href: route("admin.orders"), icon: "Package", current: route().current("admin.orders") },
  { name: "AFA Orders", href: route("admin.afa-orders"), icon: "FileText", current: route().current("admin.afa-orders") },
  { name: "Transactions", href: route("admin.transactions"), icon: "Receipt", current: route().current("admin.transactions") },
  { name: "Settings", href: route("profile.edit"), icon: "Settings", current: route().current("profile.edit") || route().current("password.edit") || route().current("appearance") },
];

export const AdminLayout: React.FC<AdminLayoutProps> = ({ children, user, header }) => {
  const handleLogout = () => {
    router.post(route('logout'));
  };

  const renderNavigationItems = () =>
    adminNavigation.map((item) => (
      <Link
        key={item.name}
        href={item.href}
        className={`flex h-9 w-9 items-center justify-center rounded-lg ${
          item.current ? "bg-accent text-accent-foreground" : "text-muted-foreground hover:text-foreground"
        } transition-colors md:h-8 md:w-8`}
      >
        <Icon name={item.icon} className="h-5 w-5" />
        <span className="sr-only">{item.name}</span>
      </Link>
    ));

  const renderMobileNavItems = () =>
    adminNavigation.map((item) => (
      <Link
        key={item.name}
        href={item.href}
        className={`flex items-center gap-4 px-2.5 ${
          item.current ? "text-foreground" : "text-muted-foreground hover:text-foreground"
        }`}
      >
        <Icon name={item.icon} className="h-5 w-5" />
        {item.name}
      </Link>
    ));

  return (
    <div className="flex min-h-screen w-full flex-col bg-muted/40">
      {/* Sidebar */}
      <aside className="fixed inset-y-0 left-0 z-10 hidden w-14 flex-col border-r bg-background sm:flex">
        <nav className="flex flex-col items-center gap-4 px-2 sm:py-5">
          {renderNavigationItems()}
        </nav>
      </aside>

      {/* Main Content */}
      <div className="flex flex-col sm:gap-4 sm:py-4 sm:pl-14">
        {/* Toast notifications */}
        <Toaster />
        
        <header className="sticky top-0 z-30 flex h-14 items-center justify-between gap-4 border-b bg-background px-4 sm:static sm:h-auto sm:border-0 sm:bg-transparent sm:px-6">
          <Sheet>
            <SheetTrigger asChild>
              <Button size="icon" variant="outline" className="sm:hidden">
                <PanelLeft className="h-5 w-5" />
                <span className="sr-only">Toggle Menu</span>
              </Button>
            </SheetTrigger>
            <SheetContent side="left" className="sm:max-w-xs">
              <nav className="grid gap-6 text-lg font-medium">
                {renderMobileNavItems()}
              </nav>
            </SheetContent>
          </Sheet>
          {/* Optional: Show header title */}
          {header && (
            <h2 className="text-lg font-semibold text-gray-800 dark:text-gray-200">
              {header}
            </h2>
          )}
          {/* User info, settings, and logout */}
          <div className="flex items-center gap-2">
            <div className="text-sm text-muted-foreground hidden sm:block">
              {user.name}
            </div>
            <ThemeToggle />
            <Link href={route('profile.edit')} className="h-8 w-8 flex items-center justify-center rounded-lg text-muted-foreground hover:text-foreground transition-colors">
              <Icon name="Settings" className="h-4 w-4" />
            </Link>
            <Button
              size="icon"
              variant="outline"
              onClick={handleLogout}
              className="h-8 w-8"
              title="Logout"
            >
              <LogOut className="h-4 w-4" />
            </Button>
          </div>
        </header>
        <main className="grid flex-1 items-start gap-4 p-4 sm:px-6 sm:py-0 md:gap-8">{children}</main>
      </div>
    </div>
  );
};
