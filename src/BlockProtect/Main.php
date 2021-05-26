<?php

namespace BlockProtect;

use pocketmine\Server;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\utils\Config;
use pocketmine\event\block\{BlockBreakEvent, BlockPlaceEvent};
use BlockProtect\Setting;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\item\Item;
use pocketmine\level\sound\PopSound;
use pocketmine\network\mcpe\protocol\OnScreenTextureAnimationPacket;

class Main extends PluginBase implements Listener
{

	public $start, $end;

	public function onEnable()
	{
		$this->getLogger()->info("Load...");
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->eco = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
		$this->land = $this->getServer()->getPluginManager()->getPlugin("BlockProtectAPI");
		$this->form = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
		@mkdir($this->getDataFolder() . "land_data");
	}

	public function onBreak(BlockBreakEvent $ev)
	{
		$p = $ev->getPlayer();
		$block = $ev->getBlock();
		if ($ev->isCancelled()) {
			return true;
		}
		$data = new Config($this->getDataFolder() . "land_data/" . strtolower($p->getName()) . ".yml", Config::YAML);
		$key = "data_" . $block->x . $block->y . $block->z;
		if (!$data->exists($key)) {
			return true;
		}
		$x = $data->get($key)["x"];
		$y = $data->get($key)["y"];
		$z = $data->get($key)["z"];
		$level = $data->get($key)["level"];
		$player_name = $data->get($key)["owner"];
		$size = $data->get($key)["size"];
		if ($block->x == $x && $block->y == $y && $block->z == $z && $p->getLevel()->getFolderName() == $level) {
			$this->getServer()->getCommandMap()->dispatch($p, "§rlandsell§r here");

			foreach (Setting::getBlockPT() as $id => $arr) {
				$ex = explode(":", $arr);
				$size1 = $ex[0];
				$name = $ex[1];
				if ($size1 == $size) {
					$item = Item::get($id, 0, 1);
					$item->setCustomName($name);
					$p->getInventory()->addItem($item);
					$p->sendMessage(Setting::getPerfix() . "เก็บบล็อกโพรเทคเรียบร้อย ");
				}
			}
			$ev->setDrops([Item::get(0)]);
			$data->remove($key);
			$data->save();
		}
	}

	public $check = [];
	public $c2 = "";

	public function onMove(PlayerMoveEvent $ev)
	{

		$p = $ev->getPlayer();
		$info = $this->land->db->getByCoord($p->x, $p->z, $p->getLevel()->getFolderName());
		if ($info === false) {
			$this->check[$p->getName()] = "quit";
		} else {
			$this->check[$p->getName()] = "in";
		}

		if ($this->check[$p->getName()] == "in") {
			$this->c2 = "off";
		}
		if ($this->check[$p->getName()] == "quit") {
			if ($this->c2 == "on") {
				return;
			}
			$p->sendTip(" ออกนอกเขตโพรเทค§e" . $info["owner"] . "");
			$this->c2 = "on";
		}
	}

	public function onPlace(BlockPlaceEvent $ev)
	{
		$p = $ev->getPlayer();
		if ($p->isCreative()) {
			return true;
		}
		$block = $p->getInventory()->getItemInHand();
		foreach (Setting::getBlockPT() as $id => $arr) {
			$ex = explode(":", $arr);
			$size = $ex[0];
			$name = $ex[1];
			if ($block->getId() == $id && $block->getCustomName() == $name) {
				$this->saveLand($ev, $p, $size);
				break;
			}
		}
	}

	public function saveLand($ev, $p, $size)
	{
		$block = $ev->getBlock();
		$this->start[$p->getName()] = array("x" => (int) floor($block->x - $size), "z" => (int) floor($block->z + $size), "level" => $block->getLevel()->getFolderName());
		$startX = (int) $this->start[$p->getName()]["x"];
		$startZ = (int) $this->start[$p->getName()]["z"];
		$endX = (int) floor($block->x + $size);
		$endZ = (int) floor($block->z - $size);
		$this->end[$p->getName()] = array(
			"x" => $endX,
			"z" => $endZ
		);
		if ($startX > $endX) {
			$temp = $endX;
			$endX = $startX;
			$startX = $temp;
		}
		if ($startZ > $endZ) {
			$temp = $endZ;
			$endZ = $startZ;
			$startZ = $temp;
		}
		$startX--;
		$endX++;
		$startZ--;
		$endZ++;

		$result = $this->land->db->checkOverlap($startX, $endX, $startZ, $endZ, $p->getLevel()->getFolderName());
		if ($result) {
			$p->sendMessage(Setting::getPerfix() . "พื้นที่รอบๆ นี้เป็นของ§4:§e " . $result["owner"] . " §fid §e" . $result["ID"] . " §fไม่สามารถวางโพรเทคได้");
			$ev->setCancelled(true);
			return true;
		}

		$l = $this->start[$p->getName()];
		$endp1 = $this->end[$p->getName()];
		$startX1 = (int) floor($l["x"]);
		$endX1 = (int) floor($endp1["x"]);
		$startZ1 = (int) floor($l["z"]);
		$endZ1 = (int) floor($endp1["z"]);
		if ($startX1 > $endX1) {
			$backup1 = $startX;
			$startX1 = $endX1;
			$endX1 = $backup1;
		}
		if ($startZ1 > $endZ1) {
			$backup1 = $startZ1;
			$startZ1 = $endZ1;
			$endZ1 = $backup1;
		}

		$exp = ((($endX + 1) - ($startX - 1)) - 1) * ((($endZ + 1) - ($startZ - 1)) - 1) * $this->land->getConfig()->get("price-per-y-axis", 100);
		$this->land->db->addLand($startX1, $endX1, $startZ1, $endZ1, $p->getLevel()->getFolderName(), $exp, $p->getName());
		$data = new Config($this->getDataFolder() . "land_data/" . strtolower($p->getName()) . ".yml", Config::YAML);
		$key = "data_" . $block->x . $block->y . $block->z;
		$arr = [
			"x" => $block->x,
			"y" => $block->y,
			"z" => $block->z,
			"size" => $size,
			"level" => $p->getLevel()->getFolderName(),
			"owner" => $p->getName()
		];
		$data->set($key, $arr);
		$data->save();
		$p->sendMessage(Setting::getPerfix() . "วางบล๊อกโพรเทค ขนาด " . $size . "§6x§f" . $size . " เรียบร้อย \n  §e/§fblockpt = เปิดการตั้งค่าโพรเทค");
		unset($this->start[$p->getName()], $this->end[$p->getName()]);
	}

	public function onCommand(CommandSender $s, Command $command, String $label, array $a): bool
	{
		$c = $command->getName();

		if ($c == "blockpt") {
			$this->showForm($s);
			return true;
		}
		if ($c == "shoppt") {
			$this->shop($s);
			return true;
		}
		if ($c == "spawn") {
			$this->spawn($s);
			return true;
		}
	}

	public function spawn($player)
	{
		$name = $player->getName();
		$cmd1 = "mw tp Lobby $name";
		$this->getServer()->dispatchCommand(new ConsoleCommandSender, $cmd1);
		$player->getLevel()->addSound(new PopSound($player));
		$ideffict = 24;
		$this->showOnScreenAnimation($player, $ideffict);
	}

	public function showOnScreenAnimation(Player $player, int $ideffict)
	{
		$packet = new OnScreenTextureAnimationPacket();
		$packet->effectId = $ideffict;
		$player->sendDataPacket($packet);
	}

	public function showForm($player)
	{
		$form = $this->form->createSimpleForm(function (Player $player, int $data = null) {
			$result = $data;
			if ($result === null) {
				return true;
			}
			switch ($result) {
				case "0";
					$this->FormInfo($player);
					break;
				case "1";
					$this->FormInvite($player);
					break;
				case "2";
					$this->FormKick($player);
					break;
				case "3";
					$this->FormMove($player);
					break;
				case "4";
					$this->FormGive($player);
					break;
			}
		});
		$form->setTitle(" Setting โพรเทค ");
		$form->setContent("สามารถตั้งค่าต่างๆได้ที่นี้");
		$form->addButton("  Info\nดูข้อมูลโพรเทคที่ยืนตอนนี้");
		$form->addButton("  Invute\nเพิ่มสมาชิกเข้าโพรเทค");
		$form->addButton("  Kick\nไล่สมาชิกในโพรเทคออก");
		$form->addButton("  Give\nโอนย้ายเจ้าของโพรเทค");
		$form->sendToPlayer($player);
	}

	public function shop($player)
	{
		$form = $this->form->createSimpleForm(function (Player $player, int $data = null) {
			$result = $data;
			if ($result === null) {
				return true;
			}
			switch ($result) {
				case "0";
					$p = $player;
					if ($this->eco->myMoney($p) <= 0) {
						$p->sendMessage(" มีเงินไม่พอที่จะซื้อ§rโพรเทค  ขนาด§e §f10§6x§f10");
					} else {
						$items = Item::get(19, 0, 1);
						$items->setCustomName("§fโพรเทค  ขนาด§e §f10§6x§f10");
						$p->getInventory()->addItem($items);
						$p->sendMessage(Setting::getPerfix() . "ซื้อโพรเทค  ขนาด§e §f10§6x§f10 ");
						$this->eco->reduceMoney($p, 0);
					}
					break;
				case "1";
					$p = $player;
					if ($this->eco->myMoney($p) <= 0) {
						$p->sendMessage(" มีเงินไม่พอที่จะซื้อ§rโพรเทค  ขนาด§e §f20§6x§f20");
					} else {
						$items = Item::get(49, 0, 1);
						$items->setCustomName("§fโพรเทค  ขนาด§e §f20§6x§f20");
						$p->getInventory()->addItem($items);
						$p->sendMessage(Setting::getPerfix() . "ซื้อโพรเทค  ขนาด§e §f20§6x§f20 ");
						$this->eco->reduceMoney($p, 0);
					}
					break;
				case "2";
					$p = $player;
					if ($this->eco->myMoney($p) <= 0) {
						$p->sendMessage(" มีเงินไม่พอที่จะซื้อ§rโพรเทค  ขนาด§e §f30§6x§f30");
					} else {
						$items = Item::get(41, 0, 1);
						$items->setCustomName("§fโพรเทค  ขนาด§e §f30§6x§f30");
						$p->getInventory()->addItem($items);
						$p->sendMessage(Setting::getPerfix() . "ซื้อโพรเทค  ขนาด§e §f30§6x§f30 ");
						$this->eco->reduceMoney($p, 0);
					}
					break;
				case "3";
					$p = $player;
					if ($this->eco->myMoney($p) <= 0) {
						$p->sendMessage(" มีเงินไม่พอที่จะซื้อ§rโพรเทค  ขนาด§e §f50§6x§f50");
					} else {
						$items = Item::get(57, 0, 1);
						$items->setCustomName("§fโพรเทค  ขนาด§e §f50§6x§f50");
						$p->getInventory()->addItem($items);
						$p->sendMessage(Setting::getPerfix() . "ซื้อโพรเทค  ขนาด§e §f50§6x§f50 ");
						$this->eco->reduceMoney($p, 0);
					}
					break;
			}
		});
		$form->setTitle(" ร้านขายบล๊อกโพรเทค ");
		$form->setContent("บล๊อกโพรเทค มีขนาดไม่เท่ากัน\nโปรดเลือกขนาดตามความต้องการ\nและ มีเงินเพียงพอต่อการซื้อ");
		$form->addButton("§fโพรเทค  ขนาด§e §f10§6x§f10\nราคา§6 10000");
		$form->addButton("§fโพรเทค  ขนาด§e §f20§6x§f20\nราคา§6 20000");
		$form->addButton("§fโพรเทค  ขนาด§e §f30§6x§f30\nราคา§6 30000");
		$form->addButton("§fโพรเทค  ขนาด§e §f50§6x§f50\nราคา§6 50000");
		$form->sendToPlayer($player);
	}

	public function FormGive($player)
	{
		$form = $this->form->createCustomForm(function (Player $player, array $data = null) {
			$result = $data[0];
			if ($result === null) {
				return true;
			};
			$name = "$data[0]";
			if ($data[0] === "") {
				$player->sendMessage(Setting::getPerfix() . "กรุณาใส่ชื่อผู้เล่นที่จะโอนโพรเทคให้");
			} else {
				$info = $this->land->db->getByCoord($player->x, $player->z, $player->getLevel()->getFolderName());
				if ($info === false) {
					$player->sendMessage(Setting::getPerfix() . "จุดตรงนี้ไม่มีโพรเทคอยู่");
					return true;
				}
				$id = $info["ID"];
				$this->getServer()->getCommandMap()->dispatch($player, "§rland§r give $name $id");
			}
		});
		$form->setTitle("Give");
		$form->addInput("ไส่ชื่อผู้เล่นที่ต้องการ โอนโพรเทคให้", 0, 0);
		$form->sendToPlayer($player);
		return $form;
	}

