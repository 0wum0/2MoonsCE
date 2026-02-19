<?php
declare(strict_types=1);

interface CronjobTask {
	function run(): void;
}
