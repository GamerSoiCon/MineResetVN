<?php
namespace falkirks\minereset;


use falkirks\minereset\exception\JsonFieldMissingException;
use falkirks\minereset\store\ConfigStore;
use falkirks\minereset\store\DataStore;
use falkirks\minereset\store\Reloadable;
use falkirks\minereset\store\Saveable;
use pocketmine\math\Vector3;
use pocketmine\utils\Color;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class MineManager implements \ArrayAccess, \IteratorAggregate, \Countable {
    const MEMORY_TILL_CLOSE = 0;
    const FLUSH_ON_CHANGE = 1;

    /** @var MineReset  */
    private $api;
    /** @var DataStore  */
    private $store;
    /** @var  Mine[] */
    private $mines;
    private $flag;

    public function __construct(MineReset $api, DataStore $store, $flag = MineManager::FLUSH_ON_CHANGE){
        $this->api = $api;
        $this->store = $store;
        $this->flag = $flag;
        $this->mines = [];
        if(file_exists($api->getDataFolder() . "mines.yml")){
            rename($api->getDataFolder() . "mines.yml", $api->getDataFolder() . "_minesOLD.yml");
            $api->getLogger()->info("MineReset đang nâng cấp file config mine.yml cũ của bạn lên phiên bản mới nhất");
            $api->getLogger()->info("Một bản sao lưu của tập tin cũ của bạn sẽ được đặt tại " . TextFormat::BLACK . "_minesOLD.yml" . TextFormat::RESET);
            $this->triggerConfigUpdate();

        }
        if($this->flag < 2){
            $this->mines = $this->loadMines();
        }
    }

    private function triggerConfigUpdate(){
        $store = new ConfigStore(new Config($this->getApi()->getDataFolder() . "_minesOLD.yml", Config::YAML));
        foreach($store->getIterator() as $name => $data){
            $this->mines[$name] = $this->mineFromData($name, $data);
        }
        foreach($this->mines as $mine){
            $this->store->add($mine->getName(), $mine);
        }
        $this->saveStore(true);
    }
    /**
     * @deprecated
     */
    protected function reloadStore(){
        if($this->flag >= 2 && $this->store instanceof Reloadable){
            $this->store->reload();
        }
    }
    protected function saveStore($force = false){
        if(($this->flag > 0 || $force) && $this->store instanceof Saveable){
            $this->store->save();
        }
    }
    protected function loadMines(): array{
        $out = [];
        foreach($this->store->getIterator() as $name => $data){
            try {
                $out[$name] = Mine::fromJson($this, $data, $name);
            }
            catch(JsonFieldMissingException $e){
                $this->getApi()->getLogger()->warning("Khu mine tên " . $name . " đang bị thiếu dữ liệu so với file đã lưu.");
            }
            //$out[$name] = $this->mineFromData($name, $data);
        }
        return $out;
    }

    /**
     * WARNING
     * This function is for internal use only.
     */
    public function saveAll(){
        if($this->flag === 0){
            $this->store->clear();
            foreach($this->mines as $mine){
                $this->store->add($mine->getName(), $mine);
            }
            $this->saveStore(true);
        }
    }
    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset){
        return isset($this->mines[$offset]);
    }
    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset){
        return $this->mines[$offset] ?? null;
    }
    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     */
    public function offsetSet($offset, $value){
        if($value instanceof Mine && $value->getName() === $offset) {

            if(isset($this->mines[$offset]) && $value !== $this->mines[$offset] && $this->mines[$offset] instanceof Mine){
                $this->mines[$offset]->destroy();
            }

            $this->mines[$offset] = $value;
            if ($this->flag === 1) {
                $this->store->add($offset, $value);
                $this->saveStore();
            }
        }
        else{
            throw new \RuntimeException("\$offset không hợp lệ cho dữ liệu khu mine.");
        }
    }
    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     */
    public function offsetUnset($offset){
        if(isset($this->mines[$offset])) {
            if ($this->mines[$offset] instanceof Mine) {
                $this->mines[$offset]->destroy();
            }
            unset($this->mines[$offset]);
            if ($this->flag === 1) {
                $this->store->remove($offset);
                $this->saveStore();
            }
        }
    }
    /**
     * This method requires the key of the warp in order
     * to construct a mine object
     * @param $name
     * @param array $array
     * @return Mine
     * @throws \Exception
     * @DEPRECATED
     */
    protected function mineFromData($name, array $array){
        if(count($array) === 9 || count($array) === 8) {
            if(!$this->getApi()->getServer()->isLevelLoaded($array[7])){
                $this->api->getLogger()->warning("Khu mine tên " . TextFormat::AQUA . $name . TextFormat::RESET . " không thể reset do world chưa được load.");
            }
            return new Mine($this,
                new Vector3(min($array[0], $array[1]), min($array[2], $array[3]), min($array[4], $array[5])),
                new Vector3(max($array[0], $array[1]), max($array[2], $array[3]), max($array[4], $array[5])),
                $array[7],
                $name,
                (is_array($array[6]) ? $array[6] : []),
                $array[8] ?? -1);
        }
        $this->api->getLogger()->critical("Khu mine tên " . TextFormat::AQUA . $name . TextFormat::RESET . " chưa được reset xong. Nó sẽ tự động xóa khi bạn tắt máy chủ.");
        return null;
    }
    /**
     * In order to pass data to a DataStore
     * a key is needed. Typically one should
     * use $warp->getName()
     * @param Mine $mine
     * @return array
     * @DEPRECATED
     */
    protected function mineToData(Mine $mine){
        return  [
            $mine->getPointA()->getX(),
            $mine->getPointB()->getX(),
            $mine->getPointA()->getY(),
            $mine->getPointB()->getY(),
            $mine->getPointA()->getZ(),
            $mine->getPointB()->getZ(),
            (count($mine->getData()) > 0 ? $mine->getData() : false),
            $mine->getLevelName(),
            $mine->getResetInterval()
        ];
    }
    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return \Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     */
    public function getIterator(){
        return new \ArrayIterator($this->mines);
    }

    public function count(){
        return count($this->mines);
    }


    /**
     * Returns the current storage-mode
     * #####
     *  MEMORY_TILL_CLOSE = 0
     * Mines are loaded into memory when the server starts and are
     * held there until the server closes. When the server closes
     * they are converted back into YAML. This new YAML will
     * replace mines.yml, this means that changes are lost and
     * warps which fail to load are discarded.
     *
     *
     * FLUSH_ON_CHANGE = 1
     * Mines are loaded into memory when the server starts. Whenever a
     * mine is updated, it will be updated in the mines.yml. When the
     * server closes, the mines file is NOT overwritten.
     *
     * NO_MEMORY_STORE = 2
     * THIS IS NOT SUPPORTED
     * ####
     * @return int
     */
    public function getFlag(): int{
        return $this->flag;
    }
    /**
     * returns the current data store
     * @return DataStore
     */
    public function getStore(): DataStore{
        return $this->store;
    }
    /**
     * Injects a new DataStore for warps
     * ! This will inject your code into MineReset, potentially breaking!
     * @param DataStore $store
     */
    public function setStore(DataStore $store){
        $this->saveAll();
        $this->store = $store;
        $this->mines = $this->loadMines();
    }

    /**
     * @return MineReset
     */
    public function getApi(): MineReset{
        return $this->api;
    }

    /**
     * @return Mine[]
     */
    public function getMines(): array{
        return $this->mines;
    }
}