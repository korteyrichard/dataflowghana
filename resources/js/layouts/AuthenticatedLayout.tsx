import React, { PropsWithChildren, useState } from 'react';
import { Link } from '@inertiajs/react';

import { icons } from 'lucide-react';

interface AuthenticatedLayoutProps extends PropsWithChildren {
    user: {
        id: number;
        name: string;
        email: string;
        role: string; // Added role here
    };
    header?: React.ReactNode;
}

type IconName = keyof typeof icons;



export default function AuthenticatedLayout({ user, header, children }: AuthenticatedLayoutProps) {
   
    return (
        <main className="min-h-screen bg-gray-100 dark:bg-gray-900">
               {children}
        </main>
        
    );
}