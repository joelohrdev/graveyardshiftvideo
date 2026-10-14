import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { CopyFormFields } from '@/components/store/copy-form-fields';
import { CrtScreen, OsdText } from '@/components/store/crt-screen';
import {
    FormatSticker,
    RentalSticker,
} from '@/components/store/format-sticker';
import { WatchCard } from '@/components/store/watch-card';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { spineStock, tmdbImage } from '@/lib/store';
import { destroy, store, update } from '@/routes/copies';
import { index as aisles } from '@/routes/movies';
import { show as showPerson } from '@/routes/people';
import { store as storeWatch } from '@/routes/watches';
import type { Copy, FormatValue, Option, PersonRef, Watch } from '@/types';

type Movie = {
    id: number;
    tmdb_id: number;
    title: string;
    original_title: string | null;
    overview: string | null;
    release_date: string | null;
    year: number | null;
    runtime: number | null;
    poster_path: string | null;
    backdrop_path: string | null;
    vote_average: number;
    vote_count: number;
    directors: PersonRef[];
    cast: (PersonRef & {
        character: string | null;
        profile_path: string | null;
    })[];
    genres: { id: number; name: string }[];
    keywords: { id: number; name: string }[];
};

type Props = {
    movie: Movie;
    copies: Copy[];
    watches: Watch[];
    formats: Option<FormatValue>[];
    conditions: Option[];
};

