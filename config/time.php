<?php

return [
    /** Open shifts ouder dan dit aantal uren worden automatisch gesloten. */
    'stale_shift_hours' => (int) env('TIME_STALE_SHIFT_HOURS', 16),

    /** Standaard QR-rotatie-interval (maanden) wanneer tenant geen eigen waarde heeft. 0 = uit. */
    'qr_rotation_months_default' => (int) env('TIME_QR_ROTATION_MONTHS_DEFAULT', 6),

    /** Grace-periode (dagen) waarin oude QR na vernieuwen nog werkt. */
    'qr_grace_days' => (int) env('TIME_QR_GRACE_DAYS', 7),
];
