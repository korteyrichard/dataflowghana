import React, { useState, useEffect } from 'react';
import DashboardLayout from '../../layouts/DashboardLayout';
import { Head, usePage, router } from '@inertiajs/react';
import { strict } from 'node:assert';

interface CartItem {
  id: number;
  product: {
    id: number;
    name: string;
    price: number;
  };
  quantity: string;
}

interface CheckoutPageProps {
  cartItems: CartItem[];
  walletBalance: number;
  auth: any;
  flash?: {
    success?: string;
    error?: string;
  };
  [key: string]: any;
}

export default function CheckoutPage() {
  const {
    cartItems = [],
    walletBalance = 0,
    auth,
    flash = {},
  } = usePage<CheckoutPageProps>().props;

  const [loading, setLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');

  useEffect(() => {
    if (flash.success) alert(flash.success);
    if (flash.error) alert(flash.error);
  }, [flash]);



  const handleCheckout = () => {
    // Check if user has sufficient balance
    if (walletBalance < total) {
      alert('Your balance is not enough to make this purchase');
      return;
    }

    setLoading(true);
    setErrorMessage('');

    router.post(route('checkout.process'), {  cartItems: JSON.stringify(cartItems)  }, {
      onFinish: () => setLoading(false),
      onError: (errors) => {
        setErrorMessage('Failed to place order. Please try again.');
        console.error(errors);
      },
    });
  };


  // Fixed total calculation - just sum the prices since quantity represents data amount (1GB, 2GB)
  const total = cartItems.reduce((sum, item) => {
    const price = Number(item.product?.price) || 0;
    return sum + price;
  }, 0);

  return (
    <DashboardLayout
      user={auth.user}
      header={
        <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Checkout
        </h2>
      }
    >
      <Head title="Checkout" />
      <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-blue-100 to-blue-200 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 py-10 flex items-center justify-center">
        <div className="w-full max-w-2xl bg-white dark:bg-gray-800 rounded-3xl shadow-2xl p-8">
          <h3 className="text-3xl font-extrabold text-gray-800 dark:text-white mb-8 text-center flex items-center justify-center gap-2">
            <span className="inline-block bg-gradient-to-r from-indigo-500 to-blue-500 text-white px-4 py-2 rounded-xl shadow">
              Checkout
            </span>
          </h3>

          <div className="mb-8">
            <h4 className="text-xl font-semibold text-gray-700 dark:text-gray-200 mb-4">Order Summary</h4>
            <ul className="divide-y divide-gray-200 dark:divide-gray-700">
              {cartItems.map(item => (
                <li key={item.id} className="py-4 flex items-center justify-between">
                  <div>
                    <span className="font-medium text-gray-900 dark:text-gray-100">{item.product?.name || 'Unknown Product'}</span>
                    <span className="ml-2 text-gray-500 dark:text-gray-400">({item.quantity})</span>
                  </div>
                  <div className="font-semibold text-indigo-600 dark:text-indigo-400">
                    GHS {Number(item.product?.price || 0).toFixed(2)}
                  </div>
                </li>
              ))}
            </ul>

            <div className="flex justify-between items-center mt-6 border-t pt-4">
              <span className="text-lg font-bold text-gray-700 dark:text-gray-200">Total</span>
              <span className="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400">
                GHS {total.toFixed(2)}
              </span>
            </div>
          </div>

          {(flash.error || errorMessage) && (
            <div className="text-red-500 mb-4 text-center font-semibold">
              {flash.error || errorMessage}
            </div>
          )}
          {flash.success && (
            <div className="text-green-600 mb-4 text-center font-semibold">
              {flash.success}
            </div>
          )}

          <button
            className="w-full bg-gradient-to-r from-indigo-500 to-blue-500 hover:from-indigo-600 hover:to-blue-600 text-white font-bold py-4 rounded-2xl shadow-lg text-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
            onClick={handleCheckout}
            disabled={loading || cartItems.length === 0}
          >
            {loading ? (
              <span className="flex items-center justify-center gap-2">
                <svg className="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                </svg>
                Processing...
              </span>
            ) : (
              <span className="flex items-center justify-center gap-2">
                <svg className="h-6 w-6 text-white" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M3 10h2l1 2h13l1-2h2M7 10V6a5 5 0 0110 0v4" />
                </svg>
                Place Order
              </span>
            )}
          </button>
        </div>
      </div>
    </DashboardLayout>
  );
}