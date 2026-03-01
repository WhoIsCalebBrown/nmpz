export function resultColor(result: 'win' | 'loss' | 'draw' | string): string {
    switch (result) {
        case 'win':
            return 'text-green-400';
        case 'loss':
            return 'text-red-400';
        case 'draw':
            return 'text-amber-400';
        default:
            return 'text-white/50';
    }
}

export const FORMAT_LABELS: Record<string, string> = {
    classic: 'Classic',
    best_of_3: 'Best of 3',
    best_of_5: 'Best of 5',
    best_of_7: 'Best of 7',
};

export const DIFFICULTY_COLORS: Record<string, string> = {
    easy: 'text-green-400 bg-green-400/10',
    medium: 'text-amber-400 bg-amber-400/10',
    hard: 'text-orange-400 bg-orange-400/10',
    extreme: 'text-red-400 bg-red-400/10',
};
