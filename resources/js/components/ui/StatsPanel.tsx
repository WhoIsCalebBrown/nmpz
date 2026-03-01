import type { ReactNode } from 'react';

export default function StatsPanel({
    title,
    titleRight,
    children,
    className = '',
}: {
    title?: string;
    titleRight?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={`w-full rounded border border-white/10 bg-black/60 p-3 backdrop-blur-sm ${className}`}>
            {(title || titleRight) && (
                <div className="mb-2 flex items-center justify-between">
                    {title && <span className="text-xs text-white/60">{title}</span>}
                    {titleRight}
                </div>
            )}
            {children}
        </div>
    );
}
