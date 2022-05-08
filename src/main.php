<?php
declare(strict_types=1);

namespace UnusedPingStatusBot;

use Discord\Discord;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Phar;
use React\EventLoop\Loop;
use Discord\Helpers\Collection;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Builders\MessageBuilder;

class main{
	private string $program_dir;
	private string $working_dir;
	private string $config_dir;
	private string $resources_dir;
	private string $config_token;
	private bool $debug = true;
	private string $config_file;
	private string $channelId;
	private Discord $discord;
	private Message $targetmessage;
	private MinecraftQueryProvider $provider;
	protected string $address;
	private int $port;

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
		$token = file_get_contents($this->config_token);
		if($token === false || $token === ""){
			throw new \RuntimeException("token is empty, Please write the bot token in the ".$this->config_token." file.");
		}

		echo "starting query: ".$this->address.":".$this->port."\n";

		$this->discord = new Discord([
			'token' => trim($token),
			'loop' => $loop,
			'logger' => $logger
		]);
		unset($token);

		$this->discord->on("ready", function() : void{
			$channel = $this->discord->getChannel($this->channelId);
			$channel->getMessageHistory([
				'limit' => 3,
			])->done(function(Collection $messages) use ($channel) : void{
				foreach($messages as $message){
					/** @var Message $message */
					if($message->author->id === $this->discord->id){
						$this->targetmessage = $message;
						$this->task();
						$this->updateMessage();
						break;
					}
				}
				if(!isset($this->targetmessage)){
					$embed = $this->getEmbed();
					$channel->sendMessage("", false, $embed)->then(function(Message $message){
						$this->targetmessage = $message;
						$this->task();
					}, function() : void{
						$this->discord->close();
						throw new \RuntimeException("sendMessage is rejected.(errno 2)");
					});
				}
			}, function() : void{
				$this->discord->close();
				throw new \RuntimeException("getMessageHistory is rejected.(errno 1)");
			});
		});

		$timer = $loop->addPeriodicTimer(60, function(){
//			if($this->isKilled){
//				$discord->close();
//				$discord->loop->stop();
//				$this->started = false;
//				return;
//			}
			$this->task();
			$this->updateMessage();
		});
		$this->discord->run();
	}

	public function task() : void{
		echo "ping: ";
		$time = microtime(true);
		if(!$this->provider->Connect()){
			echo "query falled\n";
			return;
		}
		$ping = microtime(true) - $time;
		echo "Success in ".(bcmul(sprintf("%.5f", $ping), "1000", 0))."ms, player: ".$this->provider->getNumPlayers()."/".$this->provider->getMaxPlayers()."\n";
	}

	public function updateMessage() : void{
		if(!isset($this->targetmessage) || $this->provider->getPingFalledCount() > 4) return;
		$embed = $this->getEmbed();
		$builder = MessageBuilder::new()->setEmbeds([$embed]);
		var_dump("update edit");
		$this->targetmessage->edit($builder)->then(null, function() : void{
			$this->discord->close();
			throw new \RuntimeException("edit is rejected.(errno 3)");
		});
	}

	private function getEmbed() : Embed{
//		if(!$this->provider->isHasConnected()){
//			throw new \LogicException("isHasConnected() === false");
//		}
		$online = $this->provider->isHasConnected() && $this->provider->getPingFalledCount() < 2;
		$numPlayer = $this->provider->getNumPlayers();
		$maxPlayer = $this->provider->getMaxPlayers();
		$maxplayer = $this->provider->getVersion();
		$extradata = $this->provider->getExtraData();
		foreach($extradata as $value){
			if(preg_match('/^[^\x01-\x20]*$/', $value)){
				$extradata = [];
				break;
			}
		}
		$games = implode("\n", $extradata);

		$embed = new Embed($this->discord);
		$embed->setTitle("Server Status");
		$embed->addFieldValues("Server Status", $online ? "ONLINE" : "OFFLINE", false);
		$embed->setColor($online ? "#007700" : "#D93A00");
		if($online){
			$embed->addFieldValues("players", $numPlayer."/".$maxPlayer, true);
			$embed->addFieldValues("version", $maxplayer, true);
			if(trim($games) !== ""){
				$embed->addFieldValues("games", trim($games));
			}
			$embed->setTimestamp($this->provider->getLastConnectTime());
		}
		return $embed;
	}

	private function init() : void{
		error_reporting(E_ALL);
		ini_set('memory_limit', '-1');
		$cwd = getcwd();
		if($cwd === false){
			throw new \RuntimeException("The current working directory is not available, getcwd() === false");
		}
		$this->working_dir = $cwd."/";
		$this->config_dir = $this->working_dir."config/";
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

		include $this->program_dir."/vendor/autoload.php";

		/** @var array<string, scalar> $config */
		$config = yaml_parse(file_get_contents($this->config_file));
		$channelId = $config["send_channelId"] ?? "your-channel-id";
		if(!is_string($channelId) || $channelId === "your-channel-id"){
			throw new \RuntimeException($this->config_file." is invalid: Please write the channel id in \"send_channelId\". ");
		}
		$this->address = $config["address"] ?? "";
		$this->port = $config["port"] ?? 19132;

		$this->provider = new MinecraftQueryProvider($this->address, $this->port);

		if(!preg_match('/^((25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])\.){3}(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])$/', $this->address) && !preg_match('^([a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]*\.)+[a-zA-Z]{2,}$', $this->address)){
			throw new \RuntimeException("ip address \"".$this->address."\" not valid, Please write the remote address in \"address\".");
		}

		if(!preg_match('/\A[0-9]*\z/', (string) $this->port)){
			throw new \RuntimeException("ip address \"".$this->address."\" not integer, Please write the remote address in \"address\".");
		}

		$this->channelId = $channelId;
	}
}

(new main())->run();

