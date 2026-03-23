<?php

declare(strict_types=1);

/**
 *  SmartMoons / 2Moons Community Edition (2MoonsCE)
 *
 * @copyright 2024-2026 Florian Engelhardt (0wum0)
 * @link https://github.com/0wum0/2MoonsCE
 * Licensed under the MIT License.
 */

class ShowAdminNotificationsPage extends AbstractAdminPage
{
    public function __construct()
    {
        parent::__construct('');
    }

    protected function run(): void
    {
        // Open ticket count
        $tickets = 0;
        try {
            $db  = Database::get();
            $row = $db->selectSingle(
                "SELECT COUNT(*) AS cnt FROM %%TICKETS%% WHERE universe = :uni AND status = 0;",
                [':uni' => Universe::getEmulated()]
            );
            $tickets = (int)($row['cnt'] ?? 0);
        } catch (\Throwable $e) {
            $tickets = 0;
        }

        // Error log entry count — each entry starts with [dd-Mon-YYYY
        $errors = 0;
        try {
            $logFile = ROOT_PATH . 'includes/error.log';
            if (is_readable($logFile) && filesize($logFile) > 0) {
                $count  = preg_match_all('/^\[\d{2}-[A-Za-z]{3}-\d{4}/m', file_get_contents($logFile));
                $errors = ($count !== false) ? $count : 0;
            }
        } catch (\Throwable $e) {
            $errors = 0;
        }

        $this->sendJSON([
            'tickets' => $tickets,
            'errors'  => $errors,
        ]);
    }
}
