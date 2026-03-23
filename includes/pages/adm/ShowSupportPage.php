<?php

declare(strict_types=1);

/**
 *	SmartMoons / 2Moons Community Edition (2MoonsCE)
 * 
 *	Based on the original 2Moons project:
 *	
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 *  2Moons 
 *   by Jan-Otto Kröpke 2009-2016
 *
 * Modernization, PHP 8.3/8.4 compatibility, Twig Migration (Smarty removed)
 * Refactoring and feature extensions:
 * @copyright 2024-2026 Florian Engelhardt (0wum0)
 * @link https://github.com/0wum0/2MoonsCE
 * @eMail info.browsergame@gmail.com
 * 
 * Licensed under the MIT License.
 * See LICENSE for details.
 * @visit http://makeit.uno/
 */

// @admin-migrated (Phase 11 — AbstractAdminPage)

class ShowSupportPage extends AbstractAdminPage
{
	private object $ticketObj;

	public function __construct()
	{
		require_once 'includes/classes/class.SupportTickets.php';
		$this->ticketObj = new SupportTickets();
		parent::__construct('ShowSupportPage');
	}

	protected function run(): void
	{
		$mode = HTTP::_GP('mode', 'show');
		if ($mode !== 'show' && method_exists($this, $mode)) {
			$this->{$mode}();
		} else {
			$this->showList();
		}
	}

	private function showList(): void
	{
		global $USER, $LNG;

		$db  = Database::get();
		$sql = "SELECT t.*, u.username, COUNT(a.ticketID) as answer
                FROM %%TICKETS%% t
                INNER JOIN %%TICKETS_ANSWER%% a USING (ticketID)
                INNER JOIN %%USERS%% u ON u.id = t.ownerID
                WHERE t.universe = :uni
                GROUP BY a.ticketID
                ORDER BY t.ticketID DESC;";

		$ticketResult = $db->select($sql, [':uni' => Universe::current()]);
		$ticketList   = [];

		foreach ($ticketResult as $ticketRow) {
			$ticketRow['time']                    = _date($LNG['php_tdformat'], $ticketRow['time'], $USER['timezone']);
			$ticketList[$ticketRow['ticketID']]   = $ticketRow;
		}

		$this->assign([
			'ticketList'   => $ticketList,
			'categoryList' => $this->ticketObj->getCategoryList(),
		]);
		$this->show('page.ticket.default.twig');
	}

	private function view(): void
	{
		global $USER, $LNG;

		$db       = Database::get();
		$ticketID = HTTP::_GP('id', 0);

		if (!class_exists('BBCode')) {
			require_once 'includes/classes/BBCode.class.php';
		}
		$bbcode = new BBCode();

		$sql = "SELECT a.*, t.categoryID, t.status
                FROM %%TICKETS_ANSWER%% a
                INNER JOIN %%TICKETS%% t USING(ticketID)
                WHERE a.ticketID = :id
                ORDER BY a.answerID;";

		$answerResult  = $db->select($sql, [':id' => (int) $ticketID]);
		$answerList    = [];
		$ticket_status = 0;

		foreach ($answerResult as $answerRow) {
			if (empty($ticket_status)) {
				$ticket_status = $answerRow['status'];
			}
			$answerRow['time']    = _date($LNG['php_tdformat'], $answerRow['time'], $USER['timezone']);
			$answerRow['message'] = $bbcode->parse($answerRow['message']);
			$answerList[$answerRow['answerID']] = $answerRow;
		}

		$this->assign([
			'ticketID'     => $ticketID,
			'status'       => $ticket_status,
			'categoryList' => $this->ticketObj->getCategoryList(),
			'answerList'   => $answerList,
		]);
		$this->show('page.ticket.view.twig');
	}

	private function send(): void
	{
		global $USER;

		$ticketID = HTTP::_GP('id', 0);
		$message  = HTTP::_GP('message', '', true);

		$this->ticketObj->createAnswer((int) $ticketID, (int) $USER['id'], $USER['username'], 'Support Antwort', $message, 1);
		HTTP::redirectTo('admin.php?page=support&mode=view&id=' . (int) $ticketID);
	}
}