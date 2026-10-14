<?php

namespace Database\Seeders;

use App\Enums\CreditRole;
use App\Enums\ListingCondition;
use App\Enums\MediaFormat;
use App\Models\Copy;
use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Movie;
use App\Models\Person;
use App\Models\User;
use App\Models\Watch;
use Illuminate\Database\Seeder;

/**
 * Stocks the store with a handful of real horror classics, a shelf of copies for the
 * test user and a want list with a month of price history, so every screen has
 * something on it before a TMDB import runs.
 *
 * Movies use their real TMDB ids and are left unsynced, so the first import or
 * refresh replaces this placeholder data with TMDB's (posters included).
 */
class DemoCollectionSeeder extends Seeder
{
    /**
     * Placeholder people and keywords get ids far above TMDB's range so they never collide with real ones.
     */
    private const int PlaceholderIdBase = 900_000_000;

    /**
     * @var list<array{tmdb_id: int, title: string, year: int, runtime: int, director: string, genres: list<string>, keywords: list<string>, overview: string}>
     */
    private const array Movies = [
        ['tmdb_id' => 948, 'title' => 'Halloween', 'year' => 1978, 'runtime' => 91, 'director' => 'John Carpenter', 'genres' => ['Horror', 'Thriller'], 'keywords' => ['slasher', 'masked killer'], 'overview' => 'Fifteen years after murdering his sister, a silent killer escapes and comes home to Haddonfield.'],
        ['tmdb_id' => 1091, 'title' => 'The Thing', 'year' => 1982, 'runtime' => 109, 'director' => 'John Carpenter', 'genres' => ['Horror', 'Science Fiction'], 'keywords' => ['body horror', 'creature', 'antarctica'], 'overview' => 'An Antarctic research crew finds something in the ice that can become any one of them.'],
        ['tmdb_id' => 790, 'title' => 'The Fog', 'year' => 1980, 'runtime' => 89, 'director' => 'John Carpenter', 'genres' => ['Horror'], 'keywords' => ['ghost', 'small town'], 'overview' => 'A glowing fog rolls into a coastal town on its centennial, carrying the dead with it.'],
        ['tmdb_id' => 8769, 'title' => 'Christine', 'year' => 1983, 'runtime' => 110, 'director' => 'John Carpenter', 'genres' => ['Horror'], 'keywords' => ['possession', 'stephen king'], 'overview' => 'A nerdy teenager restores a 1958 Plymouth Fury that is jealous, vengeful and alive.'],
        ['tmdb_id' => 8337, 'title' => 'They Live', 'year' => 1988, 'runtime' => 94, 'director' => 'John Carpenter', 'genres' => ['Horror', 'Science Fiction'], 'keywords' => ['aliens', 'satire'], 'overview' => 'A drifter finds sunglasses that reveal the aliens running the world and the messages hidden in every ad.'],
        ['tmdb_id' => 764, 'title' => 'The Evil Dead', 'year' => 1981, 'runtime' => 85, 'director' => 'Sam Raimi', 'genres' => ['Horror'], 'keywords' => ['cabin in the woods', 'demon', 'possession'], 'overview' => 'Five friends at a remote cabin play a tape that wakes something in the woods.'],
        ['tmdb_id' => 765, 'title' => 'Evil Dead II', 'year' => 1987, 'runtime' => 84, 'director' => 'Sam Raimi', 'genres' => ['Horror', 'Comedy'], 'keywords' => ['cabin in the woods', 'demon', 'splatter'], 'overview' => 'Ash returns to the cabin and spends one very long night fighting the Deadites, and his own hand.'],
        ['tmdb_id' => 766, 'title' => 'Army of Darkness', 'year' => 1992, 'runtime' => 81, 'director' => 'Sam Raimi', 'genres' => ['Horror', 'Comedy'], 'keywords' => ['demon', 'time travel'], 'overview' => 'Thrown back to the Middle Ages, Ash has to recover the Necronomicon and lead a castle against the dead.'],
        ['tmdb_id' => 377, 'title' => 'A Nightmare on Elm Street', 'year' => 1984, 'runtime' => 91, 'director' => 'Wes Craven', 'genres' => ['Horror'], 'keywords' => ['slasher', 'dream'], 'overview' => 'Teenagers on Elm Street are hunted in their dreams by a burned man with a bladed glove.'],
        ['tmdb_id' => 4232, 'title' => 'Scream', 'year' => 1996, 'runtime' => 111, 'director' => 'Wes Craven', 'genres' => ['Horror', 'Thriller'], 'keywords' => ['slasher', 'masked killer'], 'overview' => 'A killer who knows every horror movie rule stalks the students of Woodsboro.'],
        ['tmdb_id' => 11906, 'title' => 'Suspiria', 'year' => 1977, 'runtime' => 99, 'director' => 'Dario Argento', 'genres' => ['Horror'], 'keywords' => ['witch', 'giallo'], 'overview' => 'An American dancer arrives at a German ballet academy run by a coven.'],
        ['tmdb_id' => 1694, 'title' => 'Re-Animator', 'year' => 1985, 'runtime' => 86, 'director' => 'Stuart Gordon', 'genres' => ['Horror', 'Comedy'], 'keywords' => ['mad scientist', 'splatter', 'lovecraft'], 'overview' => 'A medical student brings the dead back with a glowing green serum. They do not come back well.'],
        ['tmdb_id' => 9003, 'title' => 'Hellraiser', 'year' => 1987, 'runtime' => 94, 'director' => 'Clive Barker', 'genres' => ['Horror'], 'keywords' => ['demon', 'puzzle box', 'body horror'], 'overview' => 'A puzzle box opens the door to the Cenobites, and a dead man wants back in.'],
        ['tmdb_id' => 694, 'title' => 'The Shining', 'year' => 1980, 'runtime' => 144, 'director' => 'Stanley Kubrick', 'genres' => ['Horror', 'Thriller'], 'keywords' => ['haunted hotel', 'stephen king'], 'overview' => 'A writer takes the winter caretaker job at an empty mountain hotel with his wife and son.'],
        ['tmdb_id' => 9552, 'title' => 'The Exorcist', 'year' => 1973, 'runtime' => 122, 'director' => 'William Friedkin', 'genres' => ['Horror'], 'keywords' => ['possession', 'demon'], 'overview' => 'Two priests try to save a twelve-year-old girl from the demon inside her.'],
        ['tmdb_id' => 30497, 'title' => 'The Texas Chain Saw Massacre', 'year' => 1974, 'runtime' => 84, 'director' => 'Tobe Hooper', 'genres' => ['Horror'], 'keywords' => ['slasher', 'cannibal'], 'overview' => 'Five friends on a Texas road trip stop at the wrong farmhouse.'],
        ['tmdb_id' => 609, 'title' => 'Poltergeist', 'year' => 1982, 'runtime' => 114, 'director' => 'Tobe Hooper', 'genres' => ['Horror'], 'keywords' => ['ghost', 'haunted house'], 'overview' => 'A suburban family\'s youngest daughter is taken by the spirits in their television.'],
        ['tmdb_id' => 923, 'title' => 'Dawn of the Dead', 'year' => 1978, 'runtime' => 127, 'director' => 'George A. Romero', 'genres' => ['Horror'], 'keywords' => ['zombie', 'shopping mall'], 'overview' => 'Four survivors barricade themselves in a shopping mall as the dead take over.'],
        ['tmdb_id' => 10331, 'title' => 'Night of the Living Dead', 'year' => 1968, 'runtime' => 96, 'director' => 'George A. Romero', 'genres' => ['Horror'], 'keywords' => ['zombie', 'farmhouse'], 'overview' => 'Strangers hole up in a Pennsylvania farmhouse while the recently dead walk.'],
        ['tmdb_id' => 4488, 'title' => 'Friday the 13th', 'year' => 1980, 'runtime' => 95, 'director' => 'Sean S. Cunningham', 'genres' => ['Horror'], 'keywords' => ['slasher', 'summer camp'], 'overview' => 'Counselors reopening Camp Crystal Lake are picked off one by one.'],
        ['tmdb_id' => 9426, 'title' => 'The Fly', 'year' => 1986, 'runtime' => 96, 'director' => 'David Cronenberg', 'genres' => ['Horror', 'Science Fiction'], 'keywords' => ['body horror', 'mad scientist'], 'overview' => 'A scientist\'s teleporter splices his DNA with a housefly\'s.'],
        ['tmdb_id' => 837, 'title' => 'Videodrome', 'year' => 1983, 'runtime' => 88, 'director' => 'David Cronenberg', 'genres' => ['Horror', 'Science Fiction'], 'keywords' => ['body horror', 'television'], 'overview' => 'A cable programmer finds a broadcast of torture that starts to rewrite his body.'],
        ['tmdb_id' => 814, 'title' => 'An American Werewolf in London', 'year' => 1981, 'runtime' => 97, 'director' => 'John Landis', 'genres' => ['Horror', 'Comedy'], 'keywords' => ['werewolf'], 'overview' => 'Two American backpackers on the Yorkshire moors are attacked under a full moon.'],
        ['tmdb_id' => 7340, 'title' => 'Carrie', 'year' => 1976, 'runtime' => 98, 'director' => 'Brian De Palma', 'genres' => ['Horror'], 'keywords' => ['telekinesis', 'stephen king', 'prom'], 'overview' => 'A bullied teenager with telekinetic powers is pushed too far at the senior prom.'],
        ['tmdb_id' => 9638, 'title' => 'Phantasm', 'year' => 1979, 'runtime' => 89, 'director' => 'Don Coscarelli', 'genres' => ['Horror', 'Science Fiction'], 'keywords' => ['undertaker', 'dream'], 'overview' => 'A boy discovers the Tall Man at the local mortuary is shrinking the dead.'],
    ];

