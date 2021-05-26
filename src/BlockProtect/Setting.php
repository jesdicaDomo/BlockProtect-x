<?php

namespace BlockProtect;

class Setting
{

	public static function getPerfix()
	{
		return "  ";
	}

	public static function getBlockPT()
	{
		$array = [
			19 => "10:§fโพรเทค  ขนาด§e §f10§6x§f10",
			49 => "20:§fโพรเทค  ขนาด§e §f20§6x§f20",
			41 => "30:§fโพรเทค  ขนาด§e §f30§6x§f30",
			57 => "50:§fโพรเทค  ขนาด§e §f50§6x§f50"
		];
		return $array;
	}
}
