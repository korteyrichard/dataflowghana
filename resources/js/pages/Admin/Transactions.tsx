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
}

interface Transaction {
  id: number;
  amount: number;
  status: string;
  description: string;
  created_at: string;
  type: string;
  user?: User; // Direct user relationship for topups
  order?: Order; // Order relationship for order transactions
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
  [key: string]: any;
}

const typeLabels: Record<string, string> = {
  topup: 'Wallet Top Up',
  order: 'Order Purchase',
  agent_fee: 'Agent Fee',
  refund: 'Refund',
  admin_credit: 'Admin Credit',
  admin_debit: 'Admin Debit',
};

const typeColors: Record<string, string> = {
  topup: 'bg-green-100 text-green-800',
  order: 'bg-blue-100 text-blue-800',
  agent_fee: 'bg-orange-100 text-orange-800',
  refund: 'bg-green-100 text-green-800',
  admin_credit: 'bg-emerald-100 text-emerald-800',
  admin_debit: 'bg-red-100 text-red-800',
};

export default function AdminTransactions() {
  const { transactions, auth, filterType: initialFilterType } = usePage<AdminTransactionsPageProps>().props;
  const [filterType, setFilterType] = useState(initialFilterType);

  const handleFilterChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newFilter = e.target.value;
    setFilterType(newFilter);
    router.get(route('admin.transactions'), { type: newFilter }, { preserveState: true, replace: true });
  };

  return (
    <AdminLayout user={auth?.user} header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Admin Transactions</h2>}>
      <Head title="Admin Transactions" />
      <div className="py-8 max-w-4xl mx-auto">
        <div className="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
          <label className="font-medium">Filter by Type:</label>
          <select
            className="border rounded px-3 py-2 w-full sm:w-60"
            value={filterType}
            onChange={handleFilterChange}
          >
            <option value="" className='text-slate-600'>All Types</option>
            <option value="topup" className='text-slate-600'>Wallet Top Ups</option>
            <option value="order" className='text-slate-600'>Order Purchases</option>
            <option value="agent_fee" className='text-slate-600'>Agent Fees</option>
            <option value="refund" className='text-slate-600'>Refunds</option>
            <option value="admin_credit" className='text-slate-600'>Admin Credits</option>
            <option value="admin_debit" className='text-slate-600'>Admin Debits</option>
          </select>
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
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                {transactions.data.map(transaction => (
                  <tr key={transaction.id} className="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
                    <td className="px-4 py-3 font-bold">{transaction.id}</td>
                    <td className="px-4 py-3 text-sm">{transaction.user?.name || transaction.order?.user?.name}</td>
                    <td className="px-4 py-3 text-sm">${transaction.amount}</td>
                    <td className="px-4 py-3">
                      <span className={`px-3 py-1 rounded-full text-xs font-bold  ${typeColors[transaction.type]}`}>{typeLabels[transaction.type]}</span>
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