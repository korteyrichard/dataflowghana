import { AdminLayout } from '../../layouts/admin-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';
import React, { useState } from 'react';
import { route } from 'ziggy-js';

interface ProductVariant {
  id: number;
  price: number;
  quantity: number;
  status: 'IN STOCK' | 'OUT OF STOCK';
}

interface Product {
  id: number;
  name: string;
  description?: string;
  network: string;
  product_type: 'agent_product' | 'customer_product' | 'dealer_product' | 'elite_product';
  expiry: string;
  has_variants: boolean;
  variants: ProductVariant[];
  price_range?: string;
}

interface AdminProductsProps extends PageProps {
  products: Product[];
  filterNetwork: string;
}

export default function AdminProducts({
  auth,
  products,
  filterNetwork: initialFilterNetwork,
}: AdminProductsProps) {
  const [filterNetwork, setFilterNetwork] = useState(initialFilterNetwork);
  const [showAddProductModal, setShowAddProductModal] = useState(false);
  const [showEditProductModal, setShowEditProductModal] = useState(false);
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
  const [variants, setVariants] = useState<Array<{price: string; quantity: string; status: string}>>([{price: '', quantity: '', status: 'IN STOCK'}]);

  const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm<{
    name: string;
    description: string;
    expiry: string;
    network: string;
    product_type: 'agent_product' | 'customer_product' | 'dealer_product' | 'elite_product';
    has_variants: boolean;
    variants: Array<{price: number; quantity: string; status: string}>;
  }>({
    name: '',
    description: '',
    expiry: '',
    network: '',
    product_type: 'customer_product',
    has_variants: false,
    variants: [],
  });

  const handleFilterChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newFilter = e.target.value;
    setFilterNetwork(newFilter);
    router.get(route('admin.products'), { network: newFilter }, { preserveState: true, replace: true });
  };

const submitAddProduct = (e: React.FormEvent) => {
  e.preventDefault();

  const variantsData = variants.map(v => ({
    price: Number(v.price),
    quantity: v.quantity,
    status: v.status,
  }));

  const formDataWithVariants = {
    ...data,
    variants: variantsData,
    has_variants: variantsData.length > 0,
  };


 router.post(route('admin.products.store'), formDataWithVariants, {
  forceFormData: false, // 🔑 ensure JSON, not FormData
  onSuccess: () => {
    reset();
    setVariants([{ price: '', quantity: '', status: 'IN STOCK' }]);
    setShowAddProductModal(false);
    router.reload({ only: ['products'] });
  },
  onError: (errors) => {
    console.error('❌ Add product errors:', errors);
  },
});
};





  const handleEditProduct = (product: Product) => {
    setSelectedProduct(product);
    setData({
      name: product.name,
      description: product.description || '',
      expiry: product.expiry,
      network: product.network,
      product_type: product.product_type,
      has_variants: product.has_variants,
      variants: [],
    });
    setVariants(product.variants.map(v => ({
      price: v.price.toString(),
      quantity: v.quantity.toString(),
      status: v.status
    })));
    setShowEditProductModal(true);
  };


const submitEditProduct = (e: React.FormEvent) => {
  e.preventDefault();
  if (!selectedProduct) return;

  const formDataWithVariants = {
    ...data,
    variants: variants
      .filter(v => v.price && v.quantity)
      .map(v => ({
        price: parseFloat(v.price),
        quantity: v.quantity,
        status: v.status,
      })),
  };

  // console.log('📝 Submitting edit product with data:', formDataWithVariants);
  // console.log('Selected product:', selectedProduct);
  // console.log('Variants data:', variants);

  router.put(route('admin.products.update', selectedProduct.id), formDataWithVariants, {
    forceFormData: false, // 🔑 ensure JSON, not FormData
    onSuccess: () => {
      reset();
      setVariants([{ price: '', quantity: '', status: 'IN STOCK' }]);
      setShowEditProductModal(false);
      setSelectedProduct(null);
      router.reload({ only: ['products'] });
    },
    onError: (errors) => {
      console.error('❌ Update product errors:', errors);
    },
    onFinish: () => {
      alert("PRODUCT UPDATED SUCCESSFULY")
    },
  });
};



  const handleDeleteProduct = (product: Product) => {
    if (confirm('Are you sure you want to delete this product?')) {
      destroy(route('admin.products.delete', product.id), {
        onSuccess: () => {
          router.reload({ only: ['products'] });
        },
      });
    }
  };

  return (
    <AdminLayout
      user={auth.user}
      header={<h2 className="font-semibold text-2xl text-gray-800 dark:text-gray-100">Admin Products</h2>}
    >
      <Head title="Admin Products" />

      <div className="py-6 px-2 sm:py-10 sm:px-4 lg:px-8">
        <div className="bg-white dark:bg-gray-900 shadow rounded-xl p-4 sm:p-6">
          <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
            <h3 className="text-lg sm:text-xl font-semibold text-gray-800 dark:text-white">Product List</h3>
            <button
              onClick={() => setShowAddProductModal(true)}
              className="w-full sm:w-auto px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
              Add Product
            </button>
          </div>

          <div className="mb-6">
            <label htmlFor="networkFilter" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Filter by Network
            </label>
            <input
              type="text"
              id="networkFilter"
              value={filterNetwork}
              onChange={handleFilterChange}
              placeholder="e.g., MTN, Glo"
              className="w-full sm:max-w-xs px-4 py-2 rounded-md border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <div className="overflow-x-auto -mx-2 sm:mx-0">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs sm:text-sm">
              <thead className="bg-gray-100 dark:bg-gray-800">
                <tr>
                  <th className="px-2 sm:px-6 py-3 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">ID</th>
                  <th className="px-2 sm:px-6 py-3 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Name</th>
                  <th className="px-2 sm:px-6 py-3 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Network</th>
                  <th className="px-2 sm:px-6 py-3 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Type</th>
                  <th className="px-2 sm:px-6 py-3 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Variants</th>
                  <th className="px-2 sm:px-6 py-3 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                {products.length > 0 ? (
                  products.map((product) => (
                    <tr key={product.id} className="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors cursor-pointer" onClick={() => handleEditProduct(product)}>
                      <td className="px-2 sm:px-6 py-4 font-medium text-gray-900 dark:text-white whitespace-nowrap">{product.id}</td>
                      <td className="px-2 sm:px-6 py-4 text-gray-700 dark:text-gray-300 whitespace-nowrap">{product.name}</td>
                      <td className="px-2 sm:px-6 py-4 text-gray-700 dark:text-gray-300 whitespace-nowrap">{product.network}</td>
                      <td className="px-2 sm:px-6 py-4 whitespace-nowrap">
                        <span className={`px-2 py-1 rounded text-xs font-medium ${
                          product.product_type === 'agent_product' ? 'bg-purple-100 text-purple-800' : 
                          product.product_type === 'dealer_product' ? 'bg-green-100 text-green-800' :
                          product.product_type === 'elite_product' ? 'bg-yellow-100 text-yellow-800' :
                          'bg-blue-100 text-blue-800'
                        }`}>
                          {product.product_type === 'agent_product' ? 'Agent' : 
                           product.product_type === 'dealer_product' ? 'Dealer' :
                           product.product_type === 'elite_product' ? 'Elite' : 'Customer'}
                        </span>
                      </td>
                      <td className="px-2 sm:px-6 py-4 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                        <span className="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">
                          {product.variants.length} variant{product.variants.length !== 1 ? 's' : ''}
                        </span>
                        {product.price_range && (
                          <div className="text-xs text-gray-500 mt-1">${product.price_range}</div>
                        )}
                      </td>
                      <td className="px-2 sm:px-6 py-4 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                        <button
                          onClick={(e) => {
                            e.stopPropagation();
                            handleDeleteProduct(product);
                          }}
                          className="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                        >
                          Delete
                        </button>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={6} className="px-2 sm:px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                      No products found.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>


      {/* ADD PRODUCT MODAL */}
      {showAddProductModal && (
        <div className="fixed mt-5 inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex justify-center items-center z-50 p-2 sm:p-0">
          <div className="bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-lg shadow-xl w-full max-w-md">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Add New Product</h3>
            <form onSubmit={submitAddProduct}>
              <div className="mb-4">
                <label htmlFor="name" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Product Name</label>
                <input
                  type="text"
                  id="name"
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  required
                  className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                />
                {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name}</p>}
              </div>

              <div className="mb-4">
                <label htmlFor="description" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Product Description</label>
                <input
                  type="text"
                  id="description"
                  value={data.description}
                  onChange={(e) => setData('description', e.target.value)}
                  required
                  className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                />
                {errors.description && <p className="text-red-500 text-xs mt-1">{errors.description}</p>}
              </div>

              <div className="mb-4">
                <label htmlFor="network" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Network</label>
                <select
                  id="network"
                  value={data.network}
                  onChange={(e) => setData('network', e.target.value)}
                  required
                  className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                  <option value="">Select network</option>
                  <option value="MTN">MTN</option>
                  <option value="Telecel">Telecel</option>
                  <option value="Ishare">Ishare</option>
                  <option value="Bigtime">Bigtime</option>
                </select>
                {errors.network && <p className="text-red-500 text-xs mt-1">{errors.network}</p>}
              </div>

              <div className="mb-4">
                <label htmlFor="expiry" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Expiry</label>
                <select
                  id="expiry"
                  value={data.expiry}
                  onChange={(e) => setData('expiry', e.target.value)}
                  required
                  className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                  <option value="">Select expiry</option>
                  <option value="non expiry">NON EXPIRY</option>
                  <option value="30 days">30 DAYS EXPIRY</option>
                  <option value="24 hours">24 HOURS EXPIRY</option>
                </select>
                {errors.expiry && <p className="text-red-500 text-xs mt-1">{errors.expiry}</p>}
              </div>

              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Product Variants</label>
                {variants.map((variant, index) => (
                  <div key={index} className="border border-gray-300 dark:border-gray-600 rounded-md p-3 mb-3">
                    <div className="flex justify-between items-center mb-2">
                      <span className="text-sm font-medium">Variant {index + 1}</span>
                      {variants.length > 1 && (
                        <button
                          type="button"
                          onClick={() => setVariants(variants.filter((_, i) => i !== index))}
                          className="text-red-500 hover:text-red-700 text-sm"
                        >
                          Remove
                        </button>
                      )}
                    </div>
                    
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                      <div>
                        <label className="block text-xs text-gray-600 dark:text-gray-400">Price</label>
                        <input
                          type="number"
                          step="0.01"
                          value={variant.price}
                          onChange={(e) => {
                            const newVariants = [...variants];
                            newVariants[index].price = e.target.value;
                            setVariants(newVariants);
                          }}
                          className="w-full px-2 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                          placeholder="0.00"
                        />
                      </div>
                      <div>
                        <label className="block text-xs text-gray-600 dark:text-gray-400">Quantity</label>
                        <select
                          value={variant.quantity}
                          onChange={(e) => {
                            const newVariants = [...variants];
                            newVariants[index].quantity = e.target.value;
                            setVariants(newVariants);
                          }}
                          className="w-full px-2 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                          <option value="">Select</option>
                          {[...Array(500)].map((_, i) => (
                            <option key={i + 1} value={`${i + 1}GB`}>{i + 1}GB</option>
                          ))}
                        </select>
                      </div>
                      <div>
                        <label className="block text-xs text-gray-600 dark:text-gray-400">Status</label>
                        <select
                          value={variant.status}
                          onChange={(e) => {
                            const newVariants = [...variants];
                            newVariants[index].status = e.target.value;
                            setVariants(newVariants);
                          }}
                          className="w-full px-2 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                          <option value="IN STOCK">IN STOCK</option>
                          <option value="OUT OF STOCK">OUT OF STOCK</option>
                        </select>
                      </div>
                    </div>
                  </div>
                ))}
                
                <button
                  type="button"
                  onClick={() => setVariants([...variants, {price: '', quantity: '', status: 'IN STOCK'}])}
                  className="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                >
                  + Add Another Variant
                </button>
              </div>

              <div className="mb-4">
                <label htmlFor="product_type" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Product Type</label>
                <select
                  id="product_type"
                  value={data.product_type}
                  onChange={(e) => setData('product_type', e.target.value as 'agent_product' | 'customer_product' | 'dealer_product' | 'elite_product')}
                  className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                  <option value="customer_product">Customer Product</option>
                  <option value="agent_product">Agent Product</option>
                  <option value="dealer_product">Dealer Product</option>
                  <option value="elite_product">Elite Product</option>
                </select>
                {errors.product_type && <p className="text-red-500 text-xs mt-1">{errors.product_type}</p>}
              </div>

              <div className="flex flex-col sm:flex-row justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setShowAddProductModal(false)}
                  className="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 focus:ring-2 focus:ring-offset-2"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 disabled:opacity-50"
                >
                  {processing ? 'Adding...' : 'Add Product'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* EDIT PRODUCT MODAL */}

      {showEditProductModal && selectedProduct && (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex justify-center items-center z-50 p-2 sm:p-0">
          <div className="bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-lg shadow-xl w-full max-w-md">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Edit Product</h3>
            <form onSubmit={submitEditProduct}>
              <div className="mb-4">
                <label htmlFor="edit-name" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Product Name</label>
                <input
                  type="text"
                  id="edit-name"
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  required
                  className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                />
                {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name}</p>}
              </div>

              <div className="mb-4">
                <label htmlFor="edit-description" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Product Description</label>
                <input
                  type="text"
                  id="edit-description"
                  value={data.description}
                  onChange={(e) => setData('description', e.target.value)}
                  required
                  className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                />
                {errors.description && <p className="text-red-500 text-xs mt-1">{errors.description}</p>}
              </div>

              <div className="mb-4">
                <label htmlFor="edit-network" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Network</label>
                <select
                  id="edit-network"
                  value={data.network}
                  onChange={(e) => setData('network', e.target.value)}
                  required
                  className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                  <option value="">Select network</option>
                  <option value="MTN">MTN</option>
                  <option value="Telecel">Telecel</option>
                  <option value="Ishare">Ishare</option>
                  <option value="Bigtime">Bigtime</option>
                </select>
                {errors.network && <p className="text-red-500 text-xs mt-1">{errors.network}</p>}
              </div>

              <div className="mb-4">
                <label htmlFor="edit-expiry" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Expiry</label>
                <select
                  id="edit-expiry"
                  value={data.expiry}
                  onChange={(e) => setData('expiry', e.target.value)}
                  required
                  className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                  <option value="">Select expiry</option>
                  <option value="non expiry">NON EXPIRY</option>
                  <option value="30 days">30 DAYS EXPIRY</option>
                  <option value="24 hours">24 HOURS EXPIRY</option>
                </select>
                {errors.expiry && <p className="text-red-500 text-xs mt-1">{errors.expiry}</p>}
              </div>

              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Product Variants</label>
                {variants.map((variant, index) => (
                  <div key={index} className="border border-gray-300 dark:border-gray-600 rounded-md p-3 mb-3">
                    <div className="flex justify-between items-center mb-2">
                      <span className="text-sm font-medium">Variant {index + 1}</span>
                      {variants.length > 1 && (
                        <button
                          type="button"
                          onClick={() => setVariants(variants.filter((_, i) => i !== index))}
                          className="text-red-500 hover:text-red-700 text-sm"
                        >
                          Remove
                        </button>
                      )}
                    </div>
                    
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                      <div>
                        <label className="block text-xs text-gray-600 dark:text-gray-400">Price</label>
                        <input
                          type="number"
                          step="0.01"
                          value={variant.price}
                          onChange={(e) => {
                            const newVariants = [...variants];
                            newVariants[index].price = e.target.value;
                            setVariants(newVariants);
                          }}
                          className="w-full px-2 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                          placeholder="0.00"
                        />
                      </div>
                      <div>
                        <label className="block text-xs text-gray-600 dark:text-gray-400">Quantity</label>
                        <select
                          value={variant.quantity}
                          onChange={(e) => {
                            const newVariants = [...variants];
                            newVariants[index].quantity = e.target.value;
                            setVariants(newVariants);
                          }}
                          className="w-full px-2 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                          <option value="">Select</option>
                          {[...Array(500)].map((_, i) => (
                            <option key={i + 1} value={`${i + 1}GB`}>{i + 1}GB</option>
                          ))}
                        </select>
                      </div>
                      <div>
                        <label className="block text-xs text-gray-600 dark:text-gray-400">Status</label>
                        <select
                          value={variant.status}
                          onChange={(e) => {
                            const newVariants = [...variants];
                            newVariants[index].status = e.target.value;
                            setVariants(newVariants);
                          }}
                          className="w-full px-2 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                          <option value="IN STOCK">IN STOCK</option>
                          <option value="OUT OF STOCK">OUT OF STOCK</option>
                        </select>
                      </div>
                    </div>
                  </div>
                ))}
                
                <button
                  type="button"
                  onClick={() => setVariants([...variants, {price: '', quantity: '', status: 'IN STOCK'}])}
                  className="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                >
                  + Add Another Variant
                </button>
              </div>

              <div className="mb-4">
                <label htmlFor="edit-product_type" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Product Type</label>
                <select
                  id="edit-product_type"
                  value={data.product_type}
                  onChange={(e) => setData('product_type', e.target.value as 'agent_product' | 'customer_product' | 'dealer_product' | 'elite_product')}
                  className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                  <option value="customer_product">Customer Product</option>
                  <option value="agent_product">Agent Product</option>
                  <option value="dealer_product">Dealer Product</option>
                  <option value="elite_product">Elite Product</option>
                </select>
                {errors.product_type && <p className="text-red-500 text-xs mt-1">{errors.product_type}</p>}
              </div>

              <div className="flex flex-col sm:flex-row justify-end gap-2">
                <button
                  type="button"
                  onClick={() => {
                    setShowEditProductModal(false);
                    setSelectedProduct(null);
                    reset();
                  }}
                  className="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 focus:ring-2 focus:ring-offset-2"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 disabled:opacity-50"
                >
                  {processing ? 'Updating...' : 'Update Product'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AdminLayout>
  );
}