    /**
     * Formats the test user owns, keyed by TMDB id.
     *
     * @var array<int, list<MediaFormat>>
     */
    private const array Shelf = [
        948 => [MediaFormat::Vhs, MediaFormat::UltraHd],
        1091 => [MediaFormat::Vhs, MediaFormat::BluRay],
        790 => [MediaFormat::Dvd],
        8769 => [MediaFormat::Vhs],
        8337 => [MediaFormat::BluRay],
        764 => [MediaFormat::Vhs],
        765 => [MediaFormat::Vhs, MediaFormat::UltraHd],
        377 => [MediaFormat::Vhs],
        11906 => [MediaFormat::LaserDisc, MediaFormat::UltraHd],
        1694 => [MediaFormat::Vhs],
        9003 => [MediaFormat::BluRay],
        694 => [MediaFormat::Dvd],
        923 => [MediaFormat::Betamax],
        9426 => [MediaFormat::BluRay],
        837 => [MediaFormat::Vhs],
        4232 => [MediaFormat::Digital],
    ];

    /**
     * Want-list entries: TMDB id, format, target price and the shape of the last month of lowest prices.
     *
     * @var list<array{tmdb_id: int, format: MediaFormat, target: float|null, days: int, start: float, end: float}>
     */
    private const array WantList = [
        ['tmdb_id' => 9552, 'format' => MediaFormat::Vhs, 'target' => 12.0, 'days' => 30, 'start' => 19.0, 'end' => 11.5],
        ['tmdb_id' => 30497, 'format' => MediaFormat::UltraHd, 'target' => null, 'days' => 30, 'start' => 34.0, 'end' => 24.0],
        ['tmdb_id' => 609, 'format' => MediaFormat::Vhs, 'target' => 8.0, 'days' => 30, 'start' => 14.0, 'end' => 14.5],
        ['tmdb_id' => 9638, 'format' => MediaFormat::BluRay, 'target' => null, 'days' => 4, 'start' => 28.0, 'end' => 26.0],
    ];

    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => 'password', 'email_verified_at' => now()],
        );

        $movies = collect(self::Movies)->mapWithKeys(fn (array $data): array => [
            $data['tmdb_id'] => $this->stockMovie($data),
        ]);

        $user->copies()->delete();

        foreach (self::Shelf as $tmdbId => $formats) {
            foreach ($formats as $index => $format) {
                Copy::factory()->for($user)->for($movies[$tmdbId])->create([
                    'format' => $format,
                    'edition' => $format === MediaFormat::Vhs ? 'Original rental clamshell' : null,
                    'condition' => $index === 0 ? 'Ex-rental, sticker on spine' : 'Like new',
                    'acquired_on' => now()->subDays(($tmdbId % 600) + $index * 30)->toDateString(),
                ]);
            }
        }

        $user->watches()->delete();

        foreach (self::WantList as $wanted) {
            $this->stockWantList($user, $movies[$wanted['tmdb_id']], $wanted);
        }
    }

    /**
     * @param  array{tmdb_id: int, title: string, year: int, runtime: int, director: string, genres: list<string>, keywords: list<string>, overview: string}  $data
     */
    private function stockMovie(array $data): Movie
    {
        $movie = Movie::query()->updateOrCreate(['tmdb_id' => $data['tmdb_id']], [
            'title' => $data['title'],
            'original_title' => $data['title'],
            'overview' => $data['overview'],
            'release_date' => "{$data['year']}-10-01",
            'release_year' => $data['year'],
            'runtime' => $data['runtime'],
            'original_language' => 'en',
            'vote_average' => 7.0 + ($data['tmdb_id'] % 15) / 10,
            'vote_count' => 1000 + $data['tmdb_id'] % 4000,
            'popularity' => 10 + $data['tmdb_id'] % 90,
        ]);

        $director = Person::query()->firstOrCreate(
            ['name' => $data['director']],
            ['tmdb_id' => self::PlaceholderIdBase + crc32($data['director']) % 1_000_000],
        );

        $movie->people()->syncWithPivotValues([$director->id], ['role' => CreditRole::Director->value, 'order' => 0]);

        $movie->genres()->sync(collect($data['genres'])->map(fn (string $name): int => Genre::query()->firstOrCreate(
            ['name' => $name],
            ['tmdb_id' => self::GenreIds[$name]],
        )->id));

        $movie->keywords()->sync(collect($data['keywords'])->map(fn (string $name): int => Keyword::query()->firstOrCreate(
            ['name' => $name],
            ['tmdb_id' => self::PlaceholderIdBase + crc32($name) % 1_000_000],
        )->id));

        return $movie;
    }

    /**
     * TMDB's own genre ids, so a later import lines up with these rows.
     *
     * @var array<string, int>
     */
    private const array GenreIds = [
        'Horror' => 27,
        'Science Fiction' => 878,
        'Comedy' => 35,
        'Thriller' => 53,
    ];

    /**
     * @param  array{tmdb_id: int, format: MediaFormat, target: float|null, days: int, start: float, end: float}  $wanted
     */
    private function stockWantList(User $user, Movie $movie, array $wanted): void
    {
        $watch = Watch::factory()->for($user)->for($movie)->create([
            'format' => $wanted['format'],
            'condition' => ListingCondition::Any,
            'target_price' => $wanted['target'],
            'last_checked_at' => now(),
        ]);

        for ($day = $wanted['days'] - 1; $day >= 0; $day--) {
            $progress = $wanted['days'] > 1 ? 1 - $day / ($wanted['days'] - 1) : 1;
            $wobble = (($day * 7) % 5 - 2) * 0.6;
            $lowest = $day === 0
                ? $wanted['end']
                : round($wanted['start'] + ($wanted['end'] - $wanted['start']) * $progress ** 3 + $wobble, 2);

            $watch->snapshots()->create([
                'checked_on' => today()->subDays($day),
                'listing_count' => 8 + ($day % 6),
                'lowest_price' => $lowest,
                'median_price' => round(max($wanted['start'], $wanted['end']) * 1.25, 2),
                'highest_price' => round(max($wanted['start'], $wanted['end']) * 2.4, 2),
                'lowest_listing_url' => 'https://www.ebay.com/sch/i.html?_nkw='.urlencode("{$movie->title} {$wanted['format']->label()}"),
                'lowest_listing_title' => "{$movie->title} ({$movie->release_year}) {$wanted['format']->label()}",
            ]);
        }
    }
}
