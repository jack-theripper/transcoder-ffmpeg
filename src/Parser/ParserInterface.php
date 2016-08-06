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

/**
 * Interface ParserInterface.
 *
 * @package Arhitector\Transcoder\FFMpeg\Parser
 */
interface ParserInterface
{
	
	/**
	 * Receive raw data.
	 *
	 * @param string $filePath
	 *
	 * @return string
	 */
	public function read($filePath);
	
	/**
	 * Parse raw data.
	 *
	 * @param string $output
	 *
	 * @return \stdClass    streams, format and etc.
	 */
	public function parse($output);
	
	/**
	 * Set path.
	 *
	 * @param string $path
	 *
	 * @return ParserInterface
	 */
	public function setBinaryPath($path);
	
}