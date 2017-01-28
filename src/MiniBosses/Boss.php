<?php

namespace MiniBosses;

use pocketmine\entity\Creature;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\event\Timings;
use pocketmine\Player;
use pocketmine\entity\Entity;
use pocketmine\utils\UUID;
use pocketmine\math\Math;
use pocketmine\math\Vector3;

class Boss extends Creature {
	
	const NETWORK_ID = 1000;
	public $networkId = 32;
	public $target;
	public $spawnPos;
	public $attackDamage = 1;
	public $attackRate = 10;
	public $attackDelay = 0;
	public $speed;
	public $drops;
	public $respawnTime;
	public $skin;
	public $heldItem;
	public $range;
	public $knockbackTicks = 0;
	public $plugin;
	public $isJumping = false;
	public $scale;
	
	public function __construct($chunk, $nbt) {
		parent::__construct($chunk, $nbt);
		$this->networkId = $this->namedtag["networkId"];
		$this->range = $this->namedtag["range"];
		$this->spawnPos = new Position($this->namedtag["spawnPos"][0], $this->namedtag["spawnPos"][1], $this->namedtag["spawnPos"][2], $this->level);
		$this->attackDamage = $this->namedtag["attackDamage"];
		$this->attackRate = $this->namedtag["attackRate"];
		$this->speed = $this->namedtag["speed"];
		$this->scale = $this->namedtag["scale"];
		foreach(explode(' ', $this->namedtag["drops"]) as $item) {
			$item = explode(';', $item);
			$this->drops[] = Item::get($item[0], isset($item[1]) ? $item[1] : 0, isset($item[2]) ? $item[2] : 1, isset($item[3]) ? $item[3] : "");
		}
		$this->respawnTime = $this->namedtag["respawnTime"];
		$this->skin = $this->namedtag["skin"];
		$heldItem = explode(';', $this->namedtag["heldItem"]);
		$this->heldItem = Item::get($heldItem[0], isset($heldItem[1]) ? $heldItem[1] : 0, isset($heldItem[2]) ? $heldItem[2] : 1, isset($heldItem[3]) ? $heldItem[3] : "");
	}
	
	public function initEntity() {
		$this->plugin = $this->server->getPluginManager()->getPlugin("MiniBosses");
		parent::initEntity();
		$this->dataProperties[self::DATA_FLAG_NO_AI] = [self::DATA_TYPE_BYTE, 1];
		$this->dataProperties[self::DATA_SCALE] = [self::DATA_TYPE_INT, $this->scale];
		if(isset($this->namedtag->maxHealth)) {
			parent::setMaxHealth($this->namedtag["maxHealth"]);
			$this->setHealth($this->namedtag["maxHealth"]);
		} else {
			$this->setMaxHealth(20);
			$this->setHealth(20);
		}
	}
	
	public function setMaxHealth($health) {
		$this->namedtag->maxHealth = new IntTag("maxHealth", $health);
		parent::setMaxHealth($health);
	}
	
	public function spawnTo(Player $player) {
		parent::spawnTo($player);
		if($this->networkId === 63) {
			$pk = new AddPlayerPacket();
			$pk->uuid = UUID::fromData($this->getId(), $this->skin, $this->getNameTag());
			$pk->username = $this->getName();
			$pk->eid = $this->getId();
			$pk->x = $this->x;
			$pk->y = $this->y;
			$pk->z = $this->z;
			$pk->speedX = $this->motionX;
			$pk->speedY = $this->motionY;
			$pk->speedZ = $this->motionZ;
			$pk->yaw = $this->yaw;
			$pk->pitch = $this->pitch;
			$pk->item = $this->heldItem;
			$pk->metadata = $this->dataProperties;
			$player->dataPacket($pk);
		} else {
			$pk = new AddEntityPacket();
			$pk->eid = $this->getID();
			$pk->type = $this->networkId;
			$pk->x = $this->x;
			$pk->y = $this->y;
			$pk->z = $this->z;
			$pk->speedX = 0;
			$pk->speedY = 0;
			$pk->speedZ = 0;
			$pk->yaw = $this->yaw;
			$pk->pitch = $this->pitch;
			$pk->metadata = $this->dataProperties;
			$player->dataPacket($pk);
		}
	}
	
	public function getName() {
		return $this->getNameTag();
	}
	
	public function saveNBT() {
		parent::saveNBT();
		$this->namedtag->maxHealth = new IntTag("maxHealth", $this->getMaxHealth());
		$this->namedtag->spawnPos = new ListTag("spawnPos", [
			new DoubleTag("", $this->spawnPos->x),
			new DoubleTag("", $this->spawnPos->y),
			new DoubleTag("", $this->spawnPos->z)
		]);
		$this->namedtag->range = new FloatTag("range", $this->range);
		$this->namedtag->attackDamage = new FloatTag("attackDamage", $this->attackDamage);
		$this->namedtag->networkId = new IntTag("networkId", $this->networkId);
		$this->namedtag->attackRate = new IntTag("attackRate", $this->attackRate);
		$this->namedtag->speed = new FloatTag("speed", $this->speed);
		$drops2 = [];
		foreach($this->drops as $drop)
			$drops2[] = $drop->getId() . ";" . $drop->getDamage() . ";" . $drop->getCount() . ";" . $drop->getCompoundTag();
		$this->namedtag->drops = new StringTag("drops", implode(' ', $drops2));
		$this->namedtag->respawnTime = new IntTag("respawnTime", $this->respawnTime);
		$this->namedtag->skin = new StringTag("skin", $this->skin);
		$this->namedtag->heldItem = new StringTag("heldItem", ($this->heldItem instanceof Item ? $this->heldItem->getId() . ";" . $this->heldItem->getDamage() . ";" . $this->heldItem->getCount() . ";" . $this->heldItem->getCompoundTag() : ""));
	}
	
