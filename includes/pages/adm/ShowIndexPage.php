<?php

declare(strict_types=1);

/**
 * ShowIndexPage.php
 * Fix: Admin Default muss funktionieren.
 * -> Index ist Alias / Redirect zur Overview
 */

if (!allowedTo(str_replace([dirname(__FILE__), '\\', '/', '.php'], '', __FILE__))) {
    exit;
}

function ShowIndexPage(): void
{
    // Wenn du willst, dass das Dashboard (Charts etc.) immer die Startseite ist:
    require_once ROOT_PATH . 'includes/pages/adm/ShowOverviewPage.php';
    ShowOverviewPage();
}