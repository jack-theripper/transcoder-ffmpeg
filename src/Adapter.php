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
namespace Arhitector\Transcoder\Adapter\FFMpeg;

use Arhitector\Transcoder\Adapter\AdapterInterface;
use Arhitector\Transcoder\Adapter\AdapterTrait;
use Arhitector\Transcoder\Adapter\SharedPreferences;
use Arhitector\Transcoder\Codec;
use Arhitector\Transcoder\Exception\ExecutableNotFoundException;
use Arhitector\Transcoder\Exception\TranscoderException;
use Arhitector\Transcoder\Filter\Filters;
use Arhitector\Transcoder\Format\FormatInterface;
use Arhitector\Transcoder\MediaInterface;
use Arhitector\Transcoder\Process;
use Arhitector\Transcoder\Stream\Streams;
use Arhitector\Transcoder\TranscodeInterface;
use Iterator;

/**
 * Class Adapter
 *
 * @package Arhitector\Transcoder\Adapter\FFMpeg
 */
class Adapter implements AdapterInterface
{
	use AdapterTrait;
	
	/**
	 * @var Executor The Executor instance.
	 */
	protected $executor;
	
	/**
	 * Adapter constructor.
	 *
	 * @param string[] $options
	 */
	public function __construct(array $options = [])
	{
		$options = array_merge([
			'ffmpeg.path'    => 'ffmpeg',
			'ffmpeg.threads' => null,
			'ffprobe.path'   => 'ffprobe',
			'timeout'        => 0
		], SharedPreferences::get('ffmpeg.global', []), $options);
		
		foreach (
			[
				'ffmpeg.path'  => $options['ffmpeg.path'],
				'ffprobe.path' => $options['ffprobe.path']
			] as $option => $binary
		)
		{
			try
			{
				$options[$option] = $this->findExecutableFile($binary);
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
		
		$this->setExecutor(new Executor($options));
	}
	
	/**
	 * Check whether the codec supports.
	 *
	 * @param string|Codec $codec
	 *
	 * @return bool
	 */
	public function hasSupportedCodec($codec)
	{
		// TODO: Implement hasSupportedCodec() method.
	}
	
	/**
	 * The adapter initialize.
	 *
	 * @param TranscodeInterface $media
	 *
	 * @return void
	 */
	public function initialize(TranscodeInterface $media)
	{
		/**
		 * @var MediaInterface $media
		 */
		$parsed = array_merge([
			'format'     => [],
			'streams'    => [],
			'properties' => []
		], $this->executor->parse($media));
		
		if ( ! empty($parsed['error']))
		{
			throw new TranscoderException($parsed['error']);
		}
		
		$format     = $this->findFormatClass($media);
		$format     = new $format();
		$reflection = new \ReflectionClass($format);
		
		$method = $reflection->getMethod('setBitRate');
		$method->setAccessible(true);
		$method->invoke($format, $parsed['format']['bit_rate']);
		
		$method = $reflection->getMethod('setDuration');
		$method->setAccessible(true);
		$method->invoke($format, $parsed['format']['duration']);
		
		$property = $reflection->getProperty('name');
		$property->setAccessible(true);
		$property->setValue($format, $parsed['format']['name']);
		
		$this->setFormat($format);
		
		foreach ($parsed['properties'] as $property => $value)
		{
			$media[$property] = $value;
		}
		
		$this->setStreams(new Streams((array) $parsed['streams']));
	}
	
	/**
	 * Constructs and returns the iterator with instances of 'Process'.
	 * If the value $media instance of 'TranscoderInterface' then it is full media or instance of 'StreamInterface'
	 * then it is stream.
	 *
	 * @param MediaInterface  $media   it may be a stream or media wrapper.
	 * @param FormatInterface $format  new format.
	 * @param Filters         $filters list of filters.
	 *
	 * @return Iterator|Process[]  returns the instances of 'Process'.
	 */
	public function transcode(MediaInterface $media, FormatInterface $format, Filters $filters)
	{
		$options_ = array_merge([
			'y',
			'input'  => [$media->getFilePath(),],
			'strict' => -2
		], []);
		
		foreach ($filters as $filter)
		{
			$options_ = array_replace_recursive($options_, $filter->apply($media, $format));
		}
		
		if ( ! isset($options_['output']))
		{
			throw new TranscoderException('Output file path not found.');
		}
		
		$filePath = $options_['output'];
		$options  = array_diff_key($options_, array_fill_keys(array_merge([
			'ffmpeg_force_format',
			'ffmpeg_video_filters',
			'ffmpeg_audio_filters'
		], Process::getInternalOptions()), null));
		
		foreach (
			[
				'input'                  => 'i',
				//'output'                 => '',
				'audio_disable'          => 'an',
				'audio_quality'          => 'qscale:a',
				'audio_codec'            => 'codec:a',
				'audio_bitrate'          => 'b:a',
				'audio_sample_frequency' => 'ar',
				'audio_channels'         => 'ac',
				//'audio_volume',
				'video_disable'          => 'vn',
				'video_quality'          => 'qscale:v',
				'video_codec'            => 'codec:v',
				'video_aspect_ratio'     => 'aspect',
				'video_frame_rate'       => 'r',
				'video_max_frames'       => 'vframes',
				'video_bitrate'          => 'b:v',
				'video_pixel_format'     => 'pix_fmt',
				//'force_format',
				//'metadata'               => 'metadata',
				'ffmpeg_force_format'    => '-f',
				'ffmpeg_video_filters'   => (sizeof($options_['input']) > 1 ? '-filter_complex:v' : '-filter:v'),
				'ffmpeg_audio_filters'   => (sizeof($options_['input']) > 1 ? '-filter_complex:a' : '-filter:a')
			] as $option => $value
		)
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
		
		$options_ = new ProcessBuilder($options);
		$options_->add($filePath);
		$options_->setPrefix($this->executor->getOption('ffmpeg.path'));
		
		return [$this->executor->executeAsync($options_)];
	}
	
	/**
	 * Set Executor instance.
	 *
	 * @param Executor $instance
	 *
	 * @return Adapter
	 */
	protected function setExecutor(Executor $instance)
	{
		$this->executor = $instance;
		
		return $this;
	}
	
}
