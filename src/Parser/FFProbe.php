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

use Arhitector\Transcoder\Codec;
use Arhitector\Transcoder\FFMpeg\Stream\AudioStream;
use Arhitector\Transcoder\FFMpeg\Stream\SubtitleStream;
use Arhitector\Transcoder\FFMpeg\Stream\VideoStream;
use Arhitector\Transcoder\Format\FormatInterface;
use Arhitector\Transcoder\Stream\StreamInterface;
use Arhitector\Transcoder\Tools\FormatFinderTrait;
use Arhitector\Transcoder\Tools\Instantiator;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;

/**
 * Class FFProbe.
 *
 * @package Arhitector\Transcoder\FFMpeg\Parser
 */
class FFProbe implements ParserInterface
{
	use FormatFinderTrait;
	
	/**
	 * @var string  Path to ffprobe.
	 */
	protected $binaryPath = 'ffprobe';
	
	/**
	 * Set the ffprobe binary path.
	 *
	 * @param string $path
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct($path)
	{
		if ( ! is_string($path))
		{
			throw new \InvalidArgumentException('FFProbe path must be a string type.');
		}
		
		$this->binaryPath = $path;
	}
	
	/**
	 * Receive and parse raw data.
	 *
	 * @param string $filePath
	 *
	 * @return array    streams, format and etc.
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 */
	public function parse($filePath)
	{
		$output = $this->read($filePath);
		
		if ( ! is_string($output))
		{
			throw new \InvalidArgumentException('Output must be a string type.');
		}
		
		if (empty($output) || ! ($output = json_decode($output, true)))
		{
			throw new \InvalidArgumentException('Unable to parse ffprobe output.');
		}
		
		$result = [];
		
		if (isset($output['error']))
		{
			$result['error'] = $output['error']['string'];
		}
		
		if (isset($output['format']))
		{
			$result['format'] = $this->createFormat($filePath, $output['format']);
			
			if (isset($output['format']['tags']))
			{
				$result['properties'] = new \ArrayObject($output['format']['tags']);
			}
		}
		
		if (isset($output['streams']))
		{
			foreach ($output['streams'] as $stream)
			{
				try
				{
					$result['streams'][$stream['index']] = $this->createStream($filePath, $stream);
				}
				catch (\Exception $exc)
				{
					
				}
			}
		}
		
		return $result;
	}
	
	/**
	 * Get ffprobe path.
	 *
	 * @return string
	 */
	public function getBinaryPath()
	{
		return $this->binaryPath;
	}
	
	/**
	 * Receive raw data.
	 *
	 * @param string $filePath
	 *
	 * @return string
	 * @throws \RuntimeException
	 */
	protected function read($filePath)
	{
		if ( ! is_file($filePath))
		{
			throw new \RuntimeException('File path not found.');
		}
		
		$command = ProcessUtils::escapeArgument($this->getBinaryPath());
		$command .= ' "-loglevel" "quiet" "-print_format" "json" "-show_format" "-show_streams" "-show_error"';
		$command .= ' "-i" '.ProcessUtils::escapeArgument($filePath);
		
		$process = new Process($command);
		$process->setTimeout(30);
		$process->run();
		
		return $process->getOutput();
	}
	
	/**
	 * Normalize array and create a format instance.
	 *
	 * @param string $filePath
	 * @param array  $parsed
	 *
	 * @return FormatInterface
	 */
	protected function createFormat($filePath, array $parsed)
	{
		$format = $this->getFormatClassString($filePath, mime_content_type($filePath));
		$format = new Instantiator($format);
		$parsed += [
			'format_long_name' => '',
			'duration'         => .0,
			'bit_rate'         => 0
		];
		
		$format->setValue('name', $parsed['format_long_name'])
			->setValue('duration', (float) $parsed['duration'])
			->setValue('bitRate', (int) $parsed['bit_rate']);
		
		return $format->getInstance();
	}
	
	/**
	 * Create stream instance.
	 *
	 * @param string $filePath
	 * @param array  $parsed
	 *
	 * @return StreamInterface
	 */
	protected function createStream($filePath, array $parsed)
	{
		if (isset($parsed['codec_type'], $parsed['codec_name']))
		{
			$stream = null;
			$parsed += [
				'codec_long_name' => '',
				'index'        => 0,
				'channels'     => 1,
				'width'        => 0,
				'height'       => 0,
				'sample_rate'  => 0,
				'bit_rate'     => 0,
				'has_b_frames' => 0,
				'r_frame_rate' => 0,
				'start_time'   => 0,
				'duration'     => 0,
				'profile'      => '',
				'tags'         => []
			];
			
			$codec = new Codec($parsed['codec_name'], $parsed['codec_long_name']);
			
			switch ($parsed['codec_type'])
			{
				case 'audio':
					
					$stream = new Instantiator(AudioStream::class, [
						(int) $parsed['sample_rate'],
						(int) $parsed['channels'],
						$filePath,
						$parsed['profile'],
						(int) $parsed['bit_rate'],
						(float) $parsed['start_time'],
						(float) $parsed['duration']
					]);
					
				break;
				
				case 'video':
					
					$stream = new Instantiator(VideoStream::class, [
						(int) $parsed['width'],
						(int) $parsed['height'],
						(float) $parsed['r_frame_rate'],
						$filePath,
						$parsed['profile'],
						(int) $parsed['bit_rate'],
						(float) $parsed['start_time'],
						(float) $parsed['duration']
					]);
				
				break;
				
				case 'subtitle':
					
					$stream = new Instantiator(SubtitleStream::class, [
						$filePath,
						$parsed['profile'],
						(int) $parsed['bit_rate'],
						(float) $parsed['start_time'],
						(float) $parsed['duration']
					]);
				
				break;
			}
			
			if ($stream != null)
			{
				$stream = $stream->getInstance();
				$stream->setCodec($codec);
				$stream->setIndex((int) $parsed['index']);
				
				foreach ($parsed['tags'] as $key => $value)
				{
					$stream[$key] = $value;
				}
				
				return $stream;
			}
			
			throw new \RuntimeException('Not supported codec type.');
		}
		
		throw new \InvalidArgumentException('Unable to parse ffprobe output: not found "codec_type" etc.');
	}
	
}
