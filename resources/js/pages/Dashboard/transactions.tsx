import DashboardLayout from '../../layouts/DashboardLayout';
import { Head, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import React, { useState } from 'react';

interface Transaction {
  id: number;
  type: string;
  amount: number;
  balance_before?: number | null;
  balance_after?: number | null;
  description: string;
  created_at: string;
  order?: {
    is_api_order: boolean;
  };
}

interface TransactionsPageProps extends PageProps {
  transactions?: {
    data: Transaction[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  allTimeSales?: number;
  dailySales?: number;
}

const typeLabels: Record<string, string> = {
  topup: 'Wallet Top Up',
  order: 'Order Purchase',
  admin_credit: 'Admin Credit',
  admin_debit: 'Admin Debit',
  refund: 'Refund',
};

const typeColors: Record<string, string> = {
  topup: 'bg-green-100 text-green-800',
  order: 'bg-blue-100 text-blue-800',
  admin_credit: 'bg-emerald-100 text-emerald-800',
  admin_debit: 'bg-red-100 text-red-800',
  refund: 'bg-green-100 text-green-800',
};

const formatBalance = (balance: number | null | undefined): string => {
  if (balance === null || balance === undefined) {
    return '-';
  }
  const num = typeof balance === 'string' ? parseFloat(balance) : balance;
  return isNaN(num) ? '-' : `GHC ${num.toLocaleString()}`;
};

export default function Transactions({ auth }: TransactionsPageProps) {
  const { transactions, allTimeSales = 0, dailySales = 0 } = usePage<TransactionsPageProps>().props;
  const [filter, setFilter] = useState<string>('all');

  const transactionData = transactions?.data || [];
  const filteredTransactions =
    filter === 'all' ? transactionData : transactionData.filter((t) => t.type === filter);

  return (
    <DashboardLayout
      user={auth.user}
      header={
        <h2 className="font-bold text-2xl text-gray-800 dark:text-gray-200 leading-tight flex items-center gap-2">
          <span className="inline-block w-2 h-6 bg-blue-600 rounded mr-2"></span>Transactions
        </h2>
      }
    >
      <Head title="Transactions" />

      <div className="py-12 bg-gradient-to-br from-blue-50 to-white dark:from-gray-900 dark:to-gray-800 min-h-screen">
        <div className="max-w-6xl mx-auto sm:px-6 lg:px-8">
          {/* Sales Stats Cards */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div className="bg-white dark:bg-gray-900 shadow-lg rounded-2xl p-6 border border-gray-100 dark:border-gray-800">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-gray-600 dark:text-gray-400 text-sm font-medium">All Time Sales</p>
                  <p className="text-3xl font-bold text-gray-900 dark:text-white mt-2">
                    GHC {(allTimeSales || 0).toLocaleString()}
                  </p>
                </div>
                <div className="bg-blue-100 dark:bg-blue-900 p-4 rounded-full">
                  <svg className="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8L5.257 19.293a2 2 0 00-.263 2.495A2.972 2.972 0 015 21h12a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v12" />
                  </svg>
                </div>
              </div>
            </div>
            <div className="bg-white dark:bg-gray-900 shadow-lg rounded-2xl p-6 border border-gray-100 dark:border-gray-800">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-gray-600 dark:text-gray-400 text-sm font-medium">Today's Sales</p>
                  <p className="text-3xl font-bold text-gray-900 dark:text-white mt-2">
                    GHC {(dailySales || 0).toLocaleString()}
                  </p>
                </div>
                <div className="bg-green-100 dark:bg-green-900 p-4 rounded-full">
                  <svg className="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
              </div>
            </div>
          </div>

          <div className="bg-white dark:bg-gray-900 shadow-xl rounded-2xl p-6 sm:p-8 border border-gray-100 dark:border-gray-800">

            {/* Filter Buttons */}
            <div className="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
              <div className="flex flex-wrap justify-center sm:justify-start gap-2">
                {[
                  { value: 'all', label: 'All', color: 'blue' },
                  { value: 'topup', label: 'Wallet Top Ups', color: 'green' },
                  { value: 'order', label: 'Order Purchases', color: 'blue' },
                  { value: 'admin_credit', label: 'Admin Credits', color: 'emerald' },
                  { value: 'admin_debit', label: 'Admin Debits', color: 'red' },
                  { value: 'refund', label: 'Refunds', color: 'green' },
                ].map(({ value, label, color }) => (
                  <button
                    key={value}
                    className={`px-4 py-2 rounded-full font-medium text-sm transition-all duration-200 border ${
                      filter === value
                        ? `bg-${color}-600 text-white border-${color}-600`
                        : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-700 hover:bg-opacity-75'
                    }`}
                    onClick={() => setFilter(value)}
                  >
                    {label}
                  </button>
                ))}
              </div>
            </div>

            {/* Desktop Table */}
            <div className="overflow-x-auto hidden sm:block">
              <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                <thead>
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                    <th className="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                    <th className="px-6 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                    <th className="px-6 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Balance Before</th>
                    <th className="px-6 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Balance After</th>
                  </tr>
                </thead>
                <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
                  {filteredTransactions.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="text-center py-8 text-gray-400 dark:text-gray-500 text-lg">
                        No transactions found.
                      </td>
                    </tr>
                  ) : (
                    filteredTransactions.map((t) => (
                      <tr key={t.id} className="hover:bg-blue-50 dark:hover:bg-gray-800 transition-all ">
                        <td className="px-6 py-4 whitespace-nowrap text-gray-700 dark:text-gray-200 font-medium text-xs">
                          {new Date(t.created_at).toLocaleString()}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="flex items-center gap-2">
                            <span className={`px-3 py-1 rounded-full text-xs font-bold ${typeColors[t.type] || 'bg-gray-100 text-gray-800'}`}>
                              {typeLabels[t.type] || t.type}
                            </span>
                            {t.order?.is_api_order && (
                              <span className="px-2 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-800">API</span>
                            )}
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-right text-xs font-bold text-gray-900 dark:text-gray-100">
                          GHC {t.amount.toLocaleString()}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-right text-xs font-bold text-gray-900 dark:text-gray-100">
                          {formatBalance(t.balance_before)}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-right text-xs font-bold text-gray-900 dark:text-gray-100">
                          {formatBalance(t.balance_after)}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>

            {/* Mobile Version */}
            <div className="sm:hidden space-y-4">
              {filteredTransactions.length === 0 ? (
                <p className="text-center py-8 text-gray-400 dark:text-gray-500 text-lg">No transactions found.</p>
              ) : (
                filteredTransactions.map((t) => (
                  <div key={t.id} className="p-4 rounded-xl shadow border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                    <div className="flex justify-between items-center mb-2">
                      <span className="text-sm font-semibold text-gray-500 dark:text-gray-400">
                        {new Date(t.created_at).toLocaleDateString()}
                      </span>
                      <div className="flex items-center gap-2">
                        <span className={`text-xs font-bold px-2 py-1 rounded-full ${typeColors[t.type] || 'bg-gray-100 text-gray-800'}`}>
                          {typeLabels[t.type] || t.type}
                        </span>
                        {t.order?.is_api_order && (
                          <span className="text-xs font-bold px-2 py-1 rounded-full bg-purple-100 text-purple-800">API</span>
                        )}
                      </div>
                    </div>
                    <p className="text-gray-800 dark:text-gray-200 font-medium mb-2">{t.description}</p>
                    <div className="grid grid-cols-2 gap-2 mb-2">
                      <div>
                        <span className="text-xs text-gray-500 dark:text-gray-400">Amount:</span>
                        <div className="font-bold text-gray-900 dark:text-white text-sm">GHC {t.amount.toLocaleString()}</div>
                      </div>
                      <div className="text-right">
                        <span className="text-xs text-gray-500 dark:text-gray-400">After Balance:</span>
                        <div className="font-bold text-gray-900 dark:text-white text-sm">
                          {formatBalance(t.balance_after)}
                        </div>
                      </div>
                    </div>
                    <div className="text-xs text-gray-500 dark:text-gray-400">
                      Before: {formatBalance(t.balance_before)}
                    </div>
                  </div>
                ))
              )}
            </div>

          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}
