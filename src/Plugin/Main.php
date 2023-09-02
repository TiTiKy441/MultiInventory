<?php

namespace Plugin\Plugin;

use pocketmine\command\Command;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

use ErrorException;
use SQLite3;

class Main extends PluginBase{

  public static Main $instance;

  public Config $config;

	public function onLoad() : void{
    @mkdir($this->getDataFolder());
    $this->saveResource("config.yml");
    $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
    if (!$this->config->__isset("permission")){
      $this->getLogger()->info('Config set to defaults.');
      $this->config->set("permission", "multiinventory.multiinventory.enable");
      $this->config->set("clearondeath", 1);
      $this->config->set("survival", 0);
      $this->config->set("creative", 1);
      $this->config->set("adventure", 2);
      $this->config->set("spectator", 3);
    }
    Main::$instance = $this;
    $this->config->save();
	}

	public function onEnable() : void{
    $this->getServer()->getPluginManager()->registerEvents(new MainListener($this), $this);
    $this->getLogger()->info('Loaded: ' . InventoriesAPI::getInstance()->getCount());
	}

	public function onDisable() : void{
    $this->config->save();
    InventoriesAPI::getInstance()->close();
	}
}
