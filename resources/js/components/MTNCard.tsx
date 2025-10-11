import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  DialogFooter,
  DialogClose,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';

interface CartItem {
  id: number;
  product_id: number;
  quantity: string;
  beneficiary_number: string;
  product: {
    name: string;
    price: number;
    network: string;
    expiry: string;
  };
}

// Update props to match product data
interface ProductCardProps {
  productId: number;
  name: string;
  quantity: string;
  currency?: string;
  expiry: string;
  network: string;
  price: number; // Added price prop
  cartItems: CartItem[];
}

const MTNCard: React.FC<ProductCardProps> = ({ productId, name, quantity, currency, expiry, network, price, cartItems }) => {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [beneficiaryNumber, setBeneficiaryNumber] = useState('');


  const handleAddToCart = () => {
    // Validate phone number format
    if (!/^\d{10}$/.test(beneficiaryNumber)) {
      alert('Phone number must be exactly 10 digits');
      return;
    }
    
    // Check if phone number already exists in cart
    const existingCartItem = cartItems.find(item => item.beneficiary_number === beneficiaryNumber);
    
    if (existingCartItem) {
      alert('This phone number is already in your cart');
      return;
    }
    
    console.log(typeof quantity);
    console.log(quantity)
    router.post(route('add.to.cart'), {
      product_id: productId,
      quantity: quantity,
      beneficiary_number: beneficiaryNumber,
    }, {
      onSuccess: () => {
        setIsModalOpen(false);
        setBeneficiaryNumber('');
      },
      onError: (errors) => {
        console.error('Error adding to cart:', errors);
      }
    });
  };

  return (
    <div className="md:w-50 rounded-lg bg-yellow-500 hover:bg-yellow-700 p-4 shadow-md transition-all duration-200 hover:scale-105 hover:shadow-lg">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold text-white">{quantity}</h2>
          <p className="text-sm text-white">Data Bundle</p>
        </div>
        <div className="rounded-md bg-yellow-200 p-1">
          <p className="text-xs font-bold text-yellow-500">{network}</p>
        </div>
      </div>
      <div className="mt-2">
        <h3 className="text-xl font-semibold text-white">
          GHC : {price}
        </h3>
        <p className="text-xs text-white">{expiry}</p>
      </div>
      <Dialog open={isModalOpen} onOpenChange={setIsModalOpen}>
        <DialogTrigger asChild>
          <button
            className="mt-4 w-full rounded-md bg-white hover:bg-amber-100 py-2 text-sm font-bold text-yellow-500 transition-colors duration-200 group-hover:text-white cursor-pointer"
          >
            Add to Cart
          </button>
        </DialogTrigger>
        <DialogContent className="sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle>Buy MTN Bundle</DialogTitle>
          </DialogHeader>
          <div className="grid gap-4 py-4">
            <h3 className="text-lg font-semibold">Purchase MTN Bundle</h3>
            <div className="grid grid-cols-2 gap-2">
              <div>Data Amount</div>
              <div className="text-right">{name}</div>
              <div>Validity</div>
              <div className="text-right">{expiry}</div>
              <div>Price</div>
              <div className="text-right">{currency} {quantity}</div>
            </div>
            <div className="grid gap-2">
              <Label htmlFor="beneficiary_number">Recipient Phone Number</Label>
              <Input
                id="beneficiary_number"
                type="tel"
                placeholder="Enter phone number"
                value={beneficiaryNumber}
                onChange={(e) => {
                  const value = e.target.value.replace(/\D/g, '').slice(0, 10);
                  setBeneficiaryNumber(value);
                }}
                maxLength={10}
              />
            </div>
          </div>
          <DialogFooter>
            <DialogClose asChild>
              <Button type="button" variant="outline">
                Cancel
              </Button>
            </DialogClose>
            <Button type="submit" onClick={handleAddToCart}>
              Add To Cart
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default MTNCard;
