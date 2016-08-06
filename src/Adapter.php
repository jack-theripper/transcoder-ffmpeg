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
use Arhitector\Transcoder\DecoratorTrait;
use Arhitector\Transcoder\Exception\InvalidFilterException;
use Arhitector\Transcoder\Filter\AdapterFilterInterface;
use Arhitector\Transcoder\Filter\FilterInterface;
use Arhitector\Transcoder\Format;
use Arhitector\Transcoder\Format\FormatInterface;
use Arhitector\Transcoder\MediaInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * FFMpeg adapter.
 *
 * @package Arhitector\Transcoder\FFMpeg
 */
class Adapter implements AdapterInterface
{
	use DecoratorTrait, AdapterTrait, OptionsTrait;

	/**
	 * @var Executor
	 */
	protected $executor;

	/**
	 * @var string  Full file path.
	 */
	protected $filePath;


	/**
	 * Adapter constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config = [])
	{
		$this->setExecutor(new Executor($this->configureOptions(new OptionsResolver, $config + self::$options)));
	}
	
	/**
	 * Sets Media file instance.
	 *
	 * @param MediaInterface $media
	 *
	 * @return Adapter
	 */
	public function inject(MediaInterface $media)
	{
		$this->setFilePath($media->getFilePath());
		$this->initialize();
		
		return $this;
	}
	
	/**
	 * Check filter.
	 *
	 * @param FilterInterface $filter
	 *
	 * @return FilterInterface
	 * @throws InvalidFilterException
	 */
	public function hasSupportedFilter(FilterInterface $filter)
	{
		$filterAdapter = get_class($filter);

		if (is_subclass_of($filterAdapter, AdapterFilterInterface::class))
		{
			return $filter;
		}

		$filterAdapter = __NAMESPACE__.'\\Filter\\'.substr($filterAdapter, strrpos($filterAdapter, '\\') + 1);

		if ( ! class_exists($filterAdapter))
		{
			throw new InvalidFilterException('The FFMpeg adapter does not support the filter.');
		}

		return $filter->inject(new $filterAdapter);
	}
	
	/**
	 * Get supported encoders.
	 *
	 * @param int  $mask
	 * @param bool $strict
	 *
	 * @return array
	 */
	public function getSupportedCodecs($mask = null, $strict = false)
	{
		if ($mask === null)
		{
			return $this->getAvailableCodecs();
		}
		
		$codecs = [];
		
		foreach ((array) $this->getAvailableCodecs() as $codec => $value)
		{
			if ($strict)
			{
				if ($mask <= ($value & $mask))
				{
					$codecs[] = $codec;
				}
			}
			else if ($value & $mask)
			{
				$codecs[] = $codec;
			}
		}
		
		return $codecs;
	}
	
	

	
	
