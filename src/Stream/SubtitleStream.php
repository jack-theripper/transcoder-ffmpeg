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

use Arhitector\Transcoder\Format\FormatInterface;
use Arhitector\Transcoder\Stream\SubtitleStreamInterface;

/**
 * Class SubtitleStream
 *
 * @package Arhitector\Transcoder\FFMpeg\Stream
 */
class SubtitleStream extends \Arhitector\Transcoder\Stream\SubtitleStream implements SubtitleStreamInterface
{
	use StreamTrait;
	
	/**
	 * Stream save.
	 *
	 * @param FormatInterface $format
	 * @param string          $filePath
	 * @param bool            $overwrite
	 *
	 * @return bool
	 */
	public function save(FormatInterface $format, $filePath, $overwrite = true)
	{
		
	}
	
}
