import React, { useState } from "react";
import { Head, useForm } from "@inertiajs/react";
import { SettingsLayout } from "@/layouts/settings-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { PageProps } from "@/types";
// Removed avatar imports

interface ProfileProps extends PageProps {
  mustVerifyEmail: boolean;
  status?: string;
}

export default function Profile({ auth, mustVerifyEmail, status }: ProfileProps) {
  const user = auth.user;
  
  const { data, setData, patch, errors, processing, setError, clearErrors } = useForm({
    name: user.name,
    email: user.email,
    phone: user.phone || '',
    business_name: user.business_name || '',
  });
  


  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    
    // Ensure name and email are not empty
    if (!data.name || !data.name.trim()) {
      setError('name', 'The name field is required.');
      return;
    }
    
    if (!data.email || !data.email.trim()) {
      setError('email', 'The email field is required.');
      return;
    }
    
    // Log the data being submitted
    console.log('Submitting form data:', data);
    
    // Make sure we're sending the data explicitly
    patch(route('profile.update'), {
      name: data.name,
      email: data.email,
      phone: data.phone || '',
      business_name: data.business_name || ''
    }, {
      preserveScroll: true,
      preserveState: true,
      onError: (errors) => {
        console.error('Form submission errors:', errors);
      },
      onSuccess: () => {
        console.log('Profile updated successfully');
      }
    });
  };

  return (
    <SettingsLayout
      user={user}
      header={
        <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Profile Settings
        </h2>
      }
    >
      <Head title="Profile Settings" />

      <div>
        <Card>
          <CardHeader>
            <CardTitle>Profile Information</CardTitle>
            <CardDescription>
              Update your account's profile information and email address.
            </CardDescription>
          </CardHeader>
          <form onSubmit={submit}>
            <CardContent className="space-y-6">

              
              <div className="space-y-2">
                <Label htmlFor="name">Name</Label>
                <Input
                  id="name"
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  required
                />
                {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
              </div>

              <div className="space-y-2">
                <Label htmlFor="email">Email</Label>
                <Input
                  id="email"
                  type="email"
                  value={data.email}
                  onChange={(e) => setData('email', e.target.value)}
                  required
                />
                {errors.email && <p className="text-sm text-red-600">{errors.email}</p>}
              </div>

              <div className="space-y-2">
                <Label htmlFor="phone">Phone Number</Label>
                <Input
                  id="phone"
                  type="tel"
                  value={data.phone}
                  onChange={(e) => setData('phone', e.target.value)}
                />
                {errors.phone && <p className="text-sm text-red-600">{errors.phone}</p>}
              </div>

              <div className="space-y-2">
                <Label htmlFor="business_name">Business Name</Label>
                <Input
                  id="business_name"
                  value={data.business_name}
                  onChange={(e) => setData('business_name', e.target.value)}
                />
                {errors.business_name && <p className="text-sm text-red-600">{errors.business_name}</p>}
              </div>

              {mustVerifyEmail && user.email_verified_at === null && (
                <div className="text-sm text-amber-600">
                  Your email address is unverified.
                </div>
              )}
            </CardContent>
            <CardFooter>
              <Button type="submit" disabled={processing}>
                Save
              </Button>
            </CardFooter>
          </form>
        </Card>

        <Card className="mt-6">
          <CardHeader>
            <CardTitle>Delete Account</CardTitle>
            <CardDescription>
              Once your account is deleted, all of its resources and data will be permanently deleted.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-gray-600">
              Before deleting your account, please download any data or information that you wish to retain.
            </p>
          </CardContent>
          <CardFooter>
            <Button variant="destructive">
              Delete Account
            </Button>
          </CardFooter>
        </Card>
      </div>
    </SettingsLayout>
  );
}