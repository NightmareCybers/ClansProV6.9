<?php

namespace ClansPro;

/*
 * 
 * v1.3.0 To Do List
 * [X] Separate into Command, Listener, and Main files
 * [ ] Implement commands (plot claim, plot del)
 * [ ] Get plots to work
 * [X] Add plot to config
 * [ ] Add clan description /clan desc <clan>
 * [ ] Only leaders can edit motd, only members can check
 * [ ] More beautiful looking (and working) config
 * 
 * 
 */

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\block\Snow;
use pocketmine\math\Vector3;


class ClanMain extends PluginBase implements Listener {
	
	public $db;
	public $prefs;
	
	public function onEnable() {
		
		@mkdir($this->getDataFolder());
		
		if(!file_exists($this->getDataFolder() . "BannedNames.txt")) {
			$file = fopen($this->getDataFolder() . "BannedNames.txt", "w");
			$txt = "Admin:admin:Staff:staff:Owner:owner:Builder:builder:Op:OP:op";
			fwrite($file, $txt);
		}
		
		$this->getServer()->getPluginManager()->registerEvents(new ClanListener($this), $this);
		$this->fCommand = new ClanCommands($this);
		
		$this->prefs = new Config($this->getDataFolder() . "Prefs.yml", CONFIG::YAML, array(
				"MaxClanNameLength" => 20,
				"MaxPlayersPerClan" => 10,
				"OnlyLeadersAndCoLeaderCanInvite" => true,
				"CoLeadersCanClaim" => true,
				"PlotSize" => 25,
		));
		$this->db = new \SQLite3($this->getDataFolder() . "ClansPro.db");
		$this->db->exec("CREATE TABLE IF NOT EXISTS master (player TEXT PRIMARY KEY COLLATE NOCASE, clan TEXT, rank TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS confirm (player TEXT PRIMARY KEY COLLATE NOCASE, clan TEXT, invitedby TEXT, timestamp INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS motdrcv (player TEXT PRIMARY KEY, timestamp INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS motd (clan TEXT PRIMARY KEY, message TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS plots(clan TEXT PRIMARY KEY, x1 INT, z1 INT, x2 INT, z2 INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS home(clan TEXT PRIMARY KEY, x INT, y INT, z INT);");
	}
		
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		$this->fCommand->onCommand($sender, $command, $label, $args);
	}
	public function isInClan($player) {
		$player = strtolower($player);
		$result = $this->db->query("SELECT * FROM master WHERE player='$player';");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return empty($array) == false;
	}
	
	public function isLeader($player) {
		$clan = $this->db->query("SELECT * FROM master WHERE player='$player';");
		$clanArray = $clan->fetchArray(SQLITE3_ASSOC);
		return $clanArray["rank"] == "Leader";
	}
	
	public function isCoLeader($player) {
		$clan = $this->db->query("SELECT * FROM master WHERE player='$player';");
		$clanArray = $clan->fetchArray(SQLITE3_ASSOC);
		return $clanArray["rank"] == "CoLeader";
	}
	
	public function isMember($player) {
		$clan = $this->db->query("SELECT * FROM master WHERE player='$player';");
		$clanArray = $clan->fetchArray(SQLITE3_ASSOC);
		return $clanArray["rank"] == "Member";
	}
	
	public function getPlayerClan($player) {
		$clan = $this->db->query("SELECT * FROM master WHERE player='$player';");
		$clanArray = $clan->fetchArray(SQLITE3_ASSOC);
		return $clanArray["clan"];
	}
	
	public function getLeader($clan) {
		$leader = $this->db->query("SELECT * FROM master WHERE clan='$clan' AND rank='Leader';");
		$leaderArray = $leader->fetchArray(SQLITE3_ASSOC);
		return $leaderArray['player'];
	}
	
	public function clanExists($clan) {
		$result = $this->db->query("SELECT * FROM master WHERE clan='$clan';");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return empty($array) == false;
	}
	
	public function sameClan($player1, $player2) {
		$clan = $this->db->query("SELECT * FROM master WHERE player='$player1';");
		$player1Clan = $clan->fetchArray(SQLITE3_ASSOC);
		$clan = $this->db->query("SELECT * FROM master WHERE player='$player2';");
		$player2Clan = $clan->fetchArray(SQLITE3_ASSOC);
		return $player1Clan["clan"] == $player2Clan["clan"];
	}
	
	public function getNumberOfPlayers($clan) {
		$query = $this->db->query("SELECT COUNT(*) as count FROM master WHERE clan='$clan';");
		$number = $query->fetchArray();
		return $number['count'];
	}
	
	public function isClanFull($clan) {
		return $this->getNumberOfPlayers($clan) >= $this->prefs->get("MaxPlayersPerClan");
	}
	
	public function isNameBanned($name) {
		$bannedNames = explode(":", file_get_contents($this->getDataFolder() . "BannedNames.txt"));
		return in_array($name, $bannedNames);
	}
	
