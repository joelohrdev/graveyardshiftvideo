<?php

namespace App\Http\Controllers;

use App\Enums\DealStatus;
use App\Http\Requests\StoreWatchRequest;
use App\Http\Requests\UpdateWatchRequest;
use App\Http\Resources\WatchResource;
use App\Jobs\SnapshotWatchPrices;
use App\Models\Movie;
use App\Models\Watch;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WatchController extends Controller
{
    /**
     * The special-orders wall: every movie I'm hunting for, best deals first.
     */
    public function index(Request $request): Response
    {
        $watches = $request->user()->watches()
            ->with([
                'movie',
                'snapshots' => fn (HasMany $snapshots) => $snapshots->where('checked_on', '>=', now()->subDays(90)),
            ])
            ->get()
            ->sortBy([
                fn (Watch $a, Watch $b) => $b->active <=> $a->active,
                fn (Watch $a, Watch $b) => $a->dealStatus()->rank() <=> $b->dealStatus()->rank(),
                fn (Watch $a, Watch $b) => $a->movie->title <=> $b->movie->title,
            ])
            ->values();

        return Inertia::render('want-list/index', [
            'watches' => WatchResource::collection($watches)->resolve(),
            'ebayConfigured' => $this->ebayConfigured(),
        ]);
    }

    /**
     * Start pricing a movie I don't own yet.
     */
    public function store(StoreWatchRequest $request, Movie $movie): RedirectResponse
    {
        $request->user()->watches()->create([
            ...$request->validated(),
            'movie_id' => $movie->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$movie->title} added to your want list."]);

        return back();
    }

    public function update(UpdateWatchRequest $request, Watch $watch): RedirectResponse
    {
        $watch->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Want list updated.']);

        return back();
    }

    public function destroy(Watch $watch): RedirectResponse
    {
        Gate::authorize('delete', $watch);

        $watch->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Removed from your want list.']);

        return back();
    }

    /**
     * Price a watch right now instead of waiting for the daily run.
     */
    public function check(Watch $watch): RedirectResponse
    {
        Gate::authorize('update', $watch);

        if (! $this->ebayConfigured()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Add EBAY_CLIENT_ID and EBAY_CLIENT_SECRET to .env first.']);

            return back();
        }

        try {
            SnapshotWatchPrices::dispatchSync($watch);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);
            Inertia::flash('toast', ['type' => 'error', 'message' => 'eBay did not answer. Try again in a bit.']);

            return back();
        }

        $status = $watch->load('snapshots')->dealStatus();
        $message = $status === DealStatus::Steal ? 'STEAL! Under your target price.' : 'Prices checked.';

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    protected function ebayConfigured(): bool
    {
        return filled(config('services.ebay.client_id')) && filled(config('services.ebay.client_secret'));
    }
}
