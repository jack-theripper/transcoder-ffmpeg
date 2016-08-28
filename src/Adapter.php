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
use Arhitector\Transcoder\Format\SimpleAudio;
use Arhitector\Transcoder\Format\SimpleSubtitle;
use Arhitector\Transcoder\Format\SimpleVideo;
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
	 * @var Executor
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
		], $options);
		
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
		
		$this->setExecutor(new Executor($options));
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
		// TODO: Implement getSupportedCodecs() method.
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
		
		if ( ! isset($parsed['format']))
		{
			if ($media instanceof AudioInterface)
			{
				$this->setFormat(new SimpleAudio());
			}
			else if ($media instanceof VideoInterface)
			{
				$this->setFormat(new SimpleVideo());
			}
			else if ($media instanceof SubtitleInterface)
			{
				$this->setFormat(new SimpleSubtitle());
			}
			else
			{
				throw new \RuntimeException('The format not found.');
			}
		}
		else
		{
			$this->setFormat($parsed['format']);
		}
		
		if (isset($parsed['properties']))
		{
			foreach ($parsed['properties'] as $property => $value)
			{
				$media[$property] = $value;
			}
		}
		
		if ( ! isset($parsed['streams']))
		{
			$parsed['streams'];
		}
		
		foreach ($parsed['streams'] as $stream)
		{
			$stream->injectExecutor($this->executor);
		}
		
		$this->setStreams(\SplFixedArray::fromArray($parsed['streams']));
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
			'A' => TranscoderInterface::CODEC_AUDIO,
			'V' => TranscoderInterface::CODEC_VIDEO,
			'S' => TranscoderInterface::CODEC_SUBTITLE,
			'E' => TranscoderInterface::CODEC_ENCODER,
			'D' => TranscoderInterface::CODEC_DECODER
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
