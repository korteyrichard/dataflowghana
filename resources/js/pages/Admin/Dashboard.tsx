import React from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/admin-layout';
import { PageProps, User } from '@/types';
import { route } from 'ziggy-js';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';

interface Product {
  id: number;
  name: string;
  network: string;
  amount: number;
}

interface Order {
  id: number;
  user: User;
  total_amount: number;
  status: string;
}

interface SalesData {
  date: string;
  fullDate: string;
  sales: number;
}

interface AdminDashboardProps extends PageProps {
  usersCount: number;
  productsCount: number;
  ordersCount: number;
  todayUsersCount: number;
  todayOrdersCount: number;
  past30DaysSales: SalesData[];
  jaybartOrderPusherEnabled: boolean;
  codecraftOrderPusherEnabled: boolean;
  dataeasyOrderPusherEnabled: boolean;
  dataSourceOrderPusherEnabled: boolean;
  codecraftMtnOrderPusherEnabled: boolean;
  dataMastaOrderPusherEnabled: boolean;
  rexamartOrderPusherEnabled: boolean;
  codeCraftTelecelOrderPusherEnabled: boolean;
  bundlePortalMtnApiEnabled: boolean;
  bundlePortalApiEnabled: boolean;
  dataworksOrderPusherEnabled: boolean;
  maxDataOrderPusherEnabled: boolean;
}

const StatCard = ({ title, value }: { title: string; value: number | string }) => (
  <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md hover:shadow-lg transition-shadow">
    <h3 className="text-sm font-medium text-gray-500 dark:text-gray-300">{title}</h3>
    <p className="text-3xl font-bold text-gray-900 dark:text-white mt-2">{value}</p>
  </div>
);

const CustomTooltip = ({ active, payload }: any) => {
  if (active && payload && payload[0]) {
    const data = payload[0].payload;
    return (
      <div className="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700">
        <p className="text-sm font-medium text-gray-900 dark:text-white">{data.date}</p>
        <p className="text-sm font-bold text-blue-600 dark:text-blue-400">
          GHC {data.sales.toLocaleString()}
        </p>
      </div>
    );
  }
  return null;
};

