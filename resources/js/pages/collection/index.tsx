import { Head, Link } from '@inertiajs/react';
import { AllRented } from '@/components/store/all-rented';
import { Clamshell } from '@/components/store/clamshell';
import { OsdText } from '@/components/store/crt-screen';
import {
    FormatSticker,
    RentalSticker,
} from '@/components/store/format-sticker';
import { PageSign } from '@/components/store/page-sign';
import { CoverShelf } from '@/components/store/cover-shelf';
import { formatPrice } from '@/lib/store';
import { cn } from '@/lib/utils';
import { index as collection } from '@/routes/collection';
import { index as aisles } from '@/routes/movies';
import { index as wantList } from '@/routes/watches';
import type { Copy, FormatValue, Shelf, Watch } from '@/types';

type Grouping = 'director' | 'decade' | 'format' | 'subgenre';

type Props = {
    group: Grouping;
    shelves: Shelf[];
    stats: {
        movies: number;
        copies: number;
        directors: number;
        formats: { value: FormatValue; label: string; count: number }[];
    };
    recentCopies: Copy[];
    hotDeals: Watch[];
};

const groupings: { value: Grouping; label: string }[] = [
    { value: 'director', label: 'Director' },
    { value: 'decade', label: 'Decade' },
    { value: 'format', label: 'Format' },
    { value: 'subgenre', label: 'Subgenre' },
];

export default function CollectionIndex({
    group,
    shelves,
    stats,
    recentCopies,
    hotDeals,
}: Props) {
    return (
        <>
            <Head title="My shelf" />
            <div className="space-y-10 px-4 sm:px-8">
                <PageSign
                    title="My Shelf"
                    description={
                        stats.movies > 0 ? (
                            <>
                                {stats.movies} movies on {stats.copies} tapes
                                and discs, from {stats.directors} directors.
                            </>
                        ) : (
                            'Everything you own, racked the way you want to browse it.'
                        )
                    }
                />

                {stats.formats.length > 0 && (
                    <dl className="flex flex-wrap gap-x-6 gap-y-3">
                        {stats.formats.map((format) => (
                            <div
                                key={format.value}
                                className="flex items-center gap-2"
                            >
                                <dt>
                                    <FormatSticker format={format.value} />
                                </dt>
                                <dd className="font-osd text-2xl leading-none text-label tabular-nums">
                                    {format.count}
                                </dd>
                            </div>
                        ))}
                    </dl>
                )}

                {hotDeals.length > 0 && <HotDeals deals={hotDeals} />}

                <section className="space-y-6">
                    <div className="flex flex-wrap items-center gap-3">
                        <h2 className="text-sm text-muted-foreground">
                            Rack by
                        </h2>
                        <nav
                            aria-label="Group shelves by"
                            className="flex flex-wrap gap-1"
                        >
                            {groupings.map((grouping) => (
                                <Link
                                    key={grouping.value}
                                    href={collection({
                                        query: { group: grouping.value },
                                    })}
                                    preserveScroll
                                    className={cn(
                                        'px-3 pt-1 pb-0.5 text-xl uppercase',
                                        grouping.value === group
                                            ? 'osd-inverse'
                                            : 'text-osd/70 hover:text-osd',
                                    )}
                                    aria-current={
                                        grouping.value === group
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    {grouping.label}
                                </Link>
                            ))}
                        </nav>
                    </div>

                    {shelves.length === 0 ? (
                        <AllRented title="Your shelf is empty">
                            Find a movie in{' '}
                            <Link
                                href={aisles()}
                                className="text-label underline underline-offset-4"
                            >
                                the aisles
                            </Link>{' '}
                            and add the copy you own.
                        </AllRented>
                    ) : (
                        <div className="space-y-12">
                            {shelves.map((shelf) => (
                                <CoverShelf key={shelf.key} shelf={shelf} />
                            ))}
                        </div>
                    )}
                </section>

                {recentCopies.length > 0 && (
                    <section className="space-y-4">
                        <h2 className="osd-text text-3xl uppercase">
                            Just brought home
                        </h2>
                        <div className="grid grid-cols-2 gap-x-5 gap-y-7 sm:grid-cols-4 lg:grid-cols-8">
                            {recentCopies.map(
                                (copy) =>
                                    copy.movie && (
                                        <Clamshell
                                            key={copy.id}
                                            movie={{
                                                ...copy.movie,
                                                owned_formats: [copy.format],
                                            }}
                                        />
                                    ),
                            )}
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

function HotDeals({ deals }: { deals: Watch[] }) {
    return (
        <section
            aria-labelledby="hot-deals"
            className="crt rounded-md border border-neon/40 p-5"
        >
            <div className="relative z-10 flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="hot-deals">
                    <OsdText className="text-3xl text-[#ffe9ec]">
                        Deals on your want list
                    </OsdText>
                </h2>
                <Link
                    href={wantList()}
                    className="text-sm text-label underline-offset-4 hover:underline"
                >
                    Open the want list
                </Link>
            </div>
            <ul className="relative z-10 mt-4 grid gap-3 lg:grid-cols-2">
                {deals.map((deal) => (
                    <li
                        key={deal.id}
                        className="flex items-center justify-between gap-3 rounded-[3px] bg-black/40 px-3 py-2"
                    >
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium">
                                {deal.movie?.title}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {deal.format_label}
                            </p>
                        </div>
                        <div className="flex shrink-0 items-center gap-2">
                            <OsdText className="text-2xl">
                                {formatPrice(deal.latest?.lowest_price)}
                            </OsdText>
                            <RentalSticker
                                tone={
                                    deal.deal_status === 'steal'
                                        ? 'neon'
                                        : 'dayglo'
                                }
                                alarm={deal.deal_status === 'steal'}
                            >
                                {deal.deal_label}
                            </RentalSticker>
                        </div>
                    </li>
                ))}
            </ul>
        </section>
    );
}
