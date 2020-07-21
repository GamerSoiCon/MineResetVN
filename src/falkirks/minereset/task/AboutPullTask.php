<?php
namespace falkirks\minereset\task;


use pocketmine\command\CommandSender;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Internet;
use pocketmine\utils\Utils;

class AboutPullTask extends AsyncTask {

    const ABOUT_URL = "http://falkirks.com/pmabout.txt";

    /**
     * AboutPullTask constructor.
     * @param CommandSender $sender
     */
    public function __construct(CommandSender $sender){
        $this->storeLocal($sender);
    }


    public function onRun() : void {
        if(method_exists(Utils::class, "getURL")){
            $this->setResult(Utils::getURL(AboutPullTask::ABOUT_URL));
        }
        else {
            $this->setResult(Internet::getURL(AboutPullTask::ABOUT_URL));
        }

    }

    public function onCompletion(Server $server = null) : void {
        $sender = $this->fetchLocal();
        if($sender instanceof CommandSender) {
            $result = $this->getResult();
            if ($result !== false) {
                $sender->sendMessage($this->getResult());
            } else {
                $sender->sendMessage("MineReset viết bởi Falkirks. Đây là plugin tuyệt vời để làm Prison.");
            }
        }
    }


}
