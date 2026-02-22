<?php

declare(strict_types=1);

/**
 * SmartMoons Support - User Controller
 * Dateipfad: includes/pages/game/ShowTicketPage.class.php
 */

class ShowTicketPage extends AbstractGamePage
{
	public static $requireModule = MODULE_SUPPORT;

	private $ticketObj;
	
	function __construct() 
	{
		parent::__construct();
		require('includes/classes/class.SupportTickets.php');
		$this->ticketObj	= new SupportTickets;
	}
	
	public function show()
	{
		global $USER, $LNG;

		$db = Database::get();

		$sql = "SELECT t.*, COUNT(a.ticketID) as answer
		FROM %%TICKETS%% t
		INNER JOIN %%TICKETS_ANSWER%% a USING (ticketID)
		WHERE t.ownerID = :userID GROUP BY a.ticketID ORDER BY t.ticketID DESC;";

		$ticketResult = $db->select($sql, array(
			':userID'	=> $USER['id']
		));

		$ticketList		= array();
		
		foreach($ticketResult as $ticketRow)
		{
			$ticketRow['time']	= _date($LNG['php_tdformat'], $ticketRow['time'], $USER['timezone']);
			$ticketList[$ticketRow['ticketID']]	= $ticketRow;
		}

		$this->assign(array(
			'ticketList'	=> $ticketList,
			'categoryList'	=> $this->ticketObj->getCategoryList(),
		));
		
		$this->display('page.ticket.default.twig');
	}
	
	public function create()
	{
		$this->assign(array(
			'categoryList'	=> $this->ticketObj->getCategoryList(),
		));
		
		$this->display('page.ticket.create.twig');
	}
	
	public function send()
	{
		global $USER, $LNG;
		
		$ticketID	= HTTP::_GP('id', 0);
		$categoryID	= HTTP::_GP('category', 0);
		$subject	= HTTP::_GP('subject', '', true);
		$message	= HTTP::_GP('message', '', true);
		
		if (empty($message)) {
			$this->redirectTo('game.php?page=ticket');
		}

		if ($ticketID == 0) {
			if (empty($subject) || empty($categoryID)) {
				$this->redirectTo('game.php?page=ticket');
			}
			$ticketID	= $this->ticketObj->createTicket($USER['id'], $USER['username'], $categoryID, $subject, $message);
		} else {
			$db = Database::get();
			$sql = "SELECT status FROM %%TICKETS%% WHERE ticketID = :ticketID AND ownerID = :userID;";
			$status = $db->selectSingle($sql, array(
				':ticketID'	=> $ticketID,
				':userID'	=> $USER['id']
			), 'status');
			
			if($status >= 2) {
				$this->redirectTo('game.php?page=ticket');
			}
			
			$this->ticketObj->createAnswer($ticketID, $USER['id'], $USER['username'], $subject, $message, 0);
		}
		
		$this->redirectTo('game.php?page=ticket&mode=view&id='.$ticketID);
	}
	
	public function view() 
	{
		global $USER, $LNG;
		
		// BBCode Klasse laden und Objekt erstellen (Fix für PHP 8.3 static error)
		if (!class_exists('BBCode')) {
			require_once 'includes/classes/BBCode.class.php';
		}
		$bbcode = new BBCode();

		$db = Database::get();
		$ticketID = HTTP::_GP('id', 0);

		$sql = "SELECT a.*, t.categoryID, t.status FROM %%TICKETS_ANSWER%% a INNER JOIN %%TICKETS%% t USING(ticketID) WHERE a.ticketID = :ticketID AND t.ownerID = :userID ORDER BY a.answerID;";
		$answerResult = $db->select($sql, array(
			':ticketID'	=> $ticketID,
			':userID'	=> $USER['id']
		));

		if(empty($answerResult)) {
			$this->redirectTo('game.php?page=ticket');
		}

		$answerList	= array();
		$ticket_status = 0;

		foreach($answerResult as $answerRow) {
			if (empty($ticket_status)) {
				$ticket_status = $answerRow['status'];
			}
			$answerRow['time'] = _date($LNG['php_tdformat'], $answerRow['time'], $USER['timezone']);
			// FIX: Dynamischer Aufruf statt statisch
			$answerRow['message'] = $bbcode->parse($answerRow['message']);
			$answerList[$answerRow['answerID']] = $answerRow;
		}

		$this->assign(array(
			'answerList'	=> $answerList,
			'ticketID'		=> $ticketID,
			'categoryList'	=> $this->ticketObj->getCategoryList(),
			'status'		=> $ticket_status,
		));
		
		$this->display('page.ticket.view.twig');
	}
}