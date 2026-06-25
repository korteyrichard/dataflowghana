import React, { useState } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { Head, router } from '@inertiajs/react';

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
}

interface Webhook {
    id: number;
    url: string;
    secret: string;
    active: boolean;
    created_at: string;
}

interface Props {
    auth: {
        user: User;
    };
    webhooks: Webhook[];
}

export default function ApiDocs({ auth, webhooks }: Props) {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [token, setToken] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [webhookUrl, setWebhookUrl] = useState('');
    const [webhookLoading, setWebhookLoading] = useState(false);
    const [webhookMsg, setWebhookMsg] = useState('');
    const [newSecret, setNewSecret] = useState('');
    const [copiedId, setCopiedId] = useState<string | null>(null);

    const copyToClipboard = (text: string, id: string) => {
        navigator.clipboard.writeText(text);
        setCopiedId(id);
        setTimeout(() => setCopiedId(null), 2000);
    };

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
                                        onClick={() => copyToClipboard(token, 'token')}
                                        className="bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded-md"
                                    >
                                        {copiedId === 'token' ? 'Copied!' : 'Copy'}
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
                                onClick={() => copyToClipboard('https://dataflowghana.com/api/v1', 'baseurl')}
                                className="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs"
                            >
                                {copiedId === 'baseurl' ? 'Copied!' : 'Copy'}
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
                            ) : auth.user.role === 'elite' ? (
                                <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto">
{`MTN: 13
TELECEL: 14
ISHARE: 15
BIGTIME: 16`}
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

                    {/* Webhooks API */}
                    <div className="mb-8">
                        <h3 className="text-lg font-medium mb-4 text-gray-900 dark:text-gray-100">Webhooks API</h3>
                        <div className="space-y-4">
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>POST</strong> /api/v1/webhooks
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">Register a webhook URL to receive order status updates</p>
                                <p className="text-xs text-gray-500 dark:text-gray-500 mb-2">Request:</p>
                                <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto mb-2">
{`{
  "url": "https://yoursite.com/webhook/orders"
}`}
                                </pre>
                                <p className="text-xs text-gray-500 dark:text-gray-500 mb-2">Response:</p>
                                <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto">
{`{
  "message": "Webhook registered",
  "id": 1,
  "secret": "your-webhook-secret-key"
}`}
                                </pre>
                            </div>
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>GET</strong> /api/v1/webhooks
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400">List your registered webhooks</p>
                            </div>
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>DELETE</strong> /api/v1/webhooks/&#123;id&#125;
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400">Remove a webhook</p>
                            </div>
                            <div className="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 p-4 rounded-md">
                                <p className="text-sm font-medium text-yellow-800 dark:text-yellow-200 mb-2">Webhook Payload</p>
                                <p className="text-xs text-yellow-700 dark:text-yellow-300 mb-2">
                                    When an order status changes, we POST the following to your URL:
                                </p>
                                <pre className="text-xs bg-yellow-100 dark:bg-yellow-800 p-2 rounded overflow-x-auto mb-2">
{`{
  "event": "order.status_changed",
  "data": {
    "order_id": 123,
    "reference_id": "REF123",
    "beneficiary_number": "0241234567",
    "network": "MTN",
    "previous_status": "pending",
    "new_status": "completed",
    "updated_at": "2025-06-25T10:30:00+00:00"
  }
}`}
                                </pre>
                                <p className="text-xs text-yellow-700 dark:text-yellow-300">
                                    Each request includes an <code className="bg-yellow-200 dark:bg-yellow-700 px-1 rounded">X-Webhook-Signature</code> header (HMAC-SHA256 of the JSON payload using your secret). Verify it to ensure authenticity.
                                </p>
                            </div>
                        </div>
                    </div>

                </div>

                {/* Webhook Management Section */}
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h2 className="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Manage Webhooks</h2>

                    {/* Add Webhook Form */}
                    <form
                        onSubmit={async (e) => {
                            e.preventDefault();
                            setWebhookLoading(true);
                            setWebhookMsg('');
                            setNewSecret('');
                            try {
                                const res = await fetch('/api/v1/webhooks', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'Authorization': `Bearer ${token}`,
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                    },
                                    credentials: 'include',
                                    body: JSON.stringify({ url: webhookUrl }),
                                });
                                const data = await res.json();
                                if (res.ok) {
                                    setWebhookMsg('Webhook registered successfully!');
                                    setNewSecret(data.secret);
                                    setWebhookUrl('');
                                    router.reload({ only: ['webhooks'] });
                                } else {
                                    setWebhookMsg(data.message || 'Failed to register webhook');
                                }
                            } catch {
                                setWebhookMsg('Network error');
                            } finally {
                                setWebhookLoading(false);
                            }
                        }}
                        className="flex gap-2 mb-4"
                    >
                        <input
                            type="url"
                            value={webhookUrl}
                            onChange={(e) => setWebhookUrl(e.target.value)}
                            placeholder="https://yoursite.com/webhook/orders"
                            className="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            required
                        />
                        <button
                            type="submit"
                            disabled={webhookLoading || !token}
                            className="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md disabled:opacity-50"
                        >
                            {webhookLoading ? 'Adding...' : 'Add Webhook'}
                        </button>
                    </form>

                    {!token && (
                        <p className="text-sm text-yellow-600 dark:text-yellow-400 mb-4">Generate an API token above to manage webhooks.</p>
                    )}

                    {webhookMsg && (
                        <p className="text-sm text-green-600 dark:text-green-400 mb-2">{webhookMsg}</p>
                    )}
                    {newSecret && (
                        <div className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-3 rounded-md mb-4">
                            <p className="text-xs text-green-700 dark:text-green-300 mb-1 font-medium">Save this secret — it won't be shown again:</p>
                            <div className="flex items-center gap-2">
                                <code className="text-xs bg-green-100 dark:bg-green-800 px-2 py-1 rounded break-all">{newSecret}</code>
                                <button
                                    onClick={() => copyToClipboard(newSecret, 'newSecret')}
                                    className="bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs"
                                >
                                    {copiedId === 'newSecret' ? 'Copied!' : 'Copy'}
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Existing Webhooks */}
                    {webhooks.length > 0 ? (
                        <div className="space-y-2">
                            {webhooks.map((wh) => (
                                <div key={wh.id} className="flex items-center justify-between bg-gray-50 dark:bg-gray-700 p-3 rounded-md">
                                    <div className="flex-1 min-w-0 mr-3">
                                        <p className="text-sm text-gray-900 dark:text-gray-100 font-mono truncate">{wh.url}</p>
                                        <div className="flex items-center gap-2 mt-1">
                                            <span className="text-xs text-gray-500 dark:text-gray-400">Secret:</span>
                                            <code className="text-xs bg-gray-200 dark:bg-gray-600 px-1 rounded break-all">{wh.secret}</code>
                                            <button
                                                onClick={() => copyToClipboard(wh.secret, `secret-${wh.id}`)}
                                                className="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400"
                                            >
                                                {copiedId === `secret-${wh.id}` ? 'Copied!' : 'Copy'}
                                            </button>
                                        </div>
                                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">Added {new Date(wh.created_at).toLocaleDateString()}</p>
                                    </div>
                                    <button
                                        onClick={async () => {
                                            if (!token) return alert('Generate a token first');
                                            await fetch(`/api/v1/webhooks/${wh.id}`, {
                                                method: 'DELETE',
                                                headers: {
                                                    'Authorization': `Bearer ${token}`,
                                                    'Accept': 'application/json',
                                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                                },
                                                credentials: 'include',
                                            });
                                            router.reload({ only: ['webhooks'] });
                                        }}
                                        className="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-xs"
                                    >
                                        Delete
                                    </button>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-gray-500 dark:text-gray-400">No webhooks registered yet.</p>
                    )}
                </div>
            </div>
        </DashboardLayout>
    );
}