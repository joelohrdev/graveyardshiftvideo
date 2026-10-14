import { Form, Head, usePoll } from '@inertiajs/react';
import { Archive } from 'lucide-react';
import InputError from '@/components/input-error';
import { CrtScreen, OsdText } from '@/components/store/crt-screen';
import { PageSign } from '@/components/store/page-sign';
import { TmdbCredit } from '@/components/store/tmdb-credit';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/imports';

type ImportRun = {
    id: string;
    name: string;
    total_jobs: number;
    pending_jobs: number;
    processed_jobs: number;
    failed_jobs: number;
    progress: number;
    finished: boolean;
    cancelled: boolean;
    created_at: string;
    finished_at: string | null;
};

type Props = {
    tmdbConfigured: boolean;
    import: ImportRun | null;
    catalog: { movies: number; synced: number; oldest_sync: string | null };
    earliestYear: number;
};

export default function BackRoomIndex({
    tmdbConfigured,
    import: run,
    catalog,
    earliestYear,
}: Props) {
    const running = run !== null && !run.finished && !run.cancelled;

    usePoll(
        3000,
        { only: ['import', 'catalog'] },
        { autoStart: running, keepAlive: true },
    );

    const currentYear = new Date().getFullYear();

    return (
        <>
            <Head title="Back room" />
            <div className="space-y-10 px-4 sm:px-8">
                <PageSign
                    title="Back Room"
                    description="Restock the store from The Movie Database. Imports run in the background, one release year at a time, and stored movies refresh on their own before they're six months old."
                />

                <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,26rem)]">
                    <CrtScreen className="min-h-64 p-6">
                        <div className="relative z-10 space-y-5">
                            <OsdText blink={running} className="block text-3xl">
                                {running
                                    ? '▶▶ RESTOCKING'
                                    : run?.finished
                                      ? '■ STOP'
                                      : '❚❚ STANDBY'}
                            </OsdText>
                            <dl className="grid grid-cols-2 gap-x-8 gap-y-3 sm:grid-cols-3">
                                <Readout
                                    label="Movies in store"
                                    value={catalog.movies.toLocaleString()}
                                />
                                <Readout
                                    label="Details fetched"
                                    value={catalog.synced.toLocaleString()}
                                />
                                {run && (
                                    <Readout
                                        label="Jobs done"
                                        value={`${run.processed_jobs.toLocaleString()} / ${run.total_jobs.toLocaleString()}`}
                                    />
                                )}
                            </dl>
                            {run && (
                                <div className="space-y-2">
                                    <div
                                        className="h-4 overflow-hidden border border-tube/50 bg-black"
                                        role="progressbar"
                                        aria-valuenow={run.progress}
                                        aria-valuemin={0}
                                        aria-valuemax={100}
                                        aria-label="Import progress"
                                    >
                                        <div
                                            className="h-full bg-[repeating-linear-gradient(90deg,var(--tube)_0_10px,transparent_10px_13px)] transition-[width] duration-700"
                                            style={{
                                                width: `${run.progress}%`,
                                            }}
                                        />
                                    </div>
                                    <OsdText className="block text-lg">
                                        {run.progress}%{' '}
                                        {run.failed_jobs > 0 &&
                                            `  ${run.failed_jobs} FAILED`}
                                    </OsdText>
                                    <p className="text-xs text-muted-foreground">
                                        Started{' '}
                                        {new Date(
                                            run.created_at,
                                        ).toLocaleString()}
                                        {run.finished_at &&
                                            `, finished ${new Date(run.finished_at).toLocaleString()}`}
                                        . Page jobs add the rest of each year as
                                        they go, so the total keeps growing at
                                        first.
                                    </p>
                                </div>
                            )}
                        </div>
                    </CrtScreen>

                    <section aria-labelledby="restock" className="space-y-4">
                        <h2
                            id="restock"
                            className="osd-text text-3xl uppercase"
                        >
                            Restock the shelves
                        </h2>
                        {!tmdbConfigured && (
                            <p className="rounded-md border border-dayglo/50 bg-dayglo/10 px-4 py-3 text-sm">
                                Add a TMDB API read access token as{' '}
                                <code className="font-osd text-base">
                                    TMDB_TOKEN
                                </code>{' '}
                                in{' '}
                                <code className="font-osd text-base">.env</code>{' '}
                                to restock.
                            </p>
                        )}
                        <Form
                            {...store.form()}
                            className="space-y-4"
                            transform={(data) => ({
                                ...data,
                                refresh: data.refresh === 'on',
                            })}
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="from-year">
                                                From
                                            </Label>
                                            <Input
                                                id="from-year"
                                                name="from_year"
                                                type="number"
                                                min={earliestYear}
                                                max={currentYear}
                                                placeholder={String(
                                                    earliestYear,
                                                )}
                                            />
                                            <InputError
                                                message={errors.from_year}
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="to-year">To</Label>
                                            <Input
                                                id="to-year"
                                                name="to_year"
                                                type="number"
                                                min={earliestYear}
                                                max={currentYear + 1}
                                                placeholder={String(
                                                    currentYear,
                                                )}
                                            />
                                            <InputError
                                                message={errors.to_year}
                                            />
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-2">
                                        <Checkbox id="refresh" name="refresh" />
                                        <Label
                                            htmlFor="refresh"
                                            className="leading-snug font-normal"
                                        >
                                            Re-fetch details for movies already
                                            in the store
                                        </Label>
                                    </div>
                                    <p className="text-xs leading-relaxed text-muted-foreground">
                                        Leave the years blank to import every
                                        horror movie TMDB has. That's tens of
                                        thousands of titles and takes a few
                                        hours with one queue worker.
                                    </p>
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing ||
                                            running ||
                                            !tmdbConfigured
                                        }
                                    >
                                        <Archive />
                                        {running
                                            ? 'Restocking…'
                                            : 'Restock the shelves'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>
                </div>

                <TmdbCredit />
            </div>
        </>
    );
}

function Readout({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd>
                <OsdText className="text-3xl leading-none">{value}</OsdText>
            </dd>
        </div>
    );
}
