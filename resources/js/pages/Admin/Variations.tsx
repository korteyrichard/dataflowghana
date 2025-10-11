import { AdminLayout } from '../../layouts/admin-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';
import React, { useState } from 'react';

interface VariationAttribute {
  id: number;
  name: string;
  values: string[];
  created_at: string;
}

interface AdminVariationsProps extends PageProps {
  attributes: VariationAttribute[];
}

export default function AdminVariations({
  auth,
  attributes,
}: AdminVariationsProps) {
  const [showAddModal, setShowAddModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [selectedAttribute, setSelectedAttribute] = useState<VariationAttribute | null>(null);

  const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
    name: '',
    values: '',
  });

  const submitAddAttribute = (e: React.FormEvent) => {
    e.preventDefault();
    post(route('admin.variation-attributes.store'), {
      onSuccess: () => {
        reset();
        setShowAddModal(false);
        router.reload({ only: ['attributes'] });
      },
    });
  };

  const handleEditAttribute = (attribute: VariationAttribute) => {
    setSelectedAttribute(attribute);
    setData({
      name: attribute.name,
      values: attribute.values.join(', '),
    });
    setShowEditModal(true);
  };

  const submitEditAttribute = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedAttribute) return;
    
    put(route('admin.variation-attributes.update', selectedAttribute.id), {
      onSuccess: () => {
        reset();
        setShowEditModal(false);
        setSelectedAttribute(null);
        router.reload({ only: ['attributes'] });
      },
    });
  };

  const handleDeleteAttribute = (attribute: VariationAttribute) => {
    if (confirm('Are you sure you want to delete this variation attribute?')) {
      destroy(route('admin.variation-attributes.delete', attribute.id), {
        onSuccess: () => {
          router.reload({ only: ['attributes'] });
        },
      });
    }
  };

  return (
    <AdminLayout
      user={auth.user}
      header={<h2 className="font-semibold text-2xl text-gray-800 dark:text-gray-100">Variation Attributes</h2>}
    >
      <Head title="Variation Attributes" />

      <div className="py-6 px-2 sm:py-10 sm:px-4 lg:px-8">
        <div className="bg-white dark:bg-gray-900 shadow rounded-xl p-4 sm:p-6">
          <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
            <h3 className="text-lg sm:text-xl font-semibold text-gray-800 dark:text-white">Manage Variation Attributes</h3>
            <button
              onClick={() => setShowAddModal(true)}
              className="w-full sm:w-auto px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
              Add Attribute
            </button>
          </div>

          <div className="overflow-x-auto -mx-2 sm:mx-0">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs sm:text-sm">
              <thead className="bg-gray-100 dark:bg-gray-800">
                <tr>
                  <th className="px-2 sm:px-6 py-3 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                  <th className="px-2 sm:px-6 py-3 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Values</th>
                  <th className="px-2 sm:px-6 py-3 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                  <th className="px-2 sm:px-6 py-3 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                {attributes.length > 0 ? (
                  attributes.map((attribute) => (
                    <tr key={attribute.id} className="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                      <td className="px-2 sm:px-6 py-4 font-medium text-gray-900 dark:text-white">{attribute.name}</td>
                      <td className="px-2 sm:px-6 py-4 text-gray-700 dark:text-gray-300">
                        <div className="flex flex-wrap gap-1">
                          {attribute.values.map((value, index) => (
                            <span key={index} className="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-xs rounded">
                              {value}
                            </span>
                          ))}
                        </div>
                      </td>
                      <td className="px-2 sm:px-6 py-4 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                        {new Date(attribute.created_at).toLocaleDateString()}
                      </td>
                      <td className="px-2 sm:px-6 py-4 text-gray-700 dark:text-gray-300 whitespace-nowrap space-x-2">
                        <button
                          onClick={() => handleEditAttribute(attribute)}
                          className="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                          Edit
                        </button>
                        <button
                          onClick={() => handleDeleteAttribute(attribute)}
                          className="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                        >
                          Delete
                        </button>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={4} className="px-2 sm:px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                      No variation attributes found.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Add Attribute Modal */}
      {showAddModal && (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex justify-center items-center z-50 p-2 sm:p-0">
          <div className="bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-lg shadow-xl w-full max-w-md">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Add Variation Attribute</h3>
            <form onSubmit={submitAddAttribute}>
              <div className="mb-4">
                <label htmlFor="name" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Attribute Name</label>
                <input
                  type="text"
                  id="name"
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  placeholder="e.g., Size, Color, Material"
                  required
                  className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                />
                {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name}</p>}
              </div>

              <div className="mb-4">
                <label htmlFor="values" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Values (comma separated)</label>
                <input
                  type="text"
                  id="values"
                  value={data.values}
                  onChange={(e) => setData('values', e.target.value)}
                  placeholder="e.g., Small, Medium, Large, XL"
                  required
                  className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                />
                {errors.values && <p className="text-red-500 text-xs mt-1">{errors.values}</p>}
              </div>

              <div className="flex flex-col sm:flex-row justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setShowAddModal(false)}
                  className="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 focus:ring-2 focus:ring-offset-2"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 disabled:opacity-50"
                >
                  {processing ? 'Adding...' : 'Add Attribute'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Edit Attribute Modal */}
      {showEditModal && selectedAttribute && (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex justify-center items-center z-50 p-2 sm:p-0">
          <div className="bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-lg shadow-xl w-full max-w-md">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Edit Variation Attribute</h3>
            <form onSubmit={submitEditAttribute}>
              <div className="mb-4">
                <label htmlFor="edit-name" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Attribute Name</label>
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
                <label htmlFor="edit-values" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Values (comma separated)</label>
                <input
                  type="text"
                  id="edit-values"
                  value={data.values}
                  onChange={(e) => setData('values', e.target.value)}
                  required
                  className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                />
                {errors.values && <p className="text-red-500 text-xs mt-1">{errors.values}</p>}
              </div>

              <div className="flex flex-col sm:flex-row justify-end gap-2">
                <button
                  type="button"
                  onClick={() => {
                    setShowEditModal(false);
                    setSelectedAttribute(null);
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
                  {processing ? 'Updating...' : 'Update Attribute'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AdminLayout>
  );
}