<?php

namespace App;

use Dotenv\Dotenv;
use Facebook\WebDriver\Chrome\ChromeDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;

require_once './vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$gl = new GoLogin([
	'token'        => $_ENV['TOKEN'],
	'profile_id'   => '63f13bffd1e803707766834a',
	'port'         => GoLogin::getRandomPort(),
	'extra_params' => ['--lang=ru', '--start-maximized']
	//'tmpdir'     => __DIR__ . '/temp',
]);

if (strtolower(PHP_OS) == 'linux') {
	putenv("WEBDRIVER_CHROME_DRIVER=./chromedriver");
} elseif (strtolower(PHP_OS) == 'darwin') {
	putenv("WEBDRIVER_CHROME_DRIVER=/Users/evilgazz/Downloads/chromedriver109");
} elseif (strtolower(PHP_OS) == 'winnt') {
	echo 'windows start' . PHP_EOL;
	putenv("WEBDRIVER_CHROME_DRIVER=C:\Users\User\Downloads\chromedriver109.exe");
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
