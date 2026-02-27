<?php

declare(strict_types=1);

class ShowChatPage extends AbstractGamePage
{
	public static $requireModule = 0;

	private const MSG_LIMIT    = 60;
	private const MSG_MAX_LEN  = 800;
	private const POLL_SINCE   = 0;

	function __construct()
	{
		$this->setWindow('ajax');
	}

	function show()
	{
		global $USER;
		$action = HTTP::_GP('action', '');

		switch ($action) {
			case 'fetch':   $this->actionFetch();  break;
			case 'send':    $this->actionSend();   break;
			case 'delete':  $this->actionDelete(); break;
			case 'ban':     $this->actionBan();    break;
			case 'unban':   $this->actionUnban();  break;
			case 'bans':    $this->actionBanList(); break;
			default:
				$this->jsonError('unknown action');
		}
	}

	private function jsonOut(array $data): void
	{
		while (ob_get_level() > 0) { ob_end_clean(); }
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data, JSON_UNESCAPED_UNICODE);
		exit;
	}

	private function jsonError(string $msg): void
	{
		$this->jsonOut(['ok' => false, 'error' => $msg]);
	}

	private function isBanned(int $userId): bool
	{
		$db = Database::get();
		$row = $db->selectSingle("SELECT id FROM %%CHAT_BAN%% WHERE user_id = :uid LIMIT 1;", [':uid' => $userId]);
		return !empty($row);
	}

	private function formatMessages(array $rows): array
	{
		$out = [];
		foreach ($rows as $r) {
			$out[] = [
				'id'         => (int)$r['id'],
				'user_id'    => (int)$r['user_id'],
				'username'   => htmlspecialchars($r['username'], ENT_QUOTES),
				'message'    => $r['message'],
				'created_at' => (int)$r['created_at'],
			];
		}
		return $out;
	}

	private function actionFetch(): void
	{
		global $USER;
		$db       = Database::get();
		$channel  = HTTP::_GP('channel', 'global') === 'alliance' ? 'alliance' : 'global';
		$since    = (int)HTTP::_GP('since', 0);
		$alliId   = (int)($USER['ally_id'] ?? 0);

		if ($channel === 'alliance' && $alliId === 0) {
			$this->jsonOut(['ok' => true, 'messages' => [], 'is_banned' => false]);
			return;
		}

		$alliCond = $channel === 'alliance' ? " AND alliance_id = " . $alliId : " AND alliance_id = 0";
		$sinceCond = $since > 0 ? " AND id > " . $since : "";

		$rows = $db->select(
			"SELECT id, user_id, username, message, created_at
			 FROM %%CHAT_MES%%
			 WHERE channel = '" . $channel . "'" . $alliCond . $sinceCond . "
			   AND deleted_at = 0
			 ORDER BY id DESC
			 LIMIT " . self::MSG_LIMIT . ";"
		);

		$rows = array_reverse((array)$rows);
		$this->jsonOut([
			'ok'        => true,
			'messages'  => $this->formatMessages($rows),
			'is_banned' => $this->isBanned((int)$USER['id']),
			'is_admin'  => (int)($USER['authlevel'] ?? 0) > 0,
		]);
	}

	private function actionSend(): void
	{
		global $USER;
		$db      = Database::get();
		$channel = HTTP::_GP('channel', 'global') === 'alliance' ? 'alliance' : 'global';
		$msg     = trim(HTTP::_GP('message', ''));
		$alliId  = (int)($USER['ally_id'] ?? 0);
		$userId  = (int)$USER['id'];

		if ($this->isBanned($userId)) {
			$this->jsonError('Du bist vom Chat gebannt.');
		}
		if (strlen($msg) === 0) {
			$this->jsonError('Leere Nachricht.');
		}
		if (strlen($msg) > self::MSG_MAX_LEN) {
			$this->jsonError('Nachricht zu lang (max ' . self::MSG_MAX_LEN . ' Zeichen).');
		}
		if ($channel === 'alliance' && $alliId === 0) {
			$this->jsonError('Keine Allianz.');
		}

		$now     = (int)TIMESTAMP;
		$aid     = $channel === 'alliance' ? $alliId : 0;

		$db->insert(
			"INSERT INTO %%CHAT_MES%% (channel, alliance_id, user_id, username, message, created_at, deleted_at)
			 VALUES (:ch, :aid, :uid, :uname, :msg, :now, 0);",
			[':ch' => $channel, ':aid' => $aid, ':uid' => $userId, ':uname' => $USER['username'], ':msg' => $msg, ':now' => $now]
		);
		$newId = $db->lastInsertId();

		$this->jsonOut([
			'ok'      => true,
			'message' => [
				'id'         => $newId,
				'user_id'    => $userId,
				'username'   => htmlspecialchars($USER['username'], ENT_QUOTES),
				'message'    => $msg,
				'created_at' => $now,
			],
		]);
	}

	private function actionDelete(): void
	{
		global $USER;
		if ((int)($USER['authlevel'] ?? 0) < 1) {
			$this->jsonError('Keine Berechtigung.');
		}
		$db    = Database::get();
		$msgId = (int)HTTP::_GP('msg_id', 0);
		if ($msgId <= 0) {
			$this->jsonError('Ungültige ID.');
		}
		$db->update("UPDATE %%CHAT_MES%% SET deleted_at = :now WHERE id = :id;", [':now' => (int)TIMESTAMP, ':id' => $msgId]);
		$this->jsonOut(['ok' => true]);
	}

	private function actionBan(): void
	{
		global $USER;
		if ((int)($USER['authlevel'] ?? 0) < 1) {
			$this->jsonError('Keine Berechtigung.');
		}
		$db     = Database::get();
		$target = (int)HTTP::_GP('user_id', 0);
		if ($target <= 0) {
			$this->jsonError('Ungültige User-ID.');
		}
		$db->replace(
			"REPLACE INTO %%CHAT_BAN%% (user_id, banned_by, reason, created_at)
			 VALUES (:uid, :by, :reason, :now);",
			[':uid' => $target, ':by' => (int)$USER['id'], ':reason' => trim(HTTP::_GP('reason', '')), ':now' => (int)TIMESTAMP]
		);
		$this->jsonOut(['ok' => true]);
	}

	private function actionUnban(): void
	{
		global $USER;
		if ((int)($USER['authlevel'] ?? 0) < 1) {
			$this->jsonError('Keine Berechtigung.');
		}
		$db     = Database::get();
		$target = (int)HTTP::_GP('user_id', 0);
		$db->delete("DELETE FROM %%CHAT_BAN%% WHERE user_id = :uid;", [':uid' => $target]);
		$this->jsonOut(['ok' => true]);
	}

	private function actionBanList(): void
	{
		global $USER;
		if ((int)($USER['authlevel'] ?? 0) < 1) {
			$this->jsonError('Keine Berechtigung.');
		}
		$db   = Database::get();
		$rows = $db->select(
			"SELECT b.user_id, b.reason, b.created_at, u.username
			 FROM %%CHAT_BAN%% b
			 LEFT JOIN %%USERS%% u ON u.id = b.user_id
			 ORDER BY b.created_at DESC;"
		);
		$this->jsonOut(['ok' => true, 'bans' => (array)$rows]);
	}
}
