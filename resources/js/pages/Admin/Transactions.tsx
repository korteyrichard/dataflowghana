import React, { useState } from 'react';
import { AdminLayout } from '../../layouts/admin-layout';
import { Head, usePage, router } from '@inertiajs/react';
import Pagination from '@/components/pagination';

interface User {
  name: string;
  email: string;
}

interface Order {
  user: User;
  is_api_order: boolean;
}

interface Transaction {
  id: number;
  amount: number;
  status: string;
  description: string;
  created_at: string;
  type: string;
  balance_before?: number | null;
  balance_after?: number | null;
  user?: User;
  order?: Order;
}

interface PaginatedTransactions {
  data: Transaction[];
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

interface AdminTransactionsPageProps {
  transactions: PaginatedTransactions;
  auth: any;
  filterType: string;
  filterUsername: string;
  filterDate: string;
  perPage: string;
  [key: string]: any;
}

const typeLabels: Record<string, string> = {
  topup: 'Wallet Top Up',
  order: 'Order Purchase',
  bulk_order: 'Bulk Order',
  agent_fee: 'Agent Fee',
  refund: 'Refund',
  admin_credit: 'Admin Credit',
  admin_debit: 'Admin Debit',
};

const typeColors: Record<string, string> = {
  topup: 'bg-green-100 text-green-800',
  order: 'bg-blue-100 text-blue-800',
  bulk_order: 'bg-indigo-100 text-indigo-800',
  agent_fee: 'bg-orange-100 text-orange-800',
  refund: 'bg-green-100 text-green-800',
  admin_credit: 'bg-emerald-100 text-emerald-800',
  admin_debit: 'bg-red-100 text-red-800',
};

const formatBalance = (balance: number | null | undefined): string => {
  if (balance === null || balance === undefined) return '-';
  const num = typeof balance === 'string' ? parseFloat(balance) : balance;
  return isNaN(num) ? '-' : `₵${num.toFixed(2)}`;
};

export default function AdminTransactions() {
  const { transactions, auth, filterType: initialFilterType, filterUsername: initialUsername, filterDate: initialDate, perPage: initialPerPage } =
    usePage<AdminTransactionsPageProps>().props;

  const [filterType, setFilterType] = useState(initialFilterType);
  const [username, setUsername] = useState(initialUsername);
  const [date, setDate] = useState(initialDate);
  const [perPage, setPerPage] = useState(initialPerPage);

  const applyFilters = (overrides: Record<string, string> = {}) => {
    const params: Record<string, string> = {};
    const merged = { type: filterType, username, date, per_page: perPage, ...overrides };
    Object.entries(merged).forEach(([k, v]) => { if (v) params[k] = v; });
    router.get(route('admin.transactions'), params, { preserveState: true, replace: true });
  };

  return (
    <AdminLayout user={auth?.user} header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Admin Transactions</h2>}>
      <Head title="Admin Transactions" />
      <div className="py-8 max-w-6xl mx-auto">
        <div className="mb-4 flex flex-wrap gap-2 items-end">
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-gray-600">Filter by Type</label>
            <select
              className="border rounded px-3 py-2 text-sm"
              value={filterType}
              onChange={(e) => { setFilterType(e.target.value); applyFilters({ type: e.target.value }); }}
            >
              <option value="">All Types</option>
              <option value="topup">Wallet Top Ups</option>
              <option value="order">Order Purchases</option>
              <option value="bulk_order">Bulk Orders</option>
              <option value="agent_fee">Agent Fees</option>
              <option value="refund">Refunds</option>
              <option value="admin_credit">Admin Credits</option>
              <option value="admin_debit">Admin Debits</option>
            </select>
          </div>

          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-gray-600">Username</label>
            <input
              type="text"
              className="border rounded px-3 py-2 text-sm w-44"
              placeholder="Search username..."
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && applyFilters({ username })}
            />
          </div>

          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-gray-600">Date</label>
            <input
              type="date"
              className="border rounded px-3 py-2 text-sm"
              value={date}
              onChange={(e) => { setDate(e.target.value); applyFilters({ date: e.target.value }); }}
            />
          </div>

          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-gray-600">Per Page</label>
            <select
              className="border rounded px-3 py-2 text-sm"
              value={perPage}
              onChange={(e) => { setPerPage(e.target.value); applyFilters({ per_page: e.target.value }); }}
            >
              <option value="100">100</option>
              <option value="500">500</option>
              <option value="1000">1000</option>
            </select>
          </div>

          <button
            className="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700"
            onClick={() => applyFilters()}
          >
            Search
          </button>

          <button
            className="px-4 py-2 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300"
            onClick={() => {
              setFilterType(''); setUsername(''); setDate(''); setPerPage('100');
              router.get(route('admin.transactions'), {}, { preserveState: false, replace: true });
            }}
          >
            Clear
          </button>
        </div>

        {transactions.data.length === 0 ? (
          <div>No transactions found.</div>
        ) : (
          <div className="overflow-x-auto rounded-lg shadow">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
              <thead>
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Balance Before</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Balance After</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                {transactions.data.map(transaction => (
                  <tr key={transaction.id} className="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
                    <td className="px-4 py-3 font-bold">{transaction.id}</td>
                    <td className="px-4 py-3 text-sm">{transaction.user?.name || transaction.order?.user?.name}</td>
                    <td className="px-4 py-3 text-sm">₵{transaction.amount}</td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-2">
                        <span className={`px-3 py-1 rounded-full text-xs font-bold ${typeColors[transaction.type] ?? 'bg-gray-100 text-gray-800'}`}>
                          {typeLabels[transaction.type] ?? transaction.type.replace('_', ' ')}
                        </span>
                        {transaction.order?.is_api_order && (
                          <span className="px-2 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-800">API</span>
                        )}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <span className={`px-2 py-1 rounded text-xs font-medium ${
                        transaction.status === 'completed' ? 'bg-green-100 text-green-800' :
                        transaction.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                        transaction.status === 'failed' ? 'bg-red-100 text-red-800' :
                        'bg-gray-100 text-gray-800'
                      }`}>
                        {transaction.status.charAt(0).toUpperCase() + transaction.status.slice(1)}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-sm">{formatBalance(transaction.balance_before)}</td>
                    <td className="px-4 py-3 text-sm">{formatBalance(transaction.balance_after)}</td>
                    <td className="px-4 py-3 text-xs">{new Date(transaction.created_at).toLocaleString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        <Pagination data={transactions} />
      </div>
    </AdminLayout>
  );
}
