import React, { useState } from "react";
import { Head, useForm } from "@inertiajs/react";
import { SettingsLayout } from "@/layouts/settings-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { PageProps } from "@/types";
import { AlertCircle, CheckCircle2, EyeIcon, EyeOffIcon } from "lucide-react";
import { Alert, AlertDescription } from "@/components/ui/alert";

export default function Password({ auth }: PageProps) {
  const user = auth.user;
  
  const { data, setData, put, errors, processing, reset, recentlySuccessful } = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  const [showCurrentPassword, setShowCurrentPassword] = useState(false);
  const [showNewPassword, setShowNewPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  // Password strength indicators
  const hasMinLength = data.password.length >= 8;
  const hasUpperCase = /[A-Z]/.test(data.password);
  const hasLowerCase = /[a-z]/.test(data.password);
  const hasNumber = /[0-9]/.test(data.password);
  const hasSpecialChar = /[^A-Za-z0-9]/.test(data.password);
  const passwordsMatch = data.password === data.password_confirmation && data.password !== '';

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    put(route('password.update'), {
      onSuccess: () => reset(),
    });
  };

  return (
    <SettingsLayout
      user={user}
      header={
        <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Password Settings
        </h2>
      }
    >
      <Head title="Password Settings" />

      <div>
        <Card>
          <CardHeader>
            <CardTitle>Update Password</CardTitle>
            <CardDescription>
              Ensure your account is using a long, random password to stay secure.
            </CardDescription>
          </CardHeader>
          <form onSubmit={submit}>
            <CardContent className="space-y-6">
              {recentlySuccessful && (
                <Alert className="bg-green-50 border-green-200">
                  <CheckCircle2 className="h-4 w-4 text-green-600" />
                  <AlertDescription className="text-green-700">Password updated successfully.</AlertDescription>
                </Alert>
              )}
              
              <div className="space-y-2">
                <Label htmlFor="current_password">Current Password</Label>
                <div className="relative">
                  <Input
                    id="current_password"
                    type={showCurrentPassword ? "text" : "password"}
                    value={data.current_password}
                    onChange={(e) => setData('current_password', e.target.value)}
                    required
                    className="pr-10"
                  />
                  <button
                    type="button"
                    onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                    className="absolute inset-y-0 right-0 flex items-center pr-3"
                  >
                    {showCurrentPassword ? (
                      <EyeOffIcon className="h-4 w-4 text-gray-400" />
                    ) : (
                      <EyeIcon className="h-4 w-4 text-gray-400" />
                    )}
                  </button>
                </div>
                {errors.current_password && (
                  <div className="flex items-center text-sm text-red-600 mt-1">
                    <AlertCircle className="h-4 w-4 mr-1" />
                    {errors.current_password}
                  </div>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="password">New Password</Label>
                <div className="relative">
                  <Input
                    id="password"
                    type={showNewPassword ? "text" : "password"}
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    required
                    className="pr-10"
                  />
                  <button
                    type="button"
                    onClick={() => setShowNewPassword(!showNewPassword)}
                    className="absolute inset-y-0 right-0 flex items-center pr-3"
                  >
                    {showNewPassword ? (
                      <EyeOffIcon className="h-4 w-4 text-gray-400" />
                    ) : (
                      <EyeIcon className="h-4 w-4 text-gray-400" />
                    )}
                  </button>
                </div>
                {errors.password && (
                  <div className="flex items-center text-sm text-red-600 mt-1">
                    <AlertCircle className="h-4 w-4 mr-1" />
                    {errors.password}
                  </div>
                )}
                
                {/* Password strength indicators */}
                {data.password && (
                  <div className="mt-2 space-y-1">
                    <p className="text-xs font-medium text-gray-500">Password requirements:</p>
                    <ul className="space-y-1 text-xs">
                      <li className={`flex items-center ${hasMinLength ? 'text-green-600' : 'text-gray-500'}`}>
                        <CheckCircle2 className={`h-3 w-3 mr-1 ${hasMinLength ? 'text-green-600' : 'text-gray-300'}`} />
                        At least 8 characters
                      </li>
                      <li className={`flex items-center ${hasUpperCase ? 'text-green-600' : 'text-gray-500'}`}>
                        <CheckCircle2 className={`h-3 w-3 mr-1 ${hasUpperCase ? 'text-green-600' : 'text-gray-300'}`} />
                        At least one uppercase letter
                      </li>
                      <li className={`flex items-center ${hasLowerCase ? 'text-green-600' : 'text-gray-500'}`}>
                        <CheckCircle2 className={`h-3 w-3 mr-1 ${hasLowerCase ? 'text-green-600' : 'text-gray-300'}`} />
                        At least one lowercase letter
                      </li>
                      <li className={`flex items-center ${hasNumber ? 'text-green-600' : 'text-gray-500'}`}>
                        <CheckCircle2 className={`h-3 w-3 mr-1 ${hasNumber ? 'text-green-600' : 'text-gray-300'}`} />
                        At least one number
                      </li>
                      <li className={`flex items-center ${hasSpecialChar ? 'text-green-600' : 'text-gray-500'}`}>
                        <CheckCircle2 className={`h-3 w-3 mr-1 ${hasSpecialChar ? 'text-green-600' : 'text-gray-300'}`} />
                        At least one special character
                      </li>
                    </ul>
                  </div>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="password_confirmation">Confirm Password</Label>
                <div className="relative">
                  <Input
                    id="password_confirmation"
                    type={showConfirmPassword ? "text" : "password"}
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    required
                    className="pr-10"
                  />
                  <button
                    type="button"
                    onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                    className="absolute inset-y-0 right-0 flex items-center pr-3"
                  >
                    {showConfirmPassword ? (
                      <EyeOffIcon className="h-4 w-4 text-gray-400" />
                    ) : (
                      <EyeIcon className="h-4 w-4 text-gray-400" />
                    )}
                  </button>
                </div>
                {data.password && data.password_confirmation && !passwordsMatch && (
                  <div className="flex items-center text-sm text-red-600 mt-1">
                    <AlertCircle className="h-4 w-4 mr-1" />
                    Passwords do not match
                  </div>
                )}
                {data.password && data.password_confirmation && passwordsMatch && (
                  <div className="flex items-center text-sm text-green-600 mt-1">
                    <CheckCircle2 className="h-4 w-4 mr-1" />
                    Passwords match
                  </div>
                )}
              </div>
            </CardContent>
            <CardFooter>
              <Button type="submit" disabled={processing}>
                Save
              </Button>
            </CardFooter>
          </form>
        </Card>
      </div>
    </SettingsLayout>
  );
}