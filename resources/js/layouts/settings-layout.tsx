import React from "react";
import { AdminLayout } from "./admin-layout";
import { PageProps } from "@/types";
import { SettingsNavigation } from "@/components/settings-navigation";
import DashboardLayout from "./DashboardLayout";

interface SettingsLayoutProps {
  children: React.ReactNode;
  user: PageProps["auth"]["user"];
  header?: React.ReactNode;
}

const SettingsLayout: React.FC<SettingsLayoutProps> = ({ children, user, header }) => {
  return (
    <DashboardLayout user={user} header={header}>
      <div className="max-w-7xl mx-auto">
        <div className="flex flex-col md:flex-row gap-6">
          {/* Settings Navigation Sidebar */}
          <div className="w-full md:w-64 shrink-0">
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
              <SettingsNavigation />
            </div>
          </div>

          {/* Settings Content */}
          <div className="flex-1">
            {children}
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
};

export { SettingsLayout };
export default SettingsLayout;