<?php

namespace MiniBosses;

use pocketmine\entity\Creature;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Timings;
use pocketmine\item\Item;
use pocketmine\level\particle\MobSpawnParticle;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\ExplodePacket;
use pocketmine\Player;
use pocketmine\utils\UUID;

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
	
	public $strikeLightning;
	public $lightningTime;
	public $lightningFire;
	public $time = 0;
	
	public function __construct($chunk, $nbt) {
		parent::__construct($chunk, $nbt);
		$this->networkId = $this->namedtag["networkId"];
		$this->range = $this->namedtag["range"];
		$this->spawnPos = new Position($this->namedtag["spawnPos"][0], $this->namedtag["spawnPos"][1], $this->namedtag["spawnPos"][2], $this->level);
		$this->attackDamage = $this->namedtag["attackDamage"];
		$this->attackRate = $this->namedtag["attackRate"];
		$this->speed = $this->namedtag["speed"];
		$this->scale = $this->namedtag["scale"];
		
		$this->strikeLightning = $this->namedtag["strikeLightning"];
		$this->lightningTime = $this->namedtag["lightningTime"];
		$this->lightningFire = $this->namedtag["lightningFire"];
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
		$this->dataProperties[Entity::DATA_FLAG_NO_AI] = [Entity::DATA_TYPE_BYTE, 1];
		$this->setDataProperty(Entity::DATA_SCALE, Entity::DATA_TYPE_FLOAT, $this->namedtag["scale"]);
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
		$this->namedtag->scale = new IntTag("scale", $this->scale);
		
		$this->namedtag->strikeLightning = new ByteTag("strikeLightning", $this->strikeLightning);
		$this->namedtag->lightningTime = new IntTag("lightningTime", $this->lightningTime);
		$this->namedtag->lightningFire = new ByteTag("lightningFire", $this->lightningFire);
		$drops2 = [];
		foreach($this->drops as $drop)
			$drops2[] = $drop->getId() . ";" . $drop->getDamage() . ";" . $drop->getCount() . ";" . $drop->getCompoundTag();
		$this->namedtag->drops = new StringTag("drops", implode(' ', $drops2));
		$this->namedtag->respawnTime = new IntTag("respawnTime", $this->respawnTime);
		$this->namedtag->skin = new StringTag("skin", $this->skin);
		$this->namedtag->heldItem = new StringTag("heldItem", ($this->heldItem instanceof Item ? $this->heldItem->getId() . ";" . $this->heldItem->getDamage() . ";" . $this->heldItem->getCount() . ";" . $this->heldItem->getCompoundTag() : ""));
		
		
	}
	
	public function onUpdate($currentTick) {
		$this->time++;
		if($this->knockbackTicks > 0) {
			$this->knockbackTicks--;
		}
		if(($player = $this->target) && $player->isAlive()) {
			if($this->distanceSquared($this->spawnPos) > $this->range && $this->distanceSquared($this->target) > $this->target) {
				$this->setPosition($this->spawnPos);
				$this->setHealth($this->getMaxHealth());
				$this->target = null;
			} else {
				if($this->strikeLightning === true && $this->time % $this->lightningTime * 20 === 0) {
					$this->strikeLightning($this->target);
					$this->addExplosion($this->target);
				}
				
				if(!$this->onGround && !$this->isCollided) {
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
					$diff = abs($x) + abs($z);
					if($x ** 2 + $z ** 2 < 0.7) {
						$this->motionX = 0;
						$this->motionZ = 0;
					} else {
						$this->motionX = $this->speed * 0.15 * ($x / $diff);
						$this->motionZ = $this->speed * 0.15 * ($z / $diff);
					}
					$this->yaw = -atan2($x / $diff, $z / $diff) * 180 / M_PI;
					$this->pitch = $y == 0 ? 0 : rad2deg(-atan2($y, sqrt($x ** 2 + $z ** 2)));
					$this->move($this->motionX, $this->motionY, $this->motionZ);
					
					if($this->distanceSquared($this->target) < $this->scale && $this->attackDelay++ > $this->attackRate) {
						$this->attackDelay = 0;
						$ev = new EntityDamageByEntityEvent($this, $this->target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->attackDamage);
						$player->attack($ev->getFinalDamage(), $ev);
					}
				}
			}
		}
		if($this->isOnGround()) {
			if($this->isCollidedHorizontally && !$this->isJumping) {
				$this->motionY = 0.7;
			}
		}
		$this->checkBlockCollision();
		parent::onUpdate($currentTick);
		$this->updateMovement();
		return !$this->closed;
	}
	
	public function attack($damage, EntityDamageEvent $source) {
		if(!$source->isCancelled() && $source instanceof EntityDamageByEntityEvent) {
			$dmg = $source->getDamager();
			if($dmg instanceof Player) {
				$this->target = $dmg;
				parent::attack($damage, $source);
				$this->motionX = ($this->x - $dmg->x) * 0.19;
				$this->motionY = 0.5;
				$this->motionZ = ($this->z - $dmg->z) * 0.19;
				$this->knockbackTicks = 10;
			}
		}
	}
	
	public function entityBaseTick($tickDiff = 1, $EnchantL = 0) {
		Timings::$timerEntityBaseTick->startTiming();
		$hasUpdate = Entity::entityBaseTick($tickDiff);
		if($this->isInsideOfSolid()) {
			$hasUpdate = true;
			$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_SUFFOCATION, 1);
			$this->attack($ev->getFinalDamage(), $ev);
		}
		Timings::$timerEntityBaseTick->stopTiming();
		return $hasUpdate;
	}
	
	public function kill() {
		parent::kill();
		$this->plugin->respawn($this->getNameTag(), $this->respawnTime);
		$this->level->addParticle(new MobSpawnParticle($this), $this->scale * 2);
	}
	
	public function getDrops() {
		return $this->drops;
	}
	
	private function strikeLightning(Player $p) {
		$pk = new AddEntityPacket();
		$pk->type = 93;
		$pk->eid = Entity::$entityCount++;
		$pk->metadata = [];
		$pk->x = $p->x;
		$pk->y = $p->y;
		$pk->z = $p->z;
		$pk->speedX = 0;
		$pk->speedY = 0;
		$pk->speedZ = 0;
		foreach($this->plugin->getServer()->getOnlinePlayers() as $player) {
			$player->dataPacket($pk);
		}
	}
	
	private function addExplosion(Player $p) {
		$pk = new ExplodePacket();
		$pk->radius = 7;
		$pk->records = [];
		$pk->x = $p->x;
		$pk->y = $p->y;
		$pk->z = $p->z;
		foreach($this->plugin->getServer()->getOnlinePlayers() as $player) {
			$player->dataPacket($pk);
			
			if($player->distanceSquared(new Vector3($pk->x, $pk->y, $pk->z)) <= 4) {
				$player->attack(5, new EntityDamageByEntityEvent($this, $this->target, EntityDamageEvent::CAUSE_MAGIC, 5));
			}
		}
	}
}
