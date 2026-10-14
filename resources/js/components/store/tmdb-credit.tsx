/**
 * Attribution TMDB's API terms require on any app that shows its data.
 */
export function TmdbCredit() {
    return (
        <footer className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-border/60 px-1 pt-5 pb-8 text-xs text-muted-foreground">
            <a
                href="https://www.themoviedb.org/"
                target="_blank"
                rel="noreferrer"
            >
                <img
                    src="/tmdb-logo.svg"
                    alt="The Movie Database (TMDB)"
                    className="h-3.5 w-auto"
                />
            </a>
            <p>
                This product uses the TMDB API but is not endorsed or certified
                by TMDB.
            </p>
        </footer>
    );
}
