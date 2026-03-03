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

if (!allowedTo(str_replace(array(dirname(__FILE__), '\\', '/', '.php'), '', __FILE__))) throw new Exception("Permission error!");
		
class ShowSupportPage
{
	private $ticketObj;
	public $tplObj;
	
	function __construct() 
	{
		require('includes/classes/class.SupportTickets.php');
		$this->ticketObj	= new SupportTickets;
		$this->tplObj		= new template();
		
		$ACTION = HTTP::_GP('mode', 'show');
		if(method_exists($this, $ACTION)) {
			$this->{$ACTION}();
		} else {
			$this->show();
        }
	}
	
	public function show()
	{
		global $USER, $LNG;
		$db = Database::get();
				
		$sql = "SELECT t.*, u.username, COUNT(a.ticketID) as answer 
                FROM %%TICKETS%% t 
                INNER JOIN %%TICKETS_ANSWER%% a USING (ticketID) 
                INNER JOIN %%USERS%% u ON u.id = t.ownerID 
                WHERE t.universe = :uni GROUP BY a.ticketID ORDER BY t.ticketID DESC;";

		$ticketResult = $db->select($sql, [':uni' => Universe::current()]);
		$ticketList = array();
		
		foreach($ticketResult as $ticketRow) {
			$ticketRow['time'] = _date($LNG['php_tdformat'], $ticketRow['time'], $USER['timezone']);
			$ticketList[$ticketRow['ticketID']] = $ticketRow;
		}
		
		$this->tplObj->assign_vars(array(	
			'ticketList'	=> $ticketList,
			'categoryList'	=> $this->ticketObj->getCategoryList(),
		));
			
		$this->tplObj->show('page.ticket.default.twig');
	}
	
	public function view() 
	{
		global $USER, $LNG;
		$db = Database::get();
		$ticketID = HTTP::_GP('id', 0);
		
        // BBCode Fix für PHP 8.3
        if (!class_exists('BBCode')) {
            require_once 'includes/classes/BBCode.class.php';
        }
        $bbcode = new BBCode();

		$sql = "SELECT a.*, t.categoryID, t.status 
                FROM %%TICKETS_ANSWER%% a 
                INNER JOIN %%TICKETS%% t USING(ticketID) 
                WHERE a.ticketID = :id ORDER BY a.answerID;";
		
        $answerResult = $db->select($sql, [':id' => $ticketID]);
		$answerList = array();
		$ticket_status = 0;

		foreach($answerResult as $answerRow) {
			if (empty($ticket_status)) $ticket_status = $answerRow['status'];
			$answerRow['time'] = _date($LNG['php_tdformat'], $answerRow['time'], $USER['timezone']);
			// FIX: Nicht-statischer Aufruf
            $answerRow['message'] = $bbcode->parse($answerRow['message']);
			$answerList[$answerRow['answerID']] = $answerRow;
		}
			
		$this->tplObj->assign_vars(array(
			'ticketID'		=> $ticketID,
			'status'        => $ticket_status,
			'categoryList'	=> $this->ticketObj->getCategoryList(),
			'answerList'	=> $answerList,
		));
			
		$this->tplObj->show('page.ticket.view.twig');		
	}

    public function send() 
	{
		global $USER, $LNG;
		$ticketID = HTTP::_GP('id', 0);
		$message  = HTTP::_GP('message', '', true);
        
        // Status auf "Beantwortet" (1) setzen
		$this->ticketObj->createAnswer($ticketID, $USER['id'], $USER['username'], 'Support Antwort', $message, 1);
		
        HTTP::redirectTo('admin.php?page=support&mode=view&id='.$ticketID);
	}
}