import React, { useState } from 'react';

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

interface DataCardProps {
  title: string;
  value: string;
  currency?: string;
  expiry?: string;
  network?: string;
  productId: number;
  cartItems?: CartItem[];
}

const DataCard: React.FC<DataCardProps> = ({ productId, title, value, currency, expiry, network, cartItems = [] }) => {
  const [showDetails, setShowDetails] = useState(false);
  const [beneficiaryPhone, setBeneficiaryPhone] = useState('');

  const handleAddToCart = () => {
    // Check if phone number already exists in cart
    const existingCartItem = cartItems.find(item => item.beneficiary_number === beneficiaryPhone);
    
    if (existingCartItem) {
      alert('This phone number is already in your cart');
      return;
    }
    
    // Add logic to handle adding to cart with beneficiary phone
    console.log('Adding to cart for', beneficiaryPhone);
  };

  return (
    <div className="md:w-50 rounded-lg bg-yellow-500 hover:bg-yellow-700 p-4 shadow-md transition-all duration-200 hover:scale-105 hover:shadow-lg">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold text-white">{title}</h2>
          <p className="text-sm text-white">Data Bundle</p>
        </div>
        <div className="rounded-md bg-yellow-200 p-1">
          <p className="text-xs font-bold text-yellow-500">{network}</p>
        </div>
      </div>
      <div className="mt-2">
        <h3 className="text-xl font-semibold text-white">
          {currency} {value}
        </h3>
        <p className="text-xs text-white">{expiry}</p>
      </div>
      {showDetails ? (
        <div>
          <input
            type="text"
            placeholder="Beneficiary Phone"
            value={beneficiaryPhone}
            onChange={(e) => setBeneficiaryPhone(e.target.value)}
            className="mt-4 w-full rounded-md bg-white hover:bg-amber-100 py-2 text-sm font-bold text-yellow-500 transition-colors duration-200 group-hover:text-white cursor-pointer"
          />
          <button
            onClick={handleAddToCart}
            className="mt-4 w-full rounded-md bg-white hover:bg-amber-100 py-2 text-sm font-bold text-yellow-500 transition-colors duration-200 group-hover:text-white cursor-pointer"
          >
            Add to Cart
          </button>
          <button
            onClick={() => setShowDetails(false)}
            className="mt-4 w-full rounded-md bg-white hover:bg-amber-100 py-2 text-sm font-bold text-yellow-500 transition-colors duration-200 group-hover:text-white cursor-pointer"
          >
            Cancel
          </button>
        </div>
      ) : (
        <button
          onClick={() => setShowDetails(true)}
          className="mt-4 w-full rounded-md bg-white hover:bg-amber-100 py-2 text-sm font-bold text-yellow-500 transition-colors duration-200 group-hover:text-white cursor-pointer"
        >
          Buy Bundle
        </button>
      )}
    </div>
  );
};

export default DataCard;
