import React, { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';

interface NetworkCardsProps {
  onAddToCart: (data: any) => void;
  products?: Array<{
    id: number;
    name: string;
    price: number;
    network: string;
    expiry: string;
    product_type: string;
  }>;
}

interface Network {
  id: string;
  name: string;
  icon: string;
  color: string;
  bgColor: string;
}

const NetworkCards: React.FC<NetworkCardsProps> = ({ onAddToCart, products = [] }) => {
  const [expandedCard, setExpandedCard] = useState<string | null>(null);
  const [showBulkInput, setShowBulkInput] = useState<string | null>(null);
  const [phoneNumber, setPhoneNumber] = useState('');
  const [bundleSize, setBundleSize] = useState('');
  const [bulkNumbers, setBulkNumbers] = useState('');
  const [excelFile, setExcelFile] = useState<File | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const [availableSizes, setAvailableSizes] = useState<Array<{value: string, label: string, price: number}>>([]);
  const [loadingSizes, setLoadingSizes] = useState(false);
  const [isDragOver, setIsDragOver] = useState(false);

  const networks: Network[] = [
    { 
      id: 'MTN', 
      name: 'MTN', 
      icon: '/mtnlogo.jpeg', 
      color: 'text-yellow-600',
      bgColor: 'bg-gradient-to-br from-yellow-400 to-yellow-600'
    },
    { 
      id: 'TELECEL', 
      name: 'Telecel', 
      icon: '/telecellogo.png', 
      color: 'text-red-600',
      bgColor: 'bg-gradient-to-br from-red-400 to-red-600'
    },
    { 
      id: 'ISHARE', 
      name: 'AT - iShare', 
      icon: '/atlogo.png', 
      color: 'text-blue-600',
      bgColor: 'bg-gradient-to-br from-blue-400 to-blue-600'
    },
    { 
      id: 'BIGTIME', 
      name: 'AT - BigTime', 
      icon: '/atlogo.png', 
      color: 'text-purple-600',
      bgColor: 'bg-gradient-to-br from-purple-400 to-purple-600'
    }
  ];

  const fetchBundleSizes = async (network: string) => {
    setLoadingSizes(true);
    try {
      const response = await fetch(`/api/bundle-sizes?network=${network}`);
      const data = await response.json();
      if (data.success) {
        setAvailableSizes(data.sizes);
      } else {
        setAvailableSizes([]);
      }
    } catch (error) {
      console.error('Error fetching bundle sizes:', error);
      setAvailableSizes([]);
    } finally {
      setLoadingSizes(false);
    }
  };

  const handleCardClick = (networkId: string) => {
    if (expandedCard === networkId) {
      setExpandedCard(null);
    } else {
      setExpandedCard(networkId);
      fetchBundleSizes(networkId);
      // Reset form when switching networks
      setPhoneNumber('');
      setBundleSize('');
      setBulkNumbers('');
      setExcelFile(null);
      setShowBulkInput(null);
    }
  };

  const handleProcessExcel = async (networkId: string) => {
    if (!excelFile) return;
    
    setIsProcessing(true);
    const formData = new FormData();
    formData.append('file', excelFile);
    formData.append('network', networkId);
    
    try {
      const response = await fetch('/process-excel-to-cart', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: formData,
      });
      
      const data = await response.json();
      if (data.success) {
        setExcelFile(null);
        router.reload();
      } else {
        alert(data.message || 'Failed to process Excel file');
      }
    } catch (error) {
      alert('Error processing Excel file');
    } finally {
      setIsProcessing(false);
    }
  };

  const handleProcessBulk = async (networkId: string) => {
    if (!bulkNumbers.trim()) return;
    
    setIsProcessing(true);
    try {
      const response = await fetch('/process-bulk-to-cart', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          numbers: bulkNumbers,
          network: networkId
        }),
      });
      
      const data = await response.json();
      if (data.success) {
        setBulkNumbers('');
        router.reload();
      } else {
        alert(data.message || 'Failed to process bulk numbers');
      }
    } catch (error) {
      alert('Error processing bulk numbers');
    } finally {
      setIsProcessing(false);
    }
  };

  const handleAddSingle = (networkId: string) => {
    if (!phoneNumber || !bundleSize) return;
    
    router.post(route('add.to.cart'), {
      network: networkId,
      quantity: bundleSize,
      beneficiary_number: phoneNumber,
    }, {
      onSuccess: () => {
        setPhoneNumber('');
        setBundleSize('');
      },
      onError: (errors) => {
        console.error('Error adding to cart:', errors);
      }
    });
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (files && files.length > 0) {
      const file = files[0];
      if (file.type === 'text/csv' || 
          file.type === 'application/csv' ||
          file.name.endsWith('.csv')) {
        setExcelFile(file);
      } else {
        alert('Please select a valid CSV file (.csv)');
        e.target.value = '';
      }
    }
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragOver(true);
  };

  const handleDragLeave = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragOver(false);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
      const file = files[0];
      if (file.type === 'text/csv' || 
          file.type === 'application/csv' ||
          file.name.endsWith('.csv')) {
        setExcelFile(file);
      } else {
        alert('Please select a valid CSV file (.csv)');
      }
    }
  };

  return (
    <div className="space-y-4">
      <h3 className="text-lg sm:text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4 sm:mb-6">Select Network</h3>
      
      {networks.map((network) => (
        <div key={network.id} className="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
          {/* Network Card Header */}
          <div 
            className={`${network.bgColor} p-4 sm:p-6 cursor-pointer transition-all duration-300 hover:shadow-lg`}
            onClick={() => handleCardClick(network.id)}
          >
            <div className="flex items-center justify-between">
              <div className="flex items-center space-x-3 sm:space-x-4">
                <div className="w-12 h-12 sm:w-16 sm:h-16 rounded-full flex items-center justify-center shadow-lg">
                  <img src={network.icon} alt={`${network.name} logo`} className="w-8 h-8 rounded-lg sm:w-12 sm:h-12 object-contain" />
                </div>
                <div>
                  <h4 className="text-lg sm:text-2xl font-bold text-white">{network.name}</h4>
                  <p className="text-white/80 text-sm">Click to expand</p>
                </div>
              </div>
              <div className="text-white">
                <svg 
                  className={`w-5 h-5 sm:w-6 sm:h-6 transition-transform duration-300 ${expandedCard === network.id ? 'rotate-180' : ''}`} 
                  fill="none" 
                  stroke="currentColor" 
                  viewBox="0 0 24 24"
                >
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
              </div>
            </div>
          </div>

          {/* Expanded Content */}
          {expandedCard === network.id && (
            <div className={`${network.bgColor} p-0`}>
              {/* Network Header */}
              

              <div className="p-4 sm:p-6 space-y-4 sm:space-y-6">
                {/* Excel Upload Section */}
                <div>
                  <label className="block text-sm font-medium mb-2 text-white">
                    Upload Excel File
                  </label>
                  <div className="">
                    <input
                      type="file"
                      accept=".csv"
                      onChange={handleFileChange}
                      className="flex-1 px-3 py-2 bg-white rounded border-0 text-gray-700 text-sm"
                      placeholder="Choose File"
                    />
                    {/* <span className="px-3 py-2 bg-white bg-opacity-20 text-slate-700 rounded text-sm truncate">
                      {excelFile ? excelFile.name : 'No file chosen'}
                    </span> */}
                  </div>
                  <Button 
                    onClick={() => handleProcessExcel(network.id)}
                    disabled={!excelFile || isProcessing}
                    className="mt-3 px-6 py-2 bg-gray-800 hover:bg-gray-900 text-white rounded font-medium"
                  >
                    {isProcessing ? 'Processing...' : 'Upload'}
                  </Button>
                </div>

                {/* Bulk Text Input Section */}
                <div>
                  <button 
                    onClick={() => setShowBulkInput(showBulkInput === network.id ? null : network.id)}
                    className="px-4 py-2 bg-gray-800 text-white rounded font-medium mb-3"
                  >
                    📦 Text Input Orders
                  </button>
                  {showBulkInput === network.id && (
                    <div>
                      <Textarea
                        placeholder="0241234567 5
0558765432 10 
0501234567 3"
                        value={bulkNumbers}
                        onChange={(e) => setBulkNumbers(e.target.value)}
                        rows={4}
                        className="w-full bg-white text-gray-700"
                      />
                      <Button 
                        onClick={() => handleProcessBulk(network.id)}
                        disabled={!bulkNumbers.trim() || isProcessing}
                        className="mt-3 px-6 py-2 bg-gray-800 hover:bg-gray-900 text-white rounded font-medium"
                      >
                        {isProcessing ? 'Processing...' : 'Add Bulk to Cart'}
                      </Button>
                    </div>
                  )}
                </div>

                {/* Single Order Section */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium mb-2 text-white">
                      Beneficiary Phone Number <span className="text-red-300">(Required)</span>
                    </label>
                    <Input
                      type="tel"
                      placeholder="Enter Beneficiary Number here"
                      value={phoneNumber}
                      onChange={(e) => setPhoneNumber(e.target.value.replace(/\D/g, '').slice(0, 10))}
                      maxLength={10}
                      className="bg-white text-gray-700"
                    />
                  </div>
                  
                  <div>
                    <label className="block text-sm font-medium mb-2 text-white">
                      Data Volume <span className="text-red-300">(Required)</span>
                    </label>
                    <Select value={bundleSize} onValueChange={setBundleSize}>
                      <SelectTrigger className="bg-white text-gray-700">
                        <SelectValue placeholder={loadingSizes ? "Loading..." : "Select a package"} />
                      </SelectTrigger>
                      <SelectContent>
                        {availableSizes.map((size) => (
                          <SelectItem key={size.value} value={size.value}>
                            {size.label} - GHS {size.price}
                          </SelectItem>
                        ))}
                        {availableSizes.length === 0 && !loadingSizes && (
                          <SelectItem value="no-sizes" disabled>
                            No sizes available
                          </SelectItem>
                        )}
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                <div className="bg-yellow-100 p-3 rounded text-xs text-yellow-800">
                  PLEASE VERIFY THE PHONE NUMBER BEFORE PROCEEDING TO AVOID SENDING DATA TO A WRONG NUMBER
                </div>

                <Button 
                  onClick={() => handleAddSingle(network.id)}
                  disabled={!phoneNumber || !bundleSize || isProcessing}
                  className="w-full py-3 bg-gray-800 hover:bg-gray-900 text-white rounded font-semibold"
                >
                  Add to Basket
                </Button>
              </div>
            </div>
          )}
        </div>
      ))}
    </div>
  );
};

export default NetworkCards;