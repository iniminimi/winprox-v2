<?php

return [
    /** Open shifts ouder dan dit aantal uren worden automatisch gesloten. */
    'stale_shift_hours' => (int) env('TIME_STALE_SHIFT_HOURS', 16),
];
