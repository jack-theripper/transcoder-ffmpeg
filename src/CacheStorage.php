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
class CacheStorage
{
	
	/**
	 * @var array Container.
	 */
	protected static $container = [];
	
	/**
	 * Sets the values.
	 *
	 * @param string $keyContainer
	 * @param mixed  $value
	 *
	 * @return mixed
	 */
	public static function set($keyContainer, $value)
	{
		if ( ! is_string($keyContainer) || empty($keyContainer))
		{
			throw new \InvalidArgumentException('The keyContainer value must be a string type.');
		}
		
		return self::$container[$keyContainer] = $value;
	}
	
	/**
	 * Get values.
	 *
	 * @param string $keyContainer
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public static function get($keyContainer, $default = null)
	{
		if (self::has($keyContainer))
		{
			return self::$container[$keyContainer];
		}
		
		return $default;
	}
	
	/**
	 * Check exists index.
	 *
	 * @param string $keyContainer
	 *
	 * @return bool
	 */
	public static function has($keyContainer)
	{
		if ( ! is_string($keyContainer) || empty($keyContainer))
		{
			throw new \InvalidArgumentException('The keyContainer value must be a string type.');
		}
		
		return array_key_exists($keyContainer, self::$container);
	}
	
}
