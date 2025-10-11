import DashboardLayout from '../../layouts/DashboardLayout';
import { Head } from '@inertiajs/react';
import { PageProps } from '@/types';
import React from 'react';
import { FaWhatsapp, FaTelegramPlane } from 'react-icons/fa';

export default function JoinUs({ auth }: PageProps) {
    return (
        <DashboardLayout
            user={auth.user}
            header={
                <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Join Us
                </h2>
            }
        >
            <Head title="Join Us" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* WhatsApp Card */}
                        <div className="bg-green-50 dark:bg-green-900 p-6 rounded-lg shadow-md">
                            <div className="flex items-center space-x-3 mb-4">
                                <div className="bg-green-600 text-white p-2 rounded">
                                    <FaWhatsapp className="w-6 h-6" />
                                </div>
                                <h3 className="text-lg font-semibold text-gray-800 dark:text-white">
                                    Whatsapp community
                                </h3>
                            </div>
                            <a
                                href="https://whatsapp.com/channel/0029VagHPxD8V0tjZXg3M80D"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-block mt-2 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-4 py-2 rounded-md font-medium"
                            >
                                <span className="mr-1">↗</span> Join Whatsapp community
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
