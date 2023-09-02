<?

namespace Plugin\Plugin;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\Server;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\SimpleInventory;
use pocketmine\item\Item;
use pocketmine\nbt\JsonNbtParser;
use pocketmine\utils\SingletonTrait;

use SQLite3;
use array_push;
use array_map;
use array_merge;
use array_fill;
use implode;
use explode;
use serialize;
use unserialize;

/**
 * InventoriesAPI, class for loading and saving inventories
 *
 * NOTE: API uses player UUID(or XUID if signed in) or Names, depends on USE_NAMEKEY const
 *
 * @version    Release: 1 for PM5 (5.4.0)
 * @author     TiTiKy
 */

class InventoriesAPI{
  use SingletonTrait;

  // Amount of inventories slots created by default, can not be explanded after DB is created
  // Slots - different inventories for a player, from 0 to (SLOTS-1)
  public const SLOTS = 4;
  // NOTE: DO NOT EDIT
  public const ITEM_SEPARATOR = "΋"; // U+0038B
  public const INFO_SEPARATOR = "΍"; // U+0038D

  // false = use player uuid(or XUID if signed in) as a key
  // true = use player name as a key
  public const USE_NAMEKEY = true;

  private static string $dbPath;
  private SQLite3 $db;

  private function __construct(){
    if (!file_exists(Server::getInstance()->getDataPath() . '\plugin_data\Inventories')) mkdir(Server::getInstance()->getDataPath() . '\plugin_data\Inventories');
    InventoriesAPI::$dbPath = Server::getInstance()->getDataPath() . '\plugin_data\Inventories\database.db';
    $this->db = new SQLite3(InventoriesAPI::$dbPath, SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
    $req = 'CREATE TABLE IF NOT EXISTS "inventories" (
      "key" TEXT PRIMARY KEY NOT NULL, ';
    for ($i = 0; $i < InventoriesAPI::SLOTS ; $i++) {
      if ($i != (InventoriesAPI::SLOTS - 1)){
        $req .= '"' . $i . '" BLOB, ';
      }else{
        $req .= '"' . $i . '" BLOB';
      }
    }
    $req .= ')';
    $this->db->query($req);
  }

  public function __destruct(){
    $this->close();
  }

  public function close() : void{
    $this->db->close();
  }

  /**
  * Returns amount of existsing inventories
  *
  * @return int
  **/
  public function getCount() : int{
    return $this->db->query('SELECT COUNT(*) FROM inventories')->fetchArray()[0];
  }

  /**
   * Returns path to current db file
   *
   * @return string
   **/
  public function getDBPath() : string{
    return InventoriesAPI::$dbPath;
  }

  /**
   * Returns array of items for specified player in slot
   *
   * @param Player $player
   * @param int $slot
   * @return array Item[]
   **/
  public function getItems(Player $player, int $slot) : array{
    if (!$this->existsItems($player)){
      $this->createNewRow($player);
      return [];
    }
    return $this->stringToItems($this->getItemsAsString($player, $slot));
  }

  /**
  * Returns all items stored in $slot for $player as string
  *
  * @param Player $player
  * @param int $slot
  **/
  public function getItemsAsString(Player $player, int $slot) : string{
    $db = $this->db;
    $id = $this->getKey($player);
    // No prepared statements... Yea...
    return $db->query('SELECT "' . $slot . '" FROM inventories WHERE "key" = ' . $id)->fetchArray()[0];
  }

  /**
  * Sets Player items in slot
  *
  * NOTE: first 5 elements (-5, -4, -3, -2, -1 slots in local indexation) are reserved for player armor slots and offhand slot
  *
  * @param Player $player
  * @param int $slot
  * @param array $content
  **/
  public function setItems(Player $player, int $slot, array $content) : void{
    $db = $this->db;
    $id = $this->getKey($player);
    if (!$this->existsItems($player)){
      $this->createNewRow($player);
    }
    $prep = $db->prepare('UPDATE inventories SET "' . $slot . '" = :content WHERE "key" = ' . $id);
    $prep->bindValue(':content', $this->itemsToString($content), SQLITE3_BLOB);
    $prep->execute();
  }

  /**
  * Does Player have a row in database
  *
  * @param Player $player
  * @return bool
  **/
  public function existsItems(Player $player) : bool{
    $db = $this->db;
    $id = $this->getKey($player);
    return $db->query('SELECT COUNT (*) FROM inventories WHERE "key" = ' . $id)->fetchArray()[0] == 1;
  }

  /**
  * Creates new row in db for new player
  *
  * @param Player $player
  **/
  public function createNewRow(Player $player) : void{
    $db = $this->db;
    $id = $this->getKey($player);
    $prep = $db->exec('INSERT INTO inventories ("key", ' . $this->getSlotsAsString() . ') VALUES (' . $id . ',' . $this->getDefaultSlots() . ')');
  }

  // PMMP5 items does not support json_encode, serialize (for example types like trees does not support serialization) and other things
  // So I was forced to create my own serializator with inventory slot index
  // index . count . ctag
  // NOTE: First 5 elements of $items should be reserved to armor and offhand item
  private function itemsToString(array $items) : string{
    $ret = '';
    $i = -5;
    foreach ($items as $item) {
      if ($item->getCount() == 0){
        $i++;
        continue;
      }
      $nbtSerialized = serialize($item->nbtSerialize());
      if(substr_count($nbtSerialized, InventoriesAPI::INFO_SEPARATOR) > 0 || substr_count($nbtSerialized, InventoriesAPI::ITEM_SEPARATOR) > 0){
        Server::getInstance()->getLogger()->warning("Failed to convert item to string, invalid characters found");
        continue;
      }
      $ret .= $i . InventoriesAPI::INFO_SEPARATOR . $item->getCount() . InventoriesAPI::INFO_SEPARATOR . serialize($item->nbtSerialize()) . InventoriesAPI::ITEM_SEPARATOR;
      $i++;
    }
    return $ret;
  }

  private function stringToItems(string $itemsStr) : array{
      $items = [];
      //$item;
      $itemStr = explode(InventoriesAPI::ITEM_SEPARATOR, $itemsStr);
      foreach ($itemStr as $itemInfo) {
        if ($itemInfo == "") continue;
        $elements = explode(InventoriesAPI::INFO_SEPARATOR, $itemInfo);
        //$index = $elements[0];
        $count = $elements[1];
        $ctag = unserialize($elements[2]);
        $item = Item::nbtDeserialize($ctag);
        $item->setCount($count);
        array_push($items, $item);
      }
      return $items;
  }

  /**
  * Load inventory from slot to player inventory
  *
  * @param Player $player
  * @param int $slot
  **/
  public function loadItemsFromSlot(Player $player, int $slot) : void{
    $itemsStr = $this->getItemsAsString($player, $slot);
    InventoriesAPI::clearPlayerInventory($player);
    //$item;
    $itemStr = explode(InventoriesAPI::ITEM_SEPARATOR, $itemsStr);
    foreach ($itemStr as $itemInfo) {
      if(substr_count($itemInfo, InventoriesAPI::ITEM_SEPARATOR) > 0){
        Server::getInstance()->getLogger()->warning("Corrupted item found! (" . $player->getName() . ")");
        continue;
      }
      if ($itemInfo == "") continue;
      
      $elements = explode(InventoriesAPI::INFO_SEPARATOR, $itemInfo);
      $index = $elements[0];
      $count = $elements[1];
      $ctag = unserialize($elements[2]);
      $item = Item::nbtDeserialize($ctag);
      $item->setCount($count);
      // 4 slots are Armor slots, their ids: -4 -3 -2 -1
      // -5 slot is for offhand inventory
      if ($index < 0){
        if ($index == -5){
          $player->getOffHandInventory()->setItem(0, $item);
          continue;
        }
        $armorInv = $player->getArmorInventory();
        $armorInv->setItem($index + 5, $item);
      }else{
        $player->getInventory()->setItem($index, $item);
      }
    }
  }

  /**
  * Saves current player inventory to the specified slot
  *
  * @param Player $player
  * @param int $slot
  **/
  public function saveItemsToSlot(Player $player, int $slot) : void{
    $this->setItems($player, $slot, array_merge($player->getOffHandInventory()->getContents(true), $player->getArmorInventory()->getContents(true), $player->getInventory()->getContents(true)));
  }

  public function clearInventoryInSlot(Player $player, int $slot) : void{
    $this->setItems($player, $slot, []);
  }

  public static function clearPlayerInventory(Player $player){
    $player->getInventory()->clearAll();
    $player->getArmorInventory()->clearAll();
    $player->getOffHandInventory()->clearAll();
  }

  private function getSlotsAsString() : string{
    return implode(",", array_map(fn($value) : string => '"' . $value . '"', range(0, InventoriesAPI::SLOTS-1)));
  }

  private function getDefaultSlots() : string{
    return implode(",", array_fill(0, InventoriesAPI::SLOTS, '""'));
  }

  private function getKey(Player $player){
    if (InventoriesAPI::USE_NAMEKEY){
      return '"' . $player->getName() . '"';
    }elseif ($player->getXuid() !== ""){
      return '"' . $player->getXuid() . '"';
    }
    return '"' . $player->getUniqueId()->getInteger() . '"';
  }
}
