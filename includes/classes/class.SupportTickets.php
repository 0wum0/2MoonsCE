<?php

declare(strict_types=1);

/**
 * 2Moons 
 * by Jan-Otto Kröpke 2009-2016
 */
 
class SupportTickets
{
	public function createTicket($ownerID, $ownerName, $categoryID, $subject, $message)
	{
		$db = Database::get();
		$now = TIMESTAMP;

		$sql = 'INSERT INTO %%TICKETS%% SET
		ownerID		= :ownerId,
		universe	= :universe,
		categoryID	= :categoryId,
		subject		= :subject,
		status      = 0,
		time		= :time;';

		$db->insert($sql, array(
			':ownerId'		=> $ownerID,
			':universe'		=> Universe::current(),
			':categoryId'	=> $categoryID,
			':subject'		=> $subject,
			':time'			=> $now
		));
		
		$ticketID = $db->lastInsertId();
		$this->createAnswer($ticketID, $ownerID, $ownerName, $subject, $message, 0);

		return $ticketID;
	}

	public function createAnswer($ticketID, $ownerID, $ownerName, $subject, $message, $status)
	{
		$db = Database::get();
		$now = TIMESTAMP;

		$sql = 'INSERT INTO %%TICKETS_ANSWER%% SET
		ticketID	= :ticketId,
		ownerID		= :ownerId,
		ownerName	= :ownerName,
		subject		= :subject,
		message		= :message,
		time		= :time;';

		$db->insert($sql, array(
			':ticketId'		=> $ticketID,
			':ownerId'		=> $ownerID,
			':ownerName'	=> $ownerName,
			':subject'		=> $subject,
			':message'		=> $message,
			':time'			=> $now
		));

		$sql	= 'UPDATE %%TICKETS%% SET status = :status WHERE ticketID = :ticketId;';
		$db->update($sql, array(
			':status'	=> $status,
			':ticketId'	=> $ticketID
		));
	}

	public function getCategoryList()
	{
		global $LNG;
		return array(
			1 => $LNG['ti_category_error'],
			2 => $LNG['ti_category_bug'],
			3 => $LNG['ti_category_feature'],
			4 => $LNG['ti_category_other'],
		);
	}
}