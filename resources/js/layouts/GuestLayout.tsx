import { PropsWithChildren } from 'react';
import { Link } from '@inertiajs/react';
import { Icon } from '@/components/ui/icon';
import { ThemeToggle } from '@/components/theme-toggle';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="min-h-screen flex flex-col sm:justify-center items-center pt-[50px] pb-[50px] sm:pt-0 bg-gray-100 dark:bg-gray-900">
            <div className="absolute top-4 right-4">
                <ThemeToggle />
            </div>
            <div>
                <Link href="/">
                    <div className="text-gray-500 text-2xl font-bold"></div>
                </Link>
            </div>

            <div className="w-full sm:max-w-md mt-6 px-6 py-4 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg">
                {children}
            </div>
        </div>
    );
}
