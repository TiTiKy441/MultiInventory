<?
namespace Plugin\Plugin;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerGameModeChangeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\permission\PermissionManager;
use pocketmine\permission\Permission;
use pocketmine\item\LegacyStringToItemParser;
use Plugin\Plugin\InventoriesAPI;
use Ramsey\Uuid\UuidInterface;

use ErrorException;

class MainListener implements Listener{

  private Permission $permission;

  private InventoriesAPI $api;

	public function __construct(private Main $plugin){
    $this->permission = PermissionManager::getInstance()->getPermission($plugin->config->get("permission"));
    $this->api = InventoriesAPI::getInstance();
  }

	/**
	 * @param PlayerGameModeChangeEvent $event
	 *
	 * @priority LOW
	 */
	public function onGamemodeChanged(PlayerGameModeChangeEvent $event) : void{
    $player = $event->getPlayer();
    if ($event->isCancelled() || !$player->hasPermission($this->permission)) return;

    $this->api->saveItemsToSlot($player, $this->getInventorySlot($player->getGamemode()));
    $this->api->loadItemsFromSlot($player, $this->getInventorySlot($event->getNewGamemode()));

	}

  /**
	 * @param PlayerJoinEvent $event
	 *
	 * @priority LOW
	 */
  public function onPlayerJoin(PlayerJoinEvent $event) : void{
    $player = $event->getPlayer();
    if(!$player->hasPermission($this->permission)) return;
    if ($this->api->existsItems($player)){
      $this->api->loadItemsFromSlot($player, $this->getInventorySlot($player->getGamemode()));
    }else{
      $this->api->createNewRow($player);
    }
  }

  /**
	 * @param PlayerQuitEvent $event
	 *
	 * @priority LOW
	 */
  public function onPlayerQuit(PlayerQuitEvent $event) : void{
    $player = $event->getPlayer();
    if(!$player->hasPermission($this->permission)) return;
    $this->api->saveItemsToSlot($player, $this->getInventorySlot($player->getGamemode()));
  }

  /**
   * @param PlayerDeathEvent $event
   * 
   * @priority LOW
   */

  public function onPlayerDeath(PlayerDeathEvent $event) : void{
    $player = $event->getPlayer();
    if(!$player->hasPermission($this->permission) || $this->plugin->config->get("clearondeath")) return;
    $this->api->clearInventoryInSlot($player, $this->getInventorySlot($player->getGamemode()));
  }

  /**
  * Returns inventory slot for specified player gamemode
  *
  * @param GameMode $gm
  *
  * @return int slot
  **/

  public function getInventorySlot(GameMode $gm) : int{
    switch ($gm) {
      case GameMode::SURVIVAL():
        return $this->plugin->config->get("survival");

      case GameMode::CREATIVE():
        return $this->plugin->config->get("creative");

      case GameMode::ADVENTURE():
        return $this->plugin->config->get("adventure");

      case GameMode::SPECTATOR():
        return $this->plugin->config->get("spectator");

      default:
        throw new ErrorException("Undefined gamemode passed to getInventorySlot.");
    }
  }
}
