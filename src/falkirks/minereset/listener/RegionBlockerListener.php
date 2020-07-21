<?php
namespace falkirks\minereset\listener;


use falkirks\minereset\Mine;
use falkirks\minereset\MineReset;
use falkirks\simplewarp\SimpleWarp;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class RegionBlockerListener implements Listener {
    /** @var  MineReset */
    private $api;

    /**
     * RegionBlockerListener constructor.
     * @param MineReset $api
     */
    public function __construct(MineReset $api){
        $this->api = $api;
    }

    public function teleportPlayer(Player $player, Mine $mine){

        $swarp = $this->getApi()->getServer()->getPluginManager()->getPlugin('SimpleWarp');
        if($mine->hasWarp() && $swarp instanceof SimpleWarp){
            $swarp->getApi()->warpPlayerTo($player, $mine->getWarpName());
        } else {
            $player->teleport($player->getLevel()->getSafeSpawn($player->getPosition()));
        }
    }


    public function clearMine(string $mineName){
        /** @var Mine $mine */
        $mine = $this->getApi()->getMineManager()[$mineName];
        if($mine !== null){
            foreach ($this->getApi()->getServer()->getOnlinePlayers() as $player){
                if($mine->isPointInside($player->getPosition())){
                    $this->teleportPlayer($player, $mine);
                    $player->sendMessage("Bạn được dịch chuyển đi vì khu mine đang reset.");
                }
            }
        }
    }

    /**
     * @priority HIGH
     *
     * @param BlockPlaceEvent $event
     */
    public function onBlockPlace(BlockPlaceEvent $event){

        $mine = $this->getResettingMineAtPosition($event->getBlock());
        if($mine != null){
            $event->getPlayer()->sendMessage(TextFormat::RED . "Khu mine này đang reset, bạn không thể đặt block được!" . TextFormat::RESET);
            $event->setCancelled();
        }
    }

    /**
     * @priority HIGH
     *
     * @param BlockBreakEvent $event
     */
    public function onBlockDestroy(BlockBreakEvent $event){

        $mine = $this->getResettingMineAtPosition($event->getBlock());
        if($mine != null){
            $event->getPlayer()->sendMessage(TextFormat::RED . "Khu mine này đang reset, bạn không thể phá block được!" . TextFormat::RESET);
            $event->setCancelled();
        }
    }

    private function getResettingMineAtPosition(Position $position){
        foreach ($this->getApi()->getMineManager() as $mine) {
            if($mine->isResetting() && $mine->isPointInside($position)){
                return $mine;
            }
        }
        return null;
    }

    /**
     * @return MineReset
     */
    public function getApi(): MineReset{
        return $this->api;
    }


}