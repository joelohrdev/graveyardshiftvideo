import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * A television tube. With `powerOn`, the picture opens out of a bright line
 * like a set warming up.
 */
export function CrtScreen({
    children,
    powerOn = false,
    className,
}: {
    children: ReactNode;
    powerOn?: boolean;
    className?: string;
}) {
    return (
        <div className={cn('crt rounded-[14px]', className)}>
            <div
                className={cn('relative size-full', powerOn && 'crt-power-on')}
            >
                {children}
            </div>
        </div>
    );
}

/**
 * On-screen-display text, the blocky white letters a VCR paints over the picture.
 */
export function OsdText({
    children,
    blink = false,
    className,
}: {
    children: ReactNode;
    blink?: boolean;
    className?: string;
}) {
    return (
        <span className={cn('osd-text', blink && 'osd-blink', className)}>
            {children}
        </span>
    );
}
