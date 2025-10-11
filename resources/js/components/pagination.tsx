import React from 'react';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

interface PaginationData {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
  links: PaginationLink[];
}

interface PaginationProps {
  data: PaginationData;
  preserveState?: boolean;
}

export default function Pagination({ data, preserveState = true }: PaginationProps) {
  if (data.last_page <= 1) return null;

  return (
    <div className="flex flex-col sm:flex-row items-center justify-between gap-4 mt-6">
      <div className="text-sm text-gray-700 dark:text-gray-300">
        Showing {data.from} to {data.to} of {data.total} results
      </div>
      
      <div className="flex items-center space-x-1">
        {data.links.map((link, index) => {
          if (link.label === '&laquo; Previous') {
            return (
              <Link
                key={index}
                href={link.url || '#'}
                preserveState={preserveState}
                className={`px-3 py-2 rounded-md text-sm font-medium flex items-center gap-1 ${
                  link.url
                    ? 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                    : 'text-gray-400 dark:text-gray-600 cursor-not-allowed'
                }`}
              >
                <ChevronLeft className="w-4 h-4" />
                Previous
              </Link>
            );
          }
          
          if (link.label === 'Next &raquo;') {
            return (
              <Link
                key={index}
                href={link.url || '#'}
                preserveState={preserveState}
                className={`px-3 py-2 rounded-md text-sm font-medium flex items-center gap-1 ${
                  link.url
                    ? 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                    : 'text-gray-400 dark:text-gray-600 cursor-not-allowed'
                }`}
              >
                Next
                <ChevronRight className="w-4 h-4" />
              </Link>
            );
          }
          
          if (link.label === '...') {
            return (
              <span key={index} className="px-3 py-2 text-gray-500 dark:text-gray-400">
                ...
              </span>
            );
          }
          
          return (
            <Link
              key={index}
              href={link.url || '#'}
              preserveState={preserveState}
              className={`px-3 py-2 rounded-md text-sm font-medium ${
                link.active
                  ? 'bg-blue-600 text-white'
                  : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
              }`}
            >
              {link.label}
            </Link>
          );
        })}
      </div>
    </div>
  );
}