	public function FormMove($player)
	{
		$form = $this->form->createCustomForm(function (Player $player, array $data = null) {
			$result = $data[0];
			if ($result === null) {
				return true;
			};
			$id = "$data[0]";
			if ($data[0] === "") {
				$player->sendMessage(Setting::getPerfix() . "กรุณาใส่ ID โพรเทค /pt เพื่อเช็ค ID");
			} else {
				$this->getServer()->getCommandMap()->dispatch($player, "§rland§r move $id");
			}
		});
		$form->setTitle("Move");
		$form->addInput("ไส่หมายเลข โพรเทค", 0, 0);
		$form->sendToPlayer($player);
		return $form;
	}

	public function FormKick($player)
	{
		$form = $this->form->createCustomForm(function (Player $player, array $data = null) {
			$result = $data[0];
			if ($result === null) {
				return true;
			};
			$name = "$data[0]";
			if ($data[0] === "") {
				$player->sendMessage(Setting::getPerfix() . "กรุณาไส่ชื่อผู้เล่นที่ต้องการแตะออกจากโพรเทค");
			} else {
				$info = $this->land->db->getByCoord($player->x, $player->z, $player->getLevel()->getFolderName());
				if ($info === false) {
					$player->sendMessage(Setting::getPerfix() . "จุดตรงนี้ไม่มีโพรเทคอยู่");
					return true;
				}
				$id = $info["ID"];
				$this->getServer()->getCommandMap()->dispatch($player, "§rland§r kick $id $name");
			}
		});
		$form->setTitle("Kick");
		$form->addInput("ไส่ชื่อผู้เล่นที่ต้องการ แตะออก", 0, 0);
		$form->sendToPlayer($player);
		return $form;
	}

	public function FormInvite($player)
	{
		$form = $this->form->createCustomForm(function (Player $player, array $data = null) {
			$result = $data[0];
			if ($result === null) {
				return true;
			};
			$name = "$data[0]";
			if ($data[0] === "") {
				$player->sendMessage(Setting::getPerfix() . "กรุณาไส่ชื่อผู้เล่นที่ต้องการเชิญเข้าโพรเทค");
			} else {
				$info = $this->land->db->getByCoord($player->x, $player->z, $player->getLevel()->getFolderName());
				if ($info === false) {
					$player->sendMessage(Setting::getPerfix() . "จุดตรงนี้ไม่มีโพรเทคอยู่");
					return true;
				}
				$id = $info["ID"];
				$this->getServer()->getCommandMap()->dispatch($player, "§rland§r invite $id $name");
			}
		});
		$form->setTitle("Invite");
		$form->addInput("ไส่ชื่อผู้เล่นที่ต้องการเพิ่มในโพรเทค", 0, 0);
		$form->sendToPlayer($player);
		return $form;
	}

	public function FormInfo($player)
	{
		$api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
		$form = $api->createSimpleForm(function (Player $player, $data = null) {
			$result = $data[0];

			if ($result === null) {
				return true;
			}
			switch ($result) {
				case 0:
					$this->showForm($player);
					break;
			}
		});
		$form->setTitle("Info");
		$form->setContent($this->getLandInfo($player));
		$form->addButton("กลับ");
		$form->sendToPlayer($player);
	}

	public function getLandInfo($p)
	{
		$mess = "";
		$name = "";
		$info = $this->land->db->getByCoord($p->x, $p->z, $p->getLevel()->getFolderName());
		if ($info === false) {
			$mess = "จุดตรงนี้ไม่มีโพรเทคอยู่";
		} else {
			foreach ($info["invitee"] as $n => $n1) {
				$name .= "\n - " . $n;
			}
			$mess = " §fไอดีโพรเทค §e:§6 " . $info["ID"] . "\n" .
				" §fเจ้าของโพรเทค §e:§6 " . $info["owner"] . "\n" .
				" §fโลกของโพรเทค §e:§6 " . $info["level"] . "\n" .
				" §fสมาชิกในโพรเทค §e:§6 " . $name;
		}
		return $mess;
	}
}
