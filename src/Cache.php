<?php

/**
 * This file is part of the arhitector/transcoder-ffmpeg library.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author    Dmitry Arhitector <dmitry.arhitector@yandex.ru>
 *
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright Copyright (c) 2016 Dmitry Arhitector <dmitry.arhitector@yandex.ru>
 */
namespace Arhitector\Transcoder\FFMpeg;

/**
 * Class Cache.
 *
 * @package Arhitector\Transcoder\FFMpeg
 */
class Cache
{

	/**
	 * @var array Container.
	 */
	protected static $container = [];


	/**
	 * Sets the values.
	 *
	 * @param string $index
	 * @param mixed  $value
	 */
	public static function set($index, $value)
	{
		self::$container[(string) $index] = $value;
	}

	/**
	 * Get values.
	 *
	 * @param string $index
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public static function get($index, $default = null)
	{
		if (array_key_exists((string) $index, self::$container))
		{
			return self::$container[(string) $index];
		}

		return $default;
	}
	
}