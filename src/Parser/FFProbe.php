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
use Arhitector\Transcoder\FFMpeg\Stream\Audio;
use Arhitector\Transcoder\FFMpeg\Stream\Video;
use Arhitector\Transcoder\Format;
use Arhitector\Transcoder\Stream\StreamInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;

/**
 * Class FFProbe.
 *
 * @package Arhitector\Transcoder\FFMpeg\Parser
 */
class FFProbe implements ParserInterface
{
	
	/**
	 * @var string  Path to ffprobe.
	 */
	protected $binaryPath = 'ffprobe';
	
	
	/**
	 * Set ffprobe binary path.
	 *
	 * @param string $path
	 *
	 * @return FFProbe
	 * @throws \InvalidArgumentException
	 */
	public function setBinaryPath($path)
	{
		if ( ! is_string($path))
		{
			throw new \InvalidArgumentException('FFProbe path must be a string type.');
		}
		
		$this->binaryPath = $path;
		
		return $this;
	}
	
	/**
	 * Receive raw data.
	 *
	 * @param string $filePath
	 *
	 * @return string
	 * @throws \RuntimeException
	 */
	public function read($filePath)
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
	 * Parse raw data.
	 *
	 * @param string $output
	 *
	 * @return \stdClass    streams, format and etc.
	 * @throws \InvalidArgumentException
	 */
	public function parse($output)
	{
		if ( ! is_string($output))
		{
			throw new \InvalidArgumentException('Output must be a string type.');
		}
		
		if (empty($output) || ! ($output = json_decode($output, true)))
		{
			throw new \InvalidArgumentException('Unable to parse ffprobe output.');
		}
		
		$result = new \stdClass;
		
		if (isset($output['error']))
		{
			$result->error = $output['error']['string'];
		}
		
		if (isset($output['format']))
		{
			$result->format = $this->normalizeFormat($output['format']);
		}
		
		if (isset($output['streams']))
		{
			foreach ($output['streams'] as $stream)
			{
				$result->streams[$stream['index']] = $this->normalizeStream($stream);
			}
		}
		
		return $result;
	}
	
	/**
	 * Normalize array.
	 *
	 * @param array $parsed
	 *
	 * @return array
	 */
	protected function normalizeFormat(array $parsed)
	{
		$parsed = array_merge([
			'format_name'      => '',
			'format_long_name' => '',
			'duration'         => 0.00,
			'bit_rate'         => 0,
			'probe_score'      => 0,
			'tags'             => []
		], $parsed);

		$parsed = (object) $parsed;

		$format = new Format((float) $parsed->duration, (int) $parsed->bit_rate, (string) $parsed->format_long_name,
			(int) $parsed->probe_scope, array_map('trim', explode(',', (string) $parsed->format_name)));
		$format->setProperties($parsed->tags ?: []);

		return $format;
	}
	
	/**
	 * Normalize array.
	 *
	 * @param array $parsed
	 *
	 * @return StreamInterface
	 * @throws \InvalidArgumentException
	 */
	protected function normalizeStream(array $parsed)
	{
		if (isset($parsed['codec_type'], $parsed['codec_name'], $parsed['codec_long_name']))
		{
			$codec = new Codec($parsed['codec_name'], $parsed['codec_long_name']);

			if ($parsed['codec_type'] == 'audio')
			{
				$parsed = array_merge([
					'index'       => 0,
					'channels'    => 1,
					'sample_rate' => 0,
					'bit_rate'    => 0,
					'start_time'  => 0,
					'duration'    => 0,
					'profile'     => '',
					'tags'        => []
				], $parsed);

				return (new Audio($parsed['channels'], $parsed['sample_rate'], $parsed['index']))
					->setBitRate($parsed['bit_rate'])
					->setCodec($codec)
					->setStartTime((float) $parsed['start_time'])
					->setDuration((float) $parsed['duration'])
					->setProfile($parsed['profile']);
			}
			
			if ($parsed['codec_type'] == 'video')
			{
				$parsed = array_merge([
					'index'        => 0,
					'width'        => 1,
					'height'       => 0,
					'has_b_frames' => 0,
					'r_frame_rate' => 0,
					'bit_rate'     => 0,
					'start_time'   => 0,
					'duration'     => 0,
					'profile'      => '',
					'tags'         => []
				], $parsed);

				return (new Video($parsed['width'], $parsed['height'], (float) $parsed['r_frame_rate'],
						$parsed['has_b_frames'], $parsed['index']))
					->setBitRate($parsed['bit_rate'])
					->setCodec($codec)
					->setStartTime((float) $parsed['start_time'])
					->setDuration((float) $parsed['duration'])
					->setProfile($parsed['profile']);
			}

			throw new \RuntimeException('Not supported codec type.');
		}

		throw new \InvalidArgumentException('Unable to parse ffprobe output: not found "codec_type" etc.');
	}
	
	/**
	 * Get ffprobe path.
	 *
	 * @return string
	 */
	protected function getBinaryPath()
	{
		return $this->binaryPath;
	}
	
}