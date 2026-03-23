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

// @admin-migrated (Phase 10 — AbstractAdminPage)

class ShowCreatorPage extends AbstractAdminPage
{
	public function __construct()
	{
		parent::__construct('ShowCreatorPage');
	}

	protected function run(): void
	{
		global $LNG, $USER;

		$db   = Database::get();
		$uni  = (int) Universe::getEmulated();
		$mode = $_GET['mode'] ?? '';

		switch ($mode) {
			case 'user':
				$LNG->includeData(['PUBLIC']);
				if (!empty($_POST)) {
					$UserName  = HTTP::_GP('name', '', UTF8_SUPPORT);
					$UserPass  = HTTP::_GP('password', '');
					$UserPass2 = HTTP::_GP('password2', '');
					$UserMail  = HTTP::_GP('email', '');
					$UserMail2 = HTTP::_GP('email2', '');
					$UserAuth  = HTTP::_GP('authlevel', 0);
					$Galaxy    = HTTP::_GP('galaxy', 0);
					$System    = HTTP::_GP('system', 0);
					$Planet    = HTTP::_GP('planet', 0);
					$Language  = HTTP::_GP('lang', '');

					$ExistsUser  = (int) $db->selectSingle(
						"SELECT (SELECT COUNT(*) FROM %%USERS%%       WHERE universe = :u AND username = :n)
						      + (SELECT COUNT(*) FROM %%USERS_VALID%% WHERE universe = :u2 AND username = :n2);",
						[':u' => $uni, ':n' => $UserName, ':u2' => $uni, ':n2' => $UserName],
						'(SELECT COUNT(*) FROM %%USERS%%       WHERE universe = :u AND username = :n)
						      + (SELECT COUNT(*) FROM %%USERS_VALID%% WHERE universe = :u2 AND username = :n2)'
					);
					$ExistsUser  = (int) $db->selectSingle(
						"SELECT (SELECT COUNT(*) FROM %%USERS%% WHERE universe = :u AND username = :n) + (SELECT COUNT(*) FROM %%USERS_VALID%% WHERE universe = :u AND username = :n) AS cnt;",
						[':u' => $uni, ':n' => $UserName],
						'cnt'
					);
					$ExistsMails = (int) $db->selectSingle(
						"SELECT (SELECT COUNT(*) FROM %%USERS%% WHERE universe = :u AND (email = :m OR email_2 = :m)) + (SELECT COUNT(*) FROM %%USERS_VALID%% WHERE universe = :u AND email = :m) AS cnt;",
						[':u' => $uni, ':m' => $UserMail],
						'cnt'
					);

					$errors = '';
					$config = Config::get($uni);

					if (!PlayerUtil::isMailValid($UserMail))           $errors .= $LNG['invalid_mail_adress'];
					if (empty($UserName))                               $errors .= $LNG['empty_user_field'];
					if (strlen($UserPass) < 6)                         $errors .= $LNG['password_lenght_error'];
					if ($UserPass !== $UserPass2)                      $errors .= $LNG['different_passwords'];
					if ($UserMail !== $UserMail2)                      $errors .= $LNG['different_mails'];
					if (!PlayerUtil::isNameValid($UserName))           $errors .= $LNG['user_field_specialchar'];
					if ($ExistsUser  !== 0)                            $errors .= $LNG['user_already_exists'];
					if ($ExistsMails !== 0)                            $errors .= $LNG['mail_already_exists'];
					if (!PlayerUtil::isPositionFree($uni, $Galaxy, $System, $Planet)) $errors .= $LNG['planet_already_exists'];
					if ($Galaxy > $config->max_galaxy || $System > $config->max_system || $Planet > $config->max_planets) $errors .= $LNG['po_complete_all2'];

					if (!empty($errors)) {
						$this->message($errors, '?page=create&mode=user', 10);
						return;
					}

					$Language = array_key_exists($Language, $LNG->getAllowedLangs(false)) ? $Language : $config->lang;
					PlayerUtil::createPlayer($uni, $UserName, PlayerUtil::cryptPassword($UserPass), $UserMail,
						$Language, $Galaxy, $System, $Planet, $LNG['fcm_planet'], $UserAuth);
					$this->message($LNG['new_user_success'], '?page=create&mode=user', 5);
					return;
				}

				$AUTH = [AUTH_USR => $LNG['user_level_' . AUTH_USR]];
				if ($USER['authlevel'] >= AUTH_OPS) $AUTH[AUTH_OPS] = $LNG['user_level_' . AUTH_OPS];
				if ($USER['authlevel'] >= AUTH_MOD) $AUTH[AUTH_MOD] = $LNG['user_level_' . AUTH_MOD];
				if ($USER['authlevel'] >= AUTH_ADM) $AUTH[AUTH_ADM] = $LNG['user_level_' . AUTH_ADM];

				$this->assign([
					'admin_auth'          => $USER['authlevel'],
					'new_add_user'        => $LNG['new_add_user'],
					'new_creator_refresh' => $LNG['new_creator_refresh'],
					'new_creator_go_back' => $LNG['new_creator_go_back'],
					'universe'            => $LNG['mu_universe'],
					'user_reg'            => $LNG['user_reg'],
					'pass_reg'            => $LNG['pass_reg'],
					'pass2_reg'           => $LNG['pass2_reg'],
					'email_reg'           => $LNG['email_reg'],
					'email2_reg'          => $LNG['email2_reg'],
					'new_coord'           => $LNG['new_coord'],
					'new_range'           => $LNG['new_range'],
					'lang_reg'            => $LNG['lang_reg'],
					'new_title'           => $LNG['new_title'],
					'Selector'            => ['auth' => $AUTH, 'lang' => $LNG->getAllowedLangs(false)],
				]);
				$this->show('CreatePageUser.twig');
				break;

			case 'moon':
				if (!empty($_POST)) {
					$PlanetID  = HTTP::_GP('add_moon', 0);
					$MoonName  = HTTP::_GP('name', '', UTF8_SUPPORT);
					$Diameter  = HTTP::_GP('diameter', 0);

					$MoonPlanet = $db->selectSingle(
						"SELECT temp_max, temp_min, id_luna, galaxy, system, planet, planet_type, destruyed, id_owner
						 FROM %%PLANETS%%
						 WHERE id = :id AND universe = :u AND planet_type = 1 AND destruyed = 0;",
						[':id' => (int) $PlanetID, ':u' => $uni]
					);

					if (empty($MoonPlanet)) {
						$this->message($LNG['mo_planet_doesnt_exist'], '?page=create&mode=moon', 3);
						return;
					}

					$moonId = PlayerUtil::createMoon($uni, $MoonPlanet['galaxy'], $MoonPlanet['system'],
						$MoonPlanet['planet'], $MoonPlanet['id_owner'], 20,
						(($_POST['diameter_check'] ?? '') === 'on' ? null : $Diameter), $MoonName);

					if ($moonId !== false) {
						$this->message($LNG['mo_moon_added'], '?page=create&mode=moon', 3);
					} else {
						$this->message($LNG['mo_moon_unavaible'], '?page=create&mode=moon', 3);
					}
					return;
				}

				$this->assign([
					'admin_auth'          => $USER['authlevel'],
					'universum'           => $LNG['mu_universe'],
					'po_add_moon'         => $LNG['po_add_moon'],
					'input_id_planet'     => $LNG['input_id_planet'],
					'mo_moon_name'        => $LNG['mo_moon_name'],
					'mo_diameter'         => $LNG['mo_diameter'],
					'mo_temperature'      => $LNG['mo_temperature'],
					'mo_fields_avaibles'  => $LNG['mo_fields_avaibles'],
					'button_add'          => $LNG['button_add'],
					'new_creator_refresh' => $LNG['new_creator_refresh'],
					'mo_moon'             => $LNG['fcm_moon'],
					'new_creator_go_back' => $LNG['new_creator_go_back'],
				]);
				$this->show('CreatePageMoon.twig');
				break;

			case 'planet':
				if (!empty($_POST)) {
					$id        = HTTP::_GP('id', 0);
					$Galaxy    = HTTP::_GP('galaxy', 0);
					$System    = HTTP::_GP('system', 0);
					$Planet    = HTTP::_GP('planet', 0);
					$name      = HTTP::_GP('name', '', UTF8_SUPPORT);
					$field_max = HTTP::_GP('field_max', 0);
					$config    = Config::get($uni);

					if ($Galaxy > $config->max_galaxy || $System > $config->max_system || $Planet > $config->max_planets) {
						$this->message($LNG['po_complete_all2'], '?page=create&mode=planet', 3);
						return;
					}

					$ISUser = $db->selectSingle(
						"SELECT id, authlevel FROM %%USERS%% WHERE id = :id AND universe = :u;",
						[':id' => (int) $id, ':u' => $uni]
					);
					if (!PlayerUtil::checkPosition($uni, $Galaxy, $System, $Planet) || empty($ISUser)) {
						$this->message($LNG['po_complete_all'], '?page=create&mode=planet', 3);
						return;
					}

					$planetId = PlayerUtil::createPlanet($Galaxy, $System, $Planet, $uni, $id, null, false, $ISUser['authlevel']);

					$setClauses = [];
					$params     = [':pid' => (int) $planetId];
					if (($_POST['diameter_check'] ?? '') !== 'on' || $field_max > 0) {
						$setClauses[]       = 'field_max = :field_max';
						$params[':field_max'] = (int) $field_max;
					}
					if (!empty($name)) {
						$setClauses[]    = 'name = :name';
						$params[':name'] = $name;
					}
					if (!empty($setClauses)) {
						$db->update("UPDATE %%PLANETS%% SET " . implode(', ', $setClauses) . " WHERE id = :pid;", $params);
					}

					$this->message($LNG['po_complete_succes'], '?page=create&mode=planet', 3);
					return;
				}

				$this->assign([
					'admin_auth'          => $USER['authlevel'],
					'po_add_planet'       => $LNG['po_add_planet'],
					'po_galaxy'           => $LNG['po_galaxy'],
					'po_system'           => $LNG['po_system'],
					'po_planet'           => $LNG['po_planet'],
					'input_id_user'       => $LNG['input_id_user'],
					'new_creator_coor'    => $LNG['new_creator_coor'],
					'po_name_planet'      => $LNG['po_name_planet'],
					'po_fields_max'       => $LNG['po_fields_max'],
					'button_add'          => $LNG['button_add'],
					'po_colony'           => $LNG['fcp_colony'],
					'new_creator_refresh' => $LNG['new_creator_refresh'],
					'new_creator_go_back' => $LNG['new_creator_go_back'],
				]);
				$this->show('CreatePagePlanet.twig');
				break;

			default:
				$this->assign([
					'new_creator_title_u' => $LNG['new_creator_title_u'],
					'new_creator_title_p' => $LNG['new_creator_title_p'],
					'new_creator_title_l' => $LNG['new_creator_title_l'],
					'new_creator_title'   => $LNG['new_creator_title'],
				]);
				$this->show('CreatePage.twig');
				break;
		}
	}
}