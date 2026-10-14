import { router, useHttp } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
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
import { tmdbImage } from '@/lib/store';
import { show, store, tmdbSearch } from '@/routes/movies';

type SearchResult = {
    tmdb_id: number;
    title: string;
    year: number | null;
    poster_path: string | null;
    overview: string | null;
    movie_id: number | null;
};

/**
 * Finds a movie on TMDB by title, in any genre, and pulls its full record
 * into the store. For titles the horror import missed.
 */
export function AddMovieDialog() {
    const [open, setOpen] = useState(false);
    const [adding, setAdding] = useState<number | null>(null);
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
            search
                .get(tmdbSearch.url())
                .then(() => setFailed(false))
                .catch(() => setFailed(true));
        }, 350);

        return () => clearTimeout(timer);
        // Re-run only when the search terms change.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q, year]);

    const add = (result: SearchResult) => {
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
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="secondary">
                    <Plus />
                    Add a movie
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85svh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Add a movie</DialogTitle>
                    <DialogDescription>
                        Search TMDB by title. Any genre works, so you can add
                        anything the horror import missed.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_8rem]">
                    <div className="grid gap-1.5">
                        <Label htmlFor="add-movie-title">Title</Label>
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
                                        className="flex items-start gap-3 py-3"
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
                                        {result.movie_id ? (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    router.visit(
                                                        show(result.movie_id!),
                                                    )
                                                }
                                            >
                                                In store
                                            </Button>
                                        ) : (
                                            <Button
                                                size="sm"
                                                disabled={adding !== null}
                                                onClick={() => add(result)}
                                            >
                                                {adding === result.tmdb_id
                                                    ? 'Adding…'
                                                    : 'Add'}
                                            </Button>
                                        )}
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
