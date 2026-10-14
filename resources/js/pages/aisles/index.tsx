import { Head, InfiniteScroll, router } from '@inertiajs/react';
import { CheckSquare, Trash2, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { AddMovieDialog } from '@/components/store/add-movie-dialog';
import { AllRented } from '@/components/store/all-rented';
import { Clamshell, ClamshellSkeleton } from '@/components/store/clamshell';
import { FilterSelect } from '@/components/store/filter-select';
import { PageSign } from '@/components/store/page-sign';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as aisles, destroyMany } from '@/routes/movies';
import type { FormatValue, MovieCard, Option, Paginated } from '@/types';

type Filters = {
    q: string;
    owned: boolean;
    format: FormatValue | null;
    decade: number | null;
    genre: number | null;
    subgenre: number | null;
    sort: 'popular' | 'title' | 'newest' | 'oldest' | 'rating';
};

type Props = {
    movies: Paginated<MovieCard>;
    filters: Filters;
    facets: {
        genres: { id: number; name: string }[];
        subgenres: { id: number; name: string }[];
        decades: number[];
        formats: Option<FormatValue>[];
    };
    catalogSize: number;
};

const sorts: Option<Filters['sort']>[] = [
    { value: 'popular', label: 'Most rented' },
    { value: 'title', label: 'Title A–Z' },
    { value: 'newest', label: 'Newest first' },
    { value: 'oldest', label: 'Oldest first' },
    { value: 'rating', label: 'Highest rated' },
];

