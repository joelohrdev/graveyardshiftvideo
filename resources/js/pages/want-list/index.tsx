import { Head, Link } from '@inertiajs/react';
import { AllRented } from '@/components/store/all-rented';
import { PageSign } from '@/components/store/page-sign';
import { WatchCard } from '@/components/store/watch-card';
import { index as aisles } from '@/routes/movies';
import type { Watch } from '@/types';

type Props = {
    watches: Watch[];
    ebayConfigured: boolean;
};

export default function WantListIndex({ watches, ebayConfigured }: Props) {
    return (
        <>
            <Head title="Want list" />
            <div className="space-y-8 px-4 sm:px-8">
                <PageSign
                    title="Want List"
                    description="Movies you're hunting for. eBay asking prices are recorded every morning, so each tag compares today's lowest price with the last month."
                />

                {!ebayConfigured && (
                    <div className="rounded-md border border-dayglo/50 bg-dayglo/10 px-4 py-3 text-sm">
                        Prices can't be checked until you add{' '}
                        <code className="font-osd text-base">
                            EBAY_CLIENT_ID
                        </code>{' '}
                        and{' '}
                        <code className="font-osd text-base">
                            EBAY_CLIENT_SECRET
                        </code>{' '}
                        from a free eBay developer account to your{' '}
                        <code className="font-osd text-base">.env</code>.
                    </div>
                )}

                {watches.length === 0 ? (
                    <AllRented title="No special orders">
                        Open any movie in{' '}
                        <Link
                            href={aisles()}
                            className="text-label underline underline-offset-4"
                        >
                            the aisles
                        </Link>{' '}
                        and choose "Track a price".
                    </AllRented>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                        {watches.map((watch) => (
                            <WatchCard key={watch.id} watch={watch} showMovie />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
