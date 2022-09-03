<?php

use App\GoLogin;
use Dotenv\Dotenv;


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
