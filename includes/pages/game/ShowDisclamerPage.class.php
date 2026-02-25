<?php

declare(strict_types=1);

class ShowDisclamerPage extends AbstractGamePage
{
	public static $requireModule = 0;

	function __construct()
	{
		parent::__construct();
	}

	public function show(): void
	{
		global $LNG;

		$config = Config::get(Universe::getEmulated());

		$this->assign([
			'disclaimerAddress' => $config->disclamerAddress ?? '',
			'disclaimerPhone'   => $config->disclamerPhone   ?? '',
			'disclaimerMail'    => $config->disclamerMail    ?? '',
			'disclaimerNotice'  => $config->disclamerNotice  ?? '',
			'pageTitle'         => $LNG['menu_disclamer'] ?? 'Impressum',
		]);

		$this->display('page.disclamer.default.twig');
	}
}
