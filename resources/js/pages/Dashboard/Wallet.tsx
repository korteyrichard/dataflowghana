import React,{useState} from 'react';
import DashboardLayout from '../../layouts/DashboardLayout';
import { Head, router,usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import { User } from '@/types';

interface Transaction {
  id: number;
  amount: number;
  status: string;
  type: string;
  description: string;
  created_at: string;
  reference?: string;
}

interface WalletPageProps extends PageProps {
  auth: {
    user: User & {
      wallet_balance: number;
    };
  };
  transactions: {
    data: Transaction[];
    current_page: number;
    last_page: number;
    links: {
      url: string | null;
      label: string;
      active: boolean;
    }[];
  };
}





export default function Wallet({ auth, transactions }: WalletPageProps) {
     
     

      const { walletBalance: initialWalletBalance } = usePage<PageProps>().props;

      const [walletBalance, setWalletBalance] = useState(initialWalletBalance ?? 0);
      const [showAddModal, setShowAddModal] = useState(false);
      const [addAmount, setAddAmount] = useState('');
      const [isAdding, setIsAdding] = useState(false);
      const [addError, setAddError] = useState<string | null>(null);
      const [verifyingTransactions, setVerifyingTransactions] = useState<Set<number>>(new Set());



       

  const handleTopUp = () => {
    router.visit('/dashboard/wallet/add');
  };

  const handleVerifyPayment = async (transactionId: number, reference: string) => {
    setVerifyingTransactions(prev => new Set(prev).add(transactionId));
    
    try {
      const response = await fetch('/verify-payment', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({ reference }),
      });
      
      const data = await response.json();
      
      if (data.success) {
        // Refresh the page to show updated data
        router.reload();
      } else {
        alert(data.message || 'Verification failed');
      }
    } catch (error) {
      alert('Error verifying payment');
    } finally {
      setVerifyingTransactions(prev => {
        const newSet = new Set(prev);
        newSet.delete(transactionId);
        return newSet;
      });
    }
  };

  const user = auth.user;

  return (
    <DashboardLayout
      user={auth.user}
      header={
        <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Wallet
        </h2>
      }
    >
      <Head title="Wallet" />

     {showAddModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 w-full max-w-xs relative border border-gray-200 dark:border-gray-700">
            <button
              className="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 text-2xl"
              onClick={() => setShowAddModal(false)}
              aria-label="Close"
            >
              &times;
            </button>
            <h3 className="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Add to Wallet</h3>
            <form
              className="flex flex-col gap-3"
              onSubmit={async (e) => {
                e.preventDefault();
                setIsAdding(true);
                setAddError(null);
                try {
                  const response = await fetch('/dashboard/wallet/add', {
                    method: 'POST',
                    headers: {
                      'Content-Type': 'application/json',
                      'Accept': 'application/json',
                      'X-Requested-With': 'XMLHttpRequest',
                      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({ amount: addAmount }),
                  });
                  const data = await response.json();
                  if (data.success && data.payment_url) {
                    // Redirect to Paystack payment page
                    window.location.href = data.payment_url;
                  } else {
                    setAddError(data.message || 'Failed to initialize payment.');
                  }
                } catch (err) {
                  setAddError('Error initializing payment.');
                } finally {
                  setIsAdding(false);
                }
              }}
            >
              <input
                type="number"
                min="10.00"
                step="0.01"
                className="rounded px-2 py-2 w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500"
                placeholder="Amount"
                value={addAmount}
                onChange={e => setAddAmount(e.target.value)}
                required
                disabled={isAdding}
                autoFocus
              />
              <button
                type="submit"
                className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded font-semibold"
                disabled={isAdding || !addAmount}
                
              >
                {isAdding ? 'Processing...' : 'Top Up'}
              </button>
              {addError && <p className="text-red-500 text-xs mt-1">{addError}</p>}
            </form>
          </div>
        </div>
      )}
    
      <div className="py-8 max-w-7xl mx-auto px-4 space-y-8">
        {/* Wallet Balance Card */}
        <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div>
            <h3 className="text-gray-700 dark:text-gray-200 text-lg font-semibold mb-1">
              Wallet Balance
            </h3>
            <p className="text-3xl font-bold text-green-500">
              GHS {auth.user.wallet_balance}
            </p>
          </div>
          <button
            onClick={() => setShowAddModal(true)}
            className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full sm:w-auto"
          >
            Top Up Wallet
          </button>
        </div>

        {/* Transactions Table */}
        <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
          <h3 className="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-100">
            Wallet Top-up History
          </h3>

          <div className="w-full overflow-x-auto">
            <table className="min-w-full text-sm sm:text-base border border-gray-200 dark:border-gray-700">
              <thead className="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-200">
                <tr>
                  <th className="p-3 border whitespace-nowrap">ID</th>
                  <th className="p-3 border whitespace-nowrap">Amount</th>
                  <th className="p-3 border whitespace-nowrap">Status</th>
                  <th className="p-3 border whitespace-nowrap">Date</th>
                  <th className="p-3 border whitespace-nowrap">Action</th>
                </tr>
              </thead>
              <tbody>
                {transactions.data.length === 0 ? (
                  <tr>
                    <td
                      colSpan={6}
                      className="p-4 text-center text-gray-500 dark:text-gray-400"
                    >
                      No wallet top-up transactions found.
                    </td>
                  </tr>
                ) : (
                  transactions.data.map((tx) => (
                    <tr key={tx.id} className="border-t dark:border-gray-700">
                      <td className="p-3 border">{tx.id}</td>
                      <td className="p-3 border">GHS {Number(tx.amount).toFixed(2)}</td>
                      <td className="p-3 border">
                        <span className={`px-2 py-1 rounded text-xs ${
                          tx.status === 'completed' ? 'bg-green-100 text-green-800' :
                          tx.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                          'bg-red-100 text-red-800'
                        }`}>
                          {tx.status}
                        </span>
                      </td>
                      <td className="p-3 border">
                        {new Date(tx.created_at).toLocaleString()}
                      </td>
                      <td className="p-3 border">
                        {tx.status === 'pending' && tx.reference ? (
                          <button
                            onClick={() => handleVerifyPayment(tx.id, tx.reference!)}
                            disabled={verifyingTransactions.has(tx.id)}
                            className="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 disabled:opacity-50"
                          >
                            {verifyingTransactions.has(tx.id) ? 'Verifying...' : 'Verify Payment'}
                          </button>
                        ) : (
                          <span className="text-gray-400 text-sm">-</span>
                        )}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          <div className="flex flex-wrap justify-end gap-2 mt-4">
            {transactions.links.map((link, i) =>
              link.url ? (
                <button
                  key={i}
                  className={`px-3 py-1 rounded text-sm sm:text-base ${
                    link.active
                      ? 'bg-blue-600 text-white'
                      : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200'
                  }`}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                  onClick={() => router.visit(link.url!)}
                />
              ) : null
            )}
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}