export default function MovieShow({
    movie,
    copies,
    watches,
    formats,
    conditions,
}: Props) {
    const backdrop = tmdbImage(movie.backdrop_path, 'w780');
    const poster = tmdbImage(movie.poster_path, 'w500');
    const stock = spineStock(movie.id);
    const physicalFormats = formats.filter(
        (format) => format.value !== 'digital',
    );

    return (
        <>
            <Head title={movie.title} />
            <div className="space-y-12 px-4 pt-2 sm:px-8">
                <section className="grid gap-8 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
                    <div className="mx-auto w-full max-w-72 lg:max-w-none">
                        <div className="clamshell relative aspect-[2/3] overflow-hidden rounded-[3px]">
                            {poster ? (
                                <img
                                    src={poster}
                                    alt={`${movie.title} cover art`}
                                    className="size-full object-cover"
                                />
                            ) : (
                                <div
                                    className="flex size-full items-center justify-center p-6 text-center font-spine text-4xl uppercase"
                                    style={{
                                        backgroundColor: stock.background,
                                        color: stock.color,
                                    }}
                                >
                                    {movie.title}
                                </div>
                            )}
                            {copies.length > 0 && (
                                <div className="absolute top-3 right-3 z-10 flex rotate-6 flex-col items-end gap-1.5">
                                    {[
                                        ...new Set(
                                            copies.map((copy) => copy.format),
                                        ),
                                    ].map((format) => (
                                        <FormatSticker
                                            key={format}
                                            format={format}
                                            className="text-xs"
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="min-w-0 space-y-6">
                        <CrtScreen powerOn className="aspect-video w-full">
                            {backdrop && (
                                <img
                                    src={backdrop}
                                    alt=""
                                    className="absolute inset-0 size-full object-cover opacity-80"
                                />
                            )}
                            <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent" />
                            <div className="absolute top-4 left-5 z-10">
                                <OsdText className="text-2xl sm:text-3xl">
                                    CH 03
                                </OsdText>
                            </div>
                            <div className="absolute inset-x-5 bottom-4 z-10 space-y-1">
                                <h1 className="osd-text text-4xl leading-none uppercase sm:text-6xl">
                                    {movie.title}
                                </h1>
                                <OsdText className="text-xl">
                                    {[
                                        movie.year,
                                        movie.runtime
                                            ? `${movie.runtime} MIN`
                                            : null,
                                        movie.vote_count > 0
                                            ? `${movie.vote_average.toFixed(1)}/10`
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join('   ')}
                                </OsdText>
                            </div>
                        </CrtScreen>

                        {movie.directors.length > 0 && (
                            <p className="text-lg">
                                <span className="text-muted-foreground">
                                    Directed by{' '}
                                </span>
                                {movie.directors.map((director, index) => (
                                    <span key={director.id}>
                                        {index > 0 && ' and '}
                                        <Link
                                            href={showPerson(director.id)}
                                            className="font-medium text-label underline-offset-4 hover:underline"
                                        >
                                            {director.name}
                                        </Link>
                                    </span>
                                ))}
                            </p>
                        )}

                        {movie.overview && (
                            <p className="max-w-[68ch] leading-relaxed text-foreground/85">
                                {movie.overview}
                            </p>
                        )}

                        {(movie.keywords.length > 0 ||
                            movie.genres.length > 0) && (
                            <ul
                                className="flex flex-wrap gap-1.5"
                                aria-label="Tags"
                            >
                                {movie.genres.map((genre) => (
                                    <li key={`g-${genre.id}`}>
                                        <Link
                                            href={aisles({
                                                query: { genre: genre.id },
                                            })}
                                            className="inline-block bg-secondary px-2 pt-1 pb-0.5 text-base uppercase hover:osd-inverse"
                                        >
                                            {genre.name}
                                        </Link>
                                    </li>
                                ))}
                                {movie.keywords.slice(0, 14).map((keyword) => (
                                    <li key={`k-${keyword.id}`}>
                                        <Link
                                            href={aisles({
                                                query: { subgenre: keyword.id },
                                            })}
                                            className="inline-block border border-border px-2 pt-1 pb-0.5 text-base text-muted-foreground uppercase hover:border-osd hover:text-osd"
                                        >
                                            {keyword.name}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>

                <section aria-labelledby="my-copies" className="space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2
                            id="my-copies"
                            className="osd-text text-3xl uppercase"
                        >
                            On my shelf
                        </h2>
                        <AddCopyDialog movie={movie} formats={formats} />
                    </div>
                    {copies.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            You don't own this one yet. Add a copy when you
                            bring it home, or put it on the want list below.
                        </p>
                    ) : (
                        <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {copies.map((copy) => (
                                <CopyRow
                                    key={copy.id}
                                    copy={copy}
                                    formats={formats}
                                />
                            ))}
                        </ul>
                    )}
                </section>

                <section aria-labelledby="want-list" className="space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2
                                id="want-list"
                                className="osd-text text-3xl uppercase"
                            >
                                Want list
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Tracks eBay asking prices daily so you can buy
                                when it's cheap.
                            </p>
                        </div>
                        <AddWatchDialog
                            movie={movie}
                            formats={physicalFormats}
                            conditions={conditions}
                        />
                    </div>
                    {watches.length > 0 && (
                        <div className="grid gap-4 lg:grid-cols-2">
                            {watches.map((watch) => (
                                <WatchCard key={watch.id} watch={watch} />
                            ))}
                        </div>
                    )}
                </section>

                {movie.cast.length > 0 && (
                    <section aria-labelledby="cast" className="space-y-4">
                        <h2 id="cast" className="osd-text text-3xl uppercase">
                            Starring
                        </h2>
                        <ul className="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3 lg:grid-cols-5">
                            {movie.cast.map((person) => (
                                <li key={person.id} className="min-w-0">
                                    <Link
                                        href={showPerson(person.id)}
                                        className="block truncate text-sm font-medium hover:text-label"
                                    >
                                        {person.name}
                                    </Link>
                                    {person.character && (
                                        <p className="truncate text-xs text-muted-foreground">
                                            {person.character}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

function AddCopyDialog({
    movie,
    formats,
}: {
    movie: Movie;
    formats: Option<FormatValue>[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus />
                    Add a copy
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Add a copy of {movie.title}</DialogTitle>
                    <DialogDescription>
                        Each tape or disc you own is its own copy, so the VHS
                        and the 4K can sit side by side.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...store.form(movie.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <CopyFormFields
                                formats={formats}
                                errors={errors}
                                idPrefix="new-copy"
                            />
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    Add to my shelf
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function CopyRow({
    copy,
    formats,
}: {
    copy: Copy;
    formats: Option<FormatValue>[];
}) {
    const [open, setOpen] = useState(false);
    const details = [copy.edition, copy.condition].filter(Boolean).join(', ');

    return (
        <li className="flex items-start gap-3 rounded-md border border-border/70 bg-card/70 p-3">
            <FormatSticker format={copy.format} className="mt-0.5 text-xs" />
            <div className="min-w-0 flex-1 space-y-0.5">
                <p className="truncate text-sm font-medium">
                    {details || copy.format_label}
                </p>
                {copy.acquired_on && (
                    <p className="text-xs text-muted-foreground">
                        Brought home{' '}
                        {new Date(
                            `${copy.acquired_on}T00:00:00`,
                        ).toLocaleDateString(undefined, {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                        })}
                    </p>
                )}
                {copy.notes && (
                    <p className="text-xs text-muted-foreground">
                        {copy.notes}
                    </p>
                )}
            </div>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogTrigger asChild>
                    <Button size="icon" variant="ghost" aria-label="Edit copy">
                        <Pencil />
                    </Button>
                </DialogTrigger>
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>
                            Edit this {copy.format_label} copy
                        </DialogTitle>
                    </DialogHeader>
                    <Form
                        {...update.form(copy.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        className="space-y-6"
                    >
                        {({ errors, processing }) => (
                            <>
                                <CopyFormFields
                                    formats={formats}
                                    copy={copy}
                                    errors={errors}
                                    idPrefix={`copy-${copy.id}`}
                                />
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Save copy
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
            <Form {...destroy.form(copy.id)} options={{ preserveScroll: true }}>
                <Button
                    type="submit"
                    size="icon"
                    variant="ghost"
                    aria-label="Remove copy"
                >
                    <Trash2 />
                </Button>
            </Form>
        </li>
    );
}

function AddWatchDialog({
    movie,
    formats,
    conditions,
}: {
    movie: Movie;
    formats: Option<FormatValue>[];
    conditions: Option[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="secondary">
                    <Plus />
                    Track a price
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Hunt for {movie.title}</DialogTitle>
                    <DialogDescription>
                        eBay asking prices are checked every morning. Set a
                        target and the tag turns red when a copy drops under it.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...storeWatch.form(movie.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="watch-format">Format</Label>
                                    <Select name="format" defaultValue="vhs">
                                        <SelectTrigger id="watch-format">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {formats.map((format) => (
                                                <SelectItem
                                                    key={format.value}
                                                    value={format.value}
                                                >
                                                    {format.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.format} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="watch-condition">
                                        Condition
                                    </Label>
                                    <Select name="condition" defaultValue="any">
                                        <SelectTrigger id="watch-condition">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {conditions.map((condition) => (
                                                <SelectItem
                                                    key={condition.value}
                                                    value={condition.value}
                                                >
                                                    {condition.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.condition} />
                                </div>
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="watch-target">
                                    Target price, shipping included (optional)
                                </Label>
                                <Input
                                    id="watch-target"
                                    name="target_price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    inputMode="decimal"
                                    placeholder="15.00"
                                />
                                <InputError message={errors.target_price} />
                            </div>
                            <DialogFooter>
                                <RentalSticker
                                    tone="dayglo"
                                    className="mr-auto hidden -rotate-3 sm:inline-flex"
                                >
                                    Special order
                                </RentalSticker>
                                <Button type="submit" disabled={processing}>
                                    Start tracking
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
