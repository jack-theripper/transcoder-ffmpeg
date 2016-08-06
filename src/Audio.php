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

use Arhitector\Transcoder\AudioInterface;

/**
 * Class Audio.
 *
 * @package Arhitector\Transcoder\FFMpeg
 */
class Audio extends \Arhitector\Transcoder\Audio implements AudioInterface
{

	/**
	 * Audio constructor.
	 *
	 * @param string $filePath
	 * @param array  $options
	 */
	public function __construct($filePath, array $options = [])
	{
		parent::__construct($filePath, new Adapter($options));
	}

}