import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Clamshell } from '@/components/store/clamshell';
import { PageSign } from '@/components/store/page-sign';
import { Button } from '@/components/ui/button';
import { store as storeWatch } from '@/routes/watches';
import type { MovieCard } from '@/types';

type Props = {
    franchise: {
        id: number;
        name: string;
        overview: string | null;
        poster_path: string | null;
        backdrop_path: string | null;
    };
    movies: MovieCard[];
    ownedCount: number;
    wantedIds: number[];
};

export default function FranchiseShow({
    franchise,
    movies,
    ownedCount,
    wantedIds,
}: Props) {
    const [adding, setAdding] = useState<number | null>(null);
    const missing = movies.length - ownedCount;

    const want = (movie: MovieCard) => {
        router.post(
            storeWatch.url(movie.id),
            { format: null, condition: 'any', target_price: null },
            {
                preserveScroll: true,
                onStart: () => setAdding(movie.id),
                onFinish: () => setAdding(null),
            },
        );
    };

    return (
        <>
            <Head title={franchise.name} />
            <div className="space-y-8 px-4 sm:px-8">
                <PageSign
                    title={franchise.name}
                    description={
                        missing === 0
                            ? `You own all ${movies.length}. Series complete.`
                            : `You own ${ownedCount} of ${movies.length}. The dimmed covers are the ${missing} still missing.`
                    }
                />

                {franchise.overview && (
                    <p className="max-w-[68ch] text-lg leading-relaxed text-foreground/85">
                        {franchise.overview}
                    </p>
                )}

                <ol className="grid grid-cols-2 gap-x-5 gap-y-8 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                    {movies.map((movie) => {
                        const owned = (movie.owned_formats?.length ?? 0) > 0;
                        const wanted = wantedIds.includes(movie.id);

                        return (
                            <li key={movie.id} className="space-y-2">
                                <Clamshell movie={movie} dimUnowned />
                                {!owned &&
                                    (wanted ? (
                                        <p className="px-0.5 text-sm text-dayglo">
                                            On your want list
                                        </p>
                                    ) : (
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            className="w-full"
                                            disabled={adding !== null}
                                            onClick={() => want(movie)}
                                        >
                                            <Plus />
                                            {adding === movie.id
                                                ? 'Adding…'
                                                : 'Want list'}
                                        </Button>
                                    ))}
                            </li>
                        );
                    })}
                </ol>
            </div>
        </>
    );
}
