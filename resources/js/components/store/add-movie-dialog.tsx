import { router, useHttp } from '@inertiajs/react';
import { Plus, ScanBarcode } from 'lucide-react';
import { lazy, Suspense, useEffect, useRef, useState } from 'react';
import { OsdText } from '@/components/store/crt-screen';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FormatSticker } from '@/components/store/format-sticker';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatLabels, tmdbImage } from '@/lib/store';
import type { FormatValue } from '@/types';
import { barcode, show, store, tmdbSearch } from '@/routes/movies';

type SearchResult = {
    tmdb_id: number;
    title: string;
    year: number | null;
    poster_path: string | null;
    overview: string | null;
    movie_id: number | null;
    owned_formats: FormatValue[];
};

// The barcode reader is large, so it only loads when the camera opens.
const BarcodeScanner = lazy(() =>
    import('@/components/store/barcode-scanner').then((module) => ({
        default: module.BarcodeScanner,
    })),
);

type BarcodeResult =
    | { owned: { movie_id: number; title: string; format_label: string } }
    | {
          owned?: undefined;
          barcode: string;
          title: string | null;
          year: number | null;
          format: FormatValue | null;
          listings: string[];
      };

/**
 * Finds a movie on TMDB by title, in any genre. From a result you can put a
 * copy straight on your shelf (importing the movie first if the store doesn't
 * have it) without leaving the search, or just add it to the store.
 *
 * With a barcode (scanned by camera or typed), eBay identifies the product and
 * pre-fills the search, and the barcode is saved on the copy. With `scan`, the
 * dialog opens straight into the camera and returns to it after each add, for
 * cataloguing a stack of cases.
 */
