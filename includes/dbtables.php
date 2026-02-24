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
define('DB_VERSION_REQUIRED', 9);
define('DB_NAME'			, $databaseConfig['dbname']);
define('DB_PREFIX'			, $databaseConfig['prefix']);


define('DB_PREFIX_FORUM_CATEGORIES', DB_PREFIX.'forum_categories');
define('DB_PREFIX_FORUM_TOPICS', DB_PREFIX.'forum_topics');
define('DB_PREFIX_FORUM_POSTS', DB_PREFIX.'forum_posts');
define('DB_PREFIX_FORUM_POST_LIKES', DB_PREFIX.'forum_post_likes');
define('DB_PREFIX_FORUM_MENTIONS', DB_PREFIX.'forum_mentions');
define('DB_PREFIX_FORUM_SUBSCRIPTIONS', DB_PREFIX.'forum_subscriptions');
define('DB_PREFIX_FORUM_REPORTS', DB_PREFIX.'forum_reports');
define('DB_PREFIX_FORUM_TOPIC_UNREADS', DB_PREFIX.'forum_topic_unreads');

// Data Tabells
$dbTableNames	= array(
	'AKS'				=> DB_PREFIX.'aks',
	'ALLIANCE'			=> DB_PREFIX.'alliance',
	'ALLIANCE_RANK'		=> DB_PREFIX.'alliance_ranks',
	'ALLIANCE_REQUEST'	=> DB_PREFIX.'alliance_request',
	'BANNED'			=> DB_PREFIX.'banned',
	'BUDDY'				=> DB_PREFIX.'buddy',
	'BUDDY_REQUEST'		=> DB_PREFIX.'buddy_request',
	// ✅ BOTS SYSTEM (FIX: Placeholder %%BOTS%% / %%BOT_SETTING%% must exist)
	'BOTS'				=> DB_PREFIX.'bots',
	'BOT_SETTING'		=> DB_PREFIX.'bot_setting',
	'CHAT_BAN'			=> DB_PREFIX.'chat_bans',
	'CHAT_INV'			=> DB_PREFIX.'chat_invitations',
	'CHAT_MES'			=> DB_PREFIX.'chat_messages',
	'CHAT_ON'			=> DB_PREFIX.'chat_online',
	'CONFIG'			=> DB_PREFIX.'config',
	'CRONJOBS'			=> DB_PREFIX.'cronjobs',
	'CRONJOBS_LOG'		=> DB_PREFIX.'cronjobs_log',
	'DIPLO'				=> DB_PREFIX.'diplo',
	'FLEETS'			=> DB_PREFIX.'fleets',
	'FLEETS_EVENT'		=> DB_PREFIX.'fleet_event',
	// ✅ FORUM SYSTEM
	'FORUM_CATEGORIES'	=> DB_PREFIX.'forum_categories',
	'FORUM_TOPICS'		=> DB_PREFIX.'forum_topics',
	'FORUM_POSTS'		=> DB_PREFIX.'forum_posts',
	'FORUM_POST_LIKES'	=> DB_PREFIX.'forum_post_likes',
	'FORUM_MENTIONS'	=> DB_PREFIX.'forum_mentions',
	'FORUM_SUBSCRIPTIONS' => DB_PREFIX.'forum_subscriptions',
	'FORUM_REPORTS'		=> DB_PREFIX.'forum_reports',
	'FORUM_TOPIC_UNREADS' => DB_PREFIX.'forum_topic_unreads',
	'LOG'				=> DB_PREFIX.'log',
	'LOG_FLEETS'		=> DB_PREFIX.'log_fleets',
	'LOSTPASSWORD'		=> DB_PREFIX.'lostpassword',
	'NEWS'				=> DB_PREFIX.'news',
	'NOTES'				=> DB_PREFIX.'notes',
	'MESSAGES'			=> DB_PREFIX.'messages',
	'MULTI'				=> DB_PREFIX.'multi',
	'PLANETS'			=> DB_PREFIX.'planets',
	'RW'				=> DB_PREFIX.'raports',
	'RECORDS'			=> DB_PREFIX.'records',
	'SESSION'			=> DB_PREFIX.'session',
	'SHORTCUTS'			=> DB_PREFIX.'shortcuts',
	'STATPOINTS'		=> DB_PREFIX.'statpoints',
	'SYSTEM'		    => DB_PREFIX.'system',
	'TICKETS'			=> DB_PREFIX.'ticket',
	'TICKETS_ANSWER'	=> DB_PREFIX.'ticket_answer',
	'TICKETS_CATEGORY'	=> DB_PREFIX.'ticket_category',
	'TOPKB'				=> DB_PREFIX.'topkb',
	'TOPKB_USERS'		=> DB_PREFIX.'users_to_topkb',
	'USERS'				=> DB_PREFIX.'users',
	'USERS_ACS'			=> DB_PREFIX.'users_to_acs',
	'USERS_AUTH'		=> DB_PREFIX.'users_to_extauth',
	'USERS_VALID'	 	=> DB_PREFIX.'users_valid',
	'VARS'	 			=> DB_PREFIX.'vars',
	'VARS_RAPIDFIRE'	=> DB_PREFIX.'vars_rapidfire',
	'VARS_REQUIRE'	 	=> DB_PREFIX.'vars_requriements',
	'PLUGINS'			=> DB_PREFIX.'plugins',
	// sm-relics plugin tables (registered here so %%ALIAS%% substitution works in SQL)
	'RELICS_USER'		=> DB_PREFIX.'relics_user',
	'RELICS_LOG'		=> DB_PREFIX.'relics_log',
	'RELICS_SETTINGS'	=> DB_PREFIX.'relics_settings',
);