const AdminDashboard: React.FC<AdminDashboardProps> = ({
  usersCount,
  productsCount,
  ordersCount,
  todayUsersCount,
  todayOrdersCount,
  past30DaysSales,
  jaybartOrderPusherEnabled,
  codecraftOrderPusherEnabled,
  dataeasyOrderPusherEnabled,
  dataSourceOrderPusherEnabled,
  codecraftMtnOrderPusherEnabled,
  dataMastaOrderPusherEnabled,
  rexamartOrderPusherEnabled,
  codeCraftTelecelOrderPusherEnabled,
  bundlePortalMtnApiEnabled,
  bundlePortalApiEnabled,
  dataworksOrderPusherEnabled,
  maxDataOrderPusherEnabled,
}) => {
  const { auth } = usePage<AdminDashboardProps>().props;

  const toggleJaybartOrderPusher = () => {
    router.post('/admin/toggle-jaybart-order-pusher', {
      enabled: !jaybartOrderPusherEnabled
    });
  };

  const toggleCodecraftOrderPusher = () => {
    router.post('/admin/toggle-codecraft-order-pusher', {
      enabled: !codecraftOrderPusherEnabled
    });
  };

  const toggleDataeasyOrderPusher = () => {
    router.post('/admin/toggle-dataeasy-order-pusher', {
      enabled: !dataeasyOrderPusherEnabled
    });
  };

  const toggleDataSourceOrderPusher = () => {
    router.post('/admin/toggle-datasource-order-pusher', {
      enabled: !dataSourceOrderPusherEnabled
    });
  };

  const toggleDatamastaOrderPusher = () => {
    router.post('/admin/toggle-datamasta-order-pusher', {
      enabled: !dataMastaOrderPusherEnabled
    });
  };

  const toggleRexamartOrderPusher = () => {
    router.post('/admin/toggle-rexamart-order-pusher', {
      enabled: !rexamartOrderPusherEnabled
    });
  };

  const toggleCodecraftMtnOrderPusher = () => {
    router.post('/admin/toggle-codecraft-mtn-order-pusher', {
      enabled: !codecraftMtnOrderPusherEnabled
    });
  };

  const toggleCodecraftTelecelOrderPusher = () => {
    router.post('/admin/toggle-codecraft-telecel-order-pusher', {
      enabled: !codeCraftTelecelOrderPusherEnabled
    });
  };

  const toggleBundlePortalMtnApi = () => {
    router.post(route('admin.bundleportal-mtn-api.toggle'), {
      enabled: !bundlePortalMtnApiEnabled
    });
  };

  const toggleBundlePortalApi = () => {
    router.post(route('admin.bundleportal-api.toggle'), {
      enabled: !bundlePortalApiEnabled
    });
  };

  const toggleDataworksOrderPusher = () => {
    router.post('/admin/dataworks-order-pusher/toggle', {
      enabled: !dataworksOrderPusherEnabled
    });
  };

  const toggleMaxDataOrderPusher = () => {
    router.post(route('admin.maxdata-order-pusher.toggle'), {
      enabled: !maxDataOrderPusherEnabled
    });
  };

  const totalSales = past30DaysSales.reduce((sum, day) => sum + day.sales, 0);

  return (
    <AdminLayout
      user={auth?.user}
      header={<h2 className="text-3xl font-bold text-gray-800 dark:text-white">Admin Dashboard</h2>}
    >
      <Head title="Admin Dashboard" />

      <div className="p-6 space-y-10">
        {/* Summary Section */}
        <section>
          <h3 className="text-xl font-semibold text-gray-700 dark:text-gray-200 mb-4">Overall Summary</h3>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <StatCard title="Total Users" value={usersCount} />
            <StatCard title="Total Products" value={productsCount} />
            <StatCard title="Total Orders" value={ordersCount} />
          </div>
        </section>

        {/* Today Section */}
        <section>
          <h3 className="text-xl font-semibold text-gray-700 dark:text-gray-200 mb-4">Today's Statistics</h3>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <StatCard title="New Users Today" value={todayUsersCount} />
            <StatCard title="Orders Today" value={todayOrdersCount} />
          </div>
        </section>

        {/* Sales Chart Section */}
        <section>
          <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
              <div>
                <h3 className="text-xl font-semibold text-gray-700 dark:text-gray-200">Last 30 Days Sales</h3>
                <p className="text-gray-500 dark:text-gray-400 text-sm mt-1">
                  Total: <span className="font-bold text-blue-600 dark:text-blue-400">GHC {totalSales.toLocaleString()}</span>
                </p>
              </div>
            </div>
            <ResponsiveContainer width="100%" height={400}>
              <BarChart data={past30DaysSales} margin={{ top: 20, right: 30, left: 0, bottom: 20 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                <XAxis
                  dataKey="date"
                  tick={{ fill: '#6b7280', fontSize: 12 }}
                  axisLine={{ stroke: '#e5e7eb' }}
                />
                <YAxis
                  tick={{ fill: '#6b7280', fontSize: 12 }}
                  axisLine={{ stroke: '#e5e7eb' }}
                />
                <Tooltip content={<CustomTooltip />} cursor={{ fill: 'rgba(59, 130, 246, 0.1)' }} />
                <Bar
                  dataKey="sales"
                  fill="#3b82f6"
                  radius={[8, 8, 0, 0]}
                  isAnimationActive={true}
                />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </section>

        {/* Order Pusher Controls */}
        <section>
          <h3 className="text-xl font-semibold text-gray-700 dark:text-gray-200 mb-4">System Controls</h3>
          <div className="space-y-4">
            {/* Jaybart Order Pusher */}
            <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-lg font-medium text-gray-900 dark:text-white">Jaybart Order Pusher</h4>
                  <p className="text-sm text-gray-500 dark:text-gray-300">
                    {jaybartOrderPusherEnabled ? 'Orders are being pushed to Jaybart API' : 'Jaybart order pushing is disabled'}
                  </p>
                </div>
                <button
                  onClick={toggleJaybartOrderPusher}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                    jaybartOrderPusherEnabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-600'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      jaybartOrderPusherEnabled ? 'translate-x-6' : 'translate-x-1'
                    }`}
                  />
                </button>
              </div>
            </div>

            {/* CodeCraft Order Pusher */}
            <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-lg font-medium text-gray-900 dark:text-white">CodeCraft Order Pusher</h4>
                  <p className="text-sm text-gray-500 dark:text-gray-300">
                    {codecraftOrderPusherEnabled ? 'Orders are being pushed to CodeCraft API' : 'CodeCraft order pushing is disabled'}
                  </p>
                </div>
                <button
                  onClick={toggleCodecraftOrderPusher}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                    codecraftOrderPusherEnabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-600'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      codecraftOrderPusherEnabled ? 'translate-x-6' : 'translate-x-1'
                    }`}
                  />
                </button>
              </div>
            </div>

            {/* DataEasy Order Pusher */}
            <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-lg font-medium text-gray-900 dark:text-white">DataEasy Order Pusher</h4>
                  <p className="text-sm text-gray-500 dark:text-gray-300">
                    {dataeasyOrderPusherEnabled ? 'MTN orders are being pushed to DataEasy API' : 'DataEasy order pushing is disabled'}
                  </p>
                </div>
                <button
                  onClick={toggleDataeasyOrderPusher}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                    dataeasyOrderPusherEnabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-600'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      dataeasyOrderPusherEnabled ? 'translate-x-6' : 'translate-x-1'
                    }`}
                  />
                </button>
              </div>
            </div>

            {/* DataSource Order Pusher */}
            <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-lg font-medium text-gray-900 dark:text-white">DataSource Order Pusher</h4>
                  <p className="text-sm text-gray-500 dark:text-gray-300">
                    {dataSourceOrderPusherEnabled ? 'MTN orders are being pushed to DataSource Order Pusher API' : 'DataSource order pushing is disabled'}
                  </p>
                </div>
                <button
                  onClick={toggleDataSourceOrderPusher}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                    dataSourceOrderPusherEnabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-600'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      dataSourceOrderPusherEnabled ? 'translate-x-6' : 'translate-x-1'
                    }`}
                  />
                </button>
              </div>
            </div>

            {/* DataMasta Order Pusher */}
            <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-lg font-medium text-gray-900 dark:text-white">DataMasta Order Pusher</h4>
                  <p className="text-sm text-gray-500 dark:text-gray-300">
                    {dataMastaOrderPusherEnabled ? 'MTN orders are being pushed to DataMasta API' : 'DataMasta order pushing is disabled'}
                  </p>
                </div>
                <button
                  onClick={toggleDatamastaOrderPusher}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                    dataMastaOrderPusherEnabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-600'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      dataMastaOrderPusherEnabled ? 'translate-x-6' : 'translate-x-1'
                    }`}
                  />
                </button>
              </div>
            </div>

            {/* CodeCraft MTN Order Pusher */}
            <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-lg font-medium text-gray-900 dark:text-white">CodeCraft MTN Order Pusher</h4>
                  <p className="text-sm text-gray-500 dark:text-gray-300">
                    {codecraftMtnOrderPusherEnabled ? 'MTN orders are being pushed to CodeCraft MTN API' : 'CodeCraft MTN order pushing is disabled'}
                  </p>
                </div>
                <button
                  onClick={toggleCodecraftMtnOrderPusher}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                    codecraftMtnOrderPusherEnabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-600'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      codecraftMtnOrderPusherEnabled ? 'translate-x-6' : 'translate-x-1'
                    }`}
                  />
                </button>
              </div>
            </div>

            {/* CodeCraft Telecel Order Pusher */}
            <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-lg font-medium text-gray-900 dark:text-white">CodeCraft Telecel Order Pusher</h4>
                  <p className="text-sm text-gray-500 dark:text-gray-300">
                    {codeCraftTelecelOrderPusherEnabled ? 'Telecel orders are being pushed to CodeCraft API' : 'CodeCraft Telecel order pushing is disabled'}
                  </p>
                </div>
                <button
                  onClick={toggleCodecraftTelecelOrderPusher}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                    codeCraftTelecelOrderPusherEnabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-600'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      codeCraftTelecelOrderPusherEnabled ? 'translate-x-6' : 'translate-x-1'
                    }`}
                  />
                </button>
              </div>
            </div>

            {/* Rexamart Order Pusher */}
            <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-lg font-medium text-gray-900 dark:text-white">Rexamart Order Pusher</h4>
                  <p className="text-sm text-gray-500 dark:text-gray-300">
                    {rexamartOrderPusherEnabled ? 'Telecel orders are being pushed to Rexamart API' : 'Rexamart order pushing is disabled'}
                  </p>
                </div>
                <button
                  onClick={toggleRexamartOrderPusher}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                    rexamartOrderPusherEnabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-600'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      rexamartOrderPusherEnabled ? 'translate-x-6' : 'translate-x-1'
                    }`}
                  />
                </button>
              </div>
            </div>

            {/* Bundle Portal Order Pusher MTN */}
            <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-lg font-medium text-gray-900 dark:text-white">Bundle Portal MTN Order Pusher (MTN)</h4>
                  <p className="text-sm text-gray-500 dark:text-gray-300">
                    {bundlePortalMtnApiEnabled ? 'Regular MTN orders are being pushed to Bundle Portal API' : 'Bundle Portal MTN order pushing is disabled'}
                  </p>
                </div>
                <button
                  onClick={toggleBundlePortalMtnApi}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                    bundlePortalMtnApiEnabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-600'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      bundlePortalMtnApiEnabled ? 'translate-x-6' : 'translate-x-1'
                    }`}
                  />
                </button>
              </div>
            </div>

            {/* Bundle Portal Order Pusher Telecel/AT/Ishare */}
            <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-lg font-medium text-gray-900 dark:text-white">Bundle Portal Order Pusher (Telecel, AT, Ishare)</h4>
                  <p className="text-sm text-gray-500 dark:text-gray-300">
                    {bundlePortalApiEnabled ? 'Telecel/AT/Ishare orders are being pushed to Bundle Portal API' : 'Bundle Portal Telecel/AT order pushing is disabled'}
                  </p>
                </div>
                <button
                  onClick={toggleBundlePortalApi}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                    bundlePortalApiEnabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-600'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      bundlePortalApiEnabled ? 'translate-x-6' : 'translate-x-1'
                    }`}
                  />
                </button>
              </div>
            </div>

            {/* Dataworks Order Pusher (MTN Express) */}
            <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-lg font-medium text-gray-900 dark:text-white">Dataworks Order Pusher (MTN Express)</h4>
                  <p className="text-sm text-gray-500 dark:text-gray-300">
                    {dataworksOrderPusherEnabled ? 'MTN Express orders are being pushed to Dataworks API' : 'Dataworks order pushing is disabled'}
                  </p>
                </div>
                <button
                  onClick={toggleDataworksOrderPusher}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                    dataworksOrderPusherEnabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-600'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      dataworksOrderPusherEnabled ? 'translate-x-6' : 'translate-x-1'
                    }`}
                  />
                </button>
              </div>
            </div>

            {/* Max Data Order Pusher (MTN Express) */}
            <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-lg font-medium text-gray-900 dark:text-white">Max Data Order Pusher (MTN Express)</h4>
                  <p className="text-sm text-gray-500 dark:text-gray-300">
                    {maxDataOrderPusherEnabled ? 'MTN Express orders are being pushed to Max Data API' : 'Max Data order pushing is disabled'}
                  </p>
                </div>
                <button
                  onClick={toggleMaxDataOrderPusher}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                    maxDataOrderPusherEnabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-600'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      maxDataOrderPusherEnabled ? 'translate-x-6' : 'translate-x-1'
                    }`}
                  />
                </button>
              </div>
            </div>
          </div>
        </section>
      </div>
    </AdminLayout>
  );
};

export default AdminDashboard;
