<?php

declare(strict_types=1);

return [
    /* 08 §8.3: an actual amount off the approved one by more than this share needs a note. */
    'variance_note_threshold' => (float) env('DAWAH_VARIANCE_NOTE_THRESHOLD', 0.10),
];