	public function isInsideOfSolid(){
		$block = $this->level->getBlock($this->temporalVector->setComponents(Math::floorFloat($this->x), Math::floorFloat($this->y + $this->height - 0.18), Math::floorFloat($this->z)));
		$bb = $block->getBoundingBox();
		return $bb !== null and $block->isSolid() and !$block->isTransparent() and $bb->intersectsWith($this->getBoundingBox());
	}
	
	public function onUpdate($currentTick) {
		if($this->knockbackTicks > 0) {
			$this->knockbackTicks--;
		}
		if(($player = $this->target) && $player->isAlive()) {
			if($this->distanceSquared($this->spawnPos) > $this->range) {
				$this->setPosition($this->spawnPos);
				$this->setHealth($this->getMaxHealth());
				$this->target = null;
			} else {
				if(!$this->onGround) {
					if($this->motionY > -$this->gravity * 4) {
						$this->motionY = -$this->gravity * 4;
					} else {
						$this->motionY -= $this->gravity;
					}
					$this->move($this->motionX, $this->motionY, $this->motionZ);
				} elseif($this->knockbackTicks > 0) {
					
				} else {
					$x = $player->x - $this->x;
					$y = $player->y - $this->y;
					$z = $player->z - $this->z;
					if($x ** 2 + $z ** 2 < 0.7) {
						$this->motionX = 0;
						$this->motionZ = 0;
					} else {
						$diff = abs($x) + abs($z);
						$this->motionX = $this->speed * 0.15 * ($x / $diff);
						$this->motionZ = $this->speed * 0.15 * ($z / $diff);
					}
					$this->yaw = rad2deg(atan2(-$x, $z));
					$this->pitch = rad2deg(atan(-$y));
					$this->move($this->motionX, $this->motionY, $this->motionZ);
					if($this->distanceSquared($this->target) < $this->range && $this->attackDelay++ > $this->attackRate) {
						$this->attackDelay = 0;
						$ev = new EntityDamageByEntityEvent($this, $this->target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->attackDamage);
						$player->attack($ev->getFinalDamage(), $ev);
					}
				}
			}
			
			if($this->isOnGround()) {
				if($this->isCollidedHorizontally && !$this->isJumping) {
					$this->motionY = 0.7;
				}
			}
		}
		$this->updateMovement();
		parent::onUpdate($currentTick);
		return !$this->closed;
	}
	
	public function attack($damage, EntityDamageEvent $source) {
		parent::attack($damage, $source);
		if($source->isCancelled() || !($source instanceof EntityDamageByEntityEvent)){
			return;
		}
		$this->stayTime = 0;
		$this->moveTime = 0;
		$damager = $source->getDamager();
		$motion = (new Vector3($this->x - $damager->x, $this->y - $damager->y, $this->z - $damager->z))->normalize();
		$this->motionX = $motion->x * 0.19;
		$this->motionZ = $motion->z * 0.19;
		$this->motionY = 0.6;
	}
	
	public function move($dx, $dy, $dz) : bool{
		Timings::$entityMoveTimer->startTiming();
		$movX = $dx;
		$movY = $dy;
		$movZ = $dz;
		$list = $this->level->getCollisionCubes($this, $this->level->getTickRate() > 1 ? $this->boundingBox->getOffsetBoundingBox($dx, $dy, $dz) : $this->boundingBox->addCoord($dx, $dy, $dz));
		foreach($list as $bb){
			$dx = $bb->calculateXOffset($this->boundingBox, $dx);
		}
		$this->boundingBox->offset($dx, 0, 0);
		foreach($list as $bb){
			$dz = $bb->calculateZOffset($this->boundingBox, $dz);
		}
		$this->boundingBox->offset(0, 0, $dz);
		foreach($list as $bb){
			$dy = $bb->calculateYOffset($this->boundingBox, $dy);
		}
		$this->boundingBox->offset(0, $dy, 0);
		$this->setComponents($this->x + $dx, $this->y + $dy, $this->z + $dz);
		$this->checkChunks();
		$this->checkGroundState($movX, $movY, $movZ, $dx, $dy, $dz);
		$this->updateFallState($dy, $this->onGround);
		Timings::$entityMoveTimer->stopTiming();
		return true;
	}
	
	public function entityBaseTick($tickDiff = 1, $EnchantL = 0){
		Timings::$timerEntityBaseTick->startTiming();
		$hasUpdate = Entity::entityBaseTick($tickDiff);
		if($this->isInsideOfSolid()){
			$hasUpdate = true;
			$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_SUFFOCATION, 1);
			$this->attack($ev->getFinalDamage(), $ev);
		}
		Timings::$timerEntityBaseTick->startTiming();
		return $hasUpdate;
	}
	
	public function kill() {
		$this->close();
		$this->plugin->respawn($this->getNameTag(), $this->respawnTime);
	}
	
	public function getDrops() {
		return $this->drops;
	}
}
