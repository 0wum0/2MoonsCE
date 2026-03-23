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
        $db  = Database::get();
        $uni = Universe::getEmulated();

        // Open tickets — fetch up to 5 with sender name + category
        $tickets     = 0;
        $ticketItems = [];
        try {
            $rows = $db->select(
                "SELECT t.ticketID, t.subject, t.time, t.categoryID,
                        u.username AS sender_name,
                        COALESCE(tc.name, 'Support') AS category_name
                 FROM %%TICKETS%% t
                 LEFT JOIN %%USERS%%           u  ON u.id = t.ownerID
                 LEFT JOIN %%TICKET_CATEGORY%% tc ON tc.categoryID = t.categoryID
                 WHERE t.universe = :uni AND t.status = 0
                 ORDER BY t.time DESC
                 LIMIT 5",
                [':uni' => $uni]
            );
            if (is_array($rows)) {
                $tickets = count($rows);
                foreach ($rows as $r) {
                    $ticketItems[] = [
                        'id'       => (int)$r['ticketID'],
                        'subject'  => (string)($r['subject'] ?? ''),
                        'sender'   => (string)($r['sender_name'] ?? '?'),
                        'category' => (string)($r['category_name'] ?? 'Support'),
                        'time'     => date('d.m.Y H:i', (int)($r['time'] ?? 0)),
                    ];
                }
                // Total count (may be > 5)
                $countRow = $db->selectSingle(
                    "SELECT COUNT(*) AS cnt FROM %%TICKETS%% WHERE universe = :uni AND status = 0",
                    [':uni' => $uni]
                );
                $tickets = (int)($countRow['cnt'] ?? count($ticketItems));
            }
        } catch (\Throwable $e) {
            $tickets = 0;
        }

        // Error log — count entries and return last 5
        $errors     = 0;
        $errorItems = [];
        try {
            $logFile = ROOT_PATH . 'includes/error.log';
            if (is_readable($logFile) && filesize($logFile) > 0) {
                $content = file_get_contents($logFile);
                $count   = preg_match_all('/^\[\d{2}-[A-Za-z]{3}-\d{4}/m', $content);
                $errors  = ($count !== false) ? $count : 0;

                // Parse last 5 entries: each starts with [dd-Mon-YYYY HH:MM:SS UTC]
                $entryPattern = '/(\[\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2} [A-Z]+\].*?)(?=\[\d{2}-[A-Za-z]{3}-\d{4}|\z)/s';
                if (preg_match_all($entryPattern, $content, $matches)) {
                    $allEntries = $matches[1];
                    $last5      = array_slice($allEntries, -5);
                    foreach (array_reverse($last5) as $entry) {
                        $lines = explode("\n", trim($entry));
                        $errorItems[] = [
                            'header'  => trim($lines[0] ?? ''),
                            'message' => trim(implode(' ', array_slice($lines, 1, 2))),
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            $errors = 0;
        }

        $this->sendJSON([
            'tickets'      => $tickets,
            'ticketItems'  => $ticketItems,
            'errors'       => $errors,
            'errorItems'   => $errorItems,
            'errorLogUrl'  => 'admin.php?page=log',
        ]);
    }
}
