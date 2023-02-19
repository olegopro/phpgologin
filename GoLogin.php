<?php

namespace App;

use Dotenv\Dotenv;
use Exception;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use stdClass;
use ZipArchive;

class GoLogin
{
	private $access_token;
	private $profile_id;
	private $tmpdir;
	private $address;
	private $extra_params;
	private $port;
	private $local;
	private $spawn_browser;
	private $credentials_enable_service;
	private $executablePath;
	private $preferences;
	private $proxy;
	private $profile;
	private $profile_name;
	private $profile_path;
	private $profile_zip_path;
	private $profile_zip_path_upload;
	private $tz;

	public function __construct($options)
	{
		$this->access_token = $options['token'];
		$this->profile_id = $options['profile_id'] ?? null;
		$this->tmpdir = $options['tmpdir'] ?? $this->tempdir();
		$this->address = $options['address'] ?? '127.0.0.1';
		$this->extra_params = $options['extra_params'] ?? [];
		$this->port = $options['port'] ?? 3500;
		$this->local = $options['local'] ?? false;
		$this->spawn_browser = $options['spawn_browser'] ?? true;
		$this->credentials_enable_service = $options['credentials_enable_service'] ?? null;

		$home = $this->get_home_path();

		$this->executablePath = realpath(join('\\', [$home, '.gologin\browser\orbita-browser-109']));

		if (!is_dir($this->executablePath) && (strtolower(PHP_OS) == 'darwin')) {
			$this->executablePath = realpath(join('/', [$home, '.gologin/browser/orbita-browser-109/Orbita-Browser.app/Contents/MacOS/Orbita']));
		}

		echo 'executablePath: ' . $this->executablePath . PHP_EOL;

		if ($this->extra_params) {
			echo 'extra_params' . PHP_EOL;
		}
		//
		$this->setProfileId($this->profile_id);
	}

	private function get_home_path()
	{
		$p1 = $_SERVER['HOME'] ?? null;       // linux path
		$p2 = $_SERVER['HOMEDRIVE'] ?? null;  // win disk
		$p3 = $_SERVER['HOMEPATH'] ?? null;   // win path

		return $p1 . $p2 . $p3;
	}

	private function setProfileId($profile_id)
	{
		$this->profile_id = $profile_id;
		if ($this->profile_id == null) {
			return;
		}

		echo 'tempdir: ' . $this->tmpdir . PHP_EOL;
		$this->profile_path = $this->tmpdir . '/gologin_' . $this->profile_id;
		$this->profile_zip_path = $this->tmpdir . '/gologin_' . $this->profile_id . '.zip';
		$this->profile_zip_path_upload = $this->tmpdir . '/gologin_' . $this->profile_id . '_upload.zip';
	}

	private function spawnBrowser()
	{
		$proxy = $this->proxy;
		$proxy_host = '';
		if ($proxy) {

			if (($proxy->mode == null) || ($proxy->mode == 'geolocation')) {
				$proxy->mode = 'http';
			}

			$proxy_host = $proxy->host;
			$proxy = $this->formatProxyUrl($proxy);
		}

		$tz = $this->tz->timezone;

		$params = [
			'--remote-debugging-port=' . $this->port,
			'--user-data-dir=' . $this->profile_path,
			'--password-store=basic',
			'--tz=' . $tz,
			'--gologin-profile=' . $this->profile_name,
			'--lang=en-US',
		];

		if ($proxy) {
			$hr_rules = sprintf('"MAP * 0.0.0.0 , EXCLUDE %s"', $proxy_host);
			$params[] = '--proxy-server=' . $proxy;
			$params[] = '--host-resolver-rules=' . $hr_rules;
		}

		foreach ($this->extra_params as $param) {
			$params[] = $param;
		}

		if (strtolower(PHP_OS) == 'darwin') {
			$params[] = '>/dev/null 2>&1 &';
			shell_exec($this->executablePath . ' ' . implode(' ', $params));
		} elseif (strtolower(PHP_OS) == 'winnt') {
			// $params[] = '> NUL 2>&1';
			popen('start ' . $this->executablePath . '\chrome.exe ' . implode(' ', $params), 'r');
		}

		$try_count = 1;
		$url = strtolower($this->address) . ':' . $this->port;

		while (($try_count < 100)) {
			try {
				$data = (new Client())->request('GET', 'http://' . $url . '/json')->getBody()->getContents();
				break;
			} catch (Exception $e) {
				$try_count += 1;
				sleep(1);
			}
		}

		return $url;
	}

	public function start()
	{
		$profile_path = $this->createStartup();

		if ($this->spawn_browser) {

			return $this->spawnBrowser();
		}

		return $profile_path;
	}

	private function zipdir($source, $destination)
	{
		if (!extension_loaded('zip') || !file_exists($source)) {
			return false;
		}

		$zip = new ZipArchive();
		if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
			return false;
		}

		$source = str_replace('\\', DIRECTORY_SEPARATOR, realpath($source));
		$source = str_replace('/', DIRECTORY_SEPARATOR, $source);

		if (is_dir($source) === true) {
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($source),
				RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ($files as $file) {
				$file = str_replace('\\', DIRECTORY_SEPARATOR, $file);
				$file = str_replace('/', DIRECTORY_SEPARATOR, $file);

				if ($file == '.' || $file == '..' || empty($file) || $file == DIRECTORY_SEPARATOR) {
					continue;
				}
				// Ignore "." and ".." folders
				if (in_array(substr($file, strrpos($file, DIRECTORY_SEPARATOR) + 1), array('.', '..'))) {
					continue;
				}

				$file = realpath($file);
				$file = str_replace('\\', DIRECTORY_SEPARATOR, $file);
				$file = str_replace('/', DIRECTORY_SEPARATOR, $file);

				if (is_dir($file) === true) {
					$d = str_replace($source . DIRECTORY_SEPARATOR, '', $file);
					if (empty($d)) {
						continue;
					}
					$zip->addEmptyDir($d);
				} elseif (is_file($file) === true) {
					$zip->addFromString(
						str_replace($source . DIRECTORY_SEPARATOR, '', $file),
						file_get_contents($file)
					);
				}
			}
		} elseif (is_file($source) === true) {
			$zip->addFromString(basename($source), file_get_contents($source));
		}

