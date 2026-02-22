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

define('MODE', 'ADMIN');
define('DATABASE_VERSION', 'OLD');

define('ROOT_PATH', str_replace('\\', '/',dirname(__FILE__)).'/');

require_once 'includes/common.php';
require_once 'includes/classes/class.Log.php';

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
switch($page)
{
	case 'logout':
		require_once('includes/pages/adm/ShowLogoutPage.php');
		ShowLogoutPage();
	break;
	case 'infos':
		require_once('includes/pages/adm/ShowInformationPage.php');
		ShowInformationPage();
	break;
	case 'rights':
		require_once('includes/pages/adm/ShowRightsPage.php');
		ShowRightsPage();
	break;
	case 'config':
		require_once('includes/pages/adm/ShowConfigBasicPage.php');
		ShowConfigBasicPage();
	break;
	case 'configuni':
		require_once('includes/pages/adm/ShowConfigUniPage.php');
		ShowConfigUniPage();
	break;
    case 'configmods':
        require_once('includes/pages/adm/ShowConfigModsPage.php');
        ShowConfigModsPage();
        break;
	case 'chat':
		require_once('includes/pages/adm/ShowChatConfigPage.php');
		ShowChatConfigPage();
	break;
	case 'teamspeak':
		require_once('includes/pages/adm/ShowTeamspeakPage.php');
		ShowTeamspeakPage();
	break;
	case 'facebook':
		require_once('includes/pages/adm/ShowFacebookPage.php');
		ShowFacebookPage();
	break;
	case 'module':
		require_once('includes/pages/adm/ShowModulePage.php');
		ShowModulePage();
	break;
	case 'statsconf':
		require_once('includes/pages/adm/ShowStatsPage.php');
		ShowStatsPage();
	break;
	case 'disclamer':
		require_once('includes/pages/adm/ShowDisclamerPage.php');
		ShowDisclamerPage();
	break;
	case 'create':
		require_once('includes/pages/adm/ShowCreatorPage.php');
		ShowCreatorPage();
	break;
	case 'accounteditor':
		require_once('includes/pages/adm/ShowAccountEditorPage.php');
		ShowAccountEditorPage();
	break;
	case 'active':
		require_once('includes/pages/adm/ShowActivePage.php');
		ShowActivePage();
	break;
	case 'bans':
		require_once('includes/pages/adm/ShowBanPage.php');
		ShowBanPage();
	break;
		case 'EditBots':
		include_once('includes/pages/adm/ShowEditBotsPage.php');
		ShowEditBotsPage();
	break; 
	case 'messagelist':
		require_once('includes/pages/adm/ShowMessageListPage.php');
		ShowMessageListPage();
	break;
	case 'globalmessage':
		require_once('includes/pages/adm/ShowSendMessagesPage.php');
		ShowSendMessagesPage();
	break;
	case 'fleets':
		require_once('includes/pages/adm/ShowFlyingFleetPage.php');
		ShowFlyingFleetPage();
	break;
	case 'accountdata':
		require_once('includes/pages/adm/ShowAccountDataPage.php');
		ShowAccountDataPage();
	break;
	case 'support':
		require_once('includes/pages/adm/ShowSupportPage.php');
		new ShowSupportPage();
	break;
	case 'password':
		require_once('includes/pages/adm/ShowPassEncripterPage.php');
		ShowPassEncripterPage();
	break;
	case 'search':
		require_once('includes/pages/adm/ShowSearchPage.php');
		ShowSearchPage();
	break;
	case 'qeditor':
		require_once('includes/pages/adm/ShowQuickEditorPage.php');
		ShowQuickEditorPage();
	break;
	case 'statsupdate':
		require_once('includes/pages/adm/ShowStatUpdatePage.php');
		ShowStatUpdatePage();
	break;
	case 'reset':
		require_once('includes/pages/adm/ShowResetPage.php');
		ShowResetPage();
	break;
	case 'news':
		require_once('includes/pages/adm/ShowNewsPage.php');
		ShowNewsPage();
	break;
	case 'topnav':
		require_once('includes/pages/adm/ShowTopnavPage.php');
		ShowTopnavPage();
	break;
	case 'overview':
		require_once('includes/pages/adm/ShowOverviewPage.php');
		ShowOverviewPage();
	break;
	case 'menu':
		require_once('includes/pages/adm/ShowMenuPage.php');
		ShowMenuPage();
	break;
	case 'clearcache':
		require_once('includes/pages/adm/ShowClearCachePage.php');
		ShowClearCachePage();
	break;
	case 'universe':
		require_once('includes/pages/adm/ShowUniversePage.php');
		ShowUniversePage();
	break;
	case 'multiips':
		require_once('includes/pages/adm/ShowMultiIPPage.php');
		ShowMultiIPPage();
	break;
	case 'log':
		require_once('includes/pages/adm/ShowLogPage.php');
		ShowLog();
	break;
	case 'vertify':
		require_once('includes/pages/adm/ShowVertify.php');
		ShowVertify();
	break;
	case 'cronjob':
		require_once('includes/pages/adm/ShowCronjobPage.php');
		ShowCronjob();
	break;
	case 'giveaway':
		require_once('includes/pages/adm/ShowGiveawayPage.php');
		ShowGiveaway();
	break;
	case 'autocomplete':
		require_once('includes/pages/adm/ShowAutoCompletePage.php');
		ShowAutoCompletePage();
	break;
	case 'dump':
		require_once('includes/pages/adm/ShowDumpPage.php');
		ShowDumpPage();
	break;
	case 'ForumAdmin':
	case 'ForumAdminPage':
		require_once('includes/pages/adm/ShowForumAdminPage.php');
		ShowForumAdminPage();
	break;
	default:
		require_once('includes/pages/adm/ShowIndexPage.php');
		ShowIndexPage();
	break;
}
