import { Link } from '@inertiajs/react';
import { FormatSticker } from '@/components/store/format-sticker';
import { spineStock, tmdbImage } from '@/lib/store';
import { cn } from '@/lib/utils';
import { show } from '@/routes/movies';
import type { MovieCard } from '@/types';

/**
 * A face-out rental case: the cover art behind scuffed plastic, with my
 * format stickers slapped on the corner.
 */
export function Clamshell({
    movie,
    dimUnowned = false,
}: {
    movie: MovieCard;
    dimUnowned?: boolean;
}) {
    const poster = tmdbImage(movie.poster_path, 'w342');
    const owned = (movie.owned_formats?.length ?? 0) > 0;
    const stock = spineStock(movie.id);

    return (
        <Link
            href={show(movie.id)}
            prefetch
            className="clamshell-link group block rounded-sm outline-none"
        >
            <div
                className={cn(
                    'clamshell relative aspect-[2/3] overflow-hidden rounded-[3px] bg-card',
                    dimUnowned &&
                        !owned &&
                        'opacity-55 grayscale-[0.7] group-hover:opacity-100 group-hover:grayscale-0',
                )}
            >
                {poster ? (
                    <img
                        src={poster}
                        alt=""
                        loading="lazy"
                        className="size-full object-cover"
                    />
                ) : (
                    <div
                        className="flex size-full items-center justify-center p-3 text-center font-spine text-xl leading-tight uppercase"
                        style={{
                            backgroundColor: stock.background,
                            color: stock.color,
                        }}
                    >
                        {movie.title}
                    </div>
                )}
                {owned && (
                    <div className="absolute top-1.5 right-1.5 z-10 flex rotate-6 flex-col items-end gap-1">
                        {movie.owned_formats?.map((format) => (
                            <FormatSticker key={format} format={format} />
                        ))}
                    </div>
                )}
            </div>
            <div className="mt-2.5 space-y-0.5 px-0.5">
                <p className="line-clamp-2 text-sm leading-snug font-medium group-hover:text-label">
                    {movie.title}
                </p>
                <p className="text-xs text-muted-foreground tabular-nums">
                    {movie.year ?? 'Year unknown'}
                </p>
            </div>
        </Link>
    );
}

export function ClamshellSkeleton() {
    return (
        <div aria-hidden>
            <div className="static-skeleton aspect-[2/3] rounded-[3px]" />
            <div className="static-skeleton mt-2.5 h-3 w-3/4 rounded-sm" />
        </div>
    );
}
