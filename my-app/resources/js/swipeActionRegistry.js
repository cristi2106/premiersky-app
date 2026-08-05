// Ensures only one SwipeableListItem is revealed at a time, mirroring the
// behaviour of iOS Mail: opening a new row swipes any previously-open row
// shut. Plain module state (not a Vue ref) since it doesn't need to drive
// any rendering of its own — each item manages its own open/closed visuals.
let activeClose = null;

export function openExclusive(closeFn) {
    if (activeClose && activeClose !== closeFn) {
        activeClose();
    }

    activeClose = closeFn;
}

export function clearActive(closeFn) {
    if (activeClose === closeFn) {
        activeClose = null;
    }
}
