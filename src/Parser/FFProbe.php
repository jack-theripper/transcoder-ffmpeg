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
namespace Arhitector\Transcoder\Adapter\FFMpeg\Parser;

use Arhitector\Transcoder\Adapter\FFMpeg\Executor;
use Arhitector\Transcoder\Adapter\FFMpeg\ProcessBuilder;
use Arhitector\Transcoder\Exception\ExecutableNotFoundException;
use Arhitector\Transcoder\Exception\TranscoderException;
use Arhitector\Transcoder\MediaInterface;
use ArrayObject;

/**
 * Class FFProbe
 *
 * @package Arhitector\Transcoder\Adapter\FFMpeg\Parser
 */
class FFProbe implements ParserInterface
{
	
	/**
	 * Receive and parse raw data.
	 *
	 * @param MediaInterface $media
	 * @param Executor       $executor
	 *
	 * @return array
	 */
	public function parse(MediaInterface $media, Executor $executor)
	{
		$raw_data = $this->read($media, $executor);
		
		if ( ! is_array($raw_data))
		{
			throw new TranscoderException('Unable to parse ffprobe output.');
		}
		
		$parsed = [];
		
		if (isset($raw_data['error']))
		{
			$parsed['error'] = $raw_data['error']['string'];
		}
		
		if (isset($raw_data['format']))
		{
			if (isset($raw_data['format']['tags']))
			{
				$parsed['properties'] = new ArrayObject($raw_data['format']['tags']);
			}
		}

		return $parsed;
	}
	
	/**
	 * Receive raw data.
	 *
	 * @param MediaInterface $media
	 * @param Executor       $executor
	 *
	 * @return array
	 */
	protected function read(MediaInterface $media, Executor $executor)
	{
		if ( ! is_file($media->getFilePath()))
		{
			throw new TranscoderException('File path not found.');
		}
		
		$output = $executor->getOption('ffprobe.path');
		
		if ( ! $output)
		{
			throw new ExecutableNotFoundException('Executable not found, proposed: ffprobe.', 'ffprobe.path');
		}
		
		$output = $executor->execute((new ProcessBuilder([
			'loglevel'     => 'quiet',
			'print_format' => 'json',
			'show_format'  => '',
			'show_streams' => '',
			'show_error'   => '',
			'i'            => $media->getFilePath()
		]))
			->setPrefix($executor->getOption('ffprobe.path'))
			->setTimeout(30))
			->getOutput();
		
		if (empty($output) || ! ($output = json_decode($output, true)))
		{
			throw new TranscoderException('Unable to parse ffprobe output.');
		}
		
		return $output;
	}
	
}
