export type FormatValue =
    | 'vhs'
    | 'betamax'
    | 'laserdisc'
    | 'dvd'
    | 'bluray'
    | 'uhd'
    | 'digital';

export type Option<T extends string | number = string> = {
    value: T;
    label: string;
};

export type PersonRef = {
    id: number;
    name: string;
};

export type MovieCard = {
    id: number;
    title: string;
    year: number | null;
    poster_path: string | null;
    vote_average: number;
    owned_formats?: FormatValue[];
    directors?: PersonRef[];
};

export type Copy = {
    id: number;
    format: FormatValue;
    format_label: string;
    edition: string | null;
    condition: string | null;
    acquired_on: string | null;
    notes: string | null;
    movie?: MovieCard;
};

export type DealStatus = 'steal' | 'good_deal' | 'hold' | 'wait';

export type Watch = {
    id: number;
    format: FormatValue | null;
    format_label: string;
    condition: 'any' | 'new' | 'used';
    condition_label: string;
    target_price: number | null;
    active: boolean;
    last_checked_at: string | null;
    deal_status: DealStatus;
    deal_label: string;
    latest: {
        checked_on: string;
        listing_count: number;
        lowest_price: number | null;
        median_price: number | null;
        highest_price: number | null;
        lowest_listing_url: string | null;
        lowest_listing_title: string | null;
    } | null;
    history: { date: string; lowest: number | null; median: number | null }[];
    movie?: MovieCard;
};

export type Shelf = {
    key: string;
    label: string;
    person_id: number | null;
    movies: MovieCard[];
};

export type Paginated<T> = {
    data: T[];
};
