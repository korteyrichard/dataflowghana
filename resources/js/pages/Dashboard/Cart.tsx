import DashboardLayout from '../../layouts/DashboardLayout';
import { Head, usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import React from 'react';
import { PageProps } from '@/types';

interface CartProduct {
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

// Fix: Add index signature to make it compatible with PageProps constraint
interface CartPageProps extends Record<string, unknown> {
  cartItems: CartProduct[];
}

export default function Cart() {
  const { cartItems, auth } = usePage<PageProps<CartPageProps>>().props;

  const handleRemove = (cartId: number) => {
    router.delete(route('remove.from.cart', cartId));
  };

  const total = cartItems.reduce((sum, item) => sum + Number(item.product?.price || 0), 0);

  return (
    <DashboardLayout
      user={auth.user}
      header={
        <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Cart
        </h2>
      }
    >
      <Head title="Cart" />
      
      <div className="min-h-screen bg-gray-50 dark:bg-gray-900 py-4 sm:py-8 lg:py-12">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-xl overflow-hidden">
            {/* Header */}
            <div className="bg-gradient-to-r from-indigo-500 to-blue-600 px-4 sm:px-6 lg:px-8 py-6">
              <h3 className="text-2xl sm:text-3xl font-bold text-white flex items-center gap-3">
                <Icon name="ShoppingCart" className="w-7 h-7 sm:w-8 sm:h-8" />
                Your Cart
                {cartItems.length > 0 && (
                  <span className="bg-white/20 text-white text-sm px-2 py-1 rounded-full">
                    {cartItems.length} {cartItems.length === 1 ? 'item' : 'items'}
                  </span>
                )}
              </h3>
            </div>

            <div className="p-4 sm:p-6 lg:p-8">
              {cartItems.length === 0 ? (
                <div className="text-center py-16">
                  <Icon name="ShoppingCart" className="w-16 h-16 sm:w-20 sm:h-20 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                  <h4 className="text-xl sm:text-2xl font-semibold text-gray-600 dark:text-gray-400 mb-2">
                    Your cart is empty
                  </h4>
                  <p className="text-gray-500 dark:text-gray-500 mb-6">
                    Add some products to get started
                  </p>
                  <Button 
                    onClick={() => router.visit('/dashboard')}
                    className="bg-gradient-to-r from-indigo-500 to-blue-500 hover:from-indigo-600 hover:to-blue-600 text-white px-6 py-3 rounded-xl shadow-lg transition-all duration-200"
                  >
                    Browse Products
                  </Button>
                </div>
              ) : (
                <div className="space-y-4 sm:space-y-6">
                  {cartItems.map((item) => (
                    <div 
                      key={item.id} 
                      className="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 sm:p-6 shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow duration-200"
                    >
                      {/* Mobile Layout */}
                      <div className="sm:hidden space-y-4">
                        <div className="flex justify-between items-start">
                          <div className="flex-1">
                            <h4 className="font-semibold text-lg text-gray-800 dark:text-gray-100 mb-2">
                              {item.product?.name}
                            </h4>
                            <div className="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                              <div className="flex items-center gap-2">
                                <Icon name="Database" className="w-4 h-4" />
                                <span>{item.quantity}</span>
                              </div>
                              <div className="flex items-center gap-2">
                                <Icon name="Wifi" className="w-4 h-4" />
                                <span>{item.product?.network}</span>
                              </div>
                              <div className="flex items-center gap-2">
                                <Icon name="Clock" className="w-4 h-4" />
                                <span>{item.product?.expiry}</span>
                              </div>
                              <div className="flex items-center gap-2">
                                <Icon name="User" className="w-4 h-4" />
                                <span>{item.beneficiary_number}</span>
                              </div>
                            </div>
                          </div>
                          <Button 
                            variant="destructive" 
                            size="sm" 
                            onClick={() => handleRemove(item.id)}
                            className="ml-2"
                          >
                            <Icon name="Trash2" className="w-4 h-4" />
                          </Button>
                        </div>
                        
                        <div className="flex justify-between items-center pt-2 border-t border-gray-200 dark:border-gray-600">
                          <div className="flex items-center gap-2">
                            <span className="text-sm text-gray-600 dark:text-gray-400">Data:</span>
                            <span className="font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-1 rounded-lg">
                              {item.quantity}GB
                            </span>
                          </div>
                          <div className="text-right">
                            <div className="text-lg font-bold text-gray-900 dark:text-gray-100">
                              GHS {item.product?.price || 0}
                            </div>
                          </div>
                        </div>
                      </div>

                      {/* Desktop Layout */}
                      <div className="hidden sm:flex items-center justify-between">
                        <div className="flex-1">
                          <h4 className="font-semibold text-lg text-gray-800 dark:text-gray-100 mb-2">
                            {item.product?.name}
                          </h4>
                          <div className="flex flex-wrap gap-4 text-sm text-gray-600 dark:text-gray-400">
                            <div className="flex items-center gap-1">
                              <Icon name="Database" className="w-4 h-4" />
                              <span>{item.quantity}GB</span>
                            </div>
                            <div className="flex items-center gap-1">
                              <Icon name="Wifi" className="w-4 h-4" />
                              <span>{item.product?.network}</span>
                            </div>
                            <div className="flex items-center gap-1">
                              <Icon name="Clock" className="w-4 h-4" />
                              <span>{item.product?.expiry}</span>
                            </div>
                            <div className="flex items-center gap-1">
                              <Icon name="User" className="w-4 h-4" />
                              <span>{item.beneficiary_number}</span>
                            </div>
                          </div>
                        </div>
                        
                        <div className="flex items-center gap-6">
                          <div className="text-center">
                            <div className="text-sm text-gray-500 dark:text-gray-400 mb-1">Data Size</div>
                            <div className="font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-3 py-1 rounded-lg">
                              {item.quantity}GB
                            </div>
                          </div>
                          
                          <div className="text-right">
                            <div className="text-lg font-bold text-gray-900 dark:text-gray-100">
                              GHS {item.product?.price || 0}
                            </div>
                          </div>
                          
                          <Button 
                            variant="destructive" 
                            size="sm" 
                            onClick={() => handleRemove(item.id)}
                            className="ml-2"
                          >
                            <Icon name="Trash2" className="w-4 h-4 mr-1" />
                            Remove
                          </Button>
                        </div>
                      </div>
                    </div>
                  ))}
                  
                  {/* Total and Checkout Section */}
                  <div className="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 rounded-xl p-4 sm:p-6 mt-6 border border-gray-200 dark:border-gray-700">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                      <div className="text-center sm:text-left">
                        <div className="text-sm text-gray-600 dark:text-gray-400 mb-1">
                          Total Amount
                        </div>
                        <div className="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-100">
                          GHS {total}
                        </div>
                      </div>
                      
                      <div className="flex flex-col sm:flex-row gap-3">
                        <Button 
                          variant="outline" 
                          onClick={() => router.visit('/dashboard')}
                          className="w-full sm:w-auto px-6 py-3 rounded-xl border-2 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-200"
                        >
                          <Icon name="Plus" className="w-4 h-4 mr-2" />
                          Add More Items
                        </Button>
                        
                        <Button 
                          onClick={() => router.visit('/checkout')}
                          className="w-full sm:w-auto bg-gradient-to-r from-indigo-500 to-blue-500 hover:from-indigo-600 hover:to-blue-600 text-white font-semibold px-8 py-3 rounded-xl shadow-lg transition-all duration-200 transform hover:scale-105"
                        >
                          <Icon name="CreditCard" className="w-4 h-4 mr-2" />
                          Proceed to Checkout
                        </Button>
                      </div>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}