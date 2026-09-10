<?php

return [
    /** Open shifts ouder dan dit aantal uren worden automatisch gesloten. */
    'stale_shift_hours' => (int) env('TIME_STALE_SHIFT_HOURS', 16),

    /** Open shift langer dan dit aantal uren → aandacht op aanwezigheid. */
    'long_shift_hours' => (int) env('TIME_LONG_SHIFT_HOURS', 10),

    /** Shift zonder geregistreerde pauze na dit aantal uren → aandacht. */
    'break_reminder_hours' => (int) env('TIME_BREAK_REMINDER_HOURS', 6),

    /** Max. rijen per team-sectie vóór "toon meer". */
    'presence_team_page_size' => (int) env('TIME_PRESENCE_TEAM_PAGE_SIZE', 50),

    /** Standaard QR-rotatie-interval (maanden) wanneer tenant geen eigen waarde heeft. 0 = uit. */
    'qr_rotation_months_default' => (int) env('TIME_QR_ROTATION_MONTHS_DEFAULT', 6),

    /** Hop naar een ander Clock Point binnen dit aantal minuten → aandacht. */
    'rapid_hop_minutes' => (int) env('TIME_RAPID_HOP_MINUTES', 5),

    /**
     * Na een verse Clock Point-QR-scan (volle page-load): zolang mag je één prik
     * (in, uit of verplaatsen) doen. Een open tab of poll verlengt dit niet.
     */
    'punch_scan_seconds' => (int) env('TIME_PUNCH_SCAN_SECONDS', 600),
];
