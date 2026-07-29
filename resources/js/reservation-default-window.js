/**
 * Suggesteer reserveringsvenster t.o.v. browsertijd:
 * start = huidige tijd naar boven afgerond op half uur (:00 / :30),
 * eind = start + 1 uur.
 */

function pad2(value) {
    return String(value).padStart(2, '0');
}

function formatLocalDateTime(date) {
    return (
        `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}` +
        `T${pad2(date.getHours())}:${pad2(date.getMinutes())}`
    );
}

export function nextHalfHourFromNow(now = new Date()) {
    const date = new Date(now.getTime());
    date.setSeconds(0, 0);

    const minutes = date.getMinutes();
    if (minutes === 0 || minutes === 30) {
        return date;
    }

    if (minutes < 30) {
        date.setMinutes(30);

        return date;
    }

    date.setHours(date.getHours() + 1);
    date.setMinutes(0);

    return date;
}

export function defaultReservationWindow(now = new Date()) {
    const start = nextHalfHourFromNow(now);
    const end = new Date(start.getTime());
    end.setHours(end.getHours() + 1);

    return {
        start: formatLocalDateTime(start),
        end: formatLocalDateTime(end),
    };
}

window.wpDefaultReservationWindow = defaultReservationWindow;
