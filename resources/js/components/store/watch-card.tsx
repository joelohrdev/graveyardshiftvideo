import { Form, Link } from '@inertiajs/react';
import { ExternalLink, Pause, Play, RefreshCw, Trash2 } from 'lucide-react';
import { OsdText } from '@/components/store/crt-screen';
import { RentalSticker } from '@/components/store/format-sticker';
import { PriceChart } from '@/components/store/price-chart';
import { Button } from '@/components/ui/button';
import { formatPrice, tmdbImage } from '@/lib/store';
import { cn } from '@/lib/utils';
import { show as showMovie } from '@/routes/movies';
import { check, destroy, update } from '@/routes/watches';
import type { Watch } from '@/types';

const dealTones: Record<Watch['deal_status'], 'neon' | 'dayglo' | 'label'> = {
    steal: 'neon',
    good_deal: 'dayglo',
    hold: 'label',
    wait: 'label',
};

const dealHelp: Record<Watch['deal_status'], string> = {
    steal: 'At or under your target price.',
    good_deal: 'At least 20% under its usual price this month.',
    hold: 'About the going rate. No rush.',
    wait: 'Not enough price history yet to call it.',
};

/**
 * One special order on the want list: today's best eBay price, the trend and
 * whether now is the time to buy.
 */
export function WatchCard({
    watch,
    showMovie: withMovie = false,
}: {
    watch: Watch;
    showMovie?: boolean;
}) {
    const poster = tmdbImage(watch.movie?.poster_path ?? null, 'w92');

    return (
        <article
            className={cn(
                'grid gap-4 rounded-md border border-border/70 bg-card/70 p-4',
                !watch.active && 'opacity-60',
            )}
        >
            <header className="flex items-start gap-3">
                {withMovie && watch.movie && (
                    <Link href={showMovie(watch.movie.id)} className="shrink-0">
                        {poster ? (
                            <img
                                src={poster}
                                alt=""
                                className="h-16 w-11 rounded-[2px] object-cover"
                            />
                        ) : (
                            <div className="h-16 w-11 rounded-[2px] bg-muted" />
                        )}
                    </Link>
                )}
                <div className="min-w-0 flex-1">
                    {withMovie && watch.movie && (
                        <Link
                            href={showMovie(watch.movie.id)}
                            className="block truncate font-medium hover:text-label"
                        >
                            {watch.movie.title}
                            {watch.movie.year && (
                                <span className="text-muted-foreground">
                                    {' '}
                                    ({watch.movie.year})
                                </span>
                            )}
                        </Link>
                    )}
                    <p className="text-sm text-muted-foreground">
                        {watch.format_label},{' '}
                        {watch.condition_label.toLowerCase()}
                        {!watch.active && ', paused'}
                    </p>
                </div>
                <RentalSticker
                    tone={dealTones[watch.deal_status]}
                    alarm={watch.deal_status === 'steal' && watch.active}
                    className="rotate-6"
                >
                    {watch.deal_label}
                </RentalSticker>
            </header>

            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <OsdText className="text-4xl leading-none">
                        {formatPrice(watch.latest?.lowest_price)}
                    </OsdText>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {watch.latest
                            ? `Lowest of ${watch.latest.listing_count} listings, shipping included. ${dealHelp[watch.deal_status]}`
                            : dealHelp.wait}
                    </p>
                </div>
                {watch.latest?.lowest_listing_url && (
                    <a
                        href={watch.latest.lowest_listing_url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 text-sm text-label underline-offset-4 hover:underline"
                    >
                        See it on eBay
                        <ExternalLink className="size-3.5" />
                    </a>
                )}
            </div>

            <PriceChart history={watch.history} target={watch.target_price} />

            <footer className="flex flex-wrap gap-2">
                <Form
                    {...check.form(watch.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <Button
                            type="submit"
                            size="sm"
                            variant="secondary"
                            disabled={processing || !watch.active}
                        >
                            <RefreshCw
                                className={cn(processing && 'animate-spin')}
                            />
                            Check prices now
                        </Button>
                    )}
                </Form>
                <Form
                    {...update.form(watch.id)}
                    options={{ preserveScroll: true }}
                    transform={() => ({
                        format: watch.format,
                        condition: watch.condition,
                        target_price: watch.target_price,
                        active: !watch.active,
                    })}
                >
                    <Button type="submit" size="sm" variant="ghost">
                        {watch.active ? <Pause /> : <Play />}
                        {watch.active ? 'Pause' : 'Resume'}
                    </Button>
                </Form>
                <Form
                    {...destroy.form(watch.id)}
                    options={{ preserveScroll: true }}
                >
                    <Button type="submit" size="sm" variant="ghost">
                        <Trash2 />
                        Remove
                    </Button>
                </Form>
            </footer>
        </article>
    );
}
