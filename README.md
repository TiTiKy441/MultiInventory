# MultiInventory
 Plugin for PocketMine-MP (5) for switching inventories when changing gamemodes.
 By default creates 4 slots for different gamemodes and switches between them when gamemode is changed

## Status
In development, currently beta, may contain bugs
#### Latest version 
BETA 1.0.0

## Requirements
- PocketMine-MP 5

## Permissions
1. multiinventory.multiinventory.enable
   * description: Player with this permission have multiple inventories in different gamemodes
   * default: yes

## Setup
### plugin_data/MultiInventory/config.yml
 - permission (default multiinventory.multiinventory.enable) permission for switching inventories
 - clearondeath (default 1) if set to anything, except 0 will clear inventories on death in all gamemodes
 - survival (default 0) slot for survival inventory
 - creative (default 1) slot for creative inventory
 - adventure (default 2) slot for adventure inventory
 - spectator (default 3) slot for spectator inventory

## Database
### plugin_data/Inventories/database.db
 SQLite3 Database with table "inventories"

