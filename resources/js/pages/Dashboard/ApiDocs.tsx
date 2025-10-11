import React, { useState } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { Head } from '@inertiajs/react';

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
}

interface Props {
    auth: {
        user: User;
    };
}

export default function ApiDocs({ auth }: Props) {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [token, setToken] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    React.useEffect(() => {
        fetchExistingToken();
    }, []);

    const fetchExistingToken = async () => {
        try {
            const response = await fetch('/api/v1/get-token', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'include'
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.token) {
                    setToken(data.token);
                }
            }
        } catch (err) {
            console.error('Failed to fetch existing token:', err);
        }
    };

    const handleLogin = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setError('');

        try {
            const response = await fetch('/api/v1/token/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ email, password }),
            });

            const data = await response.json();

            if (response.ok) {
                setToken(data.token);
            } else {
                setError(data.message || 'Login failed');
            }
        } catch (err) {
            setError('Network error occurred');
        } finally {
            setLoading(false);
        }
    };

    const handleLogout = async () => {
        if (token) {
            try {
                const response = await fetch('/api/v1/logout-all', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                });
                const data = await response.json();
                console.log('Logout response:', data);
            } catch (err) {
                console.error('Logout failed:', err);
            }
        }
        setToken('');
        setEmail('');
        setPassword('');
        setError('');
    };

    return (
        <DashboardLayout user={auth.user} header="API Documentation">
            <Head title="API Documentation" />

            <div className="max-w-4xl mx-auto space-y-8">
                {/* API Login Section */}
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h2 className="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">API Authentication</h2>
                    
                    {!token ? (
                        <form onSubmit={handleLogin} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Email
                                </label>
                                <input
                                    type="email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                    required
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Password
                                </label>
                                <input
                                    type="password"
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                    required
                                />
                            </div>
                            {error && (
                                <div className="text-red-600 text-sm">{error}</div>
                            )}
                            <button
                                type="submit"
                                disabled={loading}
                                className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md disabled:opacity-50"
                            >
                                {loading ? 'Generating Token...' : 'Generate API Token'}
                            </button>
                        </form>
                    ) : (
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    API Token
                                </label>
                                <div className="flex gap-2">
                                    <input
                                        type="text"
                                        value={token}
                                        readOnly
                                        className="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                    />
                                    <button
                                        onClick={() => navigator.clipboard.writeText(token)}
                                        className="bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded-md"
                                    >
                                        Copy
                                    </button>
                                </div>
                            </div>
                            <button
                                onClick={handleLogout}
                                className="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md"
                            >
                                Logout
                            </button>
                        </div>
                    )}
                </div>

                {/* API Base URL */}
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h2 className="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">API Base URL</h2>
                    <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-4 rounded-md">
                        <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                            All API requests should be made to:
                        </p>
                        <div className="flex items-center gap-2">
                            <code className="bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200 px-3 py-2 rounded text-sm font-mono">
                                https://dataflowghana.com/api/v1
                            </code>
                            <button
                                onClick={() => navigator.clipboard.writeText('https://dataflowghana.com/api/v1')}
                                className="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs"
                            >
                                Copy
                            </button>
                        </div>
                    </div>
                </div>

                {/* API Documentation */}
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h2 className="text-xl font-semibold mb-6 text-gray-900 dark:text-gray-100">API Endpoints</h2>

                    {/* Authentication */}
                    <div className="mb-8">
                        <h3 className="text-lg font-medium mb-4 text-gray-900 dark:text-gray-100">Authentication</h3>
                        <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                            <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                <strong>POST</strong> /api/v1/token/create
                            </p>
                            <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">Generate API token</p>
                            <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto">
{`{
  "email": "user@example.com",
  "password": "password"
}`}
                            </pre>
                        </div>
                    </div>

                    {/* Network IDs */}
                    <div className="mb-8">
                        <h3 className="text-lg font-medium mb-4 text-gray-900 dark:text-gray-100">Network IDs</h3>
                        <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                            <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                Use these network IDs for API requests:
                            </p>
                            {auth.user.role === 'agent' ? (
                                <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto">
{`MTN: 5
TELECEL: 6
ISHARE: 7
BIGTIME: 8`}
                                </pre>
                            ) : (
                                <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto">
{`MTN: 9
TELECEL: 10
ISHARE: 11
BIGTIME: 12`}
                                </pre>
                            )}
                        </div>
                    </div>

                    {/* Orders API */}
                    <div className="mb-8">
                        <h3 className="text-lg font-medium mb-4 text-gray-900 dark:text-gray-100">Orders API</h3>
                        <div className="space-y-4">
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>GET</strong> /api/v1/normal-orders
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400">Get user's orders</p>
                            </div>
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>POST</strong> /api/v1/normal-orders
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">Create new order</p>
                                <p className="text-xs text-gray-500 dark:text-gray-500 mb-2">Request:</p>
                                <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto mb-2">
{`{
  "beneficiary_number": "0241234567",
  "network_id": 1,
  "size": "2GB"
}`}
                                </pre>
                                <p className="text-xs text-gray-500 dark:text-gray-500 mb-2">Response:</p>
                                <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto">
{`{
  "message": "Order created successfully",
  "order": {
    "reference_id": 17,
    "total": "9.00",
    "status": "pending",
    "network": "MTN",
    "beneficiary_number": "0241234567",
    "created_at": "2025-09-22T01:35:07.000000Z",
    "user": {
      "name": "richard kortey",
      "email": "richardkortey3@gmail.com"
    },
    "products": [
      {
        "name": "MTN",
        "quantity": 1,
        "price": "9.00"
      }
    ]
  }
}`}
                                </pre>
                            </div>
                        </div>
                    </div>

                    {/* AFA API */}
                    <div className="mb-8">
                        <h3 className="text-lg font-medium mb-4 text-gray-900 dark:text-gray-100">AFA API</h3>
                        <div className="space-y-4">
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>GET</strong> /api/v1/afa
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400">Get user's AFA orders</p>
                            </div>
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>GET</strong> /api/v1/afa/products
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400">Get available AFA products</p>
                            </div>
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>POST</strong> /api/v1/afa
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">Create AFA order</p>
                                <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto">
{`{
  "afa_product_id": 1,
  "full_name": "John Doe",
  "email": "john@example.com",
  "phone": "0241234567",
  "dob": "1990-01-01",
  "occupation": "Developer",
  "region": "Greater Accra"
}`}
                                </pre>
                            </div>
                        </div>
                    </div>

                    {/* Transactions API */}
                    <div className="mb-8">
                        <h3 className="text-lg font-medium mb-4 text-gray-900 dark:text-gray-100">Transactions API</h3>
                        <div className="space-y-4">
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>GET</strong> /api/v1/transactions
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">Get all orders with transactions for authenticated user</p>
                                <p className="text-xs text-gray-500 dark:text-gray-500 mb-2">Response includes orders with related transactions data</p>
                                <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto">
{`{
  "success": true,
  "data": [
    {
      "id": 123,
      "user_id": 1,
      "total": "10.00",
      "status": "completed",
      "beneficiary_number": "0241234567",
      "network": "MTN",
      "reference_id": "ORD123456",
      "transactions": [...]
    }
  ]
}`}
                                </pre>
                            </div>
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>GET</strong> /api/v1/transactions/&#123;id&#125;
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">Get single order by ID with transactions</p>
                                <p className="text-xs text-gray-500 dark:text-gray-500 mb-2">Returns 404 if order not found or doesn't belong to authenticated user</p>
                                <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto">
{`{
  "success": true,
  "data": {
    "id": 123,
    "user_id": 1,
    "total": "10.00",
    "status": "completed",
    "beneficiary_number": "0241234567",
    "network": "MTN",
    "reference_id": "ORD123456",
    "transactions": [...]
  }
}`}
                                </pre>
                            </div>
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>GET</strong> /api/v1/transaction-status
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400">Get transaction status</p>
                            </div>
                        </div>
                    </div>

                    {/* Authentication Headers */}
                    <div className="mb-8">
                        <h3 className="text-lg font-medium mb-4 text-gray-900 dark:text-gray-100">Authentication Headers</h3>
                        <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                            <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                All authenticated requests must include:
                            </p>
                            <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto">
{`Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
Accept: application/json`}
                            </pre>
                        </div>
                    </div>

                    
                </div>
            </div>
        </DashboardLayout>
    );
}