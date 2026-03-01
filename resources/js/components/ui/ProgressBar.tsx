export default function ProgressBar({
    value,
    color = 'bg-blue-400/50',
    className = '',
}: {
    value: number;
    color?: string;
    className?: string;
}) {
    return (
        <div className={`h-1.5 rounded-full bg-white/10 ${className}`}>
            <div
                className={`h-full rounded-full ${color}`}
                style={{ width: `${Math.min(100, Math.max(0, value))}%` }}
            />
        </div>
    );
}