export default function AislesIndex({
    movies,
    filters,
    facets,
    catalogSize,
}: Props) {
    const [search, setSearch] = useState(filters.q);
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const grid = useRef<HTMLDivElement>(null);
    const [selecting, setSelecting] = useState(false);
    const [selected, setSelected] = useState<Set<number>>(new Set());
    const [confirming, setConfirming] = useState(false);
    const [removing, setRemoving] = useState(false);

    const toggle = (movieId: number) =>
        setSelected((current) => {
            const next = new Set(current);

            if (next.has(movieId)) {
                next.delete(movieId);
            } else {
                next.add(movieId);
            }

            return next;
        });

    const stopSelecting = () => {
        setSelecting(false);
        setSelected(new Set());
    };

    const removeSelected = () => {
        router.delete(destroyMany.url(), {
            data: { ids: [...selected] },
            preserveScroll: true,
            reset: ['movies'],
            onStart: () => setRemoving(true),
            onFinish: () => setRemoving(false),
            onSuccess: () => {
                setConfirming(false);
                stopSelecting();
            },
        });
    };

    const browse = (changes: Partial<Filters>) => {
        const next = { ...filters, ...changes };

        // Never delete something that a filter change has scrolled out of view.
        setSelected(new Set());
        const query = Object.fromEntries(
            Object.entries({
                ...next,
                owned: next.owned ? 1 : null,
                sort: next.sort === 'popular' ? null : next.sort,
            }).filter(([, value]) => value !== null && value !== ''),
        );

        router.get(aisles.url({ query }), undefined, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['movies', 'filters'],
            reset: ['movies'],
        });
    };

    const searchFor = (value: string) => {
        setSearch(value);

        if (searchTimer.current) {
            clearTimeout(searchTimer.current);
        }

        searchTimer.current = setTimeout(() => browse({ q: value }), 300);
    };

    const filtering =
        filters.q !== '' ||
        filters.owned ||
        [filters.format, filters.decade, filters.genre, filters.subgenre].some(
            (value) => value !== null,
        );

    return (
        <>
            <Head title="The aisles" />
            <div className="space-y-8 px-4 sm:px-8">
                <PageSign
                    title="The Aisles"
                    description={`Every horror movie in the store, ${catalogSize.toLocaleString()} tapes deep. Your copies carry a sticker.`}
                    actions={
                        <>
                            <AddMovieDialog />
                            {movies.data.length > 0 &&
                                (selecting ? (
                                    <Button
                                        variant="secondary"
                                        onClick={stopSelecting}
                                    >
                                        <X />
                                        Done
                                    </Button>
                                ) : (
                                    <Button
                                        variant="secondary"
                                        onClick={() => setSelecting(true)}
                                    >
                                        <CheckSquare />
                                        Select
                                    </Button>
                                ))}
                        </>
                    }
                />

                <section
                    aria-label="Filter the aisles"
                    className="grid gap-4 rounded-md border border-border/70 bg-card/60 p-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7"
                >
                    <div className="grid gap-1.5 sm:col-span-2 xl:col-span-2">
                        <Label
                            htmlFor="aisle-search"
                            className="text-xs text-muted-foreground"
                        >
                            Title
                        </Label>
                        <Input
                            id="aisle-search"
                            type="search"
                            value={search}
                            placeholder="Search for a tape"
                            onChange={(event) => searchFor(event.target.value)}
                        />
                    </div>
                    <FilterSelect
                        id="aisle-decade"
                        label="Decade"
                        anyLabel="Any decade"
                        value={filters.decade}
                        options={facets.decades.map((decade) => ({
                            value: decade,
                            label: `${decade}s`,
                        }))}
                        onChange={(value) =>
                            browse({ decade: value ? Number(value) : null })
                        }
                    />
                    <FilterSelect
                        id="aisle-subgenre"
                        label="Subgenre"
                        anyLabel="Any subgenre"
                        value={filters.subgenre}
                        options={facets.subgenres.map((keyword) => ({
                            value: keyword.id,
                            label: keyword.name,
                        }))}
                        onChange={(value) =>
                            browse({ subgenre: value ? Number(value) : null })
                        }
                    />
                    <FilterSelect
                        id="aisle-genre"
                        label="Also tagged"
                        anyLabel="Any genre"
                        value={filters.genre}
                        options={facets.genres.map((genre) => ({
                            value: genre.id,
                            label: genre.name,
                        }))}
                        onChange={(value) =>
                            browse({ genre: value ? Number(value) : null })
                        }
                    />
                    <FilterSelect
                        id="aisle-format"
                        label="I own it on"
                        anyLabel="Any format"
                        value={filters.format}
                        options={facets.formats}
                        onChange={(value) =>
                            browse({ format: value as FormatValue | null })
                        }
                    />
                    <FilterSelect
                        id="aisle-sort"
                        label="Sort"
                        value={filters.sort}
                        options={sorts}
                        onChange={(value) =>
                            browse({
                                sort: (value ?? 'popular') as Filters['sort'],
                            })
                        }
                    />
                    <div className="flex items-center gap-2 sm:col-span-2 lg:col-span-4 xl:col-span-7">
                        <Checkbox
                            id="aisle-owned"
                            checked={filters.owned}
                            onCheckedChange={(checked) =>
                                browse({ owned: checked === true })
                            }
                        />
                        <Label htmlFor="aisle-owned" className="text-sm">
                            Only movies on my shelf
                        </Label>
                    </div>
                </section>

                {movies.data.length === 0 ? (
                    <AllRented>
                        {catalogSize === 0
                            ? 'The store has no stock yet. Restock it from the back room.'
                            : filtering
                              ? 'Nothing on these shelves matches. Loosen a filter or two.'
                              : 'Nothing here yet.'}
                    </AllRented>
                ) : (
                    <InfiniteScroll
                        data="movies"
                        itemsElement={grid}
                        buffer={600}
                        loading={
                            <div className="mt-7 grid grid-cols-2 gap-x-5 gap-y-8 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                                {Array.from({ length: 6 }, (_, index) => (
                                    <ClamshellSkeleton key={index} />
                                ))}
                            </div>
                        }
                    >
                        <div
                            ref={grid}
                            className="grid grid-cols-2 gap-x-5 gap-y-8 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"
                        >
                            {movies.data.map((movie) => (
                                <Clamshell
                                    key={movie.id}
                                    movie={movie}
                                    selection={
                                        selecting
                                            ? {
                                                  selected: selected.has(
                                                      movie.id,
                                                  ),
                                                  onToggle: () =>
                                                      toggle(movie.id),
                                              }
                                            : undefined
                                    }
                                />
                            ))}
                        </div>
                    </InfiniteScroll>
                )}
            </div>

            {selecting && (
                <div className="sticky bottom-4 z-40 mx-4 mt-8 flex flex-wrap items-center gap-x-4 gap-y-2 border border-osd/60 bg-night/95 px-4 py-3 sm:mx-8">
                    <span className="osd-text text-2xl tabular-nums">
                        {selected.size} selected
                    </span>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            setSelected(
                                new Set(movies.data.map((movie) => movie.id)),
                            )
                        }
                    >
                        Select all {movies.data.length} shown
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        disabled={selected.size === 0}
                        onClick={() => setSelected(new Set())}
                    >
                        Clear
                    </Button>
                    <Button
                        variant="destructive"
                        className="ml-auto"
                        disabled={selected.size === 0}
                        onClick={() => setConfirming(true)}
                    >
                        <Trash2 />
                        Delete {selected.size || ''}
                    </Button>
                </div>
            )}

            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Delete {selected.size}{' '}
                            {selected.size === 1 ? 'movie' : 'movies'}?
                        </DialogTitle>
                        <DialogDescription>
                            They're removed from the store and future TMDB
                            imports will skip them. Anything on your shelf or
                            want list is kept.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            disabled={removing}
                            onClick={removeSelected}
                        >
                            <Trash2 />
                            Delete {selected.size === 1 ? 'movie' : 'movies'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
