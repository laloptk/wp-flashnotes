<?php

namespace WPFlashNotes\Errors;

use RuntimeException;

/**
 * Unified exception for all plugin-level errors.
 *
 * Used by repositories, services, and controllers.
 * Carries both semantic kind (validation/db/etc.)
 * and intended HTTP status for REST output.
 */
class WPFlashNotesError extends RuntimeException {

    public string $kind;
    public int $status;

    public function __construct(string $kind, string $message, int $status = 500) {
        parent::__construct($message);
        $this->kind = $kind;
        $this->status = $status;
    }
}
