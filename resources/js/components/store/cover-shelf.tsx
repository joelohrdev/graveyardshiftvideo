import { Link } from '@inertiajs/react';
import { Clamshell } from '@/components/store/clamshell';
import { show } from '@/routes/people';
import type { Shelf } from '@/types';

/**
 * A titled row of cases displayed face-out.
 */
export function CoverShelf({
    shelf,
    dimUnowned = false,
}: {
    shelf: Shelf;
    dimUnowned?: boolean;
}) {
    const count = shelf.movies.length;

    return (
        <section aria-labelledby={`${shelf.key}-label`} className="space-y-3">
            <h2 id={`${shelf.key}-label`} className="flex items-baseline gap-3">
                <span className="osd-text text-3xl uppercase">
                    {shelf.person_id ? (
                        <Link
                            href={show(shelf.person_id)}
                            className="underline-offset-4 hover:underline"
                        >
                            {shelf.label}
                        </Link>
                    ) : (
                        shelf.label
                    )}
                </span>
                <span className="text-sm text-muted-foreground">
                    {count} {count === 1 ? 'movie' : 'movies'}
                </span>
            </h2>
            <ul className="-mx-1 flex [scrollbar-width:thin] gap-5 overflow-x-auto px-1 pt-1 pb-3">
                {shelf.movies.map((movie) => (
                    <li
                        key={`${shelf.key}-${movie.id}`}
                        className="w-32 shrink-0 sm:w-36"
                    >
                        <Clamshell movie={movie} dimUnowned={dimUnowned} />
                    </li>
                ))}
            </ul>
        </section>
    );
}
