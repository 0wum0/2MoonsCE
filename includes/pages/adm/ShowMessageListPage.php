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

// @admin-migrated (Phase 10 — AbstractAdminPage + PDO)

class ShowMessageListPage extends AbstractAdminPage
{
	public function __construct()
	{
		parent::__construct('ShowMessageListPage');
	}

	protected function run(): void
	{
	global $LNG, $USER;

	$db       = Database::get();
	$uni      = Universe::getEmulated();
	$page     = HTTP::_GP('side', 1);
	$type     = HTTP::_GP('type', 100);
	$sender   = HTTP::_GP('sender', '', UTF8_SUPPORT);
	$receiver = HTTP::_GP('receiver', '', UTF8_SUPPORT);
	$dateStart = HTTP::_GP('dateStart', array());
	$dateEnd   = HTTP::_GP('dateEnd', array());

	$perSide     = 50;
	$messageList = array();
	$params      = [':uni' => (int) $uni];

	$categories = $LNG['mg_type'];
	unset($categories[999]);

	$dateStart = array_filter($dateStart, 'is_numeric');
	$dateEnd   = array_filter($dateEnd, 'is_numeric');

	$useDateStart = count($dateStart) == 3;
	$useDateEnd   = count($dateEnd) == 3;

	$dateWhereSQL = '';
	if ($useDateStart && $useDateEnd) {
		$dateWhereSQL = ' AND message_time BETWEEN :ds AND :de';
		$params[':ds'] = mktime(0, 0, 0, (int) $dateStart['month'], (int) $dateStart['day'], (int) $dateStart['year']);
		$params[':de'] = mktime(23, 59, 59, (int) $dateEnd['month'], (int) $dateEnd['day'], (int) $dateEnd['year']);
	} elseif ($useDateStart) {
		$dateWhereSQL = ' AND message_time > :ds';
		$params[':ds'] = mktime(0, 0, 0, (int) $dateStart['month'], (int) $dateStart['day'], (int) $dateStart['year']);
	} elseif ($useDateEnd) {
		$dateWhereSQL = ' AND message_time < :de';
		$params[':de'] = mktime(23, 59, 59, (int) $dateEnd['month'], (int) $dateEnd['day'], (int) $dateEnd['year']);
	}

	$senderJoin   = '';
	$receiverJoin = '';
	$userWhereSQL = '';

	if (!empty($sender)) {
		$senderJoin   = ' LEFT JOIN %%USERS%% as us ON message_sender = us.id';
		$userWhereSQL .= ' AND us.username = :sender';
		$params[':sender'] = $sender;
	}
	if (!empty($receiver)) {
		$receiverJoin  = ' LEFT JOIN %%USERS%% as ur ON message_owner = ur.id';
		$userWhereSQL .= ' AND ur.username = :receiver';
		$params[':receiver'] = $receiver;
	}

	$joinSQL = $senderJoin . $receiverJoin;

	$typeWhereSQL = ($type != 100) ? ' AND message_type = :type' : '';
	if ($type != 100) {
		$params[':type'] = (int) $type;
	}

	$MessageCount = (int) $db->selectSingle(
		"SELECT COUNT(*) FROM %%MESSAGES%%" . $joinSQL .
		" WHERE message_universe = :uni" . $typeWhereSQL . $dateWhereSQL . $userWhereSQL . ";",
		$params,
		'COUNT(*)'
	);

	$maxPage = max(1, ceil($MessageCount / $perSide));
	$page    = max(1, min($page, $maxPage));
	$offset  = ($page - 1) * $perSide;

	$listParams = $params;
	$listParams[':limit']  = $perSide;
	$listParams[':offset'] = $offset;

	$messageRows = $db->select(
		"SELECT u.username, us2.username as senderName, m.*
		 FROM %%MESSAGES%% as m
		 LEFT JOIN %%USERS%% as u  ON m.message_owner  = u.id
		 LEFT JOIN %%USERS%% as us2 ON m.message_sender = us2.id" .
		($receiverJoin ? ' LEFT JOIN %%USERS%% as ur ON message_owner = ur.id' : '') .
		($senderJoin   ? ' LEFT JOIN %%USERS%% as us  ON message_sender = us.id' : '') .
		" WHERE m.message_universe = :uni" . $typeWhereSQL . $dateWhereSQL . $userWhereSQL .
		" ORDER BY message_time DESC, message_id DESC
		 LIMIT :limit OFFSET :offset;",
		$listParams
	);

	foreach ($messageRows as $messageRow) {
		$messageList[$messageRow['message_id']] = array(
			'sender'   => empty($messageRow['senderName']) ? $messageRow['message_from'] : $messageRow['senderName'] . ' (ID:&nbsp;' . $messageRow['message_sender'] . ')',
			'receiver' => $messageRow['username'] . ' (ID:&nbsp;' . $messageRow['message_owner'] . ')',
			'subject'  => $messageRow['message_subject'],
			'text'     => $messageRow['message_text'],
			'type'     => $messageRow['message_type'],
			'deleted'  => $messageRow['message_deleted'] != null,
			'time'     => str_replace(' ', '&nbsp;', _date($LNG['php_tdformat'], $messageRow['message_time'], $USER['timezone'])),
		);
	}

	$this->assign(array(
		'categories'  => $categories,
		'maxPage'     => $maxPage,
		'page'        => $page,
		'messageList' => $messageList,
		'type'        => $type,
		'dateStart'   => $dateStart,
		'dateEnd'     => $dateEnd,
		'sender'      => $sender,
		'receiver'    => $receiver,
		'Selected'    => $type,
	));

	$this->show('MessageList.twig');
	}
}