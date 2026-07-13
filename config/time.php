<?php

return [
    /** Open shifts ouder dan dit aantal uren worden automatisch gesloten. */
    'stale_shift_hours' => (int) env('TIME_STALE_SHIFT_HOURS', 16),

    /** Open shift langer dan dit aantal uren → aandacht op aanwezigheid. */
    'long_shift_hours' => (int) env('TIME_LONG_SHIFT_HOURS', 10),

    /** Shift zonder geregistreerde pauze na dit aantal uren → aandacht. */
    'break_reminder_hours' => (int) env('TIME_BREAK_REMINDER_HOURS', 6),

    /** Standaard QR-rotatie-interval (maanden) wanneer tenant geen eigen waarde heeft. 0 = uit. */
    'qr_rotation_months_default' => (int) env('TIME_QR_ROTATION_MONTHS_DEFAULT', 6),

    /** Grace-periode (dagen) waarin oude QR na vernieuwen nog werkt. */
    'qr_grace_days' => (int) env('TIME_QR_GRACE_DAYS', 7),
];
