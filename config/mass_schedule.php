<?php

/**
 * Settings for the recurring weekly Mass schedule generator
 * (see App\Console\Commands\GenerateMassSchedule).
 */

return [

    /**
     * How many weeks ahead of "today" the generator keeps rows stamped
     * out for. Running the command daily (see routes/console.php) keeps
     * a rolling window of this many weeks always populated; it's
     * idempotent, so re-running it more or less often is always safe.
     */
    'generate_weeks_ahead' => 8,

];