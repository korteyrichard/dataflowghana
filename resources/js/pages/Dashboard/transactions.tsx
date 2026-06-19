import DashboardLayout from '../../layouts/DashboardLayout';
import { Head, usePage, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import React, { useState, useMemo } from 'react';

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
  const { transactions } = usePage<TransactionsPageProps>().props;
  const [filter, setFilter] = useState<string>('all');
  const [currentPage, setCurrentPage] = useState(1);

  const transactionData = transactions?.data || [];
  
  const filteredTransactions = useMemo(() => {
    return filter === 'all' ? transactionData : transactionData.filter((t) => t.type === filter);
  }, [transactionData, filter]);

  const itemsPerPage = 50;
  const totalPages = Math.ceil(filteredTransactions.length / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const paginatedTransactions = filteredTransactions.slice(startIndex, startIndex + itemsPerPage);

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handleFilterChange = (value: string) => {
    setFilter(value);
    setCurrentPage(1);
  };

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
          <div className="bg-white dark:bg-gray-900 shadow-xl rounded-2xl p-6 sm:p-8 border border-gray-100 dark:border-gray-800">

            {/* Filter Select */}
            <div className="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
              <div className="flex items-center gap-4">
                <select
                  value={filter}
                  onChange={(e) => handleFilterChange(e.target.value)}
                  className="px-4 py-2 rounded-lg font-medium text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="all">All Transactions</option>
                  <option value="topup">Wallet Top Ups</option>
                  <option value="order">Order Purchases</option>
                  <option value="admin_credit">Admin Credits</option>
                  <option value="admin_debit">Admin Debits</option>
                  <option value="refund">Refunds</option>
                </select>
                <span className="text-xs text-gray-600 dark:text-gray-400">
                  {filteredTransactions.length} results
                </span>
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
                  {paginatedTransactions.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="text-center py-8 text-gray-400 dark:text-gray-500 text-lg">
                        No transactions found.
                      </td>
                    </tr>
                  ) : (
                    paginatedTransactions.map((t) => (
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

            {/* Mobile Version - Same Table Format */}
            <div className="sm:hidden space-y-4">
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                  <thead>
                    <tr>
                      <th className="px-3 py-2 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                      <th className="px-3 py-2 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                      <th className="px-3 py-2 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                      <th className="px-3 py-2 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Balance</th>
                    </tr>
                  </thead>
                  <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
                    {paginatedTransactions.length === 0 ? (
                      <tr>
                        <td colSpan={4} className="text-center py-8 text-gray-400 dark:text-gray-500 text-sm">
                          No transactions found.
                        </td>
                      </tr>
                    ) : (
                      paginatedTransactions.map((t) => (
                        <tr key={t.id} className="hover:bg-blue-50 dark:hover:bg-gray-800 transition-all">
                          <td className="px-3 py-3 whitespace-nowrap text-gray-700 dark:text-gray-200 font-medium text-xs">
                            {new Date(t.created_at).toLocaleString('en-US', { 
                              month: 'short', 
                              day: 'numeric',
                              hour: '2-digit',
                              minute: '2-digit'
                            })}
                          </td>
                          <td className="px-3 py-3 whitespace-nowrap">
                            <div className="flex items-center gap-1 flex-wrap">
                              <span className={`px-2 py-1 rounded-full text-xs font-bold ${typeColors[t.type] || 'bg-gray-100 text-gray-800'}`}>
                                {typeLabels[t.type] || t.type}
                              </span>
                              {t.order?.is_api_order && (
                                <span className="px-2 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-800">API</span>
                              )}
                            </div>
                          </td>
                          <td className="px-3 py-3 whitespace-nowrap text-right text-xs font-bold text-gray-900 dark:text-gray-100">
                            GHC {t.amount.toLocaleString()}
                          </td>
                          <td className="px-3 py-3 whitespace-nowrap text-right text-xs font-bold text-gray-900 dark:text-gray-100">
                            {formatBalance(t.balance_after)}
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>

              {/* Mobile Pagination */}
              {totalPages > 1 && (
                <div className="flex justify-center items-center gap-2 py-4">
                  <button
                    onClick={() => handlePageChange(currentPage - 1)}
                    disabled={currentPage === 1}
                    className="px-3 py-1 text-xs font-medium rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-300 dark:hover:bg-gray-600"
                  >
                    Prev
                  </button>
                  <span className="text-xs text-gray-600 dark:text-gray-400">
                    Page {currentPage} of {totalPages}
                  </span>
                  <button
                    onClick={() => handlePageChange(currentPage + 1)}
                    disabled={currentPage === totalPages}
                    className="px-3 py-1 text-xs font-medium rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-300 dark:hover:bg-gray-600"
                  >
                    Next
                  </button>
                </div>
              )}
            </div>

            {/* Desktop Pagination */}
            {totalPages > 1 && (
              <div className="hidden sm:flex justify-center items-center gap-2 mt-8 flex-wrap">
                <button
                  onClick={() => handlePageChange(currentPage - 1)}
                  disabled={currentPage === 1}
                  className="px-4 py-2 text-sm font-medium rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-300 dark:hover:bg-gray-600"
                >
                  Previous
                </button>
                
                {Array.from({ length: totalPages }, (_, i) => i + 1).map((page) => (
                  <button
                    key={page}
                    onClick={() => handlePageChange(page)}
                    className={`px-3 py-2 text-sm font-medium rounded-lg transition-colors ${
                      page === currentPage
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
                    }`}
                  >
                    {page}
                  </button>
                ))}

                <button
                  onClick={() => handlePageChange(currentPage + 1)}
                  disabled={currentPage === totalPages}
                  className="px-4 py-2 text-sm font-medium rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-300 dark:hover:bg-gray-600"
                >
                  Next
                </button>
              </div>
            )}
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}
