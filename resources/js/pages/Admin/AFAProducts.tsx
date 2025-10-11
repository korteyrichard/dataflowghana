import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/admin-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Trash2, Edit, Plus, Filter } from 'lucide-react';


interface AFAProduct {
  id: number;
  name: string;
  price: number;
  status: 'IN_STOCK' | 'OUT_OF_STOCK';
  created_at: string;
  updated_at: string;
}

interface Props {
  auth: {
    user: {
      id: number;
      name: string;
      email: string;
      role: string;
    };
  };
  afaProducts: {
    data: AFAProduct[];
    links: any[];
    current_page: number;
    last_page: number;
  };
  filterStatus: string;
}

export default function AFAProducts({ auth, afaProducts, filterStatus }: Props) {
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [isEditOpen, setIsEditOpen] = useState(false);
  const [editingProduct, setEditingProduct] = useState<AFAProduct | null>(null);

  const { data: createData, setData: setCreateData, post: createPost, processing: createProcessing, errors: createErrors, reset: resetCreate } = useForm({
    name: '',
    price: '',
    status: 'IN_STOCK' as 'IN_STOCK' | 'OUT_OF_STOCK',
  });

  const { data: editData, setData: setEditData, put: editPut, processing: editProcessing, errors: editErrors } = useForm({
    name: '',
    price: '',
    status: 'IN_STOCK' as 'IN_STOCK' | 'OUT_OF_STOCK',
  });

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    createPost(route('admin.afa-products.store'), {
      onSuccess: () => {
        setIsCreateOpen(false);
        resetCreate();
      },
    });
  };

  const handleEdit = (product: AFAProduct) => {
    setEditingProduct(product);
    setEditData({
      name: product.name,
      price: product.price.toString(),
      status: product.status,
    });
    setIsEditOpen(true);
  };

  const handleUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingProduct) return;
    
    editPut(route('admin.afa-products.update', editingProduct.id), {
      onSuccess: () => {
        setIsEditOpen(false);
        setEditingProduct(null);
      },
    });
  };

  const handleDelete = (product: AFAProduct) => {
    if (confirm('Are you sure you want to delete this AFA product?')) {
      router.delete(route('admin.afa-products.destroy', product.id), {
        onSuccess: () => {
          // Product deleted successfully
        },
      });
    }
  };

  const handleFilter = (status: string) => {
    const filterStatus = status === 'all' ? '' : status;
    router.get(route('admin.afa-products'), { status: filterStatus }, { preserveState: true });
  };

  return (
    <AdminLayout user={auth.user} header="AFA Products Management">
      <Head title="AFA Products" />

      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div className="flex items-center gap-4">
            <Select value={filterStatus || 'all'} onValueChange={handleFilter}>
              <SelectTrigger className="w-48">
                <Filter className="h-4 w-4 mr-2" />
                <SelectValue placeholder="Filter by status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Status</SelectItem>
                <SelectItem value="IN_STOCK">In Stock</SelectItem>
                <SelectItem value="OUT_OF_STOCK">Out of Stock</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
            <DialogTrigger asChild>
              <Button>
                <Plus className="h-4 w-4 mr-2" />
                Add AFA Product
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Create New AFA Product</DialogTitle>
              </DialogHeader>
              <form onSubmit={handleCreate} className="space-y-4">
                <div>
                  <Label htmlFor="name">Product Name</Label>
                  <Input
                    id="name"
                    value={createData.name}
                    onChange={(e) => setCreateData('name', e.target.value)}
                    placeholder="Enter product name"
                  />
                  {createErrors.name && <p className="text-sm text-red-600">{createErrors.name}</p>}
                </div>
                <div>
                  <Label htmlFor="price">Price</Label>
                  <Input
                    id="price"
                    type="number"
                    step="0.01"
                    value={createData.price}
                    onChange={(e) => setCreateData('price', e.target.value)}
                    placeholder="Enter price"
                  />
                  {createErrors.price && <p className="text-sm text-red-600">{createErrors.price}</p>}
                </div>
                <div>
                  <Label htmlFor="status">Status</Label>
                  <Select value={createData.status} onValueChange={(value) => setCreateData('status', value as 'IN_STOCK' | 'OUT_OF_STOCK')}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="IN_STOCK">In Stock</SelectItem>
                      <SelectItem value="OUT_OF_STOCK">Out of Stock</SelectItem>
                    </SelectContent>
                  </Select>
                  {createErrors.status && <p className="text-sm text-red-600">{createErrors.status}</p>}
                </div>
                <div className="flex justify-end gap-2">
                  <Button type="button" variant="outline" onClick={() => setIsCreateOpen(false)}>
                    Cancel
                  </Button>
                  <Button type="submit" disabled={createProcessing}>
                    {createProcessing ? 'Creating...' : 'Create'}
                  </Button>
                </div>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>AFA Products ({afaProducts.data.length})</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b">
                    <th className="text-left p-2">Name</th>
                    <th className="text-left p-2">Price</th>
                    <th className="text-left p-2">Status</th>
                    <th className="text-left p-2">Created</th>
                    <th className="text-left p-2">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {afaProducts.data.map((product) => (
                    <tr key={product.id} className="border-b">
                      <td className="p-2 font-medium">{product.name}</td>
                      <td className="p-2">GHS {product.price}</td>
                      <td className="p-2">
                        <Badge variant={product.status === 'IN_STOCK' ? 'default' : 'destructive'}>
                          {product.status === 'IN_STOCK' ? 'In Stock' : 'Out of Stock'}
                        </Badge>
                      </td>
                      <td className="p-2">{new Date(product.created_at).toLocaleDateString()}</td>
                      <td className="p-2">
                        <div className="flex gap-2">
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => handleEdit(product)}
                          >
                            <Edit className="h-4 w-4" />
                          </Button>
                          <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => handleDelete(product)}
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>

        <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Edit AFA Product</DialogTitle>
            </DialogHeader>
            <form onSubmit={handleUpdate} className="space-y-4">
              <div>
                <Label htmlFor="edit-name">Product Name</Label>
                <Input
                  id="edit-name"
                  value={editData.name}
                  onChange={(e) => setEditData('name', e.target.value)}
                  placeholder="Enter product name"
                />
                {editErrors.name && <p className="text-sm text-red-600">{editErrors.name}</p>}
              </div>
              <div>
                <Label htmlFor="edit-price">Price</Label>
                <Input
                  id="edit-price"
                  type="number"
                  step="0.01"
                  value={editData.price}
                  onChange={(e) => setEditData('price', e.target.value)}
                  placeholder="Enter price"
                />
                {editErrors.price && <p className="text-sm text-red-600">{editErrors.price}</p>}
              </div>
              <div>
                <Label htmlFor="edit-status">Status</Label>
                <Select value={editData.status} onValueChange={(value) => setEditData('status', value as 'IN_STOCK' | 'OUT_OF_STOCK')}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="IN_STOCK">In Stock</SelectItem>
                    <SelectItem value="OUT_OF_STOCK">Out of Stock</SelectItem>
                  </SelectContent>
                </Select>
                {editErrors.status && <p className="text-sm text-red-600">{editErrors.status}</p>}
              </div>
              <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={() => setIsEditOpen(false)}>
                  Cancel
                </Button>
                <Button type="submit" disabled={editProcessing}>
                  {editProcessing ? 'Updating...' : 'Update'}
                </Button>
              </div>
            </form>
          </DialogContent>
        </Dialog>
      </div>
    </AdminLayout>
  );
}