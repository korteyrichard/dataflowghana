import React, { useState } from 'react';
import { AdminLayout } from '../../layouts/admin-layout';
import { Head, usePage, router } from '@inertiajs/react';

interface AFAProduct {
  id: number;
  name: string;
  price: number;
  status: string;
}

interface User {
  id: number;
  name: string;
  email: string;
}

interface AFAOrder {
  id: number;
  full_name: string;
  ghana_card_number: string;
  phone: string;
  dob?: string;
  occupation?: string;
  region?: string;
  status: string;
  created_at: string;
  afaproduct: AFAProduct;
  user: User;
}

interface AFAOrdersPageProps {
  afaOrders: AFAOrder[];
  auth: any;
}

export default function AFAOrders() {
  const { afaOrders, auth } = usePage<AFAOrdersPageProps>().props;
  const [expandedOrder, setExpandedOrder] = useState<number | null>(null);
  const [updatingStatus, setUpdatingStatus] = useState<number | null>(null);
  const [selectedOrders, setSelectedOrders] = useState<number[]>([]);
  const [isExporting, setIsExporting] = useState(false);

  const handleStatusChange = async (orderId: number, newStatus: string) => {
    setUpdatingStatus(orderId);
    try {
      await router.put(`/admin/afa-orders/${orderId}/status`, {
        status: newStatus
      });
    } catch (error) {
      console.error('Failed to update status');
    } finally {
      setUpdatingStatus(null);
    }
  };

  const toggleSelectOrder = (orderId: number) => {
    setSelectedOrders(prev => 
      prev.includes(orderId) 
        ? prev.filter(id => id !== orderId)
        : [...prev, orderId]
    );
  };

  const toggleSelectAll = () => {
    setSelectedOrders(
      selectedOrders.length === afaOrders.length 
        ? [] 
        : afaOrders.map(order => order.id)
    );
  };

  const handleExport = async (format: 'csv' | 'excel') => {
    if (selectedOrders.length === 0) {
      alert('Please select at least one order to export');
      return;
    }

    setIsExporting(true);
    try {
      const response = await fetch('/admin/afa-orders/export', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          order_ids: selectedOrders,
          format: format,
        }),
      });

      if (response.ok) {
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `afa_orders_${new Date().toISOString().split('T')[0]}.${format === 'excel' ? 'xlsx' : 'csv'}`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
        setSelectedOrders([]);
      }
    } catch (error) {
      console.error('Export failed:', error);
      alert('Export failed. Please try again.');
    } finally {
      setIsExporting(false);
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'PENDING': return 'bg-yellow-100 text-yellow-800';
      case 'COMPLETED': return 'bg-green-100 text-green-800';
      case 'CANCELLED': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  return (
    <AdminLayout user={auth.user}>
      <Head title="AFA Orders" />
      
      <div className="py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="mb-8">
            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">AFA Orders</h1>
            <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
              Manage all AFA registration orders
            </p>
          </div>

          {selectedOrders.length > 0 && (
            <div className="mb-6 bg-blue-50 dark:bg-blue-900 p-4 rounded-lg flex justify-between items-center">
              <span className="text-sm font-medium text-blue-800 dark:text-blue-100">
                {selectedOrders.length} order(s) selected
              </span>
              <div className="flex gap-2">
                <button
                  onClick={() => handleExport('csv')}
                  disabled={isExporting}
                  className="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 disabled:opacity-50 rounded"
                >
                  Export as CSV
                </button>
                <button
                  onClick={() => handleExport('excel')}
                  disabled={isExporting}
                  className="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 rounded"
                >
                  Export as Excel
                </button>
              </div>
            </div>
          )}

          <div className="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            {afaOrders.length === 0 ? (
              <div className="p-6 text-center text-gray-500 dark:text-gray-400">
                No AFA orders found.
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                  <thead className="bg-gray-50 dark:bg-gray-700">
                    <tr>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        <input
                          type="checkbox"
                          checked={selectedOrders.length === afaOrders.length && afaOrders.length > 0}
                          onChange={toggleSelectAll}
                          className="rounded"
                        />
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Order ID
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Customer
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Ghana Card Number
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Product
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Price
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Status
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Date
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    {afaOrders.map((order) => (
                      <React.Fragment key={order.id}>
                        <tr className="hover:bg-gray-50 dark:hover:bg-gray-700">
                          <td className="px-6 py-4 whitespace-nowrap">
                            <input
                              type="checkbox"
                              checked={selectedOrders.includes(order.id)}
                              onChange={() => toggleSelectOrder(order.id)}
                              className="rounded"
                            />
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                            #{order.id}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            <div>
                              <div className="font-medium">{order.user?.name || 'N/A'}</div>
                            </div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            {order.ghana_card_number || 'N/A'}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            {order.afaproduct?.name || 'N/A'}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            GHS {order.afaproduct?.price || 0}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <select
                              value={order.status}
                              onChange={(e) => handleStatusChange(order.id, e.target.value)}
                              disabled={updatingStatus === order.id}
                              className={`px-2 py-1 text-xs font-semibold rounded-full border-0 ${getStatusColor(order.status)} ${
                                updatingStatus === order.id ? 'opacity-50' : ''
                              }`}
                            >
                              <option value="PENDING">PENDING</option>
                              <option value="COMPLETED">COMPLETED</option>
                              <option value="CANCELLED">CANCELLED</option>
                            </select>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            {new Date(order.created_at).toLocaleDateString()}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm">
                            <button
                              onClick={() => setExpandedOrder(expandedOrder === order.id ? null : order.id)}
                              className="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                            >
                              {expandedOrder === order.id ? 'Hide Details' : 'View Details'}
                            </button>
                          </td>
                        </tr>
                        {expandedOrder === order.id && (
                          <tr>
                            <td colSpan={9} className="px-6 py-4 bg-gray-50 dark:bg-gray-700">
                              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                <div>
                                  <strong className="text-gray-700 dark:text-gray-300">Full Name:</strong>
                                  <p className="text-gray-900 dark:text-gray-100">{order.full_name}</p>
                                </div>
                                <div>
                                  <strong className="text-gray-700 dark:text-gray-300">Ghana Card Number:</strong>
                                  <p className="text-gray-900 dark:text-gray-100">{order.ghana_card_number}</p>
                                </div>
                                <div>
                                  <strong className="text-gray-700 dark:text-gray-300">Phone:</strong>
                                  <p className="text-gray-900 dark:text-gray-100">{order.phone}</p>
                                </div>
                                {order.dob && (
                                  <div>
                                    <strong className="text-gray-700 dark:text-gray-300">Date of Birth:</strong>
                                    <p className="text-gray-900 dark:text-gray-100">{order.dob}</p>
                                  </div>
                                )}
                                {order.occupation && (
                                  <div>
                                    <strong className="text-gray-700 dark:text-gray-300">Occupation:</strong>
                                    <p className="text-gray-900 dark:text-gray-100">{order.occupation}</p>
                                  </div>
                                )}
                                {order.region && (
                                  <div>
                                    <strong className="text-gray-700 dark:text-gray-300">Region:</strong>
                                    <p className="text-gray-900 dark:text-gray-100">{order.region}</p>
                                  </div>
                                )}
                                <div>
                                  <strong className="text-gray-700 dark:text-gray-300">Created:</strong>
                                  <p className="text-gray-900 dark:text-gray-100">{new Date(order.created_at).toLocaleString()}</p>
                                </div>
                                <div>
                                  <strong className="text-gray-700 dark:text-gray-300">User ID:</strong>
                                  <p className="text-gray-900 dark:text-gray-100">#{order.user?.id || 'N/A'}</p>
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
            )}
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}