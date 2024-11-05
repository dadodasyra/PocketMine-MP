<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\utils;

use function mb_scrub;
use function preg_last_error;
use function preg_quote;
use function preg_replace;
use function preg_split;
use function str_repeat;
use function str_replace;
use const PREG_BACKTRACK_LIMIT_ERROR;
use const PREG_BAD_UTF8_ERROR;
use const PREG_BAD_UTF8_OFFSET_ERROR;
use const PREG_INTERNAL_ERROR;
use const PREG_JIT_STACKLIMIT_ERROR;
use const PREG_RECURSION_LIMIT_ERROR;
use const PREG_SPLIT_DELIM_CAPTURE;
use const PREG_SPLIT_NO_EMPTY;

/**
 * Class used to handle Minecraft chat format, and convert it to other formats like HTML
 */
abstract class TextFormat{
	public const ESCAPE = "\xc2\xa7"; //§
	public const EOL = "\n";

	public const BLACK = self::ESCAPE . "0";
	public const DARK_BLUE = self::ESCAPE . "1";
	public const DARK_GREEN = self::ESCAPE . "2";
	public const DARK_AQUA = self::ESCAPE . "3";
	public const DARK_RED = self::ESCAPE . "4";
	public const DARK_PURPLE = self::ESCAPE . "5";
	public const GOLD = self::ESCAPE . "6";
	public const GRAY = self::ESCAPE . "7";
	public const DARK_GRAY = self::ESCAPE . "8";
	public const BLUE = self::ESCAPE . "9";
	public const GREEN = self::ESCAPE . "a";
	public const AQUA = self::ESCAPE . "b";
	public const RED = self::ESCAPE . "c";
	public const LIGHT_PURPLE = self::ESCAPE . "d";
	public const YELLOW = self::ESCAPE . "e";
	public const WHITE = self::ESCAPE . "f";
	public const MINECOIN_GOLD = self::ESCAPE . "g";
	public const MATERIAL_QUARTZ = self::ESCAPE . "h";
	public const MATERIAL_IRON = self::ESCAPE . "i";
	public const MATERIAL_NETHERITE = self::ESCAPE . "j";
	public const MATERIAL_REDSTONE = self::ESCAPE . "m";
	public const MATERIAL_COPPER = self::ESCAPE . "n";
	public const MATERIAL_GOLD = self::ESCAPE . "o";
	public const MATERIAL_EMERALD = self::ESCAPE . "p";
	public const MATERIAL_DIAMOND = self::ESCAPE . "q";
	public const MATERIAL_LAPIS = self::ESCAPE . "t";
	public const MATERIAL_AMETHYST = self::ESCAPE . "u";
	public const MATERIAL_RESIN = self::ESCAPE . "v";

	public const COLORS = [
		self::BLACK => self::BLACK,
		self::DARK_BLUE => self::DARK_BLUE,
		self::DARK_GREEN => self::DARK_GREEN,
		self::DARK_AQUA => self::DARK_AQUA,
		self::DARK_RED => self::DARK_RED,
		self::DARK_PURPLE => self::DARK_PURPLE,
		self::GOLD => self::GOLD,
		self::GRAY => self::GRAY,
		self::DARK_GRAY => self::DARK_GRAY,
		self::BLUE => self::BLUE,
		self::GREEN => self::GREEN,
		self::AQUA => self::AQUA,
		self::RED => self::RED,
		self::LIGHT_PURPLE => self::LIGHT_PURPLE,
		self::YELLOW => self::YELLOW,
		self::WHITE => self::WHITE,
		self::MINECOIN_GOLD => self::MINECOIN_GOLD,
		self::MATERIAL_QUARTZ => self::MATERIAL_QUARTZ,
		self::MATERIAL_IRON => self::MATERIAL_IRON,
		self::MATERIAL_NETHERITE => self::MATERIAL_NETHERITE,
		self::MATERIAL_REDSTONE => self::MATERIAL_REDSTONE,
		self::MATERIAL_COPPER => self::MATERIAL_COPPER,
		self::MATERIAL_GOLD => self::MATERIAL_GOLD,
		self::MATERIAL_EMERALD => self::MATERIAL_EMERALD,
		self::MATERIAL_DIAMOND => self::MATERIAL_DIAMOND,
		self::MATERIAL_LAPIS => self::MATERIAL_LAPIS,
		self::MATERIAL_AMETHYST => self::MATERIAL_AMETHYST,
		self::MATERIAL_RESIN => self::MATERIAL_RESIN,
	];

	public const OBFUSCATED = self::ESCAPE . "k";
	public const BOLD = self::ESCAPE . "l";
	public const STRIKETHROUGH = self::ESCAPE . "m";
	public const UNDERLINE = self::ESCAPE . "n";
	public const ITALIC = self::ESCAPE . "o";

	public const FORMATS = [
		self::OBFUSCATED => self::OBFUSCATED,
		self::BOLD => self::BOLD,
		self::STRIKETHROUGH => self::STRIKETHROUGH,
		self::UNDERLINE => self::UNDERLINE,
		self::ITALIC => self::ITALIC,
	];

	public const RESET = self::ESCAPE . "r";

	private static function makePcreError() : \InvalidArgumentException{
		$errorCode = preg_last_error();
		$message = [
			PREG_INTERNAL_ERROR => "Internal error",
			PREG_BACKTRACK_LIMIT_ERROR => "Backtrack limit reached",
			PREG_RECURSION_LIMIT_ERROR => "Recursion limit reached",
			PREG_BAD_UTF8_ERROR => "Malformed UTF-8",
			PREG_BAD_UTF8_OFFSET_ERROR => "Bad UTF-8 offset",
			PREG_JIT_STACKLIMIT_ERROR => "PCRE JIT stack limit reached"
		][$errorCode] ?? "Unknown (code $errorCode)";
		throw new \InvalidArgumentException("PCRE error: $message");
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	private static function preg_replace(string $pattern, string $replacement, string $string) : string{
		$result = preg_replace($pattern, $replacement, $string);
		if($result === null){
			throw self::makePcreError();
		}
		return $result;
	}

	/**
	 * Splits the string by Format tokens
	 *
	 * @return string[]
	 */
	public static function tokenize(string $string) : array{
		$result = preg_split("/(" . self::ESCAPE . "[0-9a-gk-or])/u", $string, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		if($result === false) throw self::makePcreError();
		return $result;
	}

	/**
	 * Cleans the string from Minecraft codes, ANSI Escape Codes and invalid UTF-8 characters
	 *
	 * @return string valid clean UTF-8
	 */
	public static function clean(string $string, bool $removeFormat = true) : string{
		$string = mb_scrub($string, 'UTF-8');
		$string = self::preg_replace("/[\x{E000}-\x{F8FF}]/u", "", $string); //remove unicode private-use-area characters (they might break the console)
		if($removeFormat){
			$string = str_replace(self::ESCAPE, "", self::preg_replace("/" . self::ESCAPE . "[0-9a-gk-or]/u", "", $string));
		}
		return str_replace("\x1b", "", self::preg_replace("/\x1b[\\(\\][[0-9;\\[\\(]+[Bm]/u", "", $string));
	}

	/**
	 * Replaces placeholders of § with the correct character. Only valid codes (as in the constants of the TextFormat class) will be converted.
	 *
	 * @param string $placeholder default "&"
	 */
	public static function colorize(string $string, string $placeholder = "&") : string{
		return self::preg_replace('/' . preg_quote($placeholder, "/") . '([0-9a-gk-or])/u', self::ESCAPE . '$1', $string);
	}

	/**
	 * Adds base formatting to the string. The given format codes will be inserted directly after any RESET (§r) codes.
	 *
	 * This is useful for log messages, where a RESET code should return to the log message's original colour (e.g.
	 * blue for NOTICE), rather than whatever the terminal's base text colour is (usually some off-white colour).
	 *
	 * Example behaviour:
	 * - Base format "§c" (red) + "Hello" (no format) = "§r§cHello"
	 * - Base format "§c" + "Hello §rWorld" = "§r§cHello §r§cWorld"
	 *
	 * Note: Adding base formatting to the output string a second time will result in a combination of formats from both
	 * calls. This is not by design, but simply a consequence of the way the function is implemented.
	 */
	public static function addBase(string $baseFormat, string $string) : string{
		$baseFormatParts = self::tokenize($baseFormat);
		foreach($baseFormatParts as $part){
			if(!isset(self::FORMATS[$part]) && !isset(self::COLORS[$part])){
				throw new \InvalidArgumentException("Unexpected base format token \"$part\", expected only color and format tokens");
			}
		}
		$baseFormat = self::RESET . $baseFormat;

		return $baseFormat . str_replace(self::RESET, $baseFormat, $string);
	}

	/**
	 * Returns an HTML-formatted string with colors/markup
	 */
	public static function toHTML(string $string) : string{
		$newString = "";
		$tokens = 0;
		foreach(self::tokenize($string) as $token){
			switch($token){
				case self::BOLD:
					$newString .= "<span style=font-weight:bold>";
					++$tokens;
					break;
				case self::OBFUSCATED:
					//$newString .= "<span style=text-decoration:line-through>";
					//++$tokens;
					break;
				case self::ITALIC:
					$newString .= "<span style=font-style:italic>";
					++$tokens;
					break;
				case self::UNDERLINE:
					$newString .= "<span style=text-decoration:underline>";
					++$tokens;
					break;
				case self::STRIKETHROUGH:
					$newString .= "<span style=text-decoration:line-through>";
					++$tokens;
					break;
				case self::RESET:
					$newString .= str_repeat("</span>", $tokens);
					$tokens = 0;
					break;

				//Colors
				case self::BLACK:
					$newString .= "<span style=color:#000>";
					++$tokens;
					break;
				case self::DARK_BLUE:
					$newString .= "<span style=color:#00A>";
					++$tokens;
					break;
				case self::DARK_GREEN:
					$newString .= "<span style=color:#0A0>";
					++$tokens;
					break;
				case self::DARK_AQUA:
					$newString .= "<span style=color:#0AA>";
					++$tokens;
					break;
				case self::DARK_RED:
					$newString .= "<span style=color:#A00>";
					++$tokens;
					break;
				case self::DARK_PURPLE:
					$newString .= "<span style=color:#A0A>";
					++$tokens;
					break;
				case self::GOLD:
					$newString .= "<span style=color:#FA0>";
					++$tokens;
					break;
				case self::GRAY:
					$newString .= "<span style=color:#AAA>";
					++$tokens;
					break;
				case self::DARK_GRAY:
					$newString .= "<span style=color:#555>";
					++$tokens;
					break;
				case self::BLUE:
					$newString .= "<span style=color:#55F>";
					++$tokens;
					break;
				case self::GREEN:
					$newString .= "<span style=color:#5F5>";
					++$tokens;
					break;
				case self::AQUA:
					$newString .= "<span style=color:#5FF>";
					++$tokens;
					break;
				case self::RED:
					$newString .= "<span style=color:#F55>";
					++$tokens;
					break;
				case self::LIGHT_PURPLE:
					$newString .= "<span style=color:#F5F>";
					++$tokens;
					break;
				case self::YELLOW:
					$newString .= "<span style=color:#FF5>";
					++$tokens;
					break;
				case self::WHITE:
					$newString .= "<span style=color:#FFF>";
					++$tokens;
					break;
				case self::MINECOIN_GOLD:
					$newString .= "<span style=\"color:#DDD605\">";
					++$tokens;
					break;
				case self::MATERIAL_QUARTZ:
					$newString .= "<span style=\"color:#EACACA\">";
					++$tokens;
					break;
				case self::MATERIAL_IRON:
					$newString .= "<span style=\"color:#CECAC8\">";
					++$tokens;
					break;
				case self::MATERIAL_NETHERITE:
					$newString .= "<span style=\"color:#443A3B\">";
					++$tokens;
					break;
				case self::MATERIAL_REDSTONE:
					$newString .= "<span style=\"color:#967107\">";
					++$tokens;
					break;
				case self::MATERIAL_COPPER:
					$newString .= "<span style=\"color:#B4684D\">";
					++$tokens;
					break;
				case self::MATERIAL_GOLD:
					$newString .= "<span style=\"color:#DEB12D\">";
					++$tokens;
					break;
				case self::MATERIAL_EMERALD:
					$newString .= "<span style=\"color:#47A036\">";
					++$tokens;
					break;
				case self::MATERIAL_DIAMOND:
					$newString .= "<span style=\"color:#2CBAA8\">";
					++$tokens;
					break;
				case self::MATERIAL_LAPIS:
					$newString .= "<span style=\"color:#21497B\">";
					++$tokens;
					break;
				case self::MATERIAL_AMETHYST:
					$newString .= "<span style=\"color:#9A5C6C\">";
					++$tokens;
					break;
				case self::MATERIAL_RESIN:
					$newString .= "<span style=\"color:#EB7114\">";
					++$tokens;
					break;
				default:
					$newString .= $token;
					break;
			}
		}

		$newString .= str_repeat("</span>", $tokens);

		return $newString;
	}
}
