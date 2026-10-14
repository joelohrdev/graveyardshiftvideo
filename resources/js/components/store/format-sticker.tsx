import type { ReactNode } from 'react';
import { formatLabels, formatStickers } from '@/lib/store';
import { cn } from '@/lib/utils';
import type { FormatValue } from '@/types';

/**
 * The boxed on-screen tag that marks which formats are on my shelf.
 */
export function FormatSticker({
    format,
    className,
}: {
    format: FormatValue;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'osd-chip text-sm',
                formatStickers[format],
                className,
            )}
        >
            {formatLabels[format]}
        </span>
    );
}

/**
 * A status tag in the corner of the screen. `alarm` blinks it like a REC warning.
 */
export function RentalSticker({
    children,
    tone = 'neon',
    alarm = false,
    className,
}: {
    children: ReactNode;
    tone?: 'neon' | 'dayglo' | 'label';
    alarm?: boolean;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'osd-chip text-lg',
                tone === 'neon' && 'text-neon',
                tone === 'dayglo' && 'text-dayglo',
                tone === 'label' && 'text-osd',
                alarm && 'osd-alarm',
                className,
            )}
        >
            {children}
        </span>
    );
}
