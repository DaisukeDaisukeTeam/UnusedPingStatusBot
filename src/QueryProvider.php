<?php
declare(strict_types=1);

namespace UnusedPingStatusBot;

interface QueryProvider{
	public function getMaxPlayers() : int;

	public function getNumPlayers() : int;

	public function getVersion() : string;

	/** @return list<string> */
	public function getPlayers() : array;

	/** @return list<string> */
	public function getExtraData() : array;

	public function isHasConnected() : bool;

	public function getPingFalledCount() : int;

	function updateLastConnectTime() : void;

	public function getLastConnectTime() : ?int;
}