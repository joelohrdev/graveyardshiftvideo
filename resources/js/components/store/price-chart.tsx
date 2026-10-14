import { formatPrice } from '@/lib/store';
import type { Watch } from '@/types';

const WIDTH = 320;
const HEIGHT = 96;
const PAD = 6;

/**
 * Daily eBay asking prices drawn like a VCR's on-screen meter: the lowest
 * price as a bright trace, the median as a dim one and the target as a
 * dashed line.
 */
export function PriceChart({
    history,
    target,
}: {
    history: Watch['history'];
    target: number | null;
}) {
    const points = history.filter((point) => point.lowest !== null);

    if (points.length === 0) {
        return (
            <div className="flex h-24 items-center justify-center rounded-[3px] border border-dashed border-border text-xs text-muted-foreground">
                No prices yet. The first check runs overnight.
            </div>
        );
    }

    const values = points.flatMap((point) => [
        point.lowest ?? 0,
        point.median ?? point.lowest ?? 0,
        ...(target !== null ? [target] : []),
    ]);
    const max = Math.max(...values) * 1.08;
    const min = Math.min(...values) * 0.92;
    const range = max - min || 1;

    const x = (index: number) =>
        points.length === 1
            ? WIDTH / 2
            : PAD + (index / (points.length - 1)) * (WIDTH - PAD * 2);
    const y = (value: number) =>
        HEIGHT - PAD - ((value - min) / range) * (HEIGHT - PAD * 2);

    const trace = (pick: (point: (typeof points)[number]) => number | null) =>
        points
            .map((point, index) => {
                const value = pick(point);

                return value === null ? null : `${x(index)},${y(value)}`;
            })
            .filter(Boolean)
            .join(' ');

    const first = points[0];
    const last = points[points.length - 1];

    return (
        <figure className="space-y-1">
            <svg
                viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                className="h-24 w-full overflow-visible rounded-[3px] bg-black/50"
                role="img"
                aria-label={`Lowest price went from ${formatPrice(first.lowest)} on ${first.date} to ${formatPrice(last.lowest)} on ${last.date}.`}
            >
                {target !== null && (
                    <line
                        x1={0}
                        x2={WIDTH}
                        y1={y(target)}
                        y2={y(target)}
                        stroke="var(--neon)"
                        strokeDasharray="4 4"
                        strokeWidth={1}
                        opacity={0.8}
                    />
                )}
                <polyline
                    points={trace((point) => point.median)}
                    fill="none"
                    stroke="var(--label)"
                    strokeOpacity={0.35}
                    strokeWidth={1.5}
                />
                <polyline
                    points={trace((point) => point.lowest)}
                    fill="none"
                    stroke="var(--tube)"
                    strokeWidth={2}
                    style={{ filter: 'drop-shadow(0 0 3px var(--tube))' }}
                />
                {points.length === 1 && last.lowest !== null && (
                    <circle
                        cx={x(0)}
                        cy={y(last.lowest)}
                        r={3}
                        fill="var(--tube)"
                    />
                )}
            </svg>
            <figcaption className="flex flex-wrap gap-x-4 text-[0.7rem] text-muted-foreground">
                <span className="text-tube">Lowest</span>
                <span className="text-label/60">Median</span>
                {target !== null && (
                    <span className="text-neon">
                        Target {formatPrice(target)}
                    </span>
                )}
                <span className="ml-auto tabular-nums">
                    {points.length} {points.length === 1 ? 'day' : 'days'}
                </span>
            </figcaption>
        </figure>
    );
}
