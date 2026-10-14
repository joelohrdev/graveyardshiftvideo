import { Head, InfiniteScroll, Link, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { AllRented } from '@/components/store/all-rented';
import { PageSign } from '@/components/store/page-sign';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { tmdbImage } from '@/lib/store';
import { index as franchisesIndex, show } from '@/routes/franchises';
import type { Paginated } from '@/types';

type FranchiseCard = {
    id: number;
    name: string;
    poster_path: string | null;
    movies_count: number;
    owned_count: number;
};

type Props = {
    franchises: Paginated<FranchiseCard>;
    filters: { q: string; all: boolean };
};

export default function FranchisesIndex({ franchises, filters }: Props) {
    const [search, setSearch] = useState(filters.q);
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const grid = useRef<HTMLUListElement>(null);

    const browse = (changes: Partial<Props['filters']>) => {
        const next = { ...filters, ...changes };

        router.get(
            franchisesIndex.url({
                query: {
                    ...(next.q ? { q: next.q } : {}),
                    ...(next.all ? { all: 1 } : {}),
                },
            }),
            undefined,
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['franchises', 'filters'],
                reset: ['franchises'],
            },
        );
    };

    return (
        <>
            <Head title="Franchises" />
            <div className="space-y-8 px-4 sm:px-8">
                <PageSign
                    title="Franchises"
                    description="Every series in the store and how much of it is on your shelf. Open one to see the parts you're missing."
                />

                <div className="flex flex-wrap items-end gap-6">
                    <div className="grid w-full max-w-sm gap-1.5">
                        <Label
                            htmlFor="franchise-search"
                            className="text-xs text-muted-foreground"
                        >
                            Series
                        </Label>
                        <Input
                            id="franchise-search"
                            type="search"
                            value={search}
                            placeholder="Friday the 13th, Phantasm…"
                            onChange={(event) => {
                                const value = event.target.value;
                                setSearch(value);

                                if (searchTimer.current) {
                                    clearTimeout(searchTimer.current);
                                }

                                searchTimer.current = setTimeout(
                                    () => browse({ q: value }),
                                    300,
                                );
                            }}
                        />
                    </div>
                    <div className="flex items-center gap-2 pb-2">
                        <Checkbox
                            id="franchise-all"
                            checked={filters.all}
                            onCheckedChange={(checked) =>
                                browse({ all: checked === true })
                            }
                        />
                        <Label htmlFor="franchise-all" className="text-sm">
                            Include series I haven't started
                        </Label>
                    </div>
                </div>

                {franchises.data.length === 0 ? (
                    <AllRented title="No series yet">
                        {filters.all
                            ? 'No series match that name.'
                            : 'Series show up here once you own a movie that belongs to one. Movies imported before this feature pick up their series on the next TMDB refresh.'}
                    </AllRented>
                ) : (
                    <InfiniteScroll
                        data="franchises"
                        itemsElement={grid}
                        buffer={500}
                    >
                        <ul
                            ref={grid}
                            className="grid grid-cols-2 gap-x-5 gap-y-8 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"
                        >
                            {franchises.data.map((franchise) => (
                                <li key={franchise.id}>
                                    <FranchiseTile franchise={franchise} />
                                </li>
                            ))}
                        </ul>
                    </InfiniteScroll>
                )}
            </div>
        </>
    );
}

function FranchiseTile({ franchise }: { franchise: FranchiseCard }) {
    const poster = tmdbImage(franchise.poster_path, 'w342');
    const complete =
        franchise.movies_count > 0 &&
        franchise.owned_count === franchise.movies_count;

    return (
        <Link
            href={show(franchise.id)}
            prefetch
            className="clamshell-link group block outline-none"
        >
            <div className="clamshell relative aspect-[2/3] overflow-hidden bg-card">
                {poster ? (
                    <img
                        src={poster}
                        alt=""
                        loading="lazy"
                        className="size-full object-cover"
                    />
                ) : (
                    <div className="flex size-full items-center justify-center p-3 text-center text-2xl uppercase">
                        {franchise.name}
                    </div>
                )}
                {complete && (
                    <span className="osd-chip absolute top-1.5 right-1.5 z-10 text-base text-tube">
                        Complete
                    </span>
                )}
            </div>
            <div className="mt-2.5 space-y-1.5 px-0.5">
                <p className="line-clamp-2 text-sm leading-snug group-hover:text-label">
                    {franchise.name}
                </p>
                <div className="flex items-center gap-2">
                    <span
                        aria-hidden
                        className="block h-2 flex-1 overflow-hidden bg-muted"
                    >
                        <span
                            className="osd-meter block h-full"
                            style={{
                                width: `${Math.round(
                                    (franchise.owned_count /
                                        Math.max(franchise.movies_count, 1)) *
                                        100,
                                )}%`,
                            }}
                        />
                    </span>
                    <span className="text-base text-label tabular-nums">
                        {franchise.owned_count}
                        <span className="text-muted-foreground">
                            /{franchise.movies_count}
                        </span>
                        <span className="sr-only"> owned</span>
                    </span>
                </div>
            </div>
        </Link>
    );
}
