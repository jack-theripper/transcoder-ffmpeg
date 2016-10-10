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
use Arhitector\Transcoder\Codec;
use Arhitector\Transcoder\Exception\ExecutableNotFoundException;
use Arhitector\Transcoder\Exception\TranscoderException;
use Arhitector\Transcoder\MediaInterface;
use Arhitector\Transcoder\Stream\AudioStream;
use Arhitector\Transcoder\Stream\StreamInterface;
use Arhitector\Transcoder\Stream\SubtitleStream;
use Arhitector\Transcoder\Stream\VideoStream;

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
	 * <code>
	 * $parsed = [
	 *      'error'      => 'error string',
	 *      'format'     => array,
	 *      'properties' => object(ArrayObject),
	 *      'streams'    => object(Streams)
	 * ];
	 * </code>
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
			$parsed['format'] = $this->getFormatFromRawData($raw_data['format']);
			
			if (isset($raw_data['format']['tags']))
			{
				$parsed['properties'] = new \ArrayObject($raw_data['format']['tags']);
			}
		}
		
		if (isset($raw_data['streams']))
		{
			foreach ((array) $raw_data['streams'] as $stream)
			{
				try
				{
					$parsed['streams'][$stream['index']] = $this->createStreamInstance($media, $stream);
				}
				catch (\Exception $exc)
				{
					
				}
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
	
	/**
	 * Normalize array.
	 *
	 * @param array $raw_data
	 *
	 * @return array
	 */
	protected function getFormatFromRawData(array $raw_data)
	{
		return [
			'bit_rate' => isset($raw_data['bit_rate']) ? (int) $raw_data['bit_rate'] : 0,
			'duration' => isset($raw_data['duration']) ? (float) $raw_data['duration'] : 0.0,
			'name'     => isset($raw_data['format_long_name']) ? $raw_data['format_long_name'] : ''
		];
	}
	
	/**
	 * Create stream instance.
	 *
	 * @param MediaInterface $media
	 * @param array          $parsed
	 *
	 * @return StreamInterface
	 */
	protected function createStreamInstance(MediaInterface $media, array $parsed)
	{
		if (isset($parsed['codec_type'], $parsed['codec_name']))
		{
			$stream = null;
			
			if ($parsed['codec_type'] == 'audio')
			{
				$stream = AudioStream::create($media, [
					'frequency'  => isset($parsed['sample_rate']) ? (int) $parsed['sample_rate'] : 0,
					'channels'   => isset($parsed['channels']) ? (int) $parsed['channels'] : 1,
					'profile'    => isset($parsed['profile']) ? (string) $parsed['profile'] : '',
					'bit_rate'   => isset($parsed['bit_rate']) ? (int) $parsed['bit_rate'] : 0,
					'start_time' => isset($parsed['start_time']) ? (float) $parsed['start_time'] : 0.0,
					'duration'   => isset($parsed['duration']) ? (float) $parsed['duration'] : 0.0
				]);
			}
			else if ($parsed['codec_type'] == 'video')
			{
				$stream = VideoStream::create($media, [
					'width'      => isset($parsed['width']) ? (int) $parsed['width'] : 0,
					'height'     => isset($parsed['height']) ? (int) $parsed['height'] : 0,
					'frame_rate' => isset($parsed['r_frame_rate']) ? (float) $parsed['r_frame_rate'] : 0.0,
					'profile'    => isset($parsed['profile']) ? (string) $parsed['profile'] : '',
					'bit_rate'   => isset($parsed['bit_rate']) ? (int) $parsed['bit_rate'] : 0,
					'start_time' => isset($parsed['start_time']) ? (float) $parsed['start_time'] : 0.0,
					'duration'   => isset($parsed['duration']) ? (float) $parsed['duration'] : 0.0
				]);
			}
			else if ($parsed['codec_type'] == 'subtitle')
			{
				$stream = SubtitleStream::create($media, [
					'profile'    => isset($parsed['profile']) ? (string) $parsed['profile'] : '',
					'bit_rate'   => isset($parsed['bit_rate']) ? (int) $parsed['bit_rate'] : 0,
					'start_time' => isset($parsed['start_time']) ? (float) $parsed['start_time'] : 0.0,
					'duration'   => isset($parsed['duration']) ? (float) $parsed['duration'] : 0.0
				]);
			}
			
			if ($stream !== null)
			{
				$stream->setCodec(new Codec($parsed['codec_name'], $parsed['codec_long_name']));
				$stream->setIndex(isset($parsed['index']) ? (int) $parsed['index'] : 0);
				
				if ( ! empty($parsed['tags']))
				{
					foreach ($parsed['tags'] as $key => $value)
					{
						$stream->offsetSet($key, $value);
					}
				}
				
				return $stream;
			}
			
			throw new TranscoderException('Not supported codec type.');
		}
		
		throw new \InvalidArgumentException('Unable to parse ffprobe output: not found "codec_type" etc.');
	}
	
}
