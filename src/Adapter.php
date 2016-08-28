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
	 * Sets Media file instance.
	 *
	 * @param TranscoderInterface $media
	 *
	 * @return Adapter
	 */
	public function inject(TranscoderInterface $media)
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
		$commands = array_merge(['y' => '', 'i' => [$this->getFilePath()]], $this->getForceFormatOption($format));

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
			$commands['map_metadata'] = '-1';
			$commands['metadata'] = $this->getFormat()
				->getProperties();
		}

		$unifyOptions = [];

		foreach ($options as $property => $value)
		{
			if (is_integer($property))
			{
				$unifyOptions[$value] = '';

				continue;
			}
			
			$unifyOptions[$property] = $value;
		}

		unset($unifyOptions['pass'], $unifyOptions['passlogfile'], $unifyOptions['-pass']);

		$options = [];

		foreach (array_replace_recursive($commands, $unifyOptions) as $property => $value)
		{
			$options[] = "-{$property}";

			if (stripos($property, 'filter') !== false)
			{
				$options[] = implode(', ', $value);
			}
			else if (is_array($value))
			{
				array_pop($options);

				foreach ($value as $index => $item)
				{
					$options[] = "-{$property}";

					if ( ! is_integer($index))
					{
						$item = $index.' = '.$item;
					}

					$options[] = $item;
				}
			}
			else if ($value != '')
			{
				$options[] = $value;
			}
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
			$process = $this->executor->executeAsync($commands);
			$process->wait(function ($type, $data) use ($format, $pass) {
				if (preg_match('/frame=(.+)fps=(.+)q.+size=(.+)time=([\d\:\.]+)\s/i', $data, $matches))
				{
					$format->emit('progress', [
						'pass' => $pass,
						'duration' => $this->getFormat()->getDuration(),
						'fps' => (float) trim($matches[2]),
						'frame' => (int) trim($matches[1]),
						'remaining' => '',//gmdate('H:i:s.u', trim($matches[4])),
						'size' => (int) trim($matches[3])
					]);
				}
			});
		}
		
		return true;
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

			//	$this->streamsHash = (new Hash())->getHash($this->getStreams());
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
