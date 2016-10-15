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

use Arhitector\Transcoder\Adapter\AdapterInterface;
use Arhitector\Transcoder\Adapter\AdapterTrait;
use Arhitector\Transcoder\Adapter\SharedPreferences;
use Arhitector\Transcoder\Adapter\TemporaryPath;
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
 * @package Arhitector\Transcoder\FFMpeg
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
		], $this->executor->getParser()
			->parse($media, $this->executor));
		
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
			'y'      => null,
			'input'  => [$media->getFilePath()],
			'strict' => -2
		], $this->getFormatOptions($format), $this->getForceFormatOptions($format));
		
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
			'ffmpeg_video_filters',
			'ffmpeg_audio_filters',
			'ffmpeg_seek_start',
			'ffmpeg_seek_output'
		], Process::getInternalOptions()), null));
		
		foreach (
			[
				'input'                  => 'i',
				'audio_disable'          => 'an',
				'audio_quality'          => 'qscale:a',
				'audio_codec'            => 'codec:a',
				'audio_bitrate'          => 'b:a',
				'audio_sample_frequency' => 'ar',
				'audio_channels'         => 'ac',
				'video_disable'          => 'vn',
				'video_quality'          => 'qscale:v',
				'video_codec'            => 'codec:v',
				'video_aspect_ratio'     => 'aspect',
				'video_frame_rate'       => 'r',
				'video_max_frames'       => 'vframes',
				'video_bitrate'          => 'b:v',
				'video_pixel_format'     => 'pix_fmt',
				'force_format'           => 'f',
				'metadata'               => 'metadata',
				'ffmpeg_seek_start'      => 'ss',
				'ffmpeg_seek_output'     => '-ss',
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
		
		if ( ! empty($options['metadata']))
		{
			$options['map_metadata'] = '-1';
			
			foreach ($options['metadata'] as $key => $value)
			{
				$options['metadata'][$key] = $key.' = '.$value;
			}
		}
		
		$options = array_intersect_key(array_merge(array_fill_keys(['y', 'ss', 'i'], null), $options), $options);
		$options_ = new ProcessBuilder($options);
		
		if ($format->getPasses() > 1)
		{
			$filesystem = new TemporaryPath('transcoder');
			$options_->add('-passlogfile');
			$options_->add($filesystem->getPath().'ffmpeg.passlog');
		}
		
		for ($pass = 1; $pass <= $format->getPasses(); ++$pass)
		{
			$options = clone $options_;
			
			if ($format->getPasses() > 1)
			{
				$options->add('-pass');
				$options->add($pass);
			}
			
			$options->add($filePath);
			
			yield $this->executor->executeAsync($options);
		}
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
	
	/**
	 * Get options for format.
	 *
	 * @param FormatInterface $format
	 *
	 * @return array
	 */
	protected function getFormatOptions(FormatInterface $format)
	{
		$options = [];
		
		if ($format instanceof \Arhitector\Transcoder\Format\AudioFormatInterface)
		{
			$options['audio_codec'] = $format->getAudioCodecString() ?: 'copy';
			$options['video_codec'] = 'copy';
			
			if ($format->getAudioKiloBitRate() > 0)
			{
				$options['audio_bitrate'] = $format->getAudioKiloBitRate().'k';
			}
			
			if ($format->getAudioFrequency() > 0)
			{
				$options['audio_sample_frequency'] = $format->getAudioFrequency();
			}
			
			if ($format->getAudioChannels() > 0)
			{
				$options['audio_channels'] = $format->getAudioChannels();
			}
		}
		
		if ($format instanceof \Arhitector\Transcoder\Format\VideoFormatInterface)
		{
			$options['video_codec'] = $format->getVideoCodecString() ?: 'copy';
			
			if ($format->getVideoFrameRate() > 0)
			{
				$options['video_frame_rate'] = $format->getVideoFrameRate();
			}
			
			if ($format->getVideoKiloBitRate() > 0)
			{
				$options['video_bitrate'] = $format->getVideoKiloBitRate().'k';
			}
			
			$options['refs']         = 6;
			$options['coder']        = 1;
			$options['sc_threshold'] = 40;
			$options['flags']        = '+loop';
			$options['me_range']     = 16;
			$options['subq']         = 7;
			$options['i_qfactor']    = 0.71;
			$options['qcomp']        = 0.6;
			$options['qdiff']        = 4;
			$options['trellis']      = 1;
		}
		
		return $options;
	}
	
	/**
	 * Get force format options.
	 *
	 * @param $format
	 *
	 * @return array
	 */
	protected function getForceFormatOptions(FormatInterface $format)
	{
		return [];
	}
	
}