		return $zip->close();
	}

	// Don't work for UNIX systems
	private function waitUntilProfileUsing($try_count = 0)
	{
		popen('taskkill /F /IM chrome.exe /T', 'r');
		sleep(5);

		$profile_path = $this->profile_path;
		echo 'profile_path: ' . $profile_path . PHP_EOL;
		if (is_dir($profile_path)) {
			try {
				rename($profile_path, $profile_path);
			} catch (Exception $e) {
				echo 'waiting chrome termination';
				$this->waitUntilProfileUsing($try_count + 1);
			}
		}
	}

	private function waitUntilProfileUsingUNIX($try_count = 0)
	{
		if ($try_count > 10) {
			shell_exec('killall Orbita');

			return;
		}

		sleep(1);
		$profile_path = $this->profile_path;

		if (is_dir($profile_path) && shell_exec('lsof +D' . $profile_path)) {
			echo 'waiting chrome termination, attempt: ' . $try_count . PHP_EOL;
			$this->waitUntilProfileUsingUNIX($try_count + 1);
		}
	}

	public function stop()
	{
		if (strtolower(PHP_OS) == 'darwin') {
			$this->waitUntilProfileUsingUNIX();
		}

		if (strtolower(PHP_OS) == 'winnt') {
			$this->waitUntilProfileUsing();
		}

		$this->sanitizeProfile();

		if (!$this->local) {
			$this->commitProfile();

			unlink($this->profile_zip_path_upload);
			$this->rmtree($this->profile_path);
		}
	}

	private function commitProfile()
	{
		$this->zipdir($this->profile_path, $this->profile_zip_path_upload);

		try {
			echo 'Отправляем профайл на сервер' . PHP_EOL;

			$response = (new Client())->request('GET', $_ENV['API_URL'] . '/browser/' . $this->profile_id . '/storage-signature', ['headers' => $this->headers()]);
			$signedUrl = $response->getBody()->getContents();

			$data = file_get_contents($this->profile_zip_path_upload);
			$response = (new Client())->request('PUT', $signedUrl, ['body' => $data]);

		} catch (Exception $e) {
			echo $e->getMessage();
		}
	}

	private function sanitizeProfile()
	{
		$remove_dirs = [
			'Default/Cache',
			'Default/Service Worker/CacheStorage',
			'Default/Code Cache',
			'Default/GPUCache',
			'GrShaderCache',
			'ShaderCache',
			'Dictionaries',
			'SafetyTips',
			'fonts',
			'BrowserMetrics',
			'BrowserMetrics-spare.pma',
		];

		foreach ($remove_dirs as $dir) {
			$fpath = realpath($this->profile_path . '/' . $dir);

			if (is_dir($fpath)) {
				try {
					$this->rmtree($fpath);
				} catch (Exception $e) {
					echo $e->getMessage();
				}
			}
		}
	}

	private function formatProxyUrl($proxy)
	{
		return $proxy->mode . '://' . $proxy->host . ':' . $proxy->port;
	}

	private function formatProxyUrlPassword($proxy)
	{
		if ($proxy->username == '') {
			return $proxy->mode . '://' . $proxy->host . ':' . $proxy->port;
		} else {
			return $proxy->mode . '://' . $proxy->username . ':' . $proxy->password . '@' . $proxy->host . ':' . $proxy->port;
		}
	}

	private function getTimeZone()
	{
		$proxy = $this->proxy;
		if ($proxy) {
			$proxies = [
				'http'  => $this->formatProxyUrlPassword($proxy),
				'https' => $this->formatProxyUrlPassword($proxy)
			];

			$data = (new Client())->request('GET', 'https://time.gologin.com', ['proxy' => $proxies])->getBody()->getContents();
		} else {
			$data = (new Client())->request('GET', 'https://time.gologin.com')->getBody()->getContents();
		}

		return json_decode($data);
	}

	public function getProfile($profile_id = null)
	{
		$profile = $profile_id == null
			? $this->profile_id
			: $profile_id;

		try {
			$response = (new Client())->request('GET', $_ENV['API_URL'] . '/browser/' . $profile, ['headers' => $this->headers()]);

			return $data = json_decode($response->getBody()->getContents());

		} catch (Exception $e) {
			throw new Exception($e->getMessage(), $e->getCode(), $e);
		}
	}

	private function downloadProfileZip()
	{
		$s3path = $this->profile->s3Path ?? '';
		echo 's3path: ' . $s3path . PHP_EOL;

		$data = '';

		if ($s3path == '') {
			try {
				$response = (new Client())->request('GET', $_ENV['API_URL'] . '/browser/' . $this->profile_id, ['headers' => $this->headers()]);
				$data = $response->getBody()->getContents();

			} catch (Exception $e) {
				echo $e->getMessage();
			}
		} else {
			$s3url = 'https://gprofiles.gologin.com/' . str_replace(' ', '+', $s3path);
			try {
				$response = (new Client())->request('GET', $s3url);
				$data = $response->getBody()->getContents();
			} catch (Exception $e) {
				echo $e->getMessage();
			}
		}

		if (!$data) {
			$this->createEmptyProfile();
		} else {

			$filename = $this->profile_zip_path;
			$dirname = dirname($filename);

			if (!is_dir($dirname)) {
				mkdir($dirname, 0755, true);
			}

			$fileData = fopen($filename, "x");
			fwrite($fileData, $data);
			fclose($fileData);
		}

		try {
			$this->extractProfileZip();

		} catch (Exception $e) {
			$this->uploadEmptyProfile();
			$this->createEmptyProfile();
			$this->extractProfileZip();
		}

		if (is_file(realpath(($this->profile_path . '/Default' . '/Preferences'))) === false) {
			$this->uploadEmptyProfile();
			$this->createEmptyProfile();
			$this->extractProfileZip();
		}
	}

	private function uploadEmptyProfile()
	{
		echo 'uploadEmptyProfile' . PHP_EOL;

		if (file_exists('./gologin_zeroprofile.zip')) {
			unlink('./gologin_zeroprofile.zip');
		}

		$upload_profile = fopen('./gologin_zeroprofile.zip', 'x');

		try {
			$source = (new Client())->request('GET', 'https://gprofiles.gologin.com/zero_profile.zip');
			$data = $source->getBody()->getContents();

			fwrite($upload_profile, $data);
			fclose($upload_profile);

		} catch (Exception $e) {
			echo $e->getMessage();
		}
	}

	private function createEmptyProfile()
	{
		echo 'createEmptyProfile' . PHP_EOL;
		$empty_profile = '../gologin_zeroprofile.zip';

		if (!is_file($empty_profile)) {
			$fileData = fopen($empty_profile, "a+");
			file_put_contents($this->profile_zip_path, $fileData);
			fclose($fileData);
		}
	}

	private function extractProfileZip()
	{
		$zip = new ZipArchive;
		$res = $zip->open($this->profile_zip_path);

		if ($res === true) {
			$zip->extractTo($this->profile_path);
			$zip->close();
			unlink($this->profile_zip_path);
		} else {
			echo 'Архив профиля не найден' . PHP_EOL;
		}
	}

	private function getGeolocationParams($profileGeolocationParams, $tzGeolocationParams)
	{
		if ($profileGeolocationParams->fillBasedOnIp) {
			return (object)[
				'mode'      => $profileGeolocationParams->mode,
				'latitude'  => (float)$tzGeolocationParams->latitude,
				'longitude' => (float)$tzGeolocationParams->longitude,
				'accuracy'  => (float)$tzGeolocationParams->accuracy,
			];
		}

		return (object)[
			'mode'      => $profileGeolocationParams->mode,
			'latitude'  => $profileGeolocationParams->latitude,
			'longitude' => $profileGeolocationParams->longitude,
			'accuracy'  => $profileGeolocationParams->accuracy
		];
	}

	private function convertPreferences($preferences)
	{
		$resolution = $preferences->navigator->resolution;
		$preferences->screenWidth = (int)explode("x", $resolution)[0];
		$preferences->screenHeight = (int)explode("x", $resolution)[1];
		$preferences->screenHeight = (float)$preferences->screenHeight - rand(40, 120);

		$this->preferences = $preferences;

		$this->tz = $this->getTimeZone();
		$tzGeoLocation = (object)[
			'latitude'  => $this->tz->ll[0],
			'longitude' => $this->tz->ll[1],
			'accuracy'  => $this->tz->accuracy
		];

		$preferences->geoLocation = $this->getGeolocationParams($preferences->geolocation, $tzGeoLocation);

		$preferences->{'webRtc'} = new stdClass();
		$preferences->webRtc->mode = $preferences->webRTC->mode == 'alerted'
			? 'public'
			: $preferences->webRTC->mode;

		$preferences->webRtc->publicIP = $preferences->webRTC->fillBasedOnIp
			? $this->tz->ip
			: $preferences->webRTC->publicIp;

		$preferences->webRtc->localIps = $preferences->webRTC->localIps;

		$preferences->timezone->id = $this->tz->timezone;

		$preferences->webgl_noise_value = $preferences->webGL->noise;
		$preferences->get_client_rects_noise = $preferences->webGL->getClientRectsNoise;

		if ($preferences->clientRects->mode == 'noise') {
			$preferences->client_rects_noise_enable = true;
		}

		$preferences->canvasMode = $preferences->canvas->mode;
		$preferences->canvasNoise = $preferences->canvas->noise;

		$preferences->audioContextMode = $preferences->audioContext->mode;
		$preferences->audioContext->enable = $preferences->audioContextMode != 'off';
		$preferences->audioContext->noiseValue = $preferences->audioContext->noise;

		$preferences->webgl = (object)[
			'metadata' => (object)[
				'vendor'   => $preferences->webGLMetadata->vendor,
				'renderer' => $preferences->webGLMetadata->renderer,
				'mode'     => $preferences->webGLMetadata->mode == 'mask'
			]
		];

		if ($preferences->navigator->userAgent) {
			$preferences->userAgent = $preferences->navigator->userAgent;
		}

		if ($preferences->navigator->doNotTrack) {
			$preferences->doNotTrack = $preferences->navigator->doNotTrack;
		}

		if ($preferences->navigator->hardwareConcurrency) {
			$preferences->hardwareConcurrency = $preferences->navigator->hardwareConcurrency;
		}

		if ($preferences->navigator->language) {
			$preferences->language = $preferences->navigator->language;
		}

		if ($preferences->isM1 ?? false) {
			$preferences->is_m1 = $preferences->isM1 ?? false;
		}

		if (strtolower(PHP_OS) == 'android') {
			$devicePixelRatio = $preferences->devicePixelRatio;
			$deviceScaleFactorCeil = round($devicePixelRatio || 3.5);
			$deviceScaleFactor = $devicePixelRatio;

			if ($deviceScaleFactorCeil == $devicePixelRatio) {
				$deviceScaleFactor += 1e-08;
			}

			$preferences->mobile = (object)[
				'enable'              => true,
				'width'               => $preferences->screenWidth,
				'height'              => $preferences->screenHeight,
				'device_scale_factor' => $deviceScaleFactor
			];
		}

		return $preferences;
	}

	private function updatePreferences()
	{
		$pref_file = $this->profile_path . '/Default' . '/Preferences';

		$pfile = file_get_contents($pref_file);
		$preferences = json_decode($pfile);

		$profile = $this->profile;
		$profile->profile_id = $this->profile_id;

		$proxy = $this->profile->proxy;

		if ($proxy && ($proxy->mode == 'gologin') || ($proxy->mode == 'tor')) {
			$autoProxyServer = $profile->autoProxyServer;

			$splittedAutoProxyServer = explode('://', $autoProxyServer);
			$splittedProxyAddress = explode(':', $splittedAutoProxyServer[1]);

			$port = $splittedProxyAddress[1];

			$proxy = (object)[
				'mode'     => 'http',
				'host'     => $splittedProxyAddress[0],
				'port'     => $port,
				'username' => $profile->autoProxyUsername,
				'password' => $profile->autoProxyPassword,
				'timezone' => $profile->proxy->autoProxyRegion

				//'timezone' => $profile->autoProxyTimezone ?? 'us'
			];

			$profile->proxy->username = $profile->autoProxyUsername;
			$profile->proxy->password = $profile->autoProxyPassword;
		}

		if (!($proxy) || ($proxy->mode == 'none')) {
			echo 'no proxy' . PHP_EOL;
			$proxy = null;
		}

		if ($proxy && ($proxy->mode == null)) {
			$proxy->mode = 'http';
		}

		$this->proxy = $proxy;
		$this->profile_name = $profile->name;

		if ($this->profile_name == null) {
			echo 'empty profile name';
			echo 'profile= ' . $this->profile;
			exit;
		}

		$gologin = $this->convertPreferences($profile);

		if ($this->credentials_enable_service != null) {
			$preferences->credentials_enable_service = $this->credentials_enable_service;
		}

		$preferences->gologin = $gologin;

		$language = $preferences->gologin->navigator->language;
		$preferences->gologin->langHeader = $language;
		$preferences->gologin->language = $language;
		$preferences->gologin->languages = strtok($language, ';');

		$pfile = fopen($pref_file, 'w');
		fwrite($pfile, json_encode($preferences));
		fclose($pfile);
	}

	private function tempdir()
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'tmpdir');

		if (file_exists($tempFile)) {
			unlink($tempFile);
		}

		mkdir($tempFile);
		if (is_dir($tempFile)) {
			return $tempFile;
		}

		return null;
	}

	private function rmtree($path)
	{
		if (is_dir($path)) {
			foreach (scandir($path) as $name) {
				if (in_array($name, array('.', '..'))) {
					continue;
				}
				$subpath = $path . DIRECTORY_SEPARATOR . $name;
				$this->rmtree($subpath);
			}
			rmdir($path);
		} else {
			unlink($path);
		}
	}

	private function createStartup()
	{
		if (!$this->local && is_dir(realpath($this->profile_path))) {

			try {
				$this->rmtree($this->profile_path);
			} catch (Exception $e) {
				echo 'error removing profile ' . $this->profile_path;
			}
		}

		$this->profile = $this->getProfile();

		if (!$this->local) {
			$this->downloadProfileZip();
		}

		$this->updatePreferences();

		return $this->profile_path;
	}

	private function headers()
	{
		return [
			'Authorization' => 'Bearer ' . $this->access_token,
			'User-Agent'    => 'gologin-api'
		];
	}

	private function getRandomFingerprint($options = null)
	{
		$os_type = $options['os'] ?? 'lin';

		$data = (new Client())->request('GET', $_ENV['API_URL'] . '/browser/fingerprint?os=' . $os_type, ['headers' => $this->headers()])
		                      ->getBody()
		                      ->getContents();

		return json_decode($data);
	}

	public function profiles()
	{
		//?
		$data = (new Client())->request('GET', $_ENV['API_URL'] . '/browser/v2', ['headers' => $this->headers()])->getBody()->getContents();

		return json_decode($data);
	}

	public function create($options = null)
	{
		$profile_options = $this->getRandomFingerprint($options);

		$options = (object)$options;
		$navigator = (object)$options->navigator;

		if ($options->navigator) {

			$resolution = $navigator->resolution;
			$userAgent = $navigator->userAgent;
			$language = $navigator->language;

			if (($resolution == 'random') && ($userAgent != 'random')) {
				$profile_options->navigator->userAgent = $userAgent;
			}
			if (($userAgent == 'random') && ($resolution != 'random')) {
				$profile_options->navigator->resolution = $resolution;
			}
			if (($resolution != 'random') && ($userAgent != 'random')) {
				$profile_options->navigator->userAgent = $userAgent;
				$profile_options->navigator->resolution = $resolution;
			}

			$profile_options->navigator->language = $language;
		}

		$profile_options->webGLMetadata->mode = $profile_options->webGLMetadata->mode === 'noise' ? 'mask' : 'off';

		$profile = [
			'name'                  => 'default_name',
			'notes'                 => 'auto generated',
			'browserType'           => 'chrome',
			'os'                    => 'mac',
			'googleServicesEnabled' => true,
			'navigator'             => $profile_options->navigator,
		];

		$result = [...$profile, ...(array)$options, ...(array)$profile_options];

		$result['fonts'] = ['families' => $profile_options->fonts];
		$result['webRTC'] = [
			...(array)$profile_options->webRTC,
			'mode' => 'alerted'
		];

		$result['webGL'] = ['mode' => 'noise'];
		$result['canvas'] = ['mode' => 'noise'];
		$result['audioContextMode'] = ['mode' => 'noise'];
		$result['clientRects'] = ['mode' => 'noise'];

		try {
			$response = (new Client())->request('POST', $_ENV['API_URL'] . '/browser', [
				'headers' => $this->headers(),
				'json'    => $result
			])->getBody()->getContents();

			return json_decode($response)->id;

		} catch (ClientException $e) {
			echo $e->getResponse()->getBody()->getContents();
		}
	}

	public function delete($profile_id = null)
	{
		$profile = $profile_id == null
			? $this->profile_id
			: $profile_id;

		(new Client())->request('DELETE', $_ENV['API_URL'] . '/browser/' . $profile, ['headers' => $this->headers()]);
	}

	public function update($options)
	{
		$this->profile_id = $options['id'];
		$profile = (array)$this->getProfile();

		foreach ($options as $key => $value) {
			$profile[$key] = $value;
		}

		(new Client())->request('PUT', $_ENV['API_URL'] . '/browser/' . $this->profile_id, [
			'headers' => $this->headers(),
			'json'    => $profile
		]);
	}

	public function waitDebuggingUrl($delay_s, $try_count = 3)
	{
		$url = 'https://' . $this->profile_id . '.orbita.gologin.com/json/version';
		echo 'URL: ' . $url . PHP_EOL;

		$wsUrl = '';
		$try_number = 1;

		while ($wsUrl == '') {
			sleep($delay_s);

			try {
				$response = (new Client())->request('GET', $url)->getBody()->getContents();
				$wsUrl = json_decode($response)->webSocketDebuggerUrl;
			} catch (Exception $e) {
				echo $e->getMessage();
			}

			if ($try_number >= $try_count) {
				return (object)['status' => 'failure', 'wsUrl' => $wsUrl];
			}
			$try_number += 1;
		}

		$wsUrl = str_replace(['ws://', '127.0.0.1'], ['wss://', $this->profile_id . '.orbita.gologin.com'], $wsUrl);
		echo 'wsUrl: ' . $wsUrl . PHP_EOL;

		return (object)['status' => 'success', 'wsUrl' => $wsUrl];
	}

	public function startRemote($delay_s = 3)
	{
		$profileResponse = (new Client())->request('POST', $_ENV['API_URL'] . '/browser/' . $this->profile_id . '/web', ['headers' => $this->headers()])->getBody()->getContents();
		echo 'profileResponse: ' . $profileResponse . PHP_EOL;

		if ($profileResponse == 'ok') {
			return $this->waitDebuggingUrl($delay_s);
		}

		return (object)['status' => 'failure'];
	}

	public function stopRemote()
	{
		(new Client())->request('DELETE', $_ENV['API_URL'] . '/browser/' . $this->profile_id . '/web', ['headers' => $this->headers()]);
	}

	public function normalizePageView($page)
	{
		$width = $this->preferences->screenWidth;
		$height = $this->preferences->screenHeight;
		$page->setViewport->width = $width;
		$page->setViewport->height = $height;
	}

	public static function getRandomPort()
	{
		while (true) {
			$port = rand(1000, 35000);
			if (socket_create_listen($port) === false) {
				continue;
			} else {
				return $port;
			}
		}
	}
}



