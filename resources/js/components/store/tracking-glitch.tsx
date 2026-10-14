import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * The glass over the whole app (scanlines, snow, tube corners), plus a band of
 * tracking noise that rolls down the screen whenever a page change starts.
 */
export function TrackingGlitch() {
    const [sweep, setSweep] = useState(0);

    useEffect(() => {
        return router.on('start', (event) => {
            const visit = (event as CustomEvent).detail?.visit;

            if (
                visit?.method === 'get' &&
                !visit.preserveState &&
                !visit.only?.length
            ) {
                setSweep((count) => count + 1);
            }
        });
    }, []);

    return (
        <>
            <div aria-hidden className="static-snow" />
            <div aria-hidden className="crt-overlay" />
            {sweep > 0 && (
                <div
                    key={sweep}
                    aria-hidden
                    className="tracking-band"
                    onAnimationEnd={() => setSweep(0)}
                />
            )}
        </>
    );
}
