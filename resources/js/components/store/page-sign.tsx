import type { ReactNode } from 'react';

type PageSignProps = {
    title: string;
    description?: ReactNode;
    actions?: ReactNode;
};

/**
 * The title card at the top of every page, set like the big characters a VCR
 * paints over the picture.
 */
export function PageSign({ title, description, actions }: PageSignProps) {
    return (
        <header className="flex flex-wrap items-end justify-between gap-x-8 gap-y-4 pt-2 pb-6">
            <div className="max-w-2xl space-y-2">
                <h1 className="osd-text text-5xl uppercase sm:text-6xl">
                    {title}
                </h1>
                {description && (
                    <p className="text-lg text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </header>
    );
}