export function AddMovieDialog({ scan = false }: { scan?: boolean }) {
    const [open, setOpen] = useState(false);
    const [scanning, setScanning] = useState(false);
    const [scannedCode, setScannedCode] = useState<string | null>(null);
    const [manualCode, setManualCode] = useState('');
    const [ownedCopy, setOwnedCopy] = useState<{
        movie_id: number;
        title: string;
        format_label: string;
    } | null>(null);
    const [barcodeNote, setBarcodeNote] = useState<string | null>(null);
    const [defaultFormat, setDefaultFormat] = useState<FormatValue>('vhs');
    const cataloguing = useRef(false);
    const lookup = useHttp<Record<string, never>, BarcodeResult>({});
    const [adding, setAdding] = useState<number | null>(null);
    const [formats, setFormats] = useState<Record<number, FormatValue>>({});
    const [failed, setFailed] = useState(false);
    const search = useHttp<
        { q: string; year: string },
        { results: SearchResult[] }
    >({
        q: '',
        year: '',
    });

    const { q, year } = search.data;

    useEffect(() => {
        if (q.trim().length < 2) {
            return;
        }

        const timer = setTimeout(() => {
            search.cancel();
            void runSearch();
        }, 350);

        return () => clearTimeout(timer);
        // Re-run only when the search terms change.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q, year]);

    const runSearch = () =>
        search
            .get(tmdbSearch.url())
            .then(() => setFailed(false))
            .catch(() => setFailed(true));

    const addToShelf = (result: SearchResult) => {
        router.post(
            store.url(),
            {
                tmdb_id: result.tmdb_id,
                title: result.title,
                format: formats[result.tmdb_id] ?? defaultFormat,
                barcode: scannedCode,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => setAdding(result.tmdb_id),
                onFinish: () => setAdding(null),
                onSuccess: () => {
                    setScannedCode(null);
                    setManualCode('');
                    setBarcodeNote(null);

                    if (cataloguing.current) {
                        // Straight on to the next case in the stack.
                        search.setData({ q: '', year: '' });
                        setScanning(true);

                        return;
                    }

                    // Refresh the results so the new format tag shows up.
                    void runSearch();
                },
            },
        );
    };

    const startScanning = () => {
        cataloguing.current = true;
        setOwnedCopy(null);
        setBarcodeNote(null);
        setScanning(true);
    };

    const identify = (code: string) => {
        setScanning(false);
        setScannedCode(code);
        setManualCode(code);
        setOwnedCopy(null);
        setBarcodeNote(null);

        lookup
            .get(barcode.url({ query: { code } }))
            .then((found) => {
                if (found.owned) {
                    setOwnedCopy(found.owned);

                    return;
                }

                if (found.format) {
                    setDefaultFormat(found.format);
                }

                if (found.title) {
                    search.setData({
                        q: found.title,
                        year: found.year ? String(found.year) : '',
                    });
                    setBarcodeNote(
                        found.listings[0]
                            ? `Matched an eBay listing: "${found.listings[0]}". Pick the right movie below.`
                            : null,
                    );
                } else {
                    setBarcodeNote(
                        `No eBay listings for barcode ${code}. Search by title below; the barcode is still saved with the copy.`,
                    );
                }
            })
            .catch(() =>
                setBarcodeNote(
                    "Couldn't look up that barcode (is eBay set up?). Search by title below; the barcode is still saved with the copy.",
                ),
            );
    };

    const addToStore = (result: SearchResult) => {
        router.post(
            store.url(),
            { tmdb_id: result.tmdb_id, title: result.title },
            {
                onStart: () => setAdding(result.tmdb_id),
                onFinish: () => setAdding(null),
                onSuccess: () => setOpen(false),
            },
        );
    };

    const results =
        q.trim().length >= 2 ? (search.response?.results ?? []) : [];

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (next && scan) {
                    startScanning();
                }

                if (!next) {
                    setScanning(false);
                    cataloguing.current = false;
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant="secondary">
                    {scan ? <ScanBarcode /> : <Plus />}
                    {scan ? 'Scan a case' : 'Add a movie'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85svh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Add a movie</DialogTitle>
                    <DialogDescription>
                        Search TMDB by title or director, in any genre ("Lucio
                        Fulci zombi" works). Pick a format to put a copy on your
                        shelf without leaving the search.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3">
                    {scanning ? (
                        <>
                            <Suspense
                                fallback={
                                    <div className="static-skeleton aspect-video w-full" />
                                }
                            >
                                <BarcodeScanner onDetected={identify} />
                            </Suspense>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setScanning(false);
                                    cataloguing.current = false;
                                }}
                            >
                                Stop scanning
                            </Button>
                        </>
                    ) : (
                        <div className="flex flex-wrap items-end gap-2">
                            <Button variant="secondary" onClick={startScanning}>
                                <ScanBarcode />
                                Scan barcode
                            </Button>
                            <form
                                className="flex items-end gap-2"
                                onSubmit={(event) => {
                                    event.preventDefault();

                                    if (/^\d{8,14}$/.test(manualCode.trim())) {
                                        identify(manualCode.trim());
                                    }
                                }}
                            >
                                <Input
                                    aria-label="Barcode digits"
                                    inputMode="numeric"
                                    value={manualCode}
                                    placeholder="or type the barcode"
                                    onChange={(event) =>
                                        setManualCode(
                                            event.target.value.replace(
                                                /\D/g,
                                                '',
                                            ),
                                        )
                                    }
                                    className="w-44"
                                />
                                <Button
                                    type="submit"
                                    variant="ghost"
                                    disabled={
                                        lookup.processing ||
                                        !/^\d{8,14}$/.test(manualCode.trim())
                                    }
                                >
                                    Look up
                                </Button>
                            </form>
                        </div>
                    )}

                    {lookup.processing && (
                        <OsdText blink className="text-2xl">
                            READING BARCODE…
                        </OsdText>
                    )}

                    {ownedCopy && (
                        <div className="flex flex-wrap items-center gap-3 border border-tube/60 px-3 py-2">
                            <span>
                                Already on your shelf: {ownedCopy.title} (
                                {ownedCopy.format_label})
                            </span>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() =>
                                    router.visit(show(ownedCopy.movie_id))
                                }
                            >
                                Open
                            </Button>
                            <Button size="sm" onClick={startScanning}>
                                Scan another
                            </Button>
                        </div>
                    )}

                    {barcodeNote && (
                        <p className="text-sm text-muted-foreground">
                            {barcodeNote}
                        </p>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_8rem]">
                    <div className="grid gap-1.5">
                        <Label htmlFor="add-movie-title">
                            Title or director
                        </Label>
                        <Input
                            id="add-movie-title"
                            type="search"
                            autoFocus
                            value={q}
                            placeholder="The Return of the Living Dead"
                            onChange={(event) =>
                                search.setData('q', event.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="add-movie-year">Year (optional)</Label>
                        <Input
                            id="add-movie-year"
                            type="number"
                            inputMode="numeric"
                            value={year}
                            placeholder="1985"
                            onChange={(event) =>
                                search.setData('year', event.target.value)
                            }
                        />
                    </div>
                </div>

                <div aria-live="polite" className="min-h-24">
                    {search.processing && (
                        <OsdText blink className="text-2xl">
                            SEARCHING…
                        </OsdText>
                    )}

                    {!search.processing && failed && (
                        <p className="text-neon">
                            Couldn't reach TMDB. Check the TMDB_TOKEN setting
                            and try again.
                        </p>
                    )}

                    {!search.processing &&
                        !failed &&
                        q.trim().length >= 2 &&
                        search.response &&
                        results.length === 0 && (
                            <p className="text-muted-foreground">
                                Nothing on TMDB matches that title. Check the
                                spelling or drop the year.
                            </p>
                        )}

                    {results.length > 0 && (
                        <ul className="divide-y divide-border">
                            {results.map((result) => {
                                const poster = tmdbImage(
                                    result.poster_path,
                                    'w92',
                                );

                                return (
                                    <li
                                        key={result.tmdb_id}
                                        className="flex flex-wrap items-start gap-3 py-3"
                                    >
                                        {poster ? (
                                            <img
                                                src={poster}
                                                alt=""
                                                loading="lazy"
                                                className="h-20 w-14 shrink-0 object-cover"
                                            />
                                        ) : (
                                            <div className="h-20 w-14 shrink-0 bg-muted" />
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <p className="text-lg leading-tight">
                                                {result.title}
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    {result.year ??
                                                        'year unknown'}
                                                </span>
                                            </p>
                                            {result.overview && (
                                                <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                                    {result.overview}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex w-full shrink-0 flex-col items-start gap-2 sm:w-auto sm:items-end">
                                            <div className="flex items-center gap-2">
                                                <Select
                                                    value={
                                                        formats[
                                                            result.tmdb_id
                                                        ] ?? defaultFormat
                                                    }
                                                    onValueChange={(format) =>
                                                        setFormats(
                                                            (current) => ({
                                                                ...current,
                                                                [result.tmdb_id]:
                                                                    format as FormatValue,
                                                            }),
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger
                                                        size="sm"
                                                        className="w-28"
                                                        aria-label={`Format for ${result.title}`}
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {(
                                                            Object.keys(
                                                                formatLabels,
                                                            ) as FormatValue[]
                                                        ).map((format) => (
                                                            <SelectItem
                                                                key={format}
                                                                value={format}
                                                            >
                                                                {
                                                                    formatLabels[
                                                                        format
                                                                    ]
                                                                }
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <Button
                                                    size="sm"
                                                    disabled={adding !== null}
                                                    onClick={() =>
                                                        addToShelf(result)
                                                    }
                                                >
                                                    {adding === result.tmdb_id
                                                        ? 'Adding…'
                                                        : 'Add to my shelf'}
                                                </Button>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {result.owned_formats.map(
                                                    (format) => (
                                                        <FormatSticker
                                                            key={format}
                                                            format={format}
                                                        />
                                                    ),
                                                )}
                                                {result.movie_id ? (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            router.visit(
                                                                show(
                                                                    result.movie_id!,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        Open
                                                    </Button>
                                                ) : (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        disabled={
                                                            adding !== null
                                                        }
                                                        onClick={() =>
                                                            addToStore(result)
                                                        }
                                                    >
                                                        Add to store only
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
