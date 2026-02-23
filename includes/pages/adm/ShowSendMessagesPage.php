<?php

declare(strict_types=1);

/**
 * SmartMoons - Global Message Admin Controller
 * FIX: PHP 8.3 Compatibility & BBCode Instance
 */

if (!allowedTo(str_replace(array(dirname(__FILE__), '\\', '/', '.php'), '', __FILE__))) {
    throw new Exception("Permission error!");
}

function ShowSendMessagesPage() {
	global $USER, $LNG;
	
	$ACTION	= HTTP::_GP('action', '');
    
	if ($ACTION == 'send')
	{
		switch($USER['authlevel'])
		{
			case AUTH_MOD: $class = 'mod'; break;
			case AUTH_OPS: $class = 'ops'; break;
			case AUTH_ADM: $class = 'admin'; break;
			default: $class = ''; break;
		}

		$Subject	= HTTP::_GP('subject', '', true);
		$Message 	= HTTP::_GP('text', '', true);
		$Mode	 	= HTTP::_GP('mode', 0);
		$Lang		= HTTP::_GP('globalmessagelang', '');

		if (!empty($Message) && !empty($Subject))
		{
			if($Mode == 0 || $Mode == 2) {
				$From    	= $LNG['user_level_'.$USER['authlevel']] . ' ' . $USER['username'];
				$pmSubject 	= $Subject;
				$pmMessage 	= $Message;
				
                $USERS = $GLOBALS['DATABASE']->query("SELECT `id`, `username` FROM ".USERS." WHERE `universe` = '".Universe::getEmulated()."'".(!empty($Lang) ? " AND `lang` = '".$GLOBALS['DATABASE']->sql_escape($Lang)."'": "").";");
				
                while($UserData = $GLOBALS['DATABASE']->fetch_array($USERS))
				{
					$sendMessage = str_replace('{USERNAME}', $UserData['username'], $pmMessage);
					PlayerUtil::sendMessage($UserData['id'], $USER['id'], $From, 50, $pmSubject, $sendMessage, TIMESTAMP, NULL, 1, Universe::getEmulated());
				}
			}

			if($Mode == 1 || $Mode == 2) {
				require_once 'includes/classes/Mail.class.php';
				$userList	= array();
				
				$USERS = $GLOBALS['DATABASE']->query("SELECT `email`, `username` FROM ".USERS." WHERE `universe` = '".Universe::getEmulated()."'".(!empty($Lang) ? " AND `lang` = '".$GLOBALS['DATABASE']->sql_escape($Lang)."'": "").";");
				while($UserData = $GLOBALS['DATABASE']->fetch_array($USERS))
				{				
					$userList[$UserData['email']]	= array(
						'username'	=> $UserData['username'],
						'body'		=> $bbcode->parse(str_replace('{USERNAME}', $UserData['username'], $Message))
					);
				}
				Mail::multiSend($userList, strip_tags($Subject));
			}
			exit($LNG['ma_message_sended']);
		} else {
			exit($LNG['ma_subject_needed']);
		}
	}
	
	$sendModes	= $LNG['ma_modes'];
	if(Config::get()->mail_active == 0) {
		unset($sendModes[1]);
		unset($sendModes[2]);
	}
	
	$template	= new template();
	$template->assign_vars(array(
		'langSelector' => array_merge(array('' => $LNG['ma_all']), $LNG->getAllowedLangs(false)),
		'modes' => $sendModes,
	));
    
	$template->show('SendMessagesPage.twig');
}