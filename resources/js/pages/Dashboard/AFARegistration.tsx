import React, { useState } from 'react';
import DashboardLayout from '../../layouts/DashboardLayout';
import { Head, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';

interface AFAProduct {
  id: number;
  name: string;
  price: number;
  status: string;
}

interface AFAOrder {
  id: number;
  full_name: string;
  email: string;
  phone: string;
  dob?: string;
  occupation?: string;
  region?: string;
  status: string;
  created_at: string;
  afaproduct: AFAProduct;
}

interface AFARegistrationProps extends PageProps {
  afaProducts: AFAProduct[];
  afaOrders: AFAOrder[];
}

export default function AfaRegistration({ auth, afaProducts, afaOrders }: AFARegistrationProps) {
  const [expandedOrder, setExpandedOrder] = useState<number | null>(null);
  const [selectedProduct, setSelectedProduct] = useState<AFAProduct | null>(null);
  
  const { data, setData, post, processing, errors, reset } = useForm({
    afa_product_id: '',
    full_name: '',
    email: auth.user.email,
    phone: auth.user.phone || '',
    dob: '',
    occupation: '',
    region: ''
  });

  const regions = [
    'Greater Accra', 'Ashanti', 'Western', 'Central', 'Eastern', 
    'Northern', 'Upper East', 'Upper West', 'Volta', 'Brong Ahafo',
    'Western North', 'Ahafo', 'Bono East', 'Oti', 'North East', 'Savannah'
  ];



  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setData(name as any, value);
    
    if (name === 'afa_product_id') {
      const product = afaProducts.find(p => p.id.toString() === value);
      setSelectedProduct(product || null);
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!selectedProduct) {
      return;
    }

    if (auth.user.wallet_balance < selectedProduct.price) {
      return;
    }

    post('/dashboard/afa-registration', {
      onSuccess: () => {
        reset();
        setSelectedProduct(null);
      }
    });
  };



  return (
    <DashboardLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">AFA Registration</h2>}
    >
      <Head title="AFA Registration" />
      
      <div className="py-8 max-w-4xl mx-auto px-4">
        {/* Wallet Balance Card */}
        <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-1">
                Current Wallet Balance
              </h3>
              <p className="text-2xl font-bold text-green-500">
                GHS {auth.user.wallet_balance}
              </p>
            </div>
            <div className="text-sm text-gray-600 dark:text-gray-400">
              {selectedProduct && (
                <div className="text-right">
                  <p>Selected Product Price: <span className="font-semibold">GHS {selectedProduct.price}</span></p>
                  <p className={`font-semibold ${
                    auth.user.wallet_balance >= selectedProduct.price 
                      ? 'text-green-600' 
                      : 'text-red-600'
                  }`}>
                    {auth.user.wallet_balance >= selectedProduct.price 
                      ? '✓ Sufficient Balance' 
                      : '✗ Insufficient Balance'
                    }
                  </p>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Registration Form */}
        <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
          <h3 className="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-6">
            AFA Registration Form
          </h3>

          {Object.keys(errors).length > 0 && (
            <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
              {Object.values(errors).map((error, index) => (
                <div key={index}>{error}</div>
              ))}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-6">
            {/* Product Selection */}
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Select AFA Product *
              </label>
              <select
                name="afa_product_id"
                value={data.afa_product_id}
                onChange={handleInputChange}
                required
                className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
              >
                <option value="">Choose an AFA product...</option>
                {afaProducts.map(product => (
                  <option key={product.id} value={product.id}>
                    {product.name} - GHS {product.price}
                  </option>
                ))}
              </select>
            </div>

            {/* Personal Information */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Full Name *
                </label>
                <input
                  type="text"
                  name="full_name"
                  value={data.full_name}
                  onChange={handleInputChange}
                  required
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                  placeholder="Enter your full name"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Email Address *
                </label>
                <input
                  type="email"
                  name="email"
                  value={data.email}
                  onChange={handleInputChange}
                  required
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                  placeholder="Enter your email address"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Phone Number *
                </label>
                <input
                  type="tel"
                  name="phone"
                  value={data.phone}
                  onChange={handleInputChange}
                  required
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                  placeholder="Enter your phone number"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Date of Birth *
                </label>
                <input
                  type="date"
                  name="dob"
                  value={data.dob}
                  onChange={handleInputChange}
                  required
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Occupation *
                </label>
                <input
                  type="text"
                  name="occupation"
                  value={data.occupation}
                  onChange={handleInputChange}
                  required
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                  placeholder="Enter your occupation"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Region *
                </label>
                <select
                  name="region"
                  value={data.region}
                  onChange={handleInputChange}
                  required
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                >
                  <option value="">Select your region...</option>
                  {regions.map(region => (
                    <option key={region} value={region}>{region}</option>
                  ))}
                </select>
              </div>
            </div>

            {/* Submit Button */}
            <div className="flex items-center justify-between pt-6">
              <div className="text-sm text-gray-600 dark:text-gray-400">
                * Required fields
              </div>
              <button
                type="submit"
                disabled={processing || !selectedProduct || auth.user.wallet_balance < (selectedProduct?.price || 0)}
                className="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-semibold py-2 px-6 rounded-md transition duration-200"
              >
                {processing ? 'Submitting...' : `Register for AFA ${selectedProduct ? `(GHS ${selectedProduct.price})` : ''}`}
              </button>
            </div>
          </form>
        </div>

        {/* AFA Orders List */}
        <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mt-6">
          <h3 className="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-6">
            My AFA Orders
          </h3>
          
          {afaOrders.length === 0 ? (
            <p className="text-gray-600 dark:text-gray-400 text-center py-4">
              No AFA orders found.
            </p>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead className="bg-gray-50 dark:bg-gray-700">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                      Order ID
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                      Product
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                      Status
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                      Date
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                      Action
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                  {afaOrders.map((order) => (
                    <React.Fragment key={order.id}>
                      <tr className="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" onClick={() => setExpandedOrder(expandedOrder === order.id ? null : order.id)}>
                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                          #{order.id}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                          {order.afaproduct?.name || 'N/A'}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                            order.status === 'PENDING' ? 'bg-yellow-100 text-yellow-800' :
                            order.status === 'COMPLETED' ? 'bg-green-100 text-green-800' :
                            'bg-red-100 text-red-800'
                          }`}>
                            {order.status}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                          {new Date(order.created_at).toLocaleDateString()}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-blue-600 hover:text-blue-800">
                          {expandedOrder === order.id ? 'Hide Details' : 'View Details'}
                        </td>
                      </tr>
                      {expandedOrder === order.id && (
                        <tr>
                          <td colSpan={5} className="px-6 py-4 bg-gray-50 dark:bg-gray-700">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                              <div>
                                <strong className="text-gray-700 dark:text-gray-300">Full Name:</strong>
                                <p className="text-gray-900 dark:text-gray-100">{order.full_name}</p>
                              </div>
                              <div>
                                <strong className="text-gray-700 dark:text-gray-300">Email:</strong>
                                <p className="text-gray-900 dark:text-gray-100">{order.email}</p>
                              </div>
                              <div>
                                <strong className="text-gray-700 dark:text-gray-300">Phone:</strong>
                                <p className="text-gray-900 dark:text-gray-100">{order.phone}</p>
                              </div>
                              <div>
                                <strong className="text-gray-700 dark:text-gray-300">Product Price:</strong>
                                <p className="text-gray-900 dark:text-gray-100">GHS {order.afaproduct?.price || 0}</p>
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
    </DashboardLayout>
  );
}