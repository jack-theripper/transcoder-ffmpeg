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

use Arhitector\Transcoder\AdapterInterface;
use Arhitector\Transcoder\AdapterTrait;
use Arhitector\Transcoder\AudioInterface;
use Arhitector\Transcoder\Exception\ExecutableNotFoundException;
use Arhitector\Transcoder\Filter\FilterInterface;
use Arhitector\Transcoder\Filter\HandlerFilterInterface;
use Arhitector\Transcoder\Format\FormatInterface;
use Arhitector\Transcoder\SubtitleInterface;
use Arhitector\Transcoder\Tools\Options;
use Arhitector\Transcoder\Tools\TemporaryPath;
use Arhitector\Transcoder\TranscoderInterface;
use Arhitector\Transcoder\VideoInterface;
use Symfony\Component\Process\Process;

/**
 * Class Adapter.
 *
 * @package Arhitector\Transcoder\FFMpeg
 */
class Adapter implements AdapterInterface
{
	use AdapterTrait, CommandOptionsTrait;
	
	/**
	 * @var array Global options.
	 */
	protected static $options = [];
	
	/**
	 * Set one global option.
	 *
	 * @param string $index
	 * @param mixed  $value
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
	
	/**
	 * @var CommandExecutor
	 */
	protected $executor;
	
	/**
	 * Adapter constructor.
	 *
	 * @param array $options
	 */
	public function __construct(array $options = [])
	{
		$options = array_merge([
			'ffmpeg.path'    => 'ffmpeg',
			'ffmpeg.threads' => null,
			'ffprobe.path'   => 'ffprobe',
			'timeout'        => 0
		], self::$options, $options);
		
		foreach ([
			'ffmpeg.path'  => $options['ffmpeg.path'],
			'ffprobe.path' => $options['ffprobe.path']
		] as $option => $binary)
		{
			try
			{
				$options[$option] = $this->hasFindExecutable($binary);
			}
			catch (ExecutableNotFoundException $exc)
			{
				if ($option != 'ffprobe.path')
				{
					throw $exc;
				}
				
				$options[$option] = null; // ffprobe not installed
			}
		}
		
		$this->setExecutor(new CommandExecutor($options));
	}
	
	/**
	 * Check filter.
	 *
	 * @param FilterInterface $filter
	 *
	 * @return bool
	 */
	public function hasSupportedFilter(FilterInterface $filter)
	{
		if ( ! $filter instanceof HandlerFilterInterface)
		{
			$observer = get_class($filter);
			$observer = __NAMESPACE__.'\\Filter\\'.substr($observer, strrpos($observer, '\\') + 1);
			
			if ( ! class_exists($observer))
			{
				return false;
			}
			
			$filter->attach(new $observer($filter));
		}
		
		return true;
	}
	
	/**
	 * Get supported encoders.
	 *
	 * @param int  $mask
	 * @param bool $strict
	 *
	 * @return array
	 */
	public function getSupportedCodecs($mask, $strict = false)
	{
		$results = [];
		
		if ( ! ($codecs = CacheStorage::get('supported.codecs', false)))
		{
			$codecs = CacheStorage::set('supported.codecs', $this->getAvailableCodecs());
		}
		
		foreach ((array) $codecs as $codec => $value)
		{
			if ($strict)
			{
				if ($mask <= ($value & $mask))
				{
					$results[] = $codec;
				}
			}
			else if ($value & $mask)
			{
				$results[] = $codec;
			}
		}
		
		return $results;
	}
	
	/**
	 * Supported encoder etc.
	 *
	 * @param string $codecString
	 *
	 * @return int  bit mask or FALSE
	 */
	public function hasSupportedCodec($codecString)
	{
		return true;
	}
	
	/**
	 * The adapter initialize.
	 *
	 * @param TranscoderInterface $media
	 *
	 * @return void
	 */
	public function initialize(TranscoderInterface $media)
	{
		$parsed = $this->executor->parse($media->getFilePath());
		
		if ( ! $parsed->format)
		{
			if ($media instanceof AudioInterface)
			{
				$parsed->format = new \Arhitector\Transcoder\Format\SimpleAudio();
			}
			else if ($media instanceof VideoInterface)
			{
				$parsed->format = new \Arhitector\Transcoder\Format\SimpleVideo();
			}
			else if ($media instanceof SubtitleInterface)
			{
				$parsed->format = new \Arhitector\Transcoder\Format\SimpleSubtitle();
			}
		}
		
		$this->setFormat($parsed->format);
		
		foreach ($parsed->properties as $property => $value)
		{
			$media[$property] = $value;
		}
		
		foreach ((array) $parsed->streams as $stream)
		{
			$stream->setAdapter($this);
		}
		
		$this->setStreams(\SplFixedArray::fromArray($parsed->streams));
	}
	
	/**
	 * Transcoding.
	 *
	 * @param TranscoderInterface $media
	 * @param FormatInterface     $format
	 * @param \SplPriorityQueue   $filters
	 *
	 * @return \Iterator|Process[]
	 */
	public function transcode(TranscoderInterface $media, FormatInterface $format, \SplPriorityQueue $filters)
	{
		$options_ = new Options(['y', 'i' => [$media->getFilePath()], 'strict' => '-2']);
		$options_->merge($this->getFormatOptions($format));
		$options_->merge($this->getForceFormatOptions($format));
		
		foreach ($filters as $filter)
		{
			$options_->replace($filter->apply($media, $format));
		}
		
		if ( ! isset($options_['output']))
		{
			throw new \RuntimeException('Output file path not found.');
		}
		
		$filePath = $options_['output'];
		$options_->replace($this->getStreamOptions($this->getStreams(), $options_));
		$options = $options_->withoutTranscoderOptions();
		
		foreach ([
			'disable_audio'          => '-an',
			'audio_quality'          => '-qscale:a',
			'audio_codec'            => '-codec:a',
			'audio_bitrate'          => '-b:a',
			'audio_sample_frequency' => '-ar',
			'audio_channels'         => '-ac',
			'disable_video'          => '-vn',
			'video_quality'          => '-qscale:v',
			'video_codec'            => '-codec:v',
			'video_aspect_ratio'     => '-aspect',
			'video_frame_rate'       => '-r',
			'video_max_frames'       => '-vframes',
			'video_bitrate'          => '-b:v',
			'video_pixel_format'     => '-pix_fmt',
			'metadata'               => '-metadata',
			'ffmpeg_force_format'    => '-f',
			'ffmpeg_video_filters'   => (sizeof($options_['i']) > 1 ? '-filter_complex:v' : '-filter:v'),
			'ffmpeg_audio_filters'   => (sizeof($options_['i']) > 1 ? '-filter_complex:a' : '-filter:a')
		] as $option => $value)
		{
			if (isset($options_[$option]))
			{
				$options[$value] = $options_[$option];
				
				if (is_bool($options_[$option]))
				{
					$options[$value] = '';
				}
			}
		}
		
		unset($options['ffmpeg_force_format'], $options['ffmpeg_video_filters'], $options['ffmpeg_audio_filters']);
		
		if ( ! empty($options['metadata']))
		{
			$options['map_metadata'] = '-1';
		}
		
		$options_ = [];
		
		foreach ($options as $option => $value)
		{
			$options_[] = '-'.$option;
			
			if (stripos($option, 'filter') !== false)
			{
				$options_[] = implode(', ', (array) $value);
			}
			else if (is_array($value))
			{
				array_pop($options_);
				
				foreach ($value as $key => $val)
				{
					$options_[] = '-'.$option;
					$options_[] = is_integer($key) ? $val : "{$key}={$val}";
				}
				
			}
			else if ($value)
			{
				$options_[] = $value;
			}
		}
		
		if ($format->getPasses() > 1)
		{
			$filesystem = new TemporaryPath('transcoder');
			$options_[] = '-passlogfile';
			$options_[] = $filesystem->getPath().'ffmpeg.passlog';
		}
		
		$totalDuration = $this->getFormat()
			->getDuration();
		
		if (isset($options['t']))
		{
			$totalDuration = $options['t'];
			
			if ( ! is_numeric($totalDuration))
			{
				$matches = array_reverse(explode(":", $totalDuration));
				$totalDuration = (float) array_shift($matches);
				
				foreach ($matches as $key => $value)
				{
					$totalDuration += (int) $value * 60 * ($key + 1);
				}
			}
		}
		
		for ($pass = 1; $pass <= $format->getPasses(); ++$pass)
		{
			$options = $options_;
			
			if ($format->getPasses() > 1)
			{
				$options[] = '-pass';
				$options[] = $pass;
			}
			
			$options[] = $filePath;
			$process = $this->executor->executeAsync($options);
			$process->wait(function ($type, $data) use ($process, $format, $totalDuration, $pass) {
				if (preg_match('/size=(.*?) time=(.*?) /', $data, $matches))
				{
					$matches[2] = array_reverse(explode(":", $matches[2]));
					$duration = (float) array_shift($matches[2]);
					
					foreach ($matches[2] as $key => $value)
					{
						$duration += (int) $value * 60 * ($key + 1);
					}
					
					$format->emit('progress', $process, $totalDuration, $duration, (int) trim($matches[1]),
						$format->getPasses(), $pass);
				}
			});
			
			yield $process;
		}
	}
	
	/**
	 * Supported codecs.
	 *
	 * @return int[]
	 */
	protected function getAvailableCodecs()
	{
		$codecs = [];
		$bit = [
			'.' => 0,
			'A' => 1,
			'V' => 2,
			'S' => 4,
			'E' => 8,
			'D' => 16
		];
		
		foreach ([
			'encoders' => $this->executor->executeAsync(['-encoders']),
			'decoders' => $this->executor->executeAsync(['-decoders']),
			'codecs'   => $this->executor->executeAsync(['-codecs'])
		] as $type => $process)
		{
			while ($process->getStatus() !== Process::STATUS_TERMINATED)
			{
				usleep(200000);
			}
			
			if (preg_match_all('/\s([VASFXBDEIL\.]{6})\s(\S{3,20})\s/', $process->getOutput(), $matches))
			{
				if ($type == 'encoders')
				{
					foreach ($matches[2] as $key => $value)
					{
						$codecs[$value] = $bit[$matches[1][$key]{0}] | $bit['E'];
					}
				}
				else if ($type == 'decoders')
				{
					foreach ($matches[2] as $key => $value)
					{
						$codecs[$value] = $bit[$matches[1][$key]{0}] | $bit['D'];
					}
				}
				else // codecs, encoders + decoders
				{
					foreach ($matches[2] as $key => $value)
					{
						$key = $matches[1][$key];
						$codecs[$value] = $bit[$key{2}] | $bit[$key{0}] | $bit[$key{1}];
					}
				}
			}
		}
		
		return $codecs;
	}
	
	/**
	 * Set Executor instance.
	 *
	 * @param CommandExecutor $instance
	 *
	 * @return Adapter
	 */
	protected function setExecutor(CommandExecutor $instance)
	{
		$this->executor = $instance;
		
		return $this;
	}
	
}
