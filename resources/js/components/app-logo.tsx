/**
 * The station ident: the store's name as on-screen characters with VIDEO in
 * inverse. Collapsed, only the channel number stays.
 */
export default function AppLogo() {
    return (
        <>
            <span className="osd-text hidden text-2xl group-data-[collapsible=icon]:block">
                03
            </span>
            <div className="flex flex-col items-start gap-1 py-1 leading-none uppercase group-data-[collapsible=icon]:hidden">
                <span className="osd-text text-3xl leading-none">
                    Graveyard
                </span>
                <span className="flex items-center gap-2">
                    <span className="osd-text text-3xl leading-none">
                        Shift
                    </span>
                    <span className="osd-inverse px-1.5 pt-0.5 text-xl leading-none">
                        Video
                    </span>
                </span>
            </div>
        </>
    );
}
