<?php


namespace ClansPro;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\PluginTask;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;

class ClanCommands {
	
	public $plugin;
	
	public function __construct(ClanMain $pg) {
		$this->plugin = $pg;
	}
	
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		if($sender instanceof Player) {
			$player = $sender->getPlayer()->getName();
			if(strtolower($command->getName('clan'))) {
				if(empty($args)) {
					$sender->sendMessage($this->plugin->formatMessage("Gunakan /clan Help Untuk Melihat Info Command ClanPro"));
					return true;
				}
				if(count($args == 2)) {
					
					/////////////////////////////// CREATE ///////////////////////////////
					
					if($args[0] == "create") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Gunakan /clan create Untuk Membuat Clan"));
							return true;
						}
						if(!(ctype_alnum($args[1]))) {
							$sender->sendMessage($this->plugin->formatMessage("Karakter Harus Berformat Angka Dan Huruf"));
							return true;
						}
						if($this->plugin->isNameBanned($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Nama Ini Tidak Diperbolehkan"));
							return true;
						}
						if($this->plugin->clanExists($args[1]) == true ) {
							$sender->sendMessage($this->plugin->formatMessage("Clan Sudah Terdaftar"));
							return true;
						}
						if(strlen($args[1]) > $this->plugin->prefs->get("MaxClanNameLength")) {
							$sender->sendMessage($this->plugin->formatMessage("This name is too long. Please try again!"));
							return true;
						}
						if($this->plugin->isInClan($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("Kamu Harus Keluar Clan Terlebih Dahulu"));
							return true;
						} else {
							$clanName = $args[1];
							$player = strtolower($player);
							$rank = "Leader";
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, clan, rank) VALUES (:player, :clan, :rank);");
							$stmt->bindValue(":player", $player);
							$stmt->bindValue(":clan", $clanName);
							$stmt->bindValue(":rank", $rank);
							$result = $stmt->execute();
							$this->plugin->updateTag($player);
							$sender->sendMessage($this->plugin->formatMessage("Clan Sukses Dibuat", true));
							return true;
						}
					}
					
					/////////////////////////////// INVITE ///////////////////////////////
					
					if($args[0] == "invite") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Gunakan /clan invite Untuk Mengundang Player Ke Clanmu"));
							return true;
						}
						if( $this->plugin->isClanFull($this->plugin->getPlayerClan($player)) ) {
							$sender->sendMessage($this->plugin->formatMessage("Clan Penuh"));
							return true;
						}
						$invited = $this->plugin->getServer()->getPlayerExact($args[1]);
						if($this->plugin->isInClan($invited) == true) {
							$sender->sendMessage($this->plugin->formatMessage("Player Sudah Ada Di Clanmu"));
							return true;
						}
						if($this->plugin->prefs->get("OnlyLeadersCanInvite") & !($this->plugin->isLeader($player))) {
							$sender->sendMessage($this->plugin->formatMessage("Hanya Leader Dan CoLeader Yang Bisa Invite Player"));
							return true;
						}
						if(!$invited instanceof Player) {
							$sender->sendMessage($this->plugin->formatMessage("Player Tidak Online"));
							return true;
						}
						if($invited->isOnline() == true) {
							$clanName = $this->plugin->getPlayerClan($player);
							$invitedName = $invited->getName();
							$rank = "Member";
								
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO confirm (player, clan, invitedby, timestamp) VALUES (:player, :clan, :invitedby, :timestamp);");
							$stmt->bindValue(":player", strtolower($invitedName));
							$stmt->bindValue(":clan", $clanName);
							$stmt->bindValue(":invitedby", $sender->getName());
							$stmt->bindValue(":timestamp", time());
							$result = $stmt->execute();
	
							$sender->sendMessage($this->plugin->formatMessage("$invitedName has been invited!", true));
							$invited->sendMessage($this->plugin->formatMessage("Kamu Diundang Ke Clan $clanName. Ketik /clan accept Untuk Menerima Dan /clan deny Untuk Menolak", true));
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Player Tidak Online"));
						}
					}
					
					/////////////////////////////// LEADER ///////////////////////////////
					
					if($args[0] == "leader") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Gunakan /clan leader <player> Untuk Menjadikannya Leader Clan"));
							return true;
						}
						if(!$this->plugin->isInClan($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("Kamu Harus Berada Dalam Clan Untuk Melakukan Ini"));
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Kamu Harus Menjadi Leader Untuk Melakukan Ini"));
						}
						if($this->plugin->getPlayerClan($player) != $this->plugin->getPlayerClan($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Menambahkan Player Untuk Clan Pertama"));
						}		
						if(!$this->plugin->getServer()->getPlayerExact($args[1])->isOnline()) {
							$sender->sendMessage($this->plugin->formatMessage("Player Tidak Online"));
						}
							$clanName = $this->plugin->getPlayerClan($player);
							$clanName = $this->plugin->getPlayerClan($player);
	
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, clan, rank) VALUES (:player, :clan, :rank);");
							$stmt->bindValue(":player", $player);
							$stmt->bindValue(":clan", $clanName);
							$stmt->bindValue(":rank", "Member");
							$result = $stmt->execute();
	
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, clan, rank) VALUES (:player, :clan, :rank);");
							$stmt->bindValue(":player", strtolower($args[1]));
							$stmt->bindValue(":clan", $clanName);
							$stmt->bindValue(":rank", "Leader");
							$result = $stmt->execute();
	
	
							$sender->sendMessage($this->plugin->formatMessage("Kamu Bukan Lagi Leader", true));
							$this->plugin->getServer()->getPlayer($args[1])->sendMessage($this->plugin->formatMessage("Kamu Sekarang Leader Di Clan \nof $clanName!",  true));
							$this->plugin->updateTag($sender->getName());
							$this->plugin->updateTag($this->plugin->getServer()->getPlayer($args[1])->getName());
						}
					
					/////////////////////////////// PROMOTE ///////////////////////////////
					
					if($args[0] == "promote") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Gunakan /clan promote Untuk Mengangkat Member Menjadi CoLeader"));
							return true;
						}
						if(!$this->plugin->isInClan($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("Kamu Harus Berada Dalam Clan Untuk Melakukan Ini"));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Hanya Leader Clan Yang Dapat Melakukan Ini"));
							return true;
						}
						if($this->plugin->getPlayerClan($player) != $this->plugin->getPlayerClan($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Player Itu Tidak Ada Di Clan Ini"));
							return true;
						}
						if($this->plugin->isCoLeader($this->plugin->getServer()->getPlayer($args[1])->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("Player Ini Sudah Menjadi CoLeader"));
							return true;
						}
						$clanName = $this->plugin->getPlayerClan($player);
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, clan, rank) VALUES (:player, :clan, :rank);");
						$stmt->bindValue(":player", strtolower($args[1]));
						$stmt->bindValue(":clan", $clanName);
						$stmt->bindValue(":rank", "CoLeader");
						$result = $stmt->execute();
						$player = $this->plugin->getServer()->getPlayer($args[1]);
						$sender->sendMessage($this->plugin->formatMessage("" . $player->getName() . " Telah Diangkat Menjadi CoLeader", true));
						$player->sendMessage($this->plugin->formatMessage("Kamu Sekarang Menjadi CoLeader Clan!", true));
						$this->plugin->updateTag($player->getName());
					}
					
					/////////////////////////////// DEMOTE ///////////////////////////////
					
					if($args[0] == "demote") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Gunakan /clan demote <player> Untuk Mendemote Player"));
							return true;
						}
						if($this->plugin->isInClan($sender->getName()) == false) {
							$sender->sendMessage($this->plugin->formatMessage("Kamu Harus Berada Dalam Clan Untuk Melakukan Ini"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("Hanya Leader Yang Dapat Melakukan Ini"));
							return true;
						}
						if($this->plugin->getPlayerClan($player) != $this->plugin->getPlayerClan($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Player Tidak Terdaftar Di Clan Anda"));
							return true;
						}
						if(!$this->plugin->isCoLeader($this->plugin->getServer()->getPlayer($args[1])->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("Player Sudah Menjadi Member Di Clan Anda"));
							return true;
						}
						$clanName = $this->plugin->getPlayerClan($player);
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, clan, rank) VALUES (:player, :clan, :rank);");
						$stmt->bindValue(":player", strtolower($args[1]));
						$stmt->bindValue(":clan", $clanName);
						$stmt->bindValue(":rank", "Member");
						$result = $stmt->execute();
						$player = $this->plugin->getServer()->getPlayer($args[1]);
						$sender->sendMessage($this->plugin->formatMessage("" . $player->getName() . " Telah Di Demote Menjadi Member", true));
						$player->sendMessage($this->plugin->formatMessage("Kamu Di Demote Menjadi Member", true));
						$this->plugin->updateTag($player->getName());
					}
					
					/////////////////////////////// KICK ///////////////////////////////
					
					if($args[0] == "kick") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Gunakan /clan kick Untuk Kick Player Dari Clan"));
							return true;
						}
						if($this->plugin->isInClan($sender->getName()) == false) {
							$sender->sendMessage($this->plugin->formatMessage("Kamu Harus Berada Dalam Clan Untuk Melakukan Ini"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("Hanya Leader Yang Bisa Melakukan Ini"));
							return true;
						}
						if($this->plugin->getPlayerClan($player) != $this->$args[1]) {
							$sender->sendMessage($this->plugin->formatMessage("Player Tidak Terdaftar Di Clan Anda"));
							return true;
						}
						$kicked = $this->plugin->getServer()->getPlayer($args[1]);
						$clanName = $this->plugin->getPlayerClan($player);
						$this->plugin->db->query("DELETE FROM master WHERE player='$args[1]';");
						$sender->sendMessage($this->plugin->formatMessage("Kamu Berhasil Kick $args[1] Dari Clan", true));
						$players[] = $this->plugin->getServer()->getOnlinePlayers();
						if(in_array($args[1], $players) == true) {
							$this->plugin->getServer()->getPlayer($args[1])->sendMessage($this->plugin->formatMessage("Kamu Di Kick Dari Clan \n $clanName!, true"));
							$this->plugin->updateTag($args[1]);
							return true;
						}
					}
					
					/////////////////////////////// INFO ///////////////////////////////
					
					if(strtolower($args[0]) == 'info') {
						if(isset($args[1])) {
							if( !(ctype_alnum($args[1])) | !($this->plugin->clanExists($args[1]))) {
								$sender->sendMessage($this->plugin->formatMessage("Clan Tidak Terdaftar"));
								return true;
							}
							$clan = strtolower($args[1]);
							$leader = $this->plugin->getLeader($clan);
							$numPlayers = $this->plugin->getNumberOfPlayers($clan);
							$sender->sendMessage(TextFormat::BOLD . "-------------------------");
							$sender->sendMessage("$clan");
							$sender->sendMessage(TextFormat::BOLD . "Leader: " . TextFormat::RESET . "$leader");
							$sender->sendMessage(TextFormat::BOLD . "# of Players: " . TextFormat::RESET . "$numPlayers");
							$sender->sendMessage(TextFormat::BOLD . "MOTD: " . TextFormat::RESET . "$message");
							$sender->sendMessage(TextFormat::BOLD . "-------------------------");
						} else {
							$clan = $this->plugin->getPlayerClan(strtolower($sender->getName()));
							$result = $this->plugin->db->query("SELECT * FROM motd WHERE clan='$clan';");
							$array = $result->fetchArray(SQLITE3_ASSOC);
							$message = $array["message"];
							$leader = $this->plugin->getLeader($clan);
							$numPlayers = $this->plugin->getNumberOfPlayers($clan);
							$sender->sendMessage(TextFormat::BOLD . "-------------------------");
							$sender->sendMessage("$clan");
							$sender->sendMessage(TextFormat::BOLD . "Leader: " . TextFormat::RESET . "$leader");
							$sender->sendMessage(TextFormat::BOLD . "# of Players: " . TextFormat::RESET . "$numPlayers");
							$sender->sendMessage(TextFormat::BOLD . "MOTD: " . TextFormat::RESET . "$message");
							$sender->sendMessage(TextFormat::BOLD . "-------------------------");
						}
					}
					if(strtolower($args[0]) == "help") {
						if(!isset($args[1]) || $args[1] == 1) {
							$sender->sendMessage(TextFormat::BLUE . "ClanPro Help Page 1 of 3" . TextFormat::RED . "\n/clan about\n/clan accept\n/clan claim\n/clan create <name>\n/clan del\n/clan demote <player>\n/clan deny");
							return true;
						}
						if($args[1] == 2) {
							$sender->sendMessage(TextFormat::BLUE . "ClanPro Help Page 2 of 3" . TextFormat::RED . "\n/clan home\n/clan help <page>\n/clan info\n/clan info <clan>\n/clan invite <player>\n/clan kick <player>\n/clan leader <player>\n/clan leave");
							return true;
						} else {
							$sender->sendMessage(TextFormat::BLUE . "ClanPro Help Page 3 of 3" . TextFormat::RED . "\n/clan motd\n/clan promote <player>\n/clan sethome\n/clan unclaim\n/clan unsethome");
							return true;
						}
					}
				}
				if(count($args == 1)) {
					
					/////////////////////////////// CLAIM ///////////////////////////////
					
					if(strtolower($args[0]) == 'claim') {
						if(!$this->plugin->isInClan($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Kamu Harus Berada Dalam Clan Untuk Melakukan Ini"));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Hanya Leader Yang Dapat Melakukan Ini"));
							return true;
						}
						if($this->plugin->inOwnPlot($sender)) {
							$sender->sendMessage($this->plugin->formatMessage("Clanmu Sudah Mengambil Area Ini"));
							return true;
						}
						$x = floor($sender->getX());
						$y = floor($sender->getY());
						$z = floor($sender->getZ());
						$clan = $this->plugin->getPlayerClan($sender->getPlayer()->getName());
						if($this->plugin->drawPlot($sender, $clan, $x, $y, $z, $sender->getPlayer()->getLevel(), $this->plugin->prefs->get("PlotSize")) == false) {
							return true;
						}
						$sender->sendMessage($this->plugin->formatMessage("Plot Diambil Alih", true));
					}
					
					/////////////////////////////// UNCLAIM ///////////////////////////////
					
					if(strtolower($args[0]) == "unclaim") {
						if(!$this->plugin->isLeader($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("Hanya Leader Yang Dapat Melakukan Ini"));
							return true;
						}
						$clan = $this->plugin->getPlayerClan($sender->getName());
						$this->plugin->db->query("DELETE FROM plots WHERE clan='$clan';");
						$sender->sendMessage($this->plugin->formatMessage("Plot Dihapus", true));
					}
					
					/////////////////////////////// MOTD ///////////////////////////////
					
					if(strtolower($args[0]) == "motd") {
						if($this->plugin->isInClan($sender->getName()) == false) {
							$sender->sendMessage($this->plugin->formatMessage("Kamu Harus Berada Dalam Clan Untuk Melakukan Ini"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("Hanya Leader Yang Dapat Melakukan ini"));
							return true;
						}
						$sender->sendMessage($this->plugin->formatMessage("Type your message in chat. It will not be visible to other players", true));
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
						$stmt->bindValue(":player", strtolower($sender->getName()));
						$stmt->bindValue(":timestamp", time());
						$result = $stmt->execute();
					}
					
					/////////////////////////////// ACCEPT ///////////////////////////////
					
					if(strtolower($args[0]) == "accept") {
						$player = $sender->getName();
						$lowercaseName = strtolower($player);
						$result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("You have not been invited to any Clan"));
							return true;
						}
						$invitedTime = $array["timestamp"];
						$currentTime = time();
						if(($currentTime - $invitedTime) <= 60) { //This should be configurable
							$clan = $array["clan"];
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, clan, rank) VALUES (:player, :clan, :rank);");
							$stmt->bindValue(":player", strtolower($player));
							$stmt->bindValue(":clan", $clan);
							$stmt->bindValue(":rank", "Member");
							$result = $stmt->execute();
							$this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
							$sender->sendMessage($this->plugin->formatMessage("Kamu Berhasil Join Ke Clan $clan!", true));
							$this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player joined the Clan!", true));
							$this->plugin->updateTag($sender->getName());
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Invite has timed out!"));
							$this->plugin->db->query("DELETE * FROM confirm WHERE player='$player';");
						}
					}
					
					/////////////////////////////// DENY ///////////////////////////////
					
					if(strtolower($args[0]) == "deny") {
						$player = $sender->getName();
						$lowercaseName = strtolower($player);
						$result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("Kamu Tidak Di Invite Oleh Siapapun Ke Clan!"));
							return true;
						}
						$invitedTime = $array["timestamp"];
						$currentTime = time();
						if( ($currentTime - $invitedTime) <= 60 ) { //This should be configurable
							$this->plugin->db->query("DELETE * FROM confirm WHERE player='$lowercaseName';");
							$sender->sendMessage($this->plugin->formatMessage("Invite declined!", true));
							$this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player declined the invite!"));
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Invite has timed out!"));
							$this->plugin->db->query("DELETE * FROM confirm WHERE player='$lowercaseName';");
						}
					}
					
					/////////////////////////////// DELETE ///////////////////////////////
					
					if(strtolower($args[0]) == "del") {
						if($this->plugin->isInClan($player) == true) {
							if($this->plugin->isLeader($player)) {
								$clan = $this->plugin->getPlayerClan($player);
								$this->plugin->db->query("DELETE FROM master WHERE clan='$clan';");
								$sender->sendMessage($this->plugin->formatMessage("Clan Berhasil Dihapus", true));
								$this->plugin->updateTag($sender->getName());
							} else {
								$sender->sendMessage($this->plugin->formatMessage("Kamu Bukan Leader"));
							}
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Kamu Tidak Berada Dalam Clan"));
						}
					}
					
					/////////////////////////////// LEAVE ///////////////////////////////
					
					if(strtolower($args[0] == "leave")) {
						if($this->plugin->isLeader($player) == false) {
							$remove = $sender->getPlayer()->getNameTag();
							$clan = $this->plugin->getPlayerClan($player);
							$name = $sender->getName();
							$this->plugin->db->query("DELETE FROM master WHERE player='$name';");
							$sender->sendMessage($this->plugin->formatMessage("Kamu Berhasil Keluar Dari Clan $clan", true));
							$this->plugin->updateTag($sender->getName());
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Kamu Harus Menghapus Atau Memberikan\nJabatan Clan Dulu!"));
						}
					}
					
					/////////////////////////////// SETHOME ///////////////////////////////
					
					if(strtolower($args[0] == "sethome")) {
						if(!$this->plugin->isInClan($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Kamu Harus Berada Dalam Clan Untuk Melakukan Ini"));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Hanya Leader Yang Dapat Melakukan Ini"));
							return true;
						}
						$clanName = $this->plugin->getPlayerClan($sender->getName());
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO home (clan, x, y, z) VALUES (:clan, :x, :y, :z);");
						$stmt->bindValue(":clan", $clanName);
						$stmt->bindValue(":x", $sender->getX());
						$stmt->bindValue(":y", $sender->getY());
						$stmt->bindValue(":z", $sender->getZ());
						$result = $stmt->execute();
						$sender->sendMessage($this->plugin->formatMessage("Home updated!", true));
					}
					
					/////////////////////////////// UNSETHOME ///////////////////////////////
						
					if(strtolower($args[0] == "unsethome")) {
						if(!$this->plugin->isInClan($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Kamu Harus Berada Dalam Clan Untuk Melakukan Ini"));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Hanya Leader Yang Dapat Melakukan Ini"));
							return true;
						}
						$clan = $this->plugin->getPlayerClan($sender->getName());
						$this->plugin->db->query("DELETE FROM home WHERE Clan = '$clan';");
						$sender->sendMessage($this->plugin->formatMessage("Home unset!", true));
					}
					
					/////////////////////////////// HOME ///////////////////////////////
						
					if(strtolower($args[0] == "home")) {
						if(!$this->plugin->isInClan($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Kamu Harus Berada Dalam Clan Untuk Melakukan Ini"));
						}
						$clan = $this->plugin->getPlayerClan($sender->getName());
						$result = $this->plugin->db->query("SELECT * FROM home WHERE Clan = '$clan';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(!empty($array)) {
							$sender->getPlayer()->teleport(new Vector3($array['x'], $array['y'], $array['z']));
							$sender->sendMessage($this->plugin->formatMessage("Teleported home.", true));
							return true;
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Home is not set."));
							}
						}
					
					/////////////////////////////// ABOUT ///////////////////////////////
					
					if(strtolower($args[0] == 'about')) {
						$sender->sendMessage(TextFormat::BLUE . "ClanPro v6.9 by " . TextFormat::BOLD . "Fahmi\n" . TextFormat::RESET . TextFormat::BLUE . "Twitter: " . TextFormat::ITALIC . "@Fahmi_KillerZZZ");
					}
				}
			}
		} else {
			$this->plugin->getServer()->getLogger()->info($this->plugin->formatMessage("Please run command in game"));
		}
	}
}
