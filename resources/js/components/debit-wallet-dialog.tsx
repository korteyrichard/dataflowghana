import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { User } from '@/types';
import { Minus } from 'lucide-react';

interface DebitWalletDialogProps {
  user: User;
}

const DebitWalletDialog: React.FC<DebitWalletDialogProps> = ({ user }) => {
  const [open, setOpen] = useState(false);
  const { data, setData, post, processing, errors, reset } = useForm({
    amount: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post(route('admin.users.debit', user.id), {
      onSuccess: () => {
        setOpen(false);
        reset();
      },
    });
  };

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button variant="outline" size="sm" className="text-red-600 hover:text-red-700">
          <Minus className="h-4 w-4 mr-1" />
          Debit
        </Button>
      </DialogTrigger>
      <DialogContent className="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Debit Wallet</DialogTitle>
          <DialogDescription>
            Remove funds from {user.name}'s wallet. Current balance: ₵{user.wallet_balance || '0.00'}
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit}>
          <div className="grid gap-4 py-4">
            <div className="grid grid-cols-4 items-center gap-4">
              <Label htmlFor="amount" className="text-right">
                Amount
              </Label>
              <Input
                id="amount"
                type="number"
                step="0.01"
                min="0.01"
                max={user.wallet_balance || 0}
                placeholder="0.00"
                value={data.amount}
                onChange={(e) => setData('amount', e.target.value)}
                className="col-span-3"
                required
              />
            </div>
            {errors.amount && (
              <p className="text-sm text-red-600">{errors.amount}</p>
            )}
          </div>
          <DialogFooter>
            <Button type="submit" disabled={processing}>
              {processing ? 'Processing...' : 'Debit Wallet'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
};

export default DebitWalletDialog;