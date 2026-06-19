import React from 'react';
import DashboardLayout from '../../layouts/DashboardLayout';
import { Head, usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { PageProps } from '@/types';

interface BulkOrder {
  phone: string;
  bundle_size: string;
  price: number;
  product_variant_id?: number;
}

interface BulkOrderPreviewProps extends PageProps {
  orders: BulkOrder[];
  network: string;
  total: number;
}

export default function BulkOrderPreview() {
  const { orders, network, total, auth } = usePage<BulkOrderPreviewProps>().props;
  const [isProcessing, setIsProcessing] = React.useState(false);

  const handlePlaceOrder = () => {
    setIsProcessing(true);
    
    router.post(route('bulk.orders.process'), {
      orders: orders,
      network: network
    }, {
      onFinish: () => setIsProcessing(false),
      onError: (errors) => {
        console.error('Error placing bulk order:', errors);
      }
    });
  };

  return (
    <DashboardLayout
      user={auth.user}
      header={
        <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Bulk Order Preview
        </h2>
      }
    >
      <Head title="Bulk Order Preview" />
      
      <div className="min-h-screen bg-gray-50 dark:bg-gray-900 py-4 sm:py-8 lg:py-12">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-xl overflow-hidden">
            {/* Header */}
            <div className="bg-gradient-to-r from-indigo-500 to-blue-600 px-4 sm:px-6 lg:px-8 py-6">
              <h3 className="text-2xl sm:text-3xl font-bold text-white flex items-center gap-3">
                <Icon name="Package" className="w-7 h-7 sm:w-8 sm:h-8" />
                Bulk Order Preview - {network}
                <span className="bg-white/20 text-white text-sm px-2 py-1 rounded-full">
                  {orders.length} orders
                </span>
              </h3>
            </div>

            <div className="p-4 sm:p-6 lg:p-8">
              {orders.length === 0 ? (
                <div className="text-center py-16">
                  <Icon name="Package" className="w-16 h-16 sm:w-20 sm:h-20 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                  <h4 className="text-xl sm:text-2xl font-semibold text-gray-600 dark:text-gray-400 mb-2">
                    No orders to preview
                  </h4>
                  <p className="text-gray-500 dark:text-gray-500 mb-6">
                    Go back and add some orders to preview
                  </p>
                  <Button 
                    onClick={() => router.visit('/dashboard')}
                    className="bg-gradient-to-r from-indigo-500 to-blue-500 hover:from-indigo-600 hover:to-blue-600 text-white px-6 py-3 rounded-xl shadow-lg transition-all duration-200"
                  >
                    Back to Dashboard
                  </Button>
                </div>
              ) : (
                <div className="space-y-4 sm:space-y-6">
                  {/* Orders List */}
                  <div className="overflow-x-auto">
                    <table className="w-full">
                      <thead>
                        <tr className="border-b border-gray-200 dark:border-gray-700">
                          <th className="text-left py-3 px-4 font-semibold text-gray-900 dark:text-gray-100">
                            #
                          </th>
                          <th className="text-left py-3 px-4 font-semibold text-gray-900 dark:text-gray-100">
                            Phone Number
                          </th>
                          <th className="text-left py-3 px-4 font-semibold text-gray-900 dark:text-gray-100">
                            Data Bundle
                          </th>
                          <th className="text-left py-3 px-4 font-semibold text-gray-900 dark:text-gray-100">
                            Network
                          </th>
                          <th className="text-right py-3 px-4 font-semibold text-gray-900 dark:text-gray-100">
                            Price
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        {orders.map((order, index) => (
                          <tr 
                            key={index}
                            className="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150"
                          >
                            <td className="py-3 px-4 text-gray-900 dark:text-gray-100">
                              {index + 1}
                            </td>
                            <td className="py-3 px-4">
                              <div className="flex items-center gap-2">
                                <Icon name="Phone" className="w-4 h-4 text-gray-500" />
                                <span className="text-gray-900 dark:text-gray-100 font-medium">
                                  {order.phone}
                                </span>
                              </div>
                            </td>
                            <td className="py-3 px-4">
                              <div className="flex items-center gap-2">
                                <Icon name="Database" className="w-4 h-4 text-gray-500" />
                                <span className="text-indigo-600 dark:text-indigo-400 font-semibold bg-indigo-50 dark:bg-indigo-900/30 px-2 py-1 rounded-lg">
                                  {order.bundle_size}GB
                                </span>
                              </div>
                            </td>
                            <td className="py-3 px-4">
                              <div className="flex items-center gap-2">
                                <Icon name="Wifi" className="w-4 h-4 text-gray-500" />
                                <span className="text-gray-900 dark:text-gray-100">
                                  {network}
                                </span>
                              </div>
                            </td>
                            <td className="py-3 px-4 text-right">
                              <div className="font-bold text-gray-900 dark:text-gray-100">
                                GHS {order.price}
                              </div>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                  
                  {/* Total and Actions Section */}
                  <div className="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 rounded-xl p-4 sm:p-6 mt-6 border border-gray-200 dark:border-gray-700">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                      <div className="text-center sm:text-left">
                        <div className="text-sm text-gray-600 dark:text-gray-400 mb-1">
                          Total Amount ({orders.length} orders)
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
                          <Icon name="ArrowLeft" className="w-4 h-4 mr-2" />
                          Back to Dashboard
                        </Button>
                        
                        <Button 
                          onClick={handlePlaceOrder}
                          disabled={isProcessing}
                          className="w-full sm:w-auto bg-gradient-to-r from-indigo-500 to-blue-500 hover:from-indigo-600 hover:to-blue-600 text-white font-semibold px-8 py-3 rounded-xl shadow-lg transition-all duration-200 transform hover:scale-105 disabled:opacity-50 disabled:transform-none"
                        >
                          {isProcessing ? (
                            <>
                              <Icon name="Loader2" className="w-4 h-4 mr-2 animate-spin" />
                              Processing...
                            </>
                          ) : (
                            <>
                              <Icon name="CreditCard" className="w-4 h-4 mr-2" />
                              Place Bulk Order
                            </>
                          )}
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