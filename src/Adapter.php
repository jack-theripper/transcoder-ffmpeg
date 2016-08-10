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
use Arhitector\Transcoder\MediaInterface;
use Emgag\Flysystem\Tempdir;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Process\Process;

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
	public function getSupportedCodecs($mask, $strict = false)
	{
		$codecs = Cache::get('codecs', false);

		if ( ! $codecs)
		{
			Cache::set('codecs', $codecs = $this->getAvailableCodecs());
		}

		$matches = [];
		
		foreach ((array) $codecs as $codec => $value)
		{
			if ($strict)
			{
				if ($mask <= ($value & $mask))
				{
					$matches[] = $codec;
				}
			}
			else if ($value & $mask)
			{
				$matches[] = $codec;
			}
		}
		
		return $matches;
	}

	/**
	 * Transcoding.
	 *
	 * @param Format\FormatInterface $format
	 * @param array                  $options
	 * @param string                 $filePath
	 *
	 * @return bool
	 * @throws \Exception
	 *
	 * @TODO add '-threads', and additional streams
	 */
	public function save(Format\FormatInterface $format, array $options, $filePath)
	{
		$commands = ['-y', '-i', $this->getFilePath()];
		$commands = array_merge($commands, $this->getForceFormatOptions($format));

		if ($format instanceof Format\AudioFormatInterface)
		{
			$commands = array_merge($commands, $this->getAudioFormatOptions($format));
		}

		if ($format instanceof Format\VideoFormatInterface)
		{
			$commands = array_merge($commands, $this->getVideoFormatOptions($format));
		}

		if ($this->getFormat()->isModified())
		{
			$commands['metadata'] = $this->getFormat()->getProperties();
			//$commands['map_metadata'] = '-1';
		}

		// @TODO add remove a key 'pass' or '-pass'
		foreach (array_diff($options, ['-pass', 'pass']) as $property => $value)
		{
			if (is_integer($property))
			{
				$commands[] = $value;
			}
			else
			{
				$commands[ltrim($property, '-')] = $value;
			}
		}

		$options = [];

		foreach ($commands as $property => $value)
		{
			if ( ! is_integer($property))
			{
				$options[] = $property = sprintf("-%s", ltrim($property, '-'));

				if (stripos($property, '-filter') !== false)
				{
					$items = [];

					foreach ($value as $index => $item)
					{
						$items = array_merge($items, (array) $item);
					}

					$options[] = implode(', ', $items);

					continue;
				}
			}

			if (is_array($value))
			{
				array_pop($options);

				foreach ($value as $index => $item)
				{
					$options[] = $property;
					$options[] = $index.'='.$item;
				}

				continue;
			}

			$options[] = $value;
		}

		if ($format->getPasses() > 1)
		{
			$filesystem = new Tempdir('transcoder');
			$options[] = '-passlogfile';
			$options[] = $filesystem->getPath().'ffmpeg.passlog';
		}

		for ($pass = 1; $pass <= $format->getPasses(); ++$pass)
		{
			$commands = $options;

			if ($format->getPasses() > 1)
			{
				$commands[] = '-pass';
				$commands[] = $pass;
			}

			$commands[] = $filePath;

			try
			{
				$this->executor->execute($commands);
			}
			catch (\Exception $exc)
			{
				throw $exc;
			}
		}

		return true;
	}

	/**
	 * Get video options.
	 *
	 * @param Format\VideoFormatInterface $format
	 *
	 * @return array
	 */
	protected function getVideoFormatOptions(Format\VideoFormatInterface $format)
	{
		$options['c:v'] = $format->getVideoCodecString() ?: 'copy';

		if ($format->getVideoFrameRate() > 0)
		{
			$options['r'] = $format->getVideoFrameRate();
		}

		if ($format->getVideoKiloBitRate() > 0)
		{
			$options['b:v'] = $format->getVideoKiloBitRate().'k';
		}

		return $options;
	}

	/**
	 * Get audio options.
	 *
	 * @param Format\AudioFormatInterface $format
	 *
	 * @return string[]
	 */
	protected function getAudioFormatOptions(Format\AudioFormatInterface $format)
	{
		$options['c:a'] = $format->getAudioCodecString() ?: 'copy';

		if ($format->getAudioKiloBitRate() > 0)
		{
			$options['b:a'] = $format->getAudioKiloBitRate().'k';
		}

		if ($format->getAudioFrequency() > 0)
		{
			$options['ar'] = $format->getAudioFrequency();
		}

		if ($format->getAudioChannels())
		{
			$options['ac'] = $format->getAudioChannels();
		}

		$options['c:v'] = 'copy';

		return $options;
	}

	/**
	 * Get option value.
	 *
	 * @param Format\FormatInterface $format
	 *
	 * @return string[]
	 */
	protected function getForceFormatOptions(Format\FormatInterface $format)
	{
		$alias = [
			'ThreeGP' => '3gp',
			'Aac'     => 'aac',
			'Wmv'     => 'asf',
			'Flac'    => 'flac',
			'Flv'     => 'flv',
			'Gif'     => 'gif',
			'H264'    => 'h264',
			'Mp4'     => 'mp4',
			'Jpeg'    => 'image2',
			'WebM'    => 'webm',
			'Oga'     => 'oga',
			'Ogg'     => 'ogg',
			'mp3'     => 'mpeg',
			'Wav'     => 'wav'
		];

		$class_name = basename(get_class($format));

		if (isset($alias[$class_name]))
		{
			return ['f' => $alias[$class_name]];
		}

		return [];
	}

	/**
	 * FFMpeg codecs.
	 *
	 * @return int[]
	 */
	protected function getAvailableCodecs()
	{
		$codecs = [];
		$bit = [
			'.' => 0,
			'A' => MediaInterface::CODEC_AUDIO,
			'V' => MediaInterface::CODEC_VIDEO,
			'S' => MediaInterface::CODEC_SUBTITLE,
			'E' => MediaInterface::CODEC_ENCODER,
			'D' => MediaInterface::CODEC_DECODER
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
				switch ($type)
				{
					case 'encoders':

						foreach ($matches[2] as $key => $value)
						{
							$codecs[$value] = $bit[$matches[1][$key]{0}] | $bit['E'];
						}

					break;

					case 'decoders':

						foreach ($matches[2] as $key => $value)
						{
							$codecs[$value] = $bit[$matches[1][$key]{0}] | $bit['D'];
						}

					break;

					case 'codecs': // codecs, encoders + decoders

						foreach ($matches[2] as $key => $value)
						{
							$key = $matches[1][$key];
							$codecs[$value] = $bit[$key{2}] | $bit[$key{0}] | $bit[$key{1}];
						}

					break;
				}
			}
		}

		return $codecs;
	}
	
	/**
	 * Set file path.
	 *
	 * @param string $filePath
	 *
	 * @return Adapter
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
				$stream->setFilePath($this->getFilePath());
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
