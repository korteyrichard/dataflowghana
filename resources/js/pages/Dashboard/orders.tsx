import React, { useState } from 'react';
import DashboardLayout from '../../layouts/DashboardLayout';
import { Head, usePage, Link, router } from '@inertiajs/react';

interface Product {
  id: number;
  name: string;
  price: number;
  size?: string;
  pivot: {
    quantity: number;
    price: number;
    beneficiary_number?: string;
  };
}

interface Order {
  id: number;
  total: number;
  status: string;
  created_at: string;
  network?: string;
  beneficiary_number?: string;
  products: Product[];
}

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

interface OrdersPageProps {
  orders: {
    data: Order[];
    current_page: number;
    total: number;
    per_page: number;
    last_page: number;
    links: PaginationLink[];
  };
  auth: any;
  [key: string]: any;
}

export default function OrdersPage() {
  const { orders, auth } = usePage<OrdersPageProps>().props;
  const [expandedOrder, setExpandedOrder] = useState<number | null>(null);
  
  const params = new URLSearchParams(window.location.search);
  const [networkFilter, setNetworkFilter] = useState(params.get('network') || '');
  const [statusFilter, setStatusFilter] = useState(params.get('status') || '');
  const [beneficiaryFilter, setBeneficiaryFilter] = useState(params.get('beneficiary') || '');
  const [orderIdFilter, setOrderIdFilter] = useState(params.get('order_id') || '');

  const ordersList = orders.data || [];

  const networks = Array.from(new Set(ordersList.map(o => o.network).filter(Boolean)));
  const statuses = Array.from(new Set(ordersList.map(o => o.status).filter(Boolean)));

  const handleFilterChange = (filterType: string, value: string) => {
    const params = new URLSearchParams(window.location.search);
    
    if (value) {
      params.set(filterType, value);
    } else {
      params.delete(filterType);
    }
    
    params.set('page', '1');
    
    router.get(route('dashboard.orders'), Object.fromEntries(params));
  };

  const handleExpand = (orderId: number) => {
    setExpandedOrder(expandedOrder === orderId ? null : orderId);
  };

  const getNetworkBadgeColor = (network?: string) => {
    if (!network) return 'bg-gray-200 text-gray-700';
    if (network.toLowerCase() === 'telecel') return 'bg-red-200 text-red-700';
    if (network.toLowerCase() === 'mtn') return 'bg-yellow-200 text-yellow-700';
    if (network.toLowerCase().includes('bigtime') || network.toLowerCase().includes('ishare') || network.toLowerCase().includes('at data') || network.toLowerCase().includes('at (big')) return 'bg-blue-200 text-blue-700';
    return 'bg-purple-200 text-purple-700';
  };

  const getStatusBadgeColor = (status: string) => {
    switch (status.toLowerCase()) {
      case 'completed':
        return 'bg-green-200 text-green-700';
      case 'pending':
        return 'bg-orange-200 text-orange-700';
      case 'failed':
        return 'bg-red-200 text-red-700';
      case 'processing':
        return 'bg-blue-200 text-blue-700';
      default:
        return 'bg-gray-200 text-gray-700';
    }
  };

  return (
    <DashboardLayout 
      user={auth?.user} 
      header={
        <h2 className="font-bold text-2xl text-gray-800 dark:text-gray-200 leading-tight flex items-center gap-2">
          <span className="inline-block w-2 h-6 bg-blue-600 rounded mr-2"></span>My Orders
        </h2>
      }
    >
      <Head title="Orders" />
      
      <div className="py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            
            {/* Filter Section */}
            <div className="p-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Network:</label>
                  <select
                    className="border border-gray-300 dark:border-gray-600 rounded px-3 py-2 text-sm bg-white dark:bg-gray-800 w-full"
                    value={networkFilter}
                    onChange={e => {
                      setNetworkFilter(e.target.value);
                      handleFilterChange('network', e.target.value);
                    }}
                  >
                    <option value="">All Networks</option>
                    {networks.map(network => (
                      <option key={network} value={network}>{network}</option>
                    ))}
                  </select>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status:</label>
                  <select
                    className="border border-gray-300 dark:border-gray-600 rounded px-3 py-2 text-sm bg-white dark:bg-gray-800 w-full"
                    value={statusFilter}
                    onChange={e => {
                      setStatusFilter(e.target.value);
                      handleFilterChange('status', e.target.value);
                    }}
                  >
                    <option value="">All Statuses</option>
                    {statuses.map(status => (
                      <option key={status} value={status}>{status.charAt(0).toUpperCase() + status.slice(1)}</option>
                    ))}
                  </select>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Beneficiary Number:</label>
                  <input
                    type="text"
                    placeholder="Search by phone number"
                    className="border border-gray-300 dark:border-gray-600 rounded px-3 py-2 text-sm bg-white dark:bg-gray-800 w-full"
                    value={beneficiaryFilter}
                    onChange={e => {
                      setBeneficiaryFilter(e.target.value);
                      handleFilterChange('beneficiary', e.target.value);
                    }}
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Order ID:</label>
                  <input
                    type="text"
                    placeholder="Search by order ID"
                    className="border border-gray-300 dark:border-gray-600 rounded px-3 py-2 text-sm bg-white dark:bg-gray-800 w-full"
                    value={orderIdFilter}
                    onChange={e => {
                      setOrderIdFilter(e.target.value);
                      handleFilterChange('order_id', e.target.value);
                    }}
                  />
                </div>
              </div>
            </div>

            {ordersList.length === 0 ? (
              <div className="text-center py-12">
                <div className="text-gray-400 dark:text-gray-500 text-lg mb-2">No orders found</div>
                <div className="text-gray-500 dark:text-gray-400 text-sm">Try adjusting your filters or place your first order</div>
              </div>
            ) : (
              <>
                {/* Desktop Table */}
                <div className="overflow-x-auto hidden lg:block">
                  <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead>
                      <tr className="bg-gray-50 dark:bg-gray-800">
                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Order ID</th>
                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date & Time</th>
                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Network</th>
                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Beneficiary</th>
                        <th className="px-6 py-4 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total</th>
                        <th className="px-6 py-4 text-center text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Size</th>
                      </tr>
                    </thead>
                    <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
                      {ordersList.map(order => (
                        <React.Fragment key={order.id}>
                          <tr className="hover:bg-blue-50 dark:hover:bg-gray-800 transition-all duration-200 cursor-pointer" onClick={() => handleExpand(order.id)}>
                            <td className="px-6 py-4 whitespace-nowrap">
                              <div className="text-sm font-bold text-gray-900 dark:text-gray-100">#{order.id}</div>
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap">
                              <div className="text-sm text-gray-700 dark:text-gray-200">
                                {new Date(order.created_at).toLocaleDateString()}
                              </div>
                              <div className="text-xs text-gray-500 dark:text-gray-400">
                                {new Date(order.created_at).toLocaleTimeString()}
                              </div>
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap">
                              <span className={`px-3 py-1 rounded-full text-xs font-bold ${getNetworkBadgeColor(order.network)}`}>
                                {order.network || 'N/A'}
                              </span>
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap">
                              <span className={`px-3 py-1 rounded-full text-xs font-bold ${getStatusBadgeColor(order.status)}`}>
                                {order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                              </span>
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap">
                              <div className="text-sm text-gray-700 dark:text-gray-200">
                                {order.products[0]?.pivot?.beneficiary_number || order.beneficiary_number || '-'}
                              </div>
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap text-right">
                              <div className="text-sm font-bold text-gray-900 dark:text-gray-100">
                                GHS {order.total.toLocaleString()}
                              </div>
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap text-center">
                              <div className="text-sm text-gray-700 dark:text-gray-200">
                                {order.products[0]?.size || '-'}
                              </div>
                            </td>
                          </tr>
                          {expandedOrder === order.id && (
                            <tr>
                              <td colSpan={7} className="px-6 py-4 bg-gray-50 dark:bg-gray-800">
                                <div className="space-y-4">
                                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                      <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Order Information</h4>
                                      <div className="space-y-1 text-sm">
                                        <div><span className="text-gray-500 dark:text-gray-400">Order ID:</span> <span className="font-medium text-gray-900 dark:text-gray-100">#{order.id}</span></div>
                                        <div><span className="text-gray-500 dark:text-gray-400">Status:</span> <span className={`px-2 py-1 rounded text-xs font-bold ${getStatusBadgeColor(order.status)}`}>{order.status.charAt(0).toUpperCase() + order.status.slice(1)}</span></div>
                                        <div><span className="text-gray-500 dark:text-gray-400">Network:</span> <span className="font-medium text-gray-900 dark:text-gray-100">{order.network || 'N/A'}</span></div>
                                        <div><span className="text-gray-500 dark:text-gray-400">Total Amount:</span> <span className="font-bold text-gray-900 dark:text-gray-100">GHS {order.total.toLocaleString()}</span></div>
                                      </div>
                                    </div>
                                    <div>
                                      <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Products Ordered</h4>
                                      <div className="space-y-2">
                                        {order.products.map(product => (
                                          <div key={product.id} className="bg-white dark:bg-gray-900 p-3 rounded-lg border border-gray-200 dark:border-gray-700">
                                            <div className="flex justify-between items-start">
                                              <div>
                                                <div className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                  {product.name} {product.size ? `(${product.size})` : ''}
                                                </div>
                                                <div className="text-xs text-gray-500 dark:text-gray-400">
                                                  Quantity: {product.pivot.quantity}
                                                </div>
                                                {product.pivot.beneficiary_number && (
                                                  <div className="text-xs text-gray-500 dark:text-gray-400">
                                                    Beneficiary: {product.pivot.beneficiary_number}
                                                  </div>
                                                )}
                                              </div>
                                              <div className="text-sm font-bold text-gray-900 dark:text-gray-100">
                                                GHS {product.pivot.price.toLocaleString()}
                                              </div>
                                            </div>
                                          </div>
                                        ))}
                                      </div>
                                    </div>
                                  </div>
                                </div>
                              </td>
                            </tr>
                          )}
                        </React.Fragment>
                      ))}
                    </tbody>
                  </table>
                </div>

                {/* Mobile Table */}
                <div className="lg:hidden overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead>
                      <tr className="bg-gray-50 dark:bg-gray-800">
                        <th className="px-3 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">Order</th>
                        <th className="px-3 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">Network</th>
                        <th className="px-3 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">Status</th>
                        <th className="px-3 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">Beneficiary</th>
                        <th className="px-3 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">Total</th>
                      </tr>
                    </thead>
                    <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
                      {ordersList.map(order => (
                        <React.Fragment key={order.id}>
                          <tr className="hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200 cursor-pointer" onClick={() => handleExpand(order.id)}>
                            <td className="px-3 py-3">
                              <div className="text-sm font-bold text-gray-900 dark:text-gray-100">#{order.id}</div>
                              <div className="text-xs text-gray-500 dark:text-gray-400">
                                {new Date(order.created_at).toLocaleDateString()}
                              </div>
                              <div className="text-xs text-gray-500 dark:text-gray-400">
                                {new Date(order.created_at).toLocaleTimeString()}
                              </div>
                            </td>
                            <td className="px-3 py-3">
                              <span className={`px-2 py-1 rounded-full text-xs font-bold ${getNetworkBadgeColor(order.network)}`}>
                                {order.network || 'N/A'}
                              </span>
                            </td>
                            <td className="px-3 py-3">
                              <span className={`px-2 py-1 rounded-full text-xs font-bold ${getStatusBadgeColor(order.status)}`}>
                                {order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                              </span>
                            </td>
                            <td className="px-3 py-3">
                              <div className="text-xs text-gray-700 dark:text-gray-200">
                                {order.products[0]?.pivot?.beneficiary_number || order.beneficiary_number || '-'}
                              </div>
                            </td>
                            <td className="px-3 py-3 text-right">
                              <div className="text-sm font-bold text-gray-900 dark:text-gray-100">
                                GHS {order.total.toLocaleString()}
                              </div>
                              <div className="text-xs text-gray-500 dark:text-gray-400">
                                {order.products[0]?.size || '-'}
                              </div>
                            </td>
                          </tr>
                          {expandedOrder === order.id && (
                            <tr>
                              <td colSpan={5} className="px-3 py-3 bg-gray-50 dark:bg-gray-800">
                                <div className="text-xs space-y-2">
                                  <div><span className="font-medium">Items:</span> {order.products.length}</div>
                                  <div className="space-y-1">
                                    <div className="font-medium">Products:</div>
                                    {order.products.map(product => (
                                      <div key={product.id} className="ml-2 text-gray-600 dark:text-gray-400">
                                        • {product.name} {product.size ? `(${product.size})` : ''} - GHS {product.pivot.price}
                                        {product.pivot.beneficiary_number && (
                                          <div className="ml-2 text-xs">Beneficiary: {product.pivot.beneficiary_number}</div>
                                        )}
                                      </div>
                                    ))}
                                  </div>
                                </div>
                              </td>
                            </tr>
                          )}
                        </React.Fragment>
                      ))}
                    </tbody>
                  </table>
                </div>

                {/* Pagination */}
                {orders.last_page > 1 && (
                  <div className="px-6 py-4 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 flex items-center justify-between flex-wrap gap-4">
                    <div className="text-sm text-gray-600 dark:text-gray-400">
                      Showing <span className="font-medium">{(orders.current_page - 1) * orders.per_page + 1}</span> to <span className="font-medium">{Math.min(orders.current_page * orders.per_page, orders.total)}</span> of <span className="font-medium">{orders.total}</span> orders
                    </div>
                    <div className="flex gap-2 flex-wrap">
                      {orders.links.map((link, index) => (
                        link.url ? (
                          <Link
                            key={index}
                            href={link.url}
                            className={`px-3 py-2 text-sm font-medium rounded-lg transition-all ${
                              link.active
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
                            }`}
                          >
                            {link.label.replace(/&laquo;/, '«').replace(/&raquo;/, '»')}
                          </Link>
                        ) : (
                          <span
                            key={index}
                            className="px-3 py-2 text-sm font-medium rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-600 cursor-not-allowed"
                          >
                            {link.label.replace(/&laquo;/, '«').replace(/&raquo;/, '»')}
                          </span>
                        )
                      ))}
                    </div>
                  </div>
                )}
              </>
            )}
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}
