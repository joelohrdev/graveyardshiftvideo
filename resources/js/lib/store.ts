import type { FormatValue } from '@/types';

const TMDB_IMAGE_BASE = 'https://image.tmdb.org/t/p';

/**
 * TMDB image URL for a stored path. Images are hotlinked rather than copied,
 * which keeps us inside TMDB's caching terms.
 */
export function tmdbImage(
    path: string | null,
    size: 'w92' | 'w185' | 'w342' | 'w500' | 'w780' | 'original' = 'w342',
): string | null {
    return path ? `${TMDB_IMAGE_BASE}/${size}${path}` : null;
}

export const formatLabels: Record<FormatValue, string> = {
    vhs: 'VHS',
    betamax: 'Beta',
    laserdisc: 'LD',
    dvd: 'DVD',
    bluray: 'Blu-ray',
    uhd: '4K',
    digital: 'Digital',
};

/**
 * On-screen tag color per format, so the shelf reads at a glance.
 */
export const formatStickers: Record<FormatValue, string> = {
    vhs: 'text-dayglo',
    betamax: 'text-[#c6ff3a]',
    laserdisc: 'text-[#d6d9e8]',
    dvd: 'text-osd',
    bluray: 'text-cyan',
    uhd: 'text-[#ffe04a]',
    digital: 'text-[#c9a6ff]',
};

/**
 * Printed spine stock. Studios of the era leaned on a handful of loud cards:
 * black, blood red, bone white, mustard and sickly green.
 */
const SPINE_STOCK = [
    { background: '#0d0a0c', color: '#f0e2bf', band: '#ff2d4a' },
    { background: '#8f0d1a', color: '#fff0d6', band: '#0d0a0c' },
    { background: '#ece3cf', color: '#140c0c', band: '#b3121f' },
    { background: '#d9a21b', color: '#140c0c', band: '#140c0c' },
    { background: '#1c2f22', color: '#d8f5e6', band: '#c6ff3a' },
    { background: '#1d2440', color: '#f0e2bf', band: '#ff8c1a' },
    { background: '#3a1430', color: '#ffd5ec', band: '#ff2d4a' },
] as const;

export function spineStock(id: number): (typeof SPINE_STOCK)[number] {
    return SPINE_STOCK[id % SPINE_STOCK.length];
}

export function formatPrice(value: number | null | undefined): string {
    return value === null || value === undefined
        ? '—'
        : new Intl.NumberFormat('en-US', {
              style: 'currency',
              currency: 'USD',
          }).format(value);
}
