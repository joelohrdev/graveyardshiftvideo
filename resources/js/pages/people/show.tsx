import { Head } from '@inertiajs/react';
import { Clamshell } from '@/components/store/clamshell';
import { PageSign } from '@/components/store/page-sign';
import { CoverShelf } from '@/components/store/cover-shelf';
import { tmdbImage } from '@/lib/store';
import type { MovieCard } from '@/types';

type Props = {
    person: {
        id: number;
        tmdb_id: number;
        name: string;
        profile_path: string | null;
    };
    directed: MovieCard[];
    starred: MovieCard[];
    ownedCount: number;
};

export default function PersonShow({
    person,
    directed,
    starred,
    ownedCount,
}: Props) {
    const portrait = tmdbImage(person.profile_path, 'w185');
    const total = new Set([...directed, ...starred].map((movie) => movie.id))
        .size;

    return (
        <>
            <Head title={person.name} />
            <div className="space-y-12 px-4 sm:px-8">
                <div className="flex flex-wrap items-end gap-6">
                    {portrait && (
                        <img
                            src={portrait}
                            alt=""
                            className="clamshell h-36 w-24 rounded-[3px] object-cover"
                        />
                    )}
                    <PageSign
                        title={person.name}
                        description={`You own ${ownedCount} of the ${total} movies in the store with their name on the box. The dimmed covers are the ones still missing.`}
                    />
                </div>

                {directed.length > 0 && (
                    <section className="space-y-4">
                        <CoverShelf
                            shelf={{
                                key: 'directed',
                                label: 'Directed',
                                person_id: null,
                                movies: directed,
                            }}
                            dimUnowned
                        />
                    </section>
                )}

                {starred.length > 0 && (
                    <section aria-labelledby="starred" className="space-y-4">
                        <h2
                            id="starred"
                            className="osd-text text-3xl uppercase"
                        >
                            Starring in
                        </h2>
                        <div className="grid grid-cols-2 gap-x-5 gap-y-8 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8">
                            {starred.map((movie) => (
                                <Clamshell
                                    key={movie.id}
                                    movie={movie}
                                    dimUnowned
                                />
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}
