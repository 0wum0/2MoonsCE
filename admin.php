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

define('MODE', 'ADMIN');
define('DATABASE_VERSION', 'OLD');

define('ROOT_PATH', str_replace('\\', '/',dirname(__FILE__)).'/');

require_once 'includes/common.php';
require_once 'includes/classes/class.Log.php';
require_once 'includes/pages/adm/AbstractAdminPage.php';

if ($USER['authlevel'] == AUTH_USR)
{
	HTTP::redirectTo('game.php');
}

$session	= Session::create();
if($session->adminAccess != 1)
{
	include_once('includes/pages/adm/ShowLoginPage.php');
	ShowLoginPage();
	exit;
}

$uni	= HTTP::_GP('uni', 0);

if($USER['authlevel'] == AUTH_ADM && !empty($uni))
{
	Universe::setEmulated($uni);
}

$page	= HTTP::_GP('page', '');

// ── Plugin admin route dispatch ───────────────────────────────────────────────
// Plugins register admin routes via PluginManager::registerAdminRoute() in plugin.php.
if (PluginManager::get()->dispatchAdminRoute($page)) {
	exit;
}
// ─────────────────────────────────────────────────────────────────────────────

HookManager::get()->doAction('beforeController', ['page' => $page, 'mode' => 'show', 'context' => 'admin']);
switch($page)
{
	case 'logout':
		require_once('includes/pages/adm/ShowLogoutPage.php');
		ShowLogoutPage();
	break;
	case 'infos':
		require_once('includes/pages/adm/ShowInformationPage.php');
		new ShowInformationPage();
	break;
	case 'rights':
		require_once('includes/pages/adm/ShowRightsPage.php');
		new ShowRightsPage();
	break;
	case 'config':
		require_once('includes/pages/adm/ShowConfigBasicPage.php');
		new ShowConfigBasicPage();
	break;
	case 'configuni':
		require_once('includes/pages/adm/ShowConfigUniPage.php');
		new ShowConfigUniPage();
	break;
    case 'configmods':
        require_once('includes/pages/adm/ShowConfigModsPage.php');
        new ShowConfigModsPage();
        break;
	case 'chat':
		require_once('includes/pages/adm/ShowChatConfigPage.php');
		new ShowChatConfigPage();
	break;
	case 'teamspeak':
		require_once('includes/pages/adm/ShowTeamspeakPage.php');
		new ShowTeamspeakPage();
	break;
	case 'facebook':
		require_once('includes/pages/adm/ShowFacebookPage.php');
		new ShowFacebookPage();
	break;
	case 'module':
		require_once('includes/pages/adm/ShowModulePage.php');
		new ShowModulePage();
	break;
	case 'statsconf':
		require_once('includes/pages/adm/ShowStatsPage.php');
		new ShowStatsPage();
	break;
	case 'disclamer':
		require_once('includes/pages/adm/ShowDisclamerPage.php');
		new ShowDisclamerPage();
	break;
	case 'create':
		require_once('includes/pages/adm/ShowCreatorPage.php');
		new ShowCreatorPage();
	break;
	case 'accounteditor':
		require_once('includes/pages/adm/ShowAccountEditorPage.php');
		new ShowAccountEditorPage();
	break;
	case 'active':
		require_once('includes/pages/adm/ShowActivePage.php');
		new ShowActivePage();
	break;
	case 'bans':
		require_once('includes/pages/adm/ShowBanPage.php');
		new ShowBanPage();
	break;
	case 'EditBots':
		include_once('includes/pages/adm/ShowEditBotsPage.php');
		new ShowEditBotsPage();
	break; 
	case 'messagelist':
		require_once('includes/pages/adm/ShowMessageListPage.php');
		new ShowMessageListPage();
	break;
	case 'globalmessage':
		require_once('includes/pages/adm/ShowSendMessagesPage.php');
		new ShowSendMessagesPage();
	break;
	case 'fleets':
		require_once('includes/pages/adm/ShowFlyingFleetPage.php');
		new ShowFlyingFleetPage();
	break;
	case 'accountdata':
		require_once('includes/pages/adm/ShowAccountDataPage.php');
		new ShowAccountDataPage();
	break;
	case 'support':
		require_once('includes/pages/adm/ShowSupportPage.php');
		new ShowSupportPage();
	break;
	case 'password':
		require_once('includes/pages/adm/ShowPassEncripterPage.php');
		new ShowPassEncripterPage();
	break;
	case 'search':
		require_once('includes/pages/adm/ShowSearchPage.php');
		new ShowSearchPage();
	break;
	case 'qeditor':
		require_once('includes/pages/adm/ShowQuickEditorPage.php');
		new ShowQuickEditorPage();
	break;
	case 'statsupdate':
		require_once('includes/pages/adm/ShowStatUpdatePage.php');
		new ShowStatUpdatePage();
	break;
	case 'reset':
		require_once('includes/pages/adm/ShowResetPage.php');
		new ShowResetPage();
	break;
	case 'news':
		require_once('includes/pages/adm/ShowNewsPage.php');
		new ShowNewsPage();
	break;
	case 'topnav':
		require_once('includes/pages/adm/ShowTopnavPage.php');
		new ShowTopnavPage();
	break;
	case 'overview':
		require_once('includes/pages/adm/ShowOverviewPage.php');
		ShowOverviewPage();
	break;
	case 'menu':
		require_once('includes/pages/adm/ShowMenuPage.php');
		new ShowMenuPage();
	break;
	case 'clearcache':
		require_once('includes/pages/adm/ShowClearCachePage.php');
		new ShowClearCachePage();
	break;
	case 'universe':
		require_once('includes/pages/adm/ShowUniversePage.php');
		new ShowUniversePage();
	break;
	case 'multiips':
		require_once('includes/pages/adm/ShowMultiIPPage.php');
		new ShowMultiIPPage();
	break;
	case 'log':
		require_once('includes/pages/adm/ShowLogPage.php');
		new ShowLogPage();
	break;
	case 'vertify':
		require_once('includes/pages/adm/ShowVertify.php');
		new ShowVertify();
	break;
	case 'cronjob':
		require_once('includes/pages/adm/ShowCronjobPage.php');
		new ShowCronjobPage();
	break;
	case 'giveaway':
		require_once('includes/pages/adm/ShowGiveawayPage.php');
		new ShowGiveawayPage();
	break;
	case 'autocomplete':
		require_once('includes/pages/adm/ShowAutoCompletePage.php');
		new ShowAutoCompletePage();
	break;
	case 'dump':
		require_once('includes/pages/adm/ShowDumpPage.php');
		new ShowDumpPage();
	break;
	case 'ForumAdmin':
	case 'ForumAdminPage':
		require_once('includes/pages/adm/ShowForumAdminPage.php');
		new ShowForumAdminPage();
	break;
	case 'pluginAdmin':
		require_once('includes/pages/adm/ShowPluginAdminPage.php');
		new ShowPluginAdminPage();
	break;
	case 'systemDebug':
		require_once('includes/pages/adm/ShowSystemDebugPage.php');
		new ShowSystemDebugPage();
	break;
	default:
		require_once('includes/pages/adm/ShowIndexPage.php');
		new ShowIndexPage();
	break;
}
HookManager::get()->doAction('afterController', ['page' => $page, 'mode' => 'show', 'context' => 'admin']);
