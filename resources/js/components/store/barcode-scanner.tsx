import { BrowserMultiFormatOneDReader } from '@zxing/browser';
import type { IScannerControls } from '@zxing/browser';
import { useEffect, useRef, useState } from 'react';
import { OsdText } from '@/components/store/crt-screen';

/**
 * Live camera view that reads the UPC/EAN barcode on a case. Uses the rear
 * camera on phones and stops as soon as it gets a read.
 */
export function BarcodeScanner({
    onDetected,
}: {
    onDetected: (code: string) => void;
}) {
    const video = useRef<HTMLVideoElement>(null);
    const latestOnDetected = useRef(onDetected);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        latestOnDetected.current = onDetected;
    }, [onDetected]);

    useEffect(() => {
        if (!video.current) {
            return;
        }

        let controls: IScannerControls | undefined;
        let finished = false;
        const reader = new BrowserMultiFormatOneDReader();

        reader
            .decodeFromConstraints(
                { video: { facingMode: 'environment' } },
                video.current,
                (result, _error, scanner) => {
                    if (result && !finished) {
                        finished = true;
                        scanner.stop();
                        latestOnDetected.current(result.getText());
                    }
                },
            )
            .then((started) => {
                controls = started;

                if (finished) {
                    started.stop();
                }
            })
            .catch(() =>
                setError(
                    'The camera is unavailable. Allow camera access for this site, or type the barcode digits below.',
                ),
            );

        return () => {
            finished = true;
            controls?.stop();
        };
    }, []);

    if (error) {
        return <p className="text-neon">{error}</p>;
    }

    return (
        <div className="crt relative aspect-video w-full overflow-hidden">
            <video
                ref={video}
                muted
                playsInline
                className="absolute inset-0 size-full object-cover"
            />
            <div
                aria-hidden
                className="absolute inset-x-[12%] top-1/2 z-10 h-0.5 bg-neon shadow-[0_0_10px_var(--neon)]"
            />
            <OsdText blink className="absolute top-3 left-4 z-10 text-2xl">
                SCANNING…
            </OsdText>
            <p className="absolute inset-x-0 bottom-3 z-10 text-center text-base text-white/80">
                Line the barcode up with the red line.
            </p>
        </div>
    );
}
