<?php

declare(strict_types=1);

/**
 * SmartMoons - Message Controller
 * Modernized for PHP 8.3/8.4 and Galactic UI
 * FIX: BBCode Parsing and Entity Handling
 */

class ShowMessagesPage extends AbstractGamePage
{
    public static $requireModule = MODULE_MESSAGES;

    function __construct()
    {
        parent::__construct();
    }

    private function normalizeCategory(mixed $rawCategory): int
    {
        $validCategories = array(0, 1, 2, 3, 4, 5, 15, 50, 99, 100, 999);
        if (is_string($rawCategory)) {
            $rawCategory = trim($rawCategory);
            if ($rawCategory === '' || strtolower($rawCategory) === 'all') {
                return 100;
            }
        }
        $category = (int) $rawCategory;
        return in_array($category, $validCategories, true) ? $category : 100;
    }

    function view()
    {
        global $LNG, $USER;
        $rawMessCategory = HTTP::_GP('messcat', '100', true);
        $MessCategory  	= $this->normalizeCategory($rawMessCategory);
        $page  			= HTTP::_GP('site', 1);
        $messageDeletedWhere = '(message_deleted IS NULL OR message_deleted = 0)';

        $db = Database::get();
        
        // BBCode Instanz für PHP 8.3/8.4 Fix
        if (!class_exists('BBCode')) {
            require_once 'includes/classes/BBCode.class.php';
        }
        $bbcode = new BBCode();

        $this->initTemplate();
        $this->setWindow('ajax');

        $MessageList	= array();
        $MessagesID		= array();

        if($MessCategory == 999) {
            $sql = "SELECT COUNT(*) as state FROM %%MESSAGES%% WHERE message_sender = :userId AND message_type != 50 AND ".$messageDeletedWhere.";";
            $MessageCount = $db->selectSingle($sql, array(':userId' => $USER['id']), 'state');
            $maxPage = max(1, ceil($MessageCount / MESSAGES_PER_PAGE));
            $page = max(1, min($page, $maxPage));
            $sql = "SELECT message_id, message_time, CONCAT(username, ' [',galaxy, ':', system, ':', planet,']') as message_from, message_subject, message_sender, message_type, message_unread, message_text
			FROM %%MESSAGES%% INNER JOIN %%USERS%% ON id = message_owner
			WHERE message_sender = :userId AND message_type != 50 AND ".$messageDeletedWhere."
			ORDER BY message_time DESC LIMIT :offset, :limit;";
            $MessageResult = $db->select($sql, array(':userId' => $USER['id'], ':offset' => (($page - 1) * MESSAGES_PER_PAGE), ':limit' => MESSAGES_PER_PAGE));
        } else {
            $where = ($MessCategory == 100) ? "message_owner = :userId" : "message_owner = :userId AND message_type = :messCategory";
            $params = array(':userId' => $USER['id']);
            if($MessCategory != 100) $params[':messCategory'] = $MessCategory;

            $sqlCount = "SELECT COUNT(*) as state FROM %%MESSAGES%% WHERE ".$where." AND ".$messageDeletedWhere.";";
            $MessageCount = $db->selectSingle($sqlCount, $params, 'state');
            $maxPage = max(1, ceil($MessageCount / MESSAGES_PER_PAGE));
            $page = max(1, min($page, $maxPage));

            $sql = "SELECT message_id, message_time, message_from, message_subject, message_sender, message_type, message_unread, message_text FROM %%MESSAGES%% WHERE ".$where." AND ".$messageDeletedWhere." ORDER BY message_time DESC LIMIT :offset, :limit";
            $params[':offset'] = (($page - 1) * MESSAGES_PER_PAGE);
            $params[':limit'] = MESSAGES_PER_PAGE;
            $MessageResult = $db->select($sql, $params);
        }

        foreach ($MessageResult as $MessageRow) {
            $MessagesID[] = $MessageRow['message_id'];
            
            $parsedText = $bbcode->parse($MessageRow['message_text']);

            $MessageList[] = array(
                'id'		=> $MessageRow['message_id'],
                'time'		=> _date($LNG['php_tdformat'], $MessageRow['message_time'], $USER['timezone']),
                'from'		=> $MessageRow['message_from'],
                'subject'	=> $MessageRow['message_subject'],
                'sender'	=> $MessageRow['message_sender'],
                'type'		=> $MessageRow['message_type'],
                'unread'	=> $MessageRow['message_unread'],
                'text'		=> $parsedText,
            );
        }

        if(!empty($MessagesID) && $MessCategory != 999) {
            $db->update('UPDATE %%MESSAGES%% SET message_unread = 0 WHERE message_id IN ('.implode(',', $MessagesID).') AND message_owner = :userID;', array(':userID' => $USER['id']));
        }

        $this->assign(array(
            'MessID'		=> $MessCategory,
            'MessageCount'	=> $MessageCount,
            'MessageList'	=> $MessageList,
            'page'			=> $page,
            'maxPage'		=> $maxPage,
        ));

        $this->display('page.messages.view.twig');
    }

    function send()
    {
        global $USER, $LNG;
        $receiverID	= HTTP::_GP('id', 0);
        $receiverName = trim(HTTP::_GP('to', '', UTF8_SUPPORT));
        $subject 	= HTTP::_GP('subject', $LNG['mg_no_subject'], true);
		$text		= HTTP::_GP('text', '', true);
		$senderName	= $USER['username'].' ['.$USER['galaxy'].':'.$USER['system'].':'.$USER['planet'].']';

		$db			= Database::get();
		$session	= Session::load();
		if (empty($receiverID) && !empty($receiverName)) {
			$sql = "SELECT id, settings_blockPM FROM %%USERS%% WHERE universe = :universe AND username = :username;";
			$receiverData = $db->selectSingle($sql, array(':universe' => Universe::current(), ':username' => $receiverName));
			if (empty($receiverData)) $this->sendJSON($LNG['mg_error']);
			if ((int) $receiverData['settings_blockPM'] === 1) $this->sendJSON($LNG['mg_receiver_block_pm']);
			$receiverID = (int) $receiverData['id'];
		}

		$validToken = isset($session->messageToken) && in_array($session->messageToken, array(md5($USER['id'].'|'.$receiverID), md5($USER['id'].'|0')), true);
        if (empty($receiverID) || empty($text) || !$validToken) $this->sendJSON($LNG['mg_error']);

		$session->messageToken = NULL;
		PlayerUtil::sendMessage($receiverID, $USER['id'], $senderName, 1, $subject, $text, TIMESTAMP);
        $this->sendJSON($LNG['mg_message_send']);
    }

    function write()
    {
        global $LNG, $USER;
        $this->setWindow('popup');
        $this->initTemplate();
        $receiverID = HTTP::_GP('id', 0);
        $receiverName = trim(HTTP::_GP('to', '', UTF8_SUPPORT));
        $Subject = HTTP::_GP('subject', $LNG['mg_no_subject'], true);
        $MessageBody = HTTP::_GP('message', '', true);
		$allowFreeRecipient	= empty($receiverID);
		
		if ($allowFreeRecipient) {
			$receiverRecord = array('username' => $receiverName, 'galaxy' => '', 'system' => '', 'planet' => '');
			Session::load()->messageToken = md5($USER['id'].'|0');
		} else {
			$sql = "SELECT a.galaxy, a.system, a.planet, b.username, b.id_planet, b.settings_blockPM FROM %%PLANETS%% as a, %%USERS%% as b WHERE b.id = :receiverId AND a.id = b.id_planet;";
			$receiverRecord = Database::get()->selectSingle($sql, array(':receiverId' => $receiverID));
			if (!$receiverRecord) $this->printMessage($LNG['mg_error']);
			Session::load()->messageToken = md5($USER['id'].'|'.$receiverID);
		}

        $this->assign(array(
            'subject' => $Subject,
            'message' => $MessageBody,
            'id' => $receiverID,
            'OwnerRecord' => $receiverRecord,
			'allowFreeRecipient' => $allowFreeRecipient,
        ));
        $this->display('page.messages.write.twig');
    }

    function show()
    {
        global $USER;
        $rawCategory = HTTP::_GP('category', '100', true);
        $category = $this->normalizeCategory($rawCategory);
        $side = HTTP::_GP('side', 1);
        $db = Database::get();

        $TitleColor = array (0 => '#FFFF00', 1 => '#FF6699', 2 => '#FF3300', 3 => '#FF9900', 4 => '#773399', 5 => '#009933', 15 => '#6495ed', 50 => '#666600', 99 => '#007070', 100 => '#ABABAB', 999 => '#CCCCCC');
        $MessOut = $db->selectSingle("SELECT COUNT(*) as state FROM %%MESSAGES%% WHERE message_sender = :userID AND message_type != 50;", array(':userID' => $USER['id']), 'state');

        $Total = array(0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 15 => 0, 50 => 0, 99 => 0, 100 => 0, 999 => 0);
        $UnRead = array(0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 15 => 0, 50 => 0, 99 => 0, 100 => 0, 999 => 0);

        $CategoryResult = $db->select("SELECT message_type, SUM(message_unread) as message_unread, COUNT(*) as count FROM %%MESSAGES%% WHERE message_owner = :userID AND (message_deleted IS NULL OR message_deleted = 0) GROUP BY message_type;", array(':userID' => $USER['id']));
        foreach ($CategoryResult as $CategoryRow) {
            $UnRead[$CategoryRow['message_type']] = $CategoryRow['message_unread'];
            $Total[$CategoryRow['message_type']] = $CategoryRow['count'];
        }

        $UnRead[100] = array_sum($UnRead);
        $Total[100] = array_sum($Total);
        $Total[999] = $MessOut;

        $CategoryList = array();
        foreach($TitleColor as $CategoryID => $CategoryColor) {
            $CategoryList[$CategoryID] = array('color' => $CategoryColor, 'unread' => $UnRead[$CategoryID], 'total' => $Total[$CategoryID]);
        }

        $this->tplObj->loadscript('message.js');
        $this->assign(array('CategoryList' => $CategoryList, 'category' => $category, 'side' => $side));
        $this->display('page.messages.default.twig');
    }
}