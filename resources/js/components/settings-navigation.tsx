import React from "react";
import { Link } from "@inertiajs/react";
import { User, Settings, Lock } from "lucide-react";
import { Button } from "@/components/ui/button";

interface SettingsNavigationProps {
  className?: string;
}

export const SettingsNavigation: React.FC<SettingsNavigationProps> = ({ className = "" }) => {
  const isActive = (routeName: string) => route().current(routeName);

  return (
    <div className={`space-y-1 ${className}`}>
      <Link href={route('profile.edit')}>
        <Button
          variant={isActive('profile.edit') ? "secondary" : "ghost"}
          className="w-full justify-start"
        >
          <User className="mr-2 h-4 w-4" />
          Profile
        </Button>
      </Link>
      
      <Link href={route('password.edit')}>
        <Button
          variant={isActive('password.edit') ? "secondary" : "ghost"}
          className="w-full justify-start"
        >
          <Lock className="mr-2 h-4 w-4" />
          Password
        </Button>
      </Link>
      
    </div>
  );
};