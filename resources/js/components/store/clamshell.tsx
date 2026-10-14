import { Link } from '@inertiajs/react';
import { FormatSticker } from '@/components/store/format-sticker';
import { spineStock, tmdbImage } from '@/lib/store';
import { cn } from '@/lib/utils';
import { show } from '@/routes/movies';
import type { MovieCard } from '@/types';

/**
 * A face-out rental case: the cover art behind scuffed plastic, with my
 * format stickers slapped on the corner.
 *
 * Pass `selection` to turn the case into a toggle for bulk actions instead of
 * a link to the movie.
 */
export function Clamshell({
    movie,
    dimUnowned = false,
    selection,
}: {
    movie: MovieCard;
    dimUnowned?: boolean;
    selection?: { selected: boolean; onToggle: () => void };
}) {
    const poster = tmdbImage(movie.poster_path, 'w342');
    const owned = (movie.owned_formats?.length ?? 0) > 0;
    const stock = spineStock(movie.id);
    const selected = selection?.selected ?? false;

    const content = (
        <>
            <div
                className={cn(
                    'clamshell relative aspect-[2/3] overflow-hidden rounded-[3px] bg-card',
                    dimUnowned &&
                        !owned &&
                        'opacity-55 grayscale-[0.7] group-hover:opacity-100 group-hover:grayscale-0',
                    selected && 'outline-2 outline-offset-2 outline-osd',
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
                {selection && (
                    <span
                        aria-hidden
                        className={cn(
                            'absolute top-1.5 left-1.5 z-10 flex size-7 items-center justify-center border-2 border-osd text-xl leading-none',
                            selected ? 'osd-inverse' : 'bg-black/70',
                        )}
                    >
                        {selected ? '✓' : ''}
                    </span>
                )}
            </div>
            <div className="mt-2.5 space-y-0.5 px-0.5 text-left">
                <p className="line-clamp-2 text-sm leading-snug font-medium group-hover:text-label">
                    {movie.title}
                </p>
                <p className="text-xs text-muted-foreground tabular-nums">
                    {movie.year ?? 'Year unknown'}
                </p>
            </div>
        </>
    );

    if (selection) {
        return (
            <button
                type="button"
                role="checkbox"
                aria-checked={selected}
                aria-label={`Select ${movie.title}`}
                onClick={selection.onToggle}
                className="group block w-full outline-none focus-visible:[&_.clamshell]:outline-2 focus-visible:[&_.clamshell]:outline-offset-2 focus-visible:[&_.clamshell]:outline-dayglo"
            >
                {content}
            </button>
        );
    }

    return (
        <Link
            href={show(movie.id)}
            prefetch
            className="clamshell-link group block rounded-sm outline-none"
        >
            {content}
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
