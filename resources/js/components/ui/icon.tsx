import { LucideIcon, icons } from 'lucide-react';

interface IconProps {
    name: keyof typeof icons;
    className?: string;
}

export function Icon({ name, className }: IconProps) {
    const IconComponent = icons[name];

    if (!IconComponent) {
        return null;
    }

    return <IconComponent className={className} />;
}
