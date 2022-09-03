<?php

namespace App;

use Dotenv\Dotenv;
use Facebook\WebDriver\Chrome\ChromeDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;


$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$gl = new GoLogin([
	'token'      => $_ENV['TOKEN'],
	'profile_id' => '631344e888a97d127e972f71',
	'port'       => GoLogin::getRandomPort()
	//'tmpdir'     => __DIR__ . '/temp',
]);

if (strtolower(PHP_OS) == 'linux') {
	putenv("WEBDRIVER_CHROME_DRIVER=./chromedriver");
} elseif (strtolower(PHP_OS) == 'darwin') {
	putenv("WEBDRIVER_CHROME_DRIVER=./mac/chromedriver");
} elseif (strtolower(PHP_OS) == 'winnt') {
	putenv("WEBDRIVER_CHROME_DRIVER=chromedriver.exe");
}

$debugger_address = $gl->start();
var_dump($debugger_address) . PHP_EOL;

$chromeOptions = new ChromeOptions();
$chromeOptions->setExperimentalOption('debuggerAddress', $debugger_address);

$capabilities = DesiredCapabilities::chrome();
$capabilities->setCapability(ChromeOptions::CAPABILITY_W3C, $chromeOptions);

$driver = ChromeDriver::start($capabilities);
$driver->get('https://php.net');
sleep(20);

$driver->close();
$gl->stop();
