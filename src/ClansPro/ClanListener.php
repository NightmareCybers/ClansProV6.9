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
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\block\BlockPlaceEvent;


class ClanListener implements Listener {
	
	public $plugin;
	
	public function __construct(ClanMain $pg) {
		$this->plugin = $pg;
	}
	
	public function clanChat(PlayerChatEvent $PCE) {
		
		$player = strtolower($PCE->getPlayer()->getName());
		//MOTD Check
		//TODO Use arrays instead of database for faster chatting?
		
		if($this->plugin->motdWaiting($player)) {
			if(time() - $this->plugin->getMOTDTime($player) > 30) {
				$PCE->getPlayer()->sendMessage($this->plugin->formatMessage("Timed out. Please use /f motd again."));
				$this->plugin->db->query("DELETE FROM motdrcv WHERE player='$player';");
				$PCE->setCancelled(true);
				return true;
			} else {
				$motd = $PCE->getMessage();
				$clan = $this->plugin->getPlayerClan($player);
				$this->plugin->setMOTD($clan, $player, $motd);
				$PCE->setCancelled(true);
				$PCE->getPlayer()->sendMessage($this->plugin->formatMessage("Successfully updated Clan message of the day!", true));
			}
			return true;
		}
		
		//Member
		if($this->plugin->isInClan($PCE->getPlayer()->getName()) && $this->plugin->isMember($PCE->getPlayer()->getName())) {
			$message = $PCE->getMessage();
			$player = $PCE->getPlayer()->getName();
			$clan = $this->plugin->getPlayerClan($player);
			
			$PCE->setFormat("[$clan] $player: $message");
			return true;
		}
		//CoLeader
		elseif($this->plugin->isInClan($PCE->getPlayer()->getName()) && $this->plugin->isCoLeader($PCE->getPlayer()->getName())) {
			$message = $PCE->getMessage();
			$player = $PCE->getPlayer()->getName();
			$clan = $this->plugin->getPlayerClan($player);
			
			$PCE->setFormat("*[$clan] $player: $message");
			return true;
		}
		//Leader
		elseif($this->plugin->isInClan($PCE->getPlayer()->getName()) && $this->plugin->isLeader($PCE->getPlayer()->getName())) {
			$message = $PCE->getMessage();
			$player = $PCE->getPlayer()->getName();
			$clan = $this->plugin->getPlayerClan($player);
			$PCE->setFormat("**[$clan] $player: $message");
			return true;
		//Not in clan
		}else {
			$message = $PCE->getMessage();
			$player = $PCE->getPlayer()->getName();
			$PCE->setFormat("$player: $message");
		}
	}
	
	public function clanPVP(EntityDamageEvent $clanDamage) {
		if($clanDamage instanceof EntityDamageByEntityEvent) {
			if(!($clanDamage->getEntity() instanceof Player) or !($clanDamage->getDamager() instanceof Player)) {
				return true;
			}
			if(($this->plugin->isInClan($clanDamage->getEntity()->getPlayer()->getName()) == false) or ($this->plugin->isInClan($clanDamage->getDamager()->getPlayer()->getName()) == false) ) {
				return true;
			}
			if(($clanDamage->getEntity() instanceof Player) and ($clanDamage->getDamager() instanceof Player)) {
				$player1 = $clanDamage->getEntity()->getPlayer()->getName();
				$player2 = $clanDamage->getDamager()->getPlayer()->getName();
				if($this->plugin->sameClan($player1, $player2) == true) {
					$clanDamage->setCancelled(true);
				}
			}
		}
	}
	public function clanBlockBreakProtect(BlockBreakEvent $event) {
		if($this->plugin->isInPlot($event->getPlayer())) {
			if($this->plugin->inOwnPlot($event->getPlayer())) {
				return true;
			} else {
				$event->setCancelled(true);
				$event->getPlayer()->sendMessage($this->plugin->formatMessage("You cannot break blocks here."));
				return true;
			}
		}
	}
	
	public function clanBlockPlaceProtect(BlockPlaceEvent $event) {
		if($this->plugin->isInPlot($event->getPlayer())) {
			if($this->plugin->inOwnPlot($event->getPlayer())) {
				return true;
			} else {
				$event->setCancelled(true);
				$event->getPlayer()->sendMessage($this->plugin->formatMessage("You cannot place blocks here."));
				return true;
			}
		}
	}
	
	public function onPlayerJoin(PlayerJoinEvent $event) {
		$this->plugin->updateTag($event->getPlayer()->getName());
	}
}
