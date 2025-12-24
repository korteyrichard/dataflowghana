import DashboardLayout from '../../layouts/DashboardLayout';
import { Head, usePage, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import React, { useState, useEffect } from 'react';
import { Icon } from '@/components/ui/icon';
import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import NetworkCards from '@/components/NetworkCards';


interface Order {
  id: number;
  status: 'PENDING' | 'PROCESSING' | 'COMPLETED' | string;
}

interface Product {
  id: number;
  name: string;
  price: number;
  network: string;
  expiry: string;
  product_type: 'customer_product' | 'agent_product' | 'dealer_product' | 'elite_product';
}

interface CartItem {
  id: number;
  product_id: number;
  quantity: string;
  beneficiary_number: string;
  product: {
    name: string;
    price: number;
    network: string;
    expiry: string;
  };
}

interface DashboardProps extends PageProps {
  cartCount: number;
  cartItems: CartItem[];
  walletBalance: number;
  orders: Order[];
  totalSales: number;
  todaySales: number;
  pendingOrders: number;
  processingOrders: number;
  products: Product[];
}

export default function Dashboard({ auth }: DashboardProps) {
  const { cartCount, cartItems, walletBalance: initialWalletBalance, orders, totalSales, todaySales, pendingOrders, processingOrders, products } = usePage<DashboardProps>().props;

  const [walletBalance, setWalletBalance] = useState(initialWalletBalance ?? 0);
  const [addAmount, setAddAmount] = useState('');
  const [isAdding, setIsAdding] = useState(false);



  // Filter products based on user role
  const filteredProducts = products?.filter(product => {
    switch (auth.user.role) {
      case 'customer':
        return product.product_type === 'customer_product';
      case 'agent':
        return product.product_type === 'agent_product';
      case 'elite':
        return product.product_type === 'elite_product';
      case 'dealer':
      case 'admin':
        return product.product_type === 'dealer_product';
      default:
        return false;
    }
  }) || [];





  const handleRemoveFromCart = (cartId: number) => {
    router.delete(route('remove.from.cart', cartId));
  };



  return (
    <DashboardLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Dashboard</h2>}
    >
      <Head title="Dashboard" />



      <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
        {/* Hero Section */}
        <div className="relative h-40 bg-gradient-to-r from-blue-600 to-purple-700 overflow-hidden rounded-3xl">
          <div className="absolute inset-0 bg-gradient-to-r from-cyan-400 to-blue-500 opacity-80 rounded-3xl"></div>
          <div className="relative z-10 flex items-center h-full px-4 sm:px-8 justify-between">
            <div className="flex items-center space-x-6">
              <div className="w-20 h-20 rounded-full overflow-hidden border-4 border-white shadow-lg bg-white flex items-center justify-center">
                <span className="text-2xl font-bold text-blue-600">{auth.user.name.charAt(0)}</span>
              </div>
              <div className="text-white">
                <h1 className="text-3xl font-bold">{auth.user.name}</h1>
                <p className="text-blue-100 font-medium">{auth.user.role.toUpperCase()}</p>
              </div>
            </div>
            <div>
                      {/* Action Buttons Section */}
                {auth.user.role === 'customer' && (
                  <div className="px-4 sm:px-8 mb-4">
                    <Link
                      href={route('become_an_agent')}
                      className="inline-block px-6 py-2 text-white font-medium rounded-full bg-gradient-to-r from-purple-600 to-blue-600 hover:bg-gradient-to-r hover:from-blue-600 hover:to-purple-600 hover:text-white hover:-translate-y-0.5 transition-all duration-300"
                    >
                      Become An Agent
                    </Link>
                  </div>
                )}
            </div>
            
          </div>
        </div>

       

        {/* Wallet Section */}
        <div className="px-4 sm:px-8 -mt-8 relative z-20">
          <div className="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-6 mb-8">
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center space-y-4 sm:space-y-0">
              <div>
                <p className="text-gray-600 dark:text-gray-400 text-sm mb-1">Wallet Balance</p>
                <p className="text-lg sm:text-lg font-bold  text-gray-900 dark:text-gray-100">GHS {walletBalance}</p>
              </div>
              <div className="sm:text-right">
                <p className="text-gray-600 dark:text-gray-400 text-sm mb-2">Wallet Top Up</p>
                <div className="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                  <input 
                    type="number" 
                    placeholder="Enter Amount" 
                    value={addAmount}
                    onChange={e => setAddAmount(e.target.value)}
                    className="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm w-full sm:w-40 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400"
                  />
                  <button 
                    onClick={async () => {
                      if (!addAmount) return;
                      setIsAdding(true);
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
                          window.location.href = data.payment_url;
                        } else {
                          alert(data.message || 'Failed to initialize payment.');
                        }
                      } catch (err) {
                        alert('Error initializing payment.');
                      } finally {
                        setIsAdding(false);
                      }
                    }}
                    disabled={!addAmount || isAdding}
                    className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 w-full sm:w-auto disabled:opacity-50"
                  >
                    {isAdding ? 'Processing...' : 'Submit'}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Console Info */}
        <div className="px-4 sm:px-8 mb-8">
          <div className="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-6">
            <h3 className="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Dashboard Stats</h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
              <div>
                <p className="text-gray-600 dark:text-gray-400">Total Sales:</p>
                <p className="font-bold">GHS {totalSales}</p>
              </div>
              <div>
                <p className="text-gray-600 dark:text-gray-400">Today's Sales:</p>
                <p className="text-green-600 font-bold">GHS {todaySales}</p>
              </div>
              <div>
                <p className="text-gray-600 dark:text-gray-400">Pending Orders:</p>
                <p className="text-orange-600 font-bold">{pendingOrders}</p>
              </div>
              <div>
                <p className="text-gray-600 dark:text-gray-400">Processing Orders:</p>
                <p className="text-blue-600 font-bold">{processingOrders}</p>
              </div>
            </div>
          </div>
        </div>

        {/* Main Content Grid */}
        <div className="px-4 sm:px-8 pb-8">
          <div className="grid grid-cols-1 xl:grid-cols-3 gap-6 lg:gap-8">
            {/* Left Column - Network Cards */}
            <div className="xl:col-span-2">
              <div className="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-6">
                <h3 className="text-lg font-semibold mb-6 text-gray-900 dark:text-gray-100">Packages</h3>
                <NetworkCards onAddToCart={() => {}} products={filteredProducts} />
              </div>
            </div>

            {/* Right Column - Recent Orders */}
            <div className="xl:col-span-1">
              <div className="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-6">
                <h3 className="text-lg font-semibold mb-6 text-gray-900 dark:text-gray-100">Last 10 Orders</h3>
                <div className="space-y-3">
                  {orders && orders.length > 0 ? orders.slice(0, 10).map((order, index) => (
                    <div key={order.id} className="flex items-center justify-between p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                      <div className="flex items-center space-x-3">
                        <div className="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                          <span className="text-white text-xs font-bold">📦</span>
                        </div>
                        <div>
                          <p className="font-medium text-sm">ORDER-{order.id}</p>
                          <p className="text-xs text-gray-500">{new Date().toLocaleDateString()}</p>
                        </div>
                      </div>
                      <div className="text-right">
                        <p className="font-bold text-sm">{order.status}</p>
                      </div>
                    </div>
                  )) : (
                    <div className="text-center py-8">
                      <p className="text-gray-500 dark:text-gray-400">No recent orders</p>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Floating Cart Button */}
        {cartCount > 0 && (
          <div className="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-50">
            <button
              onClick={() => router.visit('/cart')}
              className="relative bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white rounded-full p-4 shadow-2xl transform hover:scale-110 transition-all duration-300 animate-bounce"
            >
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.5 6M7 13l-1.5 6m0 0h9M17 21a2 2 0 100-4 2 2 0 000 4zM9 21a2 2 0 100-4 2 2 0 000 4z" />
              </svg>
              <span className="absolute -top-2 -right-2 bg-yellow-400 text-black text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center animate-pulse">
                {cartCount}
              </span>
            </button>
          </div>
        )}
      </div>
    </DashboardLayout>
  );
}
