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
  

    return (
        <DashboardLayout user={auth.user} header="Terms of Service">
            <Head title="Terms of Service" />

            <div className="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                {/* Working Hours Section */}
                <div className="mb-6">
                    <h2 className="text-lg font-semibold mb-2 text-gray-900 dark:text-gray-100">Working Hours</h2>
                    <ul className="space-y-1 pl-6 text-gray-700 dark:text-gray-300">
                        <li>Monday - Saturday: 7:30am - 8:30pm</li>
                        <li>Sunday: 8am - 9pm</li>
                    </ul>
                </div>

                {/* Complaints Section */}
                <div>
                    <h2 className="text-lg font-semibold mb-2 text-gray-900 dark:text-gray-100">Complaints</h2>
                    <ol className="list-decimal space-y-2 pl-6 text-gray-700 dark:text-gray-300">
                    <li>Complaints made after end of the day will not be accepted.</li>
                    <li>
                        You should report when order is delivered but not received after 2
                        hours of placing the order.
                    </li>
                    <li>If order is pending no need to complain.</li>
                    </ol>
                </div>

      {/* Not Support Section */}
                <div>
                    <h2 className="text-lg font-semibold mb-2 text-gray-900 dark:text-gray-100">NOT SUPPORT</h2>
                    <p className="mb-2 text-gray-700 dark:text-gray-300">
                    Our data offers do not support the following:
                    </p>
                    <ol className="list-decimal space-y-2 pl-6 text-gray-700 dark:text-gray-300">
                    <li>SIM with airtime debt</li>
                    <li>Router SIM</li>
                    <li>Broadband SIM</li>
                    <li>EVD SIM</li>
                    <li>Transfer SIM</li>
                    <li>Merchant SIM</li>
                    <li>Wrong numbers</li>
                    <li>Turbonet SIM</li>
                    </ol>

                    <p className='font-bold mt-4 mb-4'>
                        <span > NOTE:</span> Dealers with GHS 1,000.00 plus daily sales should contact admin for special role to access low prices
                    </p>
                    <p className="mt-3 text-red-600 dark:text-red-400 font-medium">
                    Therefore do not place order for any of the above, if you do so the
                    order will not be delivered and you won't have any refund or
                    replacement.
                    </p>

                    
                </div>

                <p className="font-semibold text-center text-green-600 dark:text-green-400">Thank you</p>
                    </div>

        </DashboardLayout>
    );
}