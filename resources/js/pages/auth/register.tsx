import { useEffect, FormEventHandler, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        phone: '',
        business_name: '',
        password: '',
        password_confirmation: '',
    });
    const [showPassword, setShowPassword] = useState(false);
    const [showPasswordConfirmation, setShowPasswordConfirmation] = useState(false);

    useEffect(() => {
        return () => {
            reset('password', 'password_confirmation');
        };
    }, []);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'));
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 flex items-center justify-center p-4">
            <Head title="Register" />
            <div className="w-full max-w-sm">
                <div className="bg-white rounded-lg shadow-lg p-8">
                    <div className="flex flex-col items-center mb-8">
                        <img src='/dataflow.jpg' alt="Logo" className="w-24 h-24 mb-6 rounded-lg" />
                    </div>
                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <Input
                                id="name"
                                name="name"
                                placeholder="Full Name"
                                value={data.name}
                                autoComplete="name"
                                onChange={(e) => setData('name', e.target.value)}
                                required
                                className="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            {errors.name && <div className="text-red-500 text-xs mt-1">{errors.name}</div>}
                        </div>
                        <div>
                            <Input
                                id="business_name"
                                name="business_name"
                                placeholder="Business Name (Optional)"
                                value={data.business_name}
                                autoComplete="organization"
                                onChange={(e) => setData('business_name', e.target.value)}
                                className="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            {errors.business_name && <div className="text-red-500 text-xs mt-1">{errors.business_name}</div>}
                        </div>
                        <div>
                            <Input
                                id="phone"
                                name="phone"
                                placeholder="Phone Number"
                                value={data.phone}
                                autoComplete="tel"
                                onChange={(e) => setData('phone', e.target.value)}
                                className="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            {errors.phone && <div className="text-red-500 text-xs mt-1">{errors.phone}</div>}
                        </div>
                        <div>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                placeholder="Email Address"
                                value={data.email}
                                autoComplete="username"
                                onChange={(e) => setData('email', e.target.value)}
                                required
                                className="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            {errors.email && <div className="text-red-500 text-xs mt-1">{errors.email}</div>}
                        </div>
                        <div>
                            <div className="relative">
                                <Input
                                    id="password"
                                    type={showPassword ? "text" : "password"}
                                    name="password"
                                    placeholder="Password"
                                    value={data.password}
                                    autoComplete="new-password"
                                    onChange={(e) => setData('password', e.target.value)}
                                    required
                                    className="w-full px-4 py-3 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    {showPassword ? (
                                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" strokeWidth="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"/></svg>
                                    ) : (
                                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke="currentColor" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    )}
                                </button>
                            </div>
                            {errors.password && <div className="text-red-500 text-xs mt-1">{errors.password}</div>}
                        </div>
                        <div>
                            <div className="relative">
                                <Input
                                    id="password_confirmation"
                                    type={showPasswordConfirmation ? "text" : "password"}
                                    name="password_confirmation"
                                    placeholder="Confirm Password"
                                    value={data.password_confirmation}
                                    autoComplete="new-password"
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    required
                                    className="w-full px-4 py-3 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPasswordConfirmation(!showPasswordConfirmation)}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    {showPasswordConfirmation ? (
                                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" strokeWidth="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"/></svg>
                                    ) : (
                                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke="currentColor" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    )}
                                </button>
                            </div>
                            {errors.password_confirmation && <div className="text-red-500 text-xs mt-1">{errors.password_confirmation}</div>}
                        </div>
                        <Button 
                            className="w-full bg-slate-800 hover:bg-slate-700 text-white py-3 rounded-md font-medium transition-colors" 
                            disabled={processing}
                        >
                            Create Account →
                        </Button>
                    </form>
                    <div className="mt-6 text-center space-y-2">
                        <div className="text-sm text-gray-600">
                            Already have an account? <Link href={route('login')} className="text-blue-600 hover:underline">Sign in</Link>
                        </div>
                        <div className="text-sm">
                            <Link href="/" className="text-blue-600 hover:underline">Home</Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
