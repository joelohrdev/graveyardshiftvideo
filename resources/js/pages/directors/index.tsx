import { Head, InfiniteScroll, Link, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { AllRented } from '@/components/store/all-rented';
import { PageSign } from '@/components/store/page-sign';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { tmdbImage } from '@/lib/store';
import { index as directorsIndex } from '@/routes/directors';
import { show } from '@/routes/people';
import type { Paginated } from '@/types';

type Director = {
    id: number;
    name: string;
    profile_path: string | null;
    directed_count: number;
    owned_count: number;
};

type Props = {
    directors: Paginated<Director>;
    filters: { q: string; all: boolean };
};

export default function DirectorsIndex({ directors, filters }: Props) {
    const [search, setSearch] = useState(filters.q);
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const list = useRef<HTMLUListElement>(null);

    const browse = (changes: Partial<Props['filters']>) => {
        const next = { ...filters, ...changes };

        router.get(
            directorsIndex.url({
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
                only: ['directors', 'filters'],
                reset: ['directors'],
            },
        );
    };

    return (
        <>
            <Head title="Directors' row" />
            <div className="space-y-8 px-4 sm:px-8">
                <PageSign
                    title="Directors' Row"
                    description="The filmmakers on your shelf, ranked by how much of their work you own."
                />

                <div className="flex flex-wrap items-end gap-6">
                    <div className="grid w-full max-w-sm gap-1.5">
                        <Label
                            htmlFor="director-search"
                            className="text-xs text-muted-foreground"
                        >
                            Director
                        </Label>
                        <Input
                            id="director-search"
                            type="search"
                            value={search}
                            placeholder="Carpenter, Argento, Fulci…"
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
                            id="director-all"
                            checked={filters.all}
                            onCheckedChange={(checked) =>
                                browse({ all: checked === true })
                            }
                        />
                        <Label htmlFor="director-all" className="text-sm">
                            Include directors I don't own yet
                        </Label>
                    </div>
                </div>

                {directors.data.length === 0 ? (
                    <AllRented title="Nobody on the row">
                        {filters.all
                            ? 'No directors match that name.'
                            : 'Add copies to your shelf and their directors show up here.'}
                    </AllRented>
                ) : (
                    <InfiniteScroll
                        data="directors"
                        itemsElement={list}
                        buffer={500}
                    >
                        <ul
                            ref={list}
                            className="grid gap-x-8 gap-y-1 sm:grid-cols-2 xl:grid-cols-3"
                        >
                            {directors.data.map((director) => (
                                <DirectorRow
                                    key={director.id}
                                    director={director}
                                />
                            ))}
                        </ul>
                    </InfiniteScroll>
                )}
            </div>
        </>
    );
}

function DirectorRow({ director }: { director: Director }) {
    const portrait = tmdbImage(director.profile_path, 'w92');
    const share =
        director.directed_count > 0
            ? director.owned_count / director.directed_count
            : 0;

    return (
        <li>
            <Link
                href={show(director.id)}
                prefetch
                className="group flex items-center gap-3 px-2 py-2.5 hover:bg-white/5"
            >
                {portrait ? (
                    <img
                        src={portrait}
                        alt=""
                        loading="lazy"
                        className="size-11 shrink-0 object-cover grayscale group-hover:grayscale-0"
                    />
                ) : (
                    <span className="flex size-11 shrink-0 items-center justify-center bg-muted text-2xl text-muted-foreground">
                        {director.name.charAt(0)}
                    </span>
                )}
                <span className="min-w-0 flex-1">
                    <span className="block truncate font-medium group-hover:text-label">
                        {director.name}
                    </span>
                    <span
                        className="mt-1.5 block h-2 overflow-hidden bg-muted"
                        aria-hidden
                    >
                        <span
                            className="osd-meter block h-full"
                            style={{ width: `${Math.round(share * 100)}%` }}
                        />
                    </span>
                </span>
                <span className="shrink-0 text-right font-osd text-xl leading-none text-label tabular-nums">
                    {director.owned_count}
                    <span className="text-muted-foreground">
                        /{director.directed_count}
                    </span>
                    <span className="sr-only"> owned</span>
                </span>
            </Link>
        </li>
    );
}
