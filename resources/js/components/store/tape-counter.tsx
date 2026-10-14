import { useEffect, useState } from 'react';
import { OsdText } from '@/components/store/crt-screen';

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

/**
 * The VCR status line in the corner of the screen: PLAY, tape speed and a
 * counter that runs from when you sat down.
 */
export function TapeCounter() {
    const [seconds, setSeconds] = useState(0);

    useEffect(() => {
        const started = Date.now();
        const timer = setInterval(
            () => setSeconds(Math.floor((Date.now() - started) / 1000)),
            1000,
        );

        return () => clearInterval(timer);
    }, []);

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    return (
        <div aria-hidden className="flex items-center gap-5 text-2xl">
            <OsdText>PLAY ▶</OsdText>
            <OsdText className="hidden sm:inline">SP</OsdText>
            <OsdText className="tabular-nums">
                {hours}:{pad(minutes)}:{pad(seconds % 60)}
            </OsdText>
        </div>
    );
}
