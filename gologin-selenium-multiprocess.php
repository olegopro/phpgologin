<?php

namespace App;

use Dotenv\Dotenv;
use Facebook\WebDriver\Chrome\ChromeDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Parallel\Parallel;
use Parallel\Storage\ApcuStorage;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

function scrap($profile)
{
	$gl = new GoLogin([
		'token'      => $_ENV['TOKEN'],
		'profile_id' => $profile['profile_id'],
		'tmpdir'     => __DIR__ . '/temp',
		'port'       => $profile['port']
	]);

	if (strtolower(PHP_OS) == 'linux') {
		putenv("WEBDRIVER_CHROME_DRIVER=./chromedriver");
	} elseif (strtolower(PHP_OS) == 'darwin') {
		putenv("WEBDRIVER_CHROME_DRIVER=./mac/chromedriver");
	} elseif (strtolower(PHP_OS) == 'winnt') {
		putenv("WEBDRIVER_CHROME_DRIVER=chromedriver.exe");
	}

	$debugger_address = $gl->start();
	echo 'debugger_address: ' . $debugger_address . PHP_EOL;

	$chromeOptions = new ChromeOptions();
	$chromeOptions->setExperimentalOption('debuggerAddress', $debugger_address);

	$capabilities = DesiredCapabilities::chrome();
	$capabilities->setCapability(ChromeOptions::CAPABILITY_W3C, $chromeOptions);

	$driver = ChromeDriver::start($capabilities);
	$driver->get('https://php.net');

	echo 'ready ' . $profile['profile_id'] . ' ' . $driver->getTitle() . PHP_EOL;
	sleep(10);

	echo 'closing ' . $profile['profile_id'] . PHP_EOL;

	$driver->close();
	$gl->stop();
}

$profiles = [
	['profile_id' => 'yU0Pr0f1leiD', 'port' => GoLogin::getRandomPort()],
	['profile_id' => 'yU0Pr0f1leiD', 'port' => GoLogin::getRandomPort()],
	['profile_id' => 'yU0Pr0f1leiD', 'port' => GoLogin::getRandomPort()],
	['profile_id' => 'yU0Pr0f1leiD', 'port' => GoLogin::getRandomPort()],
	['profile_id' => 'yU0Pr0f1leiD', 'port' => GoLogin::getRandomPort()],
	['profile_id' => 'yU0Pr0f1leiD', 'port' => GoLogin::getRandomPort()],
	['profile_id' => 'yU0Pr0f1leiD', 'port' => GoLogin::getRandomPort()],
	['profile_id' => 'yU0Pr0f1leiD', 'port' => GoLogin::getRandomPort()],
	['profile_id' => 'yU0Pr0f1leiD', 'port' => GoLogin::getRandomPort()],
	['profile_id' => 'yU0Pr0f1leiD', 'port' => GoLogin::getRandomPort()],
];

$Parallel = new Parallel(new ApcuStorage());
foreach ($profiles as $profile) {
	$Parallel->run('parallels', fn() => scrap($profile));
}

$Parallel->wait();

sleep(10);
if (strtolower(PHP_OS) == 'winnt') {
	shell_exec('taskkill /im Orbita.exe /f');
	shell_exec('taskkill /im chromedriver.exe /f');
} else {
	shell_exec('killall -9 Orbita');
	shell_exec('killall -9 chromedriver');
}
