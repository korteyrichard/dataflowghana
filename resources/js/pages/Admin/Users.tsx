import React, { useState } from "react";
import { AdminLayout } from "../../layouts/admin-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { PageProps, User } from '@/types';
import { MoreHorizontal, Search, Users, UserCheck, Shield, Receipt } from "lucide-react";
import { router } from '@inertiajs/react';
import Pagination from '@/components/pagination';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

import AddUserDialog from "@/components/add-user-dialog";
import EditRoleDialog from "@/components/edit-role-dialog";
import DeleteUserDialog from "@/components/delete-user-dialog";
import CreditWalletDialog from "@/components/credit-wallet-dialog";
import DebitWalletDialog from "@/components/debit-wallet-dialog";

interface PaginatedUsers {
  data: User[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
  links: Array<{
    url: string | null;
    label: string;
    active: boolean;
  }>;
}

interface UsersPageProps extends PageProps {
  users: PaginatedUsers;
  filterEmail: string;
  filterPhone: string;
  filterRole: string;
  userStats: {
    total: number;
    customers: number;
    agents: number;
    admins: number;
    totalWalletBalance: number;
  };
}

const UsersPage = ({ auth, users, filterEmail, filterPhone, filterRole, userStats }: UsersPageProps) => {
  const [searchEmail, setSearchEmail] = useState(filterEmail);
  const [searchPhone, setSearchPhone] = useState(filterPhone);
  const [selectedRole, setSelectedRole] = useState(filterRole);

  const handleSearch = () => {
    const params: Record<string, string> = {};
    if (searchEmail) params.email = searchEmail;
    if (searchPhone) params.phone = searchPhone;
    if (selectedRole) params.role = selectedRole;
    
    router.get(route('admin.users'), params, {
      preserveState: true,
      replace: true,
    });
  };

  const handleReset = () => {
    setSearchEmail('');
    setSearchPhone('');
    setSelectedRole('');
    router.get(route('admin.users'), {}, {
      preserveState: true,
      replace: true,
    });
  };
  return (
    <AdminLayout
      user={auth.user}
      header={
        <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Admin Users
        </h2>
      }
    >
      {/* User Statistics */}
      <div className="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div className="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-100 dark:border-gray-700">
          <div className="flex items-center justify-between mb-2">
            <h3 className="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Total Users</h3>
            <div className="p-2 bg-blue-50 dark:bg-blue-900 rounded-lg">
              <Users className="w-4 h-4 text-blue-600 dark:text-blue-400" />
            </div>
          </div>
          <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{userStats.total}</p>
        </div>
        
        <div className="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-100 dark:border-gray-700">
          <div className="flex items-center justify-between mb-2">
            <h3 className="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Customers</h3>
            <div className="p-2 bg-green-50 dark:bg-green-900 rounded-lg">
              <Users className="w-4 h-4 text-green-600 dark:text-green-400" />
            </div>
          </div>
          <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{userStats.customers}</p>
        </div>
        
        <div className="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-100 dark:border-gray-700">
          <div className="flex items-center justify-between mb-2">
            <h3 className="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Agents</h3>
            <div className="p-2 bg-orange-50 dark:bg-orange-900 rounded-lg">
              <UserCheck className="w-4 h-4 text-orange-600 dark:text-orange-400" />
            </div>
          </div>
          <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{userStats.agents}</p>
        </div>
        
        <div className="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-100 dark:border-gray-700">
          <div className="flex items-center justify-between mb-2">
            <h3 className="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Admins</h3>
            <div className="p-2 bg-red-50 dark:bg-red-900 rounded-lg">
              <Shield className="w-4 h-4 text-red-600 dark:text-red-400" />
            </div>
          </div>
          <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{userStats.admins}</p>
        </div>
        
        <div className="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-100 dark:border-gray-700">
          <div className="flex items-center justify-between mb-2">
            <h3 className="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Total Wallet Balance</h3>
            <div className="p-2 bg-purple-50 dark:bg-purple-900 rounded-lg">
              <span className="text-purple-600 dark:text-purple-400 font-bold text-sm">₵</span>
            </div>
          </div>
          <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">₵{userStats.totalWalletBalance || '0.00'}</p>
        </div>
      </div>

      <div className="flex justify-between items-center mb-4">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Users</h1>
        <AddUserDialog />
      </div>

      {/* Search and Filter Section */}
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search by Email</label>
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 w-4 h-4" />
              <Input
                type="text"
                placeholder="Enter email address..."
                value={searchEmail}
                onChange={(e) => setSearchEmail(e.target.value)}
                className="pl-10"
                onKeyPress={(e) => e.key === 'Enter' && handleSearch()}
              />
            </div>
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search by Phone</label>
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 w-4 h-4" />
              <Input
                type="text"
                placeholder="Enter phone number..."
                value={searchPhone}
                onChange={(e) => setSearchPhone(e.target.value)}
                className="pl-10"
                onKeyPress={(e) => e.key === 'Enter' && handleSearch()}
              />
            </div>
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Filter by Role</label>
            <select
              value={selectedRole}
              onChange={(e) => setSelectedRole(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
            >
              <option value="">All Roles</option>
              <option value="customer">Customer</option>
              <option value="agent">Agent</option>
              <option value="dealer">Dealer</option>
              <option value="elite">Elite</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          
          <div className="flex gap-2 items-end">
            <Button onClick={handleSearch} className="px-6">
              Search
            </Button>
            <Button onClick={handleReset} variant="outline" className="px-6">
              Reset
            </Button>
          </div>
        </div>
      </div>

      {/* Responsive Table Wrapper */}
      <div className="hidden sm:block overflow-x-auto rounded-lg shadow">
        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead className="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Email</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Phone</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Role</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Wallet Balance</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            {users.data.length > 0 ? users.data.map((user: User) => (
              <tr key={user.id}>
                <td className="px-6 py-4 whitespace-nowrap text-gray-900 dark:text-gray-100">{user.name}</td>
                <td className="px-6 py-4 whitespace-nowrap text-gray-900 dark:text-gray-100">{user.email}</td>
                <td className="px-6 py-4 whitespace-nowrap text-gray-900 dark:text-gray-100">{user.phone || '-'}</td>
                <td className="px-6 py-4 whitespace-nowrap text-gray-900 dark:text-gray-100">{user.role}</td>
                <td className="px-6 py-4 whitespace-nowrap font-medium text-gray-900 dark:text-gray-100">₵{user.wallet_balance || '0.00'}</td>
                <td className="px-6 py-4 whitespace-nowrap">
                  <div className="flex items-center space-x-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => router.visit(route('admin.users.transactions', user.id))}
                      className="flex items-center gap-1"
                    >
                      <Receipt className="w-3 h-3" />
                      Transactions
                    </Button>
                    <CreditWalletDialog user={user} />
                    <DebitWalletDialog user={user} />
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="h-8 w-8 p-0">
                          <span className="sr-only">Open menu</span>
                          <MoreHorizontal className="h-4 w-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <EditRoleDialog user={user} />
                        <DeleteUserDialog user={user} />
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                </td>
              </tr>
            )) : (
              <tr>
                <td colSpan={6} className="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                  No users found matching your criteria.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* Mobile Card Layout */}
      <div className="sm:hidden space-y-4">
        {users.data.length > 0 ? users.data.map((user: User) => (
          <div key={user.id} className="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex flex-col gap-2">
            <div className="flex justify-between items-center">
              <div>
                <div className="font-bold text-lg text-gray-900 dark:text-gray-100">{user.name}</div>
                <div className="text-gray-500 dark:text-gray-400 text-sm">{user.email}</div>
                <div className="text-gray-500 dark:text-gray-400 text-sm">{user.phone || 'No phone'}</div>
              </div>
              <div className="flex items-center space-x-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => router.visit(route('admin.users.transactions', user.id))}
                  className="flex items-center gap-1"
                >
                  <Receipt className="w-3 h-3" />
                  Transactions
                </Button>
                <CreditWalletDialog user={user} />
                <DebitWalletDialog user={user} />
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="ghost" className="h-8 w-8 p-0">
                      <span className="sr-only">Open menu</span>
                      <MoreHorizontal className="h-4 w-4" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end">
                    <DropdownMenuLabel>Actions</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <EditRoleDialog user={user} />
                    <DeleteUserDialog user={user} />
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </div>
            <div className="flex justify-between mt-2">
              <span className="text-xs font-medium text-gray-500 dark:text-gray-400">Role:</span>
              <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{user.role}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-xs font-medium text-gray-500 dark:text-gray-400">Wallet:</span>
              <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">₵{user.wallet_balance || '0.00'}</span>
            </div>
          </div>
        )) : (
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center text-gray-500 dark:text-gray-400">
            No users found matching your criteria.
          </div>
        )}
      </div>
      
      {/* Pagination */}
      <Pagination data={users} />
    </AdminLayout>
  );
};

export default UsersPage;
