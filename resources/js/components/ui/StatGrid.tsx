export default function StatGrid({
    items,
    columns = 2,
    className = '',
}: {
    items: { label: string; value: string | number; className?: string }[];
    columns?: 2 | 3 | 4;
    className?: string;
}) {
    const gridClass = columns === 3 ? 'grid-cols-3' : columns === 4 ? 'grid-cols-4' : 'grid-cols-2';

    return (
        <div className={`grid ${gridClass} gap-2 ${className}`}>
            {items.map((item) => (
                <div key={item.label} className="rounded bg-white/5 p-2 text-center">
                    <div className={`text-sm font-semibold ${item.className ?? 'text-white/70'}`}>
                        {typeof item.value === 'number' ? item.value.toLocaleString() : item.value}
                    </div>
                    <div className="text-[10px] text-white/30">{item.label}</div>
                </div>
            ))}
        </div>
    );
}
