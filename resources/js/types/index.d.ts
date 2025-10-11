import { LucideIcon, icons } from 'lucide-react';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: string;
    icon?: keyof typeof icons;
    isActive?: boolean;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: Auth;
    users: User[];
    ziggy: Config & { location: string };
};

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    phone: string | null;
    role: 'customer' | 'agent' | 'admin';
    wallet_balance: number;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}

export interface Transaction {
    id: number;
    user_id: number;
    order_id: number | null;
    amount: string;
    status: 'pending' | 'completed' | 'failed' | 'cancelled';
    type: 'wallet_topup' | 'order_payment' | 'agent_fee' | 'refund';
    description: string;
    reference: string | null;
    created_at: string;
    updated_at: string;
    order?: any;
    user?: User;
}
