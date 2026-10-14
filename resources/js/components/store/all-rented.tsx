import type { ReactNode } from 'react';
import { OsdText } from '@/components/store/crt-screen';

/**
 * The VCR's blue screen: what you see when there's nothing on the tape.
 */
export function AllRented({
    title = 'No signal',
    children,
}: {
    title?: string;
    children?: ReactNode;
}) {
    return (
        <div className="no-signal mx-auto my-10 flex max-w-xl flex-col items-center px-6 py-14 text-center">
            <OsdText className="text-4xl uppercase">{title}</OsdText>
            {children && (
                <div className="mt-4 max-w-md text-lg text-white/85 [&_a]:text-white">
                    {children}
                </div>
            )}
        </div>
    );
}
