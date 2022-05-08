<?php

declare(strict_types=1);

namespace UnusedPingStatusBot;

use xPaw\MinecraftQuery;
use xPaw\MinecraftQueryException;

class MinecraftQueryProvider implements QueryProvider{
	protected const TYPE_NUM_PLAYER = "Players";
	protected const TYPE_MAX_PLAYER = "MaxPlayers";
	protected const TYPE_VERSION = "Version";

	private string $address;
	private int $port;

	protected MinecraftQuery $query;
	/** @var list<mixed> */
	protected array $info = [];
	protected bool $hasConnected = false;
	private int $pingFalledCount = 0;
	private int $lastConnectTime;

	public function __construct(string $address, int $port){
		$this->address = $address;
		$this->port = $port;
	}


	public function Connect() : bool{
		$this->query = new MinecraftQuery();
		try{
			$this->query->Connect($this->address, $this->port);
			$this->pingFalledCount = 0;
			$this->hasConnected = true;
			$this->updateLastConnectTime();
		}catch(MinecraftQueryException $exception){
			++$this->pingFalledCount;
			$this->hasConnected = false;
			return false;
		}
		$info = $this->query->GetInfo();
		if(is_array($info)){//$info !== false
			$this->info = $info;
		}

		return true;
	}

	public function getPlayers() : array{
		$players = $this->query->GetPlayers();
		if($players === false){
			return [];
		}
		return $players;
	}

	public function getMaxPlayers() : int{
		return $this->info[self::TYPE_MAX_PLAYER] ?? -1;
	}

	public function getNumPlayers() : int{
		return $this->info[self::TYPE_NUM_PLAYER] ?? -1;
	}

	public function getVersion() : string{
		return $this->info[self::TYPE_VERSION] ?? "N/A";
	}

	public function getExtraData() : array{
		return [];
	}

	public function isHasConnected() : bool{
		return $this->hasConnected;
	}

	public function getPingFalledCount() : int{
		return $this->pingFalledCount;
	}

	public function updateLastConnectTime() : void{
		$this->lastConnectTime = time();
	}

	public function getLastConnectTime() : ?int{
		return $this->lastConnectTime ?? null;
	}
}