	/**
	 * Transcoding.
	 *
	 * @param FormatInterface $format
	 * @param array           $options
	 * @param string          $filePath
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function save(FormatInterface $format, array $options, $filePath)
	{
		$commands = ['-y', '-i', $this->getFilePath()];




		if ($format instanceof Format\AudioFormatInterface)
		{
			if ($format->getAudioCodecString())
			{
				$commands[] = '-codec:a';
				$commands[] = $format->getAudioCodecString();
			}

			if ($format->getAudioKiloBitRate())
			{
				$commands[] = '-ab';
				$commands[] = $format->getAudioKiloBitRate().'k';
			}

			if ($format->getAudioFrequency())
			{
				$commands[] = '-ar';
				$commands[] = $format->getAudioFrequency();
			}

			if ($format->getAudioChannels())
			{
				$commands[] = '-ac';
				$commands[] = $format->getAudioChannels();
			}
		}

		if ($format instanceof Format\VideoFormatInterface)
		{
			$commands[] = '-codec:v';
			$commands[] = $format->getVideoCodecString() ?: 'copy';
		}


		if ($format->getPasses() > 1)
		{
			$pass_log_file = __DIR__.'/'.uniqid('transcoder-pass');

			$commands[] = '-passlogfile';
			$commands[] = $pass_log_file;
		}

		$forceFormat = $this->getForceFormatString($format);

		if ($forceFormat)
		{
			$commands[] = '-f';
			$commands[] = $forceFormat;
		}


		for ($i = 1; $i <= $format->getPasses(); ++$i)
		{
			$commands_pass = $commands;

			$commands_pass[] = '-pass';
			$commands_pass[] = $i;


			$commands_pass[] = $filePath;

			try
			{
				$this->executor->execute($commands_pass);
			}
			catch (\Exception $exc)
			{
				throw $exc;
			}
		}





		
		/*if ( ! empty($this->'threads', false))
		{
			$commands[] = '-threads';
			$commands[] = $this->executor->getOption('threads');
		}*/
		
		
	}

	/**
	 * Get option value.
	 *
	 * @param FormatInterface $format
	 *
	 * @return null|string
	 */
	protected function getForceFormatString(FormatInterface $format)
	{
		$option = null;

		if ($format instanceof Format\Wmv)
		{
			$option = 'asf';
		}

		return $option;
	}

	/**
	 * FFMpeg codecs.
	 *
	 * @return array
	 */
	protected function getAvailableCodecs()
	{
		$codecs = Cache::get('codecs');

		if (sizeof($codecs) < 1)
		{
			$regex = '/\s([VASFXBDEIL\.]{6})\s(\S{3,20})\s/';
			$bit = [
				'.' => 0,
				'A' => MediaInterface::CODEC_AUDIO,
				'V' => MediaInterface::CODEC_VIDEO,
				'S' => MediaInterface::CODEC_SUBTITLE,
				'E' => MediaInterface::CODEC_ENCODER,
				'D' => MediaInterface::CODEC_DECODER
			];

			// encoders
			if (preg_match_all($regex, $this->executor->execute(['-encoders'])
				->getOutput(), $matches))
			{
				foreach ($matches[2] as $key => $value)
				{
					$codecs[$value] = $bit[$matches[1][$key]{0}] | 64;
				}
			}

			// decoders
			if (preg_match_all($regex, $this->executor->execute(['-decoders'])
				->getOutput(), $matches))
			{
				foreach ($matches[2] as $key => $value)
				{
					$codecs[$value] = $bit[$matches[1][$key]{0}] | 128;
				}
			}

			// codecs, encoders + decoders
			if (preg_match_all($regex, $this->executor->execute(['-codecs'])
				->getOutput(), $matches))
			{
				foreach ($matches[2] as $key => $value)
				{
					$key = $matches[1][$key];
					$codecs[$value] = $bit[$key{2}] | $bit[$key{0}] | $bit[$key{1}];
				}
			}

			Cache::set('codecs', $codecs);
		}

		return $codecs;
	}
	
	/**
	 * Set file path.
	 *
	 * @param string $filePath
	 *
	 * @return $this
	 */
	protected function setFilePath($filePath)
	{
		if ( ! is_string($filePath))
		{
			throw new \InvalidArgumentException('File path must be a string type.');
		}
		
		$filePath = realpath($filePath);
		
		if ( ! is_file($filePath))
		{
			throw new \RuntimeException('File path not found.');
		}
		
		$this->filePath = $filePath;
		
		return $this;
	}
	
	/**
	 * Configure options.
	 *
	 * @param OptionsResolver $resolver
	 * @param array           $options
	 *
	 * @return array
	 */
	protected function configureOptions(OptionsResolver $resolver, array $options)
	{
		$options = $resolver->setDefaults([
			'ffmpeg.path'    => 'ffmpeg',
			'ffmpeg.threads' => null,
			'ffprobe.path'   => 'ffprobe',
			'timeout'        => 0
		])
			->resolve($options);
		
		return $this->hasExecutable([
			'ffmpeg.path'  => $options['ffmpeg.path'],
			'ffprobe.path' => $options['ffprobe.path']
		]) + $options;
	}
	
	/**
	 * Get full file path.
	 *
	 * @return  string
	 */
	protected function getFilePath()
	{
		return $this->filePath;
	}
	
	/**
	 * Initialize.
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function initialize()
	{
		try
		{
			$parsed = $this->executor->parse($this->getFilePath());
			
			foreach ($parsed->streams ?: [] as $stream)
			{
				$stream->inject($this->executor);
			}
			
			$this->setFormat($parsed->format ?: new Format);
			$this->setStreams(\SplFixedArray::fromArray($parsed->streams ?: []));
		}
		catch (\Exception $exc)
		{
			throw $exc;
		}
		
		return true;
	}
	
	/**
	 * Set instance executor.
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