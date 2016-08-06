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
 * Class ConfigTrait
 *
 * @package Arhitector\Transcoder\FFMpeg
 */
trait OptionsTrait
{

	/**
	 * @var array Global options.
	 */
	protected static $options = [];


	/**
	 * Set one global option.
	 *
	 * @param $index
	 * @param $value
	 *
	 * @return bool
	 */
	public static function setOption($index, $value)
	{
		if ( ! is_scalar($index))
		{
			throw new \InvalidArgumentException('Index must be a scalar type.');
		}

		static::$options[$index] = $value;

		return true;
	}

	/**
	 * Replace global options.
	 *
	 * @param array $options
	 *
	 * @return bool
	 */
	public static function setOptions(array $options)
	{
		static::$options = $options;

		return true;
	}
	
}