# phpgologin
 REST API provides programmatic access to GoLogin App. Create a new browser profile, get a list of all browser profiles, add a browser profile and running 

# class GoLogin - class for working with <a href="https://gologin.com" target="_blank">gologin.com</a> API

## Getting Started

GoLogin supports Linux, MacOS and Windows platforms.

### Installation

clone or download this repository

`https://github.com/olegopro/phpgologin.git`

for running gologin-selenium.php install selenium

`composer require php-webdriver/webdriver`

for Selenium need download <a href="https://chromedriver.chromium.org/downloads" target="_blank">webdriver</a>

### Usage

Where is token? API token is <a href="https://app.gologin.com/#/personalArea/TokenApi" target="_blank">here</a>.
To have an access to the page below you need <a href="https://app.gologin.com/#/createUser" target="_blank">register</a> GoLogin account.

![Token API in Settings](https://user-images.githubusercontent.com/12957968/146891933-c3b60b4d-c850-47a5-8adf-bc8c37372664.gif)

### Example "gologin-selenium.php"

```php
namespace App;

use Dotenv\Dotenv;
use Facebook\WebDriver\Chrome\ChromeDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;

require './vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$gl = new GoLogin([
	'token'      => $_ENV['TOKEN'],
	'profile_id' => 'yU0Pr0f1leiD',
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

```
### Running example:

`php gologin-selenium.php`

###
### Methods
#### constructor

- `options` <[Object]> Options for profile
	- `token` <[string]> your API <a href="https://gologin.com/#/personalArea/TokenApi" target="_blank">token</a>
	- `profile_id` <[string]> profile ID
	- `executablePath` <[string]> path to executable Orbita file. Orbita will be downloaded automatically if not specified.
    - `remote_debugging_port` <[int]> port for remote debugging
    - `tmpdir` <[string]> path to temporary directore for saving profiles
    - `extra_params` arrayof <[string]> extra params for browser orbita (ex. extentions etc.)
    - `port` <[integer]> Orbita start port

```php
$gl = new GoLogin([
	'token'      => $_ENV['TOKEN'],
	'profile_id' => 'yU0Pr0f1leiD',
]);

```
#### Example create profile
`php gologin-create-profile.php`
```php
use App\GoLogin;
use Dotenv\Dotenv;

require './vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$gl = new GoLogin([
	'token' => $_ENV['TOKEN']
]);

//CREATE
$profile_id = $gl->create([
		'name'         => 'profile_mac',
		'os'           => 'mac',
		'navigator'    => [
			'language'   => 'en-US',
			'userAgent'  => 'random',
			'resolution' => 'random',
			'platform'   => 'mac'
		],
		'proxyEnabled' => true,
		'proxy'        => [
			'mode'            => 'gologin',
			'autoProxyRegion' => 'us'
			//'host'            => '',
			//'port'            => '',
			//'username'        => '',
			//'password'        => '',
		],
		'webRTC'       => [
			'mode'    => 'alerted',
			'enabled' => true
		]
	]
);

echo 'profile id=' . $profile_id . PHP_EOL;
$profile = $gl->getProfile($profile_id);
echo 'new profile name=' . $profile->name . PHP_EOL;

//UPDATE
/*$gl->update([
	'id'   => 'yU0Pr0f1leiD',
	'name' => 'profile_mac2'
]);*/

//DELETE
/*$gl->delete('yU0Pr0f1leiD');*/


```

#### start()  

start browser with profile id

#### stop()  

stop browser with profile id

## Full GoLogin API
**Swagger:** <a href="https://api.gologin.com/docs" target="_blank">link here</a>

**Postman:** <a href="https://documenter.getpostman.com/view/21126834/Uz5GnvaL" target="_blank">link here</a>

### For use multiprocess  you need install APCu PECL extension and compile PHP with ZTS mode.
https://pecl.php.net/package/APCu and Zend Thread Safety (ZTS)
In php.ini change memory_limit = 512M 

