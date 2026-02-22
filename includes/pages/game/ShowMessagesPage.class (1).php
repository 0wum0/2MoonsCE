<?php

declare(strict_types=1);
/**
 *  2Moons 
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
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
        $config = Config::get();
        $isDebugMessages = !empty($config->debug);

        $messageTableName	= '%%MESSAGES%%';
        $dbTableNames		= $db->getDbTableNames();
        if(isset($dbTableNames['keys'], $dbTableNames['names']))
        {
            $tableIndex = array_search('%%MESSAGES%%', $dbTableNames['keys'], true);
            if($tableIndex !== false && isset($dbTableNames['names'][$tableIndex]))
            {
                $messageTableName = $dbTableNames['names'][$tableIndex];
            }
        }

        $this->initTemplate();
        $this->setWindow('ajax');

        $MessageList	= array();
        $MessagesID		= array();
        $MessageResult	= array();
        $legacyCount	= 0;
        $activeCount	= 0;

        if($MessCategory == 999)  {

            $sql = "SELECT COUNT(*) as state FROM %%MESSAGES%% WHERE message_sender = :userId AND message_type != 50 AND ".$messageDeletedWhere.";";
            $MessageCount = $db->selectSingle($sql, array(
                ':userId'   => $USER['id'],
            ), 'state');

            $maxPage	= max(1, ceil($MessageCount / MESSAGES_PER_PAGE));
            $page		= max(1, min($page, $maxPage));

            $sql = "SELECT message_id, message_time, CONCAT(username, ' [',galaxy, ':', system, ':', planet,']') as message_from, message_subject, message_sender, message_type, message_unread, message_text
			FROM %%MESSAGES%% INNER JOIN %%USERS%% ON id = message_owner
			WHERE message_sender = :userId AND message_type != 50 AND ".$messageDeletedWhere."
			ORDER BY message_time DESC
			LIMIT :offset, :limit;";

            $MessageResult = $db->select($sql, array(
                ':userId'   => $USER['id'],
                ':offset'   => (($page - 1) * MESSAGES_PER_PAGE),
                ':limit'    => MESSAGES_PER_PAGE
            ));

            if($isDebugMessages)
            {
                $legacySql = "SELECT COUNT(*) as state FROM %%MESSAGES%% WHERE message_sender = :userId AND message_type != 50 AND message_deleted IS NULL;";
                $legacyCount = (int) $db->selectSingle($legacySql, array(':userId' => $USER['id']), 'state');
                $activeCount = (int) $MessageCount;
            }
        }
		else
		{
            if ($MessCategory == 100)
			{
                $sql = "SELECT COUNT(*) as state FROM %%MESSAGES%% WHERE message_owner = :userId AND ".$messageDeletedWhere.";";
                $MessageCount = $db->selectSingle($sql, array(
                    ':userId'   => $USER['id'],
                ), 'state');

                $maxPage	= max(1, ceil($MessageCount / MESSAGES_PER_PAGE));
                $page		= max(1, min($page, $maxPage));

                $sql = "SELECT message_id, message_time, message_from, message_subject, message_sender, message_type, message_unread, message_text
                           FROM %%MESSAGES%%
                           WHERE message_owner = :userId AND ".$messageDeletedWhere."
                           ORDER BY message_time DESC
                           LIMIT :offset, :limit";

                $MessageResult = $db->select($sql, array(
                    ':userId'       => $USER['id'],
                    ':offset'       => (($page - 1) * MESSAGES_PER_PAGE),
                    ':limit'        => MESSAGES_PER_PAGE
                ));

                if($isDebugMessages)
                {
                    $legacySql = "SELECT COUNT(*) as state FROM %%MESSAGES%% WHERE message_owner = :userId AND message_deleted IS NULL;";
                    $legacyCount = (int) $db->selectSingle($legacySql, array(':userId' => $USER['id']), 'state');
                    $activeCount = (int) $MessageCount;
                }
            }
			else
			{
                $sql = "SELECT COUNT(*) as state FROM %%MESSAGES%% WHERE message_owner = :userId AND message_type = :messCategory AND ".$messageDeletedWhere.";";

                $MessageCount = $db->selectSingle($sql, array(
                    ':userId'       => $USER['id'],
                    ':messCategory' => $MessCategory
                ), 'state');

                $sql = "SELECT message_id, message_time, message_from, message_subject, message_sender, message_type, message_unread, message_text
                           FROM %%MESSAGES%%
                           WHERE message_owner = :userId AND message_type = :messCategory AND ".$messageDeletedWhere."
                           ORDER BY message_time DESC
                           LIMIT :offset, :limit";

                $maxPage	= max(1, ceil($MessageCount / MESSAGES_PER_PAGE));
                $page		= max(1, min($page, $maxPage));

                $MessageResult = $db->select($sql, array(
                    ':userId'       => $USER['id'],
                    ':messCategory' => $MessCategory,
                    ':offset'       => (($page - 1) * MESSAGES_PER_PAGE),
                    ':limit'        => MESSAGES_PER_PAGE
                ));

                if($isDebugMessages)
                {
                    $legacySql = "SELECT COUNT(*) as state FROM %%MESSAGES%% WHERE message_owner = :userId AND message_type = :messCategory AND message_deleted IS NULL;";
                    $legacyCount = (int) $db->selectSingle($legacySql, array(
                        ':userId'       => $USER['id'],
                        ':messCategory' => $MessCategory
                    ), 'state');
                    $activeCount = (int) $MessageCount;
                }
            }
        }

        foreach ($MessageResult as $MessageRow)
        {
            $MessagesID[]	= $MessageRow['message_id'];

            $MessageList[]	= array(
                'id'		=> $MessageRow['message_id'],
                'time'		=> _date($LNG['php_tdformat'], $MessageRow['message_time'], $USER['timezone']),
                'from'		=> $MessageRow['message_from'],
                'subject'	=> $MessageRow['message_subject'],
                'sender'	=> $MessageRow['message_sender'],
                'type'		=> $MessageRow['message_type'],
                'unread'	=> $MessageRow['message_unread'],
                'text'		=> html_entity_decode($MessageRow['message_text'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            );
        }

        if(!empty($MessagesID) && $MessCategory != 999) {
            $sql = 'UPDATE %%MESSAGES%% SET message_unread = 0 WHERE message_id IN ('.implode(',', $MessagesID).') AND message_owner = :userID;';
            $db->update($sql, array(
                ':userID'       => $USER['id'],
            ));
        }

        $this->assign(array(
            'MessID'		=> $MessCategory,
            'MessageCount'	=> $MessageCount,
            'MessageList'	=> $MessageList,
            'page'			=> $page,
            'maxPage'		=> $maxPage,
            'messagesDebug'	=> array(
                'enabled'		=> $isDebugMessages,
                'table'			=> $messageTableName,
                'category'		=> $MessCategory,
                'rawCategory'	=> $rawMessCategory,
                'page'			=> $page,
                'dbRows'		=> count($MessageResult),
                'twigCount'		=> count($MessageList),
                'legacyCount'	=> $legacyCount,
                'activeCount'	=> $activeCount,
                'filter'		=> $messageDeletedWhere,
            ),
        ));

        $this->display('page.messages.view.twig');
    }


    function action()
    {
        global $USER;

        $db = Database::get();

        $MessCategory  	= HTTP::_GP('messcat', 100);
        $page		 	= HTTP::_GP('page', 1);
        $messageIDs		= HTTP::_GP('messageID', array());

        $redirectUrl	= 'game.php?page=messages&category='.$MessCategory.'&side='.$page;

		$action			= false;

        if(isset($_POST['submitTop']))
        {
            $action	= HTTP::_GP('actionTop', '');
        }
        elseif(isset($_POST['submitBottom']))
        {
            $action	= HTTP::_GP('actionBottom', '');
        }
        else
        {
            $this->redirectTo($redirectUrl);
        }

        if($action == 'deleteunmarked' && empty($messageIDs))
            $action	= 'deletetypeall';

        if($action == 'deletetypeall' && $MessCategory == 100)
            $action	= 'deleteall';

        if($action == 'readtypeall' && $MessCategory == 100)
            $action	= 'readall';

        switch($action)
        {
            case 'readall':
                $sql = "UPDATE %%MESSAGES%% SET message_unread = 0 WHERE message_owner = :userID;";
                $db->update($sql, array(
                    ':userID'   => $USER['id']
                ));
			break;
            case 'readtypeall':
                $sql = "UPDATE %%MESSAGES%% SET message_unread = 0 WHERE message_owner = :userID AND message_type = :messCategory;";
                $db->update($sql, array(
                    ':userID'       => $USER['id'],
                    ':messCategory' => $MessCategory
                ));
			break;
            case 'readmarked':
                if(empty($messageIDs))
                {
                    $this->redirectTo($redirectUrl);
                }

                $messageIDs	= array_filter($messageIDs, 'is_numeric');

                if(empty($messageIDs))
                {
                    $this->redirectTo($redirectUrl);
                }

                $sql = 'UPDATE %%MESSAGES%% SET message_unread = 0 WHERE message_id IN ('.implode(',', array_keys($messageIDs)).') AND message_owner = :userID;';
                $db->update($sql, array(
                    ':userID'       => $USER['id'],
                ));
			break;
            case 'deleteall':
                if(Config::get()->message_delete_behavior == 1) {
                    $sql = "UPDATE %%MESSAGES%% SET message_deleted = :timestamp WHERE message_owner = :userID;";
                    $db->update($sql, array(
                        ':timestamp'    => TIMESTAMP,
                        ':userID'       => $USER['id']
                    ));
                } else {
                    $sql = "DELETE FROM %%MESSAGES%% WHERE message_owner = :userID;";
                    $db->delete($sql, array(
                        ':userID'       => $USER['id']
                    ));
                }
			break;
            case 'deletetypeall':
                if(Config::get()->message_delete_behavior == 1) {
                    $sql = "UPDATE %%MESSAGES%% SET message_deleted = :timestamp WHERE message_owner = :userID AND message_type = :messCategory;";
                    $db->update($sql, array(
                        ':timestamp' => TIMESTAMP,
                        ':userID' => $USER['id'],
                        ':messCategory' => $MessCategory
                    ));
                } else {
                    $sql = "DELETE FROM %%MESSAGES%% WHERE message_owner = :userID AND message_type = :messCategory;";
                    $db->delete($sql, array(
                        ':userID' => $USER['id'],
                        ':messCategory' => $MessCategory
                    ));
                }
			break;
            case 'deletemarked':
                if(empty($messageIDs))
                {
                    $this->redirectTo($redirectUrl);
                }

                $messageIDs	= array_filter($messageIDs, 'is_numeric');

                if(empty($messageIDs))
                {
                    $this->redirectTo($redirectUrl);
                }

                if(Config::get()->message_delete_behavior == 1) {
                    $sql = 'UPDATE %%MESSAGES%% SET message_deleted = :timestamp WHERE message_id IN (' . implode(',', array_keys($messageIDs)) . ') AND message_owner = :userId;';
                    $db->update($sql, array(
                        ':timestamp' => TIMESTAMP,
                        ':userId' => $USER['id'],
                    ));
                } else {
                    $sql = 'DELETE FROM %%MESSAGES%% WHERE message_id IN (' . implode(',', array_keys($messageIDs)) . ') AND message_owner = :userId;';
                    $db->delete($sql, array(
                        ':userId' => $USER['id'],
                    ));
                }
			break;
            case 'deleteunmarked':
                if(empty($messageIDs) || !is_array($messageIDs))
                {
                    $this->redirectTo($redirectUrl);
                }

                $messageIDs	= array_filter($messageIDs, 'is_numeric');

                if(empty($messageIDs))
                {
                    $this->redirectTo($redirectUrl);
                }

                if(Config::get()->message_delete_behavior == 1) {
                    $sql = 'UPDATE %%MESSAGES%% SET message_deleted = :timestamp WHERE message_id NOT IN (' . implode(',', array_keys($messageIDs)) . ') AND message_owner = :userId;';
                    $db->update($sql, array(
                        ':timestamp' => TIMESTAMP,
                        ':userId' => $USER['id'],
                    ));
                } else {
                    $sql = 'DELETE FROM %%MESSAGES%% WHERE message_id NOT IN ('.implode(',', array_keys($messageIDs)).') AND message_owner = :userId;';
                    $db->delete($sql, array(
                        ':userId'       => $USER['id'],
                    ));
                }
			break;
        }
        $this->redirectTo($redirectUrl);
    }

    function send()
    {
        global $USER, $LNG;
        $receiverID	= HTTP::_GP('id', 0);
        $receiverName = trim(HTTP::_GP('to', '', UTF8_SUPPORT));
        $subject 	= HTTP::_GP('subject', $LNG['mg_no_subject'], true);
		$text		= HTTP::_GP('text', '', true);
		$senderName	= $USER['username'].' ['.$USER['galaxy'].':'.$USER['system'].':'.$USER['planet'].']';

		$text		= makebr($text);

		$db			= Database::get();
		$session	= Session::load();
		if (empty($receiverID) && !empty($receiverName))
		{
			$sql = "SELECT id, settings_blockPM FROM %%USERS%% WHERE universe = :universe AND username = :username;";
			$receiverData = $db->selectSingle($sql, array(
				':universe'	=> Universe::current(),
				':username'	=> $receiverName
			));

			if (empty($receiverData))
			{
				$this->sendJSON($LNG['mg_error']);
			}

			if ((int) $receiverData['settings_blockPM'] === 1)
			{
				$this->sendJSON($LNG['mg_receiver_block_pm']);
			}

			$receiverID = (int) $receiverData['id'];
		}

		$validToken = isset($session->messageToken) && in_array($session->messageToken, array(
			md5($USER['id'].'|'.$receiverID),
			md5($USER['id'].'|0')
		), true);

        if (empty($receiverID) || empty($text) || !$validToken)
        {
            $this->sendJSON($LNG['mg_error']);
        }

		$session->messageToken = NULL;

		PlayerUtil::sendMessage($receiverID, $USER['id'], $senderName, 1, $subject, $text, TIMESTAMP);
        $this->sendJSON($LNG['mg_message_send']);
    }

    function write()
    {
        global $LNG, $USER;
        $this->setWindow('popup');
        $this->initTemplate();

        $db = Database::get();

        $receiverID       	= HTTP::_GP('id', 0);
        $receiverName		= trim(HTTP::_GP('to', '', UTF8_SUPPORT));
        $Subject 			= HTTP::_GP('subject', $LNG['mg_no_subject'], true);
        $MessageBody		= HTTP::_GP('message', '', true);

		$allowFreeRecipient	= empty($receiverID);
		if ($allowFreeRecipient)
		{
			$receiverRecord = array(
				'username'			=> $receiverName,
				'galaxy'			=> '',
				'system'			=> '',
				'planet'			=> '',
				'settings_blockPM'	=> 0,
			);
			Session::load()->messageToken = md5($USER['id'].'|0');
		}
		else
		{
			$sql = "SELECT a.galaxy, a.system, a.planet, b.username, b.id_planet, b.settings_blockPM
			FROM %%PLANETS%% as a, %%USERS%% as b WHERE b.id = :receiverId AND a.id = b.id_planet;";

			$receiverRecord = $db->selectSingle($sql, array(
				':receiverId'   => $receiverID
			));

			if (!$receiverRecord)
			{
				$this->printMessage($LNG['mg_error']);
			}

			if ($receiverRecord['settings_blockPM'] == 1)
			{
				$this->printMessage($LNG['mg_receiver_block_pm']);
			}

			Session::load()->messageToken = md5($USER['id'].'|'.$receiverID);
		}

        $this->assign(array(
            'subject'		=> $Subject,
            'message'		=> $MessageBody,
            'id'			=> $receiverID,
            'OwnerRecord'	=> $receiverRecord,
			'allowFreeRecipient' => $allowFreeRecipient,
        ));

        $this->display('page.messages.write.twig');
    }

    function show()
    {
        global $USER;

        $rawCategory    	= HTTP::_GP('category', '100', true);
        $category      	= $this->normalizeCategory($rawCategory);

        $side			= HTTP::_GP('side', 1);
        $messageDeletedWhere = '(message_deleted IS NULL OR message_deleted = 0)';

        $db = Database::get();

        $TitleColor    	= array ( 0 => '#FFFF00', 1 => '#FF6699', 2 => '#FF3300', 3 => '#FF9900', 4 => '#773399', 5 => '#009933', 15 => '#6495ed', 50 => '#666600', 99 => '#007070', 100 => '#ABABAB', 999 => '#CCCCCC');

        $sql = "SELECT COUNT(*) as state FROM %%MESSAGES%% WHERE message_sender = :userID AND message_type != 50;";
        $MessOut = $db->selectSingle($sql, array(
            ':userID'   => $USER['id']
        ), 'state');

        $OperatorList	= array();
        $Total			= array(0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 15 => 0, 50 => 0, 99 => 0, 100 => 0, 999 => 0);
        $UnRead			= array(0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 15 => 0, 50 => 0, 99 => 0, 100 => 0, 999 => 0);

        $sql = "SELECT username, email FROM %%USERS%% WHERE universe = :universe AND authlevel != :authlevel ORDER BY username ASC;";
        $OperatorResult = $db->select($sql, array(
            ':universe'     => Universe::current(),
            ':authlevel'    => AUTH_USR
        ));

        foreach($OperatorResult as $OperatorRow)
        {
            $OperatorList[$OperatorRow['username']]	= $OperatorRow['email'];
        }

        $sql = "SELECT message_type, SUM(message_unread) as message_unread, COUNT(*) as count FROM %%MESSAGES%% WHERE message_owner = :userID AND ".$messageDeletedWhere." GROUP BY message_type;";
        $CategoryResult = $db->select($sql, array(
            ':userID'   => $USER['id']
        ));

        foreach ($CategoryResult as $CategoryRow)
        {
            $UnRead[$CategoryRow['message_type']]	= $CategoryRow['message_unread'];
            $Total[$CategoryRow['message_type']]	= $CategoryRow['count'];
        }

        $UnRead[100]	= array_sum($UnRead);
        $Total[100]		= array_sum($Total);
        $Total[999]		= $MessOut;

        $CategoryList        = array();

        foreach($TitleColor as $CategoryID => $CategoryColor) {
            $CategoryList[$CategoryID]	= array(
                'color'		=> $CategoryColor,
                'unread'	=> $UnRead[$CategoryID],
                'total'		=> $Total[$CategoryID],
            );
        }

        $this->tplObj->loadscript('message.js');
        $this->assign(array(
            'CategoryList'	=> $CategoryList,
            'OperatorList'	=> $OperatorList,
            'category'		=> $category,
            'side'			=> $side,
        ));

        $this->display('page.messages.default.twig');
    }
}