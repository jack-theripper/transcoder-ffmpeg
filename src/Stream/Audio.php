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
namespace Arhitector\Transcoder\FFMpeg\Stream;

use Arhitector\Transcoder\DecoratorTrait;
use Arhitector\Transcoder\FFMpeg\Executor;
use Arhitector\Transcoder\Format\FormatInterface;
use Arhitector\Transcoder\Stream\AudioStreamInterface;

/**
 * Class Audio.
 *
 * @package Arhitector\Transcoder\FFMpeg\Stream
 */
class Audio extends \Arhitector\Transcoder\Stream\Audio implements AudioStreamInterface
{
	use DecoratorTrait;

	/**
	 * Executor instance.
	 *
	 * @param Executor $executor
	 *
	 * @return Audio
	 */
	public function inject(Executor $executor)
	{
		$this->decorator = $executor;

		return $this;
	}
	
	/**
	 * Stream save.
	 *
	 * @param FormatInterface $format
	 * @param string          $filePath
	 *
	 * @return bool
	 */
	public function save(FormatInterface $format, $filePath)
	{
		
		
	}
	
}