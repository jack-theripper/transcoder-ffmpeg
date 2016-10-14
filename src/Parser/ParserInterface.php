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
namespace Arhitector\Transcoder\FFMpeg\Parser;

use Arhitector\Transcoder\FFMpeg\Executor;
use Arhitector\Transcoder\MediaInterface;

/**
 * Interface ParserInterface
 *
 * @package Arhitector\Transcoder\FFMpeg\Parser
 */
interface ParserInterface
{
	
	/**
	 * Receive and parse raw data.
	 *
	 * @param MediaInterface $media
	 * @param Executor       $executor
	 *
	 * @return array
	 *
	 * <code>
	 * $parsed = [
	 *      'error'      => 'error string',
	 *      'format'     => object(AudioFormat),
	 *      'properties' => object(ArrayObject),
	 *      'streams'    => object(Streams)
	 * ];
	 * </code>
	 */
	public function parse(MediaInterface $media, Executor $executor);
	
}
