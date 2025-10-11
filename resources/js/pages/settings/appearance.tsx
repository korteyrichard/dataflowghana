import { Head } from '@inertiajs/react';
import { Monitor, Moon, Sun } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { useTheme } from '@/hooks/use-theme';
import SettingsLayout from '@/layouts/settings-layout';

export default function Appearance() {
    const { theme, setTheme } = useTheme();

    return (
        <SettingsLayout>
            <Head title="Appearance Settings" />

            <div className="space-y-6">
                <div>
                    <h3 className="text-lg font-medium">Appearance</h3>
                    <p className="text-sm text-muted-foreground">
                        Customize the appearance of the app. Automatically switch between day and night themes.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Theme</CardTitle>
                        <CardDescription>
                            Select the theme for the dashboard.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-3 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="light">Light</Label>
                                <Button
                                    id="light"
                                    variant={theme === 'light' ? 'default' : 'outline'}
                                    className="w-full justify-start"
                                    onClick={() => setTheme('light')}
                                >
                                    <Sun className="mr-2 h-4 w-4" />
                                    Light
                                </Button>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="dark">Dark</Label>
                                <Button
                                    id="dark"
                                    variant={theme === 'dark' ? 'default' : 'outline'}
                                    className="w-full justify-start"
                                    onClick={() => setTheme('dark')}
                                >
                                    <Moon className="mr-2 h-4 w-4" />
                                    Dark
                                </Button>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="system">System</Label>
                                <Button
                                    id="system"
                                    variant={theme === 'system' ? 'default' : 'outline'}
                                    className="w-full justify-start"
                                    onClick={() => setTheme('system')}
                                >
                                    <Monitor className="mr-2 h-4 w-4" />
                                    System
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}