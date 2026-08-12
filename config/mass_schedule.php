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

    /**
     * Maximum number of Pamisa sa Kalag intentions (deceased-name entries)
     * that can be attached to a single Mass occurrence before it's treated
     * as full and excluded from the "available Masses for this date" list
     * (see ReservationController::massSchedules()). A parish can raise this
     * if a Mass slot can reasonably announce/print more names than this.
     */
    'max_pamisa_intentions_per_mass' => 10,

];