public function newPlot($clan, $x1, $z1, $x2, $z2) {
		$stmt = $this->db->prepare("INSERT OR REPLACE INTO plots (clan, x1, z1, x2, z2) VALUES (:clan, :x1, :z1, :x2, :z2);");
		$stmt->bindValue(":clan", $clan);
		$stmt->bindValue(":x1", $x1);
		$stmt->bindValue(":z1", $z1);
		$stmt->bindValue(":x2", $x2);
		$stmt->bindValue(":z2", $z2);
		$result = $stmt->execute();
	}
	public function drawPlot($sender, $clan, $x, $y, $z, $level, $size) {
		$arm = ($size - 1) / 2;
		$block = new Snow();
		if($this->cornerIsInPlot($x + $arm, $z + $arm, $x - $arm, $z - $arm)) {
			$claimedBy = $this->clanFromPoint($x, $z);
			$sender->sendMessage($this->formatMessage("This area is aleady claimed by $claimedBy."));
			return false;
		}
		$level->setBlock(new Vector3($x + $arm, $y, $z + $arm), $block);
		$level->setBlock(new Vector3($x - $arm, $y, $z - $arm), $block);
		$this->newPlot($clan, $x + $arm, $z + $arm, $x - $arm, $z - $arm);
		return true;
	}
	
	public function isInPlot($player) {
		$x = $player->getFloorX();
		$z = $player->getFloorZ();
		$result = $this->db->query("SELECT * FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2;");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return empty($array) == false;
	}
	
	public function clanFromPoint($x,$z) {
		$result = $this->db->query("SELECT * FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2;");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return $array["clan"];
	}
	
	public function inOwnPlot($player) {
		$playerName = $player->getName();
		$x = $player->getFloorX();
		$z = $player->getFloorZ();
		return $this->getPlayerClan($playerName) == $this->clanFromPoint($x, $z);
	}
	
	public function pointIsInPlot($x,$z) {
		$result = $this->db->query("SELECT * FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2;");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return !empty($array);
	}
	
	public function cornerIsInPlot($x1, $z1, $x2, $z2) {
		return($this->pointIsInPlot($x1, $z1) || $this->pointIsInPlot($x1, $z2) || $this->pointIsInPlot($x2, $z1) || $this->pointIsInPlot($x2, $z2));
	}
	
	public function formatMessage($string, $confirm = false) {
		if($confirm) {
			return "[" . TextFormat::BLUE . "ClansPro" . TextFormat::WHITE . "] " . TextFormat::GREEN . "$string";
		} else {	
			return "[" . TextFormat::BLUE . "ClansPro" . TextFormat::WHITE . "] " . TextFormat::RED . "$string";
		}
	}
	
	public function motdWaiting($player) {
		$stmt = $this->db->query("SELECT * FROM motdrcv WHERE player='$player';");
		$array = $stmt->fetchArray(SQLITE3_ASSOC);
		$this->getServer()->getLogger()->info("\$player = " . $player);
		return !empty($array);
	}
	
	public function getMOTDTime($player) {
		$stmt = $this->db->query("SELECT * FROM motdrcv WHERE player='$player';");
		$array = $stmt->fetchArray(SQLITE3_ASSOC);
		return $array['timestamp'];
	}
	
	public function setMOTD($clan, $player, $msg) {
		$stmt = $this->db->prepare("INSERT OR REPLACE INTO motd (clan, message) VALUES (:clan, :message);");
		$stmt->bindValue(":clan", $clan);
		$stmt->bindValue(":message", $msg);
		$result = $stmt->execute();
		
		$this->db->query("DELETE FROM motdrcv WHERE player='$player';");
	}
	
	public function updateTag($player) {
		$p = $this->getServer()->getPlayer($player);
		if(!$this->isInClan($player)) {
			$p->setNameTag($player);
		} elseif($this->isLeader($player)) {
			$p->setNameTag("**[" . $this->getPlayerClan($player) . "] " . $player);
		} elseif($this->isCoLeader($player)) {
			$p->setNameTag("*[" . $this->getPlayerClan($player) . "] " . $player);
		} elseif($this->isMember($player)) {
			$p->setNameTag("[" . $this->getPlayerClan($player) . "] " . $player);
		}
	}
	
	public function onDisable() {
		$this->db->close();
	}
}
