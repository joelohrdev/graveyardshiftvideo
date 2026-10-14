import { Head, Link, usePage } from '@inertiajs/react';
import { CrtScreen, OsdText } from '@/components/store/crt-screen';
import { TmdbCredit } from '@/components/store/tmdb-credit';
import { TrackingGlitch } from '@/components/store/tracking-glitch';
import { login } from '@/routes';
import { index as collection } from '@/routes/collection';

/**
 * Channel 3 at 2 a.m.: the set warms up on the store's ident and waits for PLAY.
 */
export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Channel 3" />
            <TrackingGlitch />
            <div className="flex min-h-svh flex-col items-center justify-center px-6 py-16">
                <main className="w-full max-w-3xl">
                    <CrtScreen powerOn className="w-full">
                        <div className="relative z-10 flex min-h-[30rem] flex-col justify-between gap-12 p-6 sm:p-10">
                            <div className="flex items-start justify-between text-2xl sm:text-3xl">
                                <OsdText>CH 03</OsdText>
                                <OsdText blink>2:00 AM</OsdText>
                            </div>

                            <div className="space-y-4 text-center">
                                <h1 className="flex flex-col items-center gap-2 uppercase">
                                    <OsdText className="text-5xl leading-none sm:text-7xl">
                                        Graveyard Shift
                                    </OsdText>
                                    <span className="osd-inverse px-3 pt-1 text-3xl leading-none sm:text-4xl">
                                        Video
                                    </span>
                                </h1>
                                <OsdText className="block text-xl sm:text-2xl">
                                    Horror only. Open till dawn.
                                </OsdText>
                            </div>

                            <div className="flex flex-col items-center gap-3">
                                <Link
                                    href={auth.user ? collection() : login()}
                                    className="px-4 pt-1.5 pb-1 text-3xl uppercase hover:osd-inverse focus-visible:osd-inverse focus-visible:outline-none"
                                >
                                    ▶{' '}
                                    {auth.user
                                        ? 'Back to my shelf'
                                        : 'Press play'}
                                </Link>
                                <p className="max-w-md text-center text-lg text-muted-foreground">
                                    Your collection on the shelves, every horror
                                    movie on TMDB in the aisles, and a price on
                                    everything you're still hunting for.
                                </p>
                            </div>
                        </div>
                    </CrtScreen>
                </main>
                <div className="mt-10 w-full max-w-3xl">
                    <TmdbCredit />
                </div>
            </div>
        </>
    );
}
