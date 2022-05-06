<?php
declare(strict_types=1);

namespace UnusedPingStatusBot;

use Discord\Discord;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Phar;
use React\EventLoop\Loop;

class main{
	private string $program_dir;
	private string $working_dir;
	private string $config_dir;
	private string $resources_dir;
	private string $config_token;
	private bool $debug = false;
	private string $config_file;
	private string $channelId;
	private Discord $discord;


	public function run() : void{
		$this->init();
		$loop = Loop::get();

		$debug = $this->debug;
		$logger = new Logger('Logger');
		if($debug === true){
			$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
		}else{
			//$logger->pushHandler(new NullHandler());
			$logger->pushHandler(new StreamHandler('php://stdout', Logger::WARNING));
		}
		$token = trim(file_get_contents($this->config_token));
		if($token === ""){
			throw new \RuntimeException("token is empty, Please write the bot token in the ".$this->config_token." file.");
		}
		$this->discord = new Discord([
			'token' => $token,
			'loop' => $loop,
			'logger' => $logger
		]);
		unset($token);
//
//		$timer = $loop->addPeriodicTimer(1, function() use ($discord){
//			if($this->isKilled){
//				$discord->close();
//				$discord->loop->stop();
//				$this->started = false;
//				return;
//			}
//			$this->task($discord);
//		});
//
//
//		$discord->run();
	}

	private function init() : void{
		error_reporting(E_ALL);
		ini_set('memory_limit', '-1');
		$cwd = getcwd();
		if($cwd === false){
			throw new \RuntimeException("The current working directory is not available, getcwd() === false");
		}
		$this->working_dir = $cwd."/";
		$this->config_dir = $this->working_dir."/config/";
		$this->config_token = $this->config_dir."token";
		$this->program_dir = Phar::running()."/";
		if(strpos($this->program_dir, 'phar://') === false){
			$this->program_dir = $this->working_dir;
		}
		$this->resources_dir = $this->program_dir."resources/";
		if(is_file($this->config_dir)){
			throw new \RuntimeException($this->config_dir." is file.");
		}
		if(!is_dir($this->config_dir)){
			mkdir($this->config_dir);
		}
		$this->config_file = $this->config_dir."config.yml";
		if(!file_exists($this->config_file)){
			copy($this->resources_dir."config.yml", $this->config_file);
		}
		if(!file_exists($this->config_token)){
			touch($this->config_token);
		}
		/** @var array<string, scalar> $config */
		$config = yaml_parse($this->config_file);
		$channelId = $config["send_channelId"] ?? "your-channel-id";
		if(!is_string($channelId)||$channelId === "your-channel-id"){
			throw new \RuntimeException($this->config_file." is invalid: Please write the channel id in \"send_channelId\". ");
		}
		$this->channelId = $channelId;

		include $this->program_dir."/vendor/autoload.php";
	}
}

(new main())->run();

