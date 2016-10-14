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

use Arhitector\Transcoder\Exception\ExecutableNotFoundException;
use Arhitector\Transcoder\Exception\ExecutionFailureException;
use Arhitector\Transcoder\FFMpeg\Parser\FFProbe;
use Arhitector\Transcoder\FFMpeg\Parser\ParserInterface;
use Arhitector\Transcoder\MediaInterface;
use Arhitector\Transcoder\Process;
use Symfony\Component\Process\ProcessBuilder as SymfonyProcessBuilder;

/**
 * Class Executor
 *
 * @package Arhitector\Transcoder\FFMpeg
 */
class Executor
{
	
	/**
	 * @var array Configurations.
	 */
	protected $options;
	
	/**
	 * @var ParserInterface Parser.
	 */
	protected $parser;
	
	/**
	 * Executor constructor.
	 *
	 * @param array $options
	 */
	public function __construct(array $options = [])
	{
		$this->setOptions($options);
		
		if (empty($options['ffprobe.path']))
		{
			throw new ExecutableNotFoundException('Executable not found, proposed: ffprobe.', 'ffprobe.path');
		}
		
		$this->setParser(new FFProbe());
	}
	
	/**
	 * Get parser instance.
	 *
	 * @return ParserInterface
	 */
	public function getParser()
	{
		return $this->parser;
	}
	
	/**
	 * Execute command line.
	 *
	 * @param array|ProcessBuilder $options
	 * @param callable             $callback
	 *
	 * @return Process
	 */
	public function execute($options, callable $callback = null)
	{
		$process = $this->executeAsync($options, $callback);
		$process->wait();
		
		return $process;
	}
	
	/**
	 * Run command line.
	 *
	 * @param array|ProcessBuilder $options
	 * @param callable             $callback
	 *
	 * @return Process
	 */
	public function executeAsync($options, callable $callback = null)
	{
		$process = $this->ensureProcess($options);

		try
		{
			$process->start($callback);
		}
		catch (\Exception $exc)
		{
			throw new ExecutionFailureException($exc->getMessage(), $process, $exc->getCode(), $exc);
		}
		
		return $process;
	}
	
	/**
	 * Get the option value.
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function getOption($key)
	{
		if (array_key_exists($key, $this->options))
		{
			return $this->options[$key];
		}
		
		return null;
	}
	
	/**
	 * Replace options.
	 *
	 * @param array $options
	 *
	 * @return Executor
	 */
	protected function setOptions(array $options)
	{
		$this->options = $options;
		
		return $this;
	}
	
	/**
	 * Set parser
	 *
	 * @param ParserInterface $parser
	 *
	 * @return Executor
	 */
	protected function setParser(ParserInterface $parser)
	{
		$this->parser = $parser;
		
		return $this;
	}
	
	/**
	 * Process builder.
	 *
	 * @param array|ProcessBuilder $command
	 *
	 * @return Process
	 */
	protected function ensureProcess($command)
	{
		if (is_array($command))
		{
			if ($this->getOption('ffmpeg.threads') !== null && ! in_array('-threads', $command))
			{
				if (($input = array_search('-i', $command)))
				{
					array_splice($command, $input + 2, 0, ['-threads', (int) $this->getOption('ffmpeg.threads')]);
				}
			}
			
			$command = new ProcessBuilder($command);
			$command->setPrefix($this->getOption('ffmpeg.path'));
		}
		
		if ($command instanceof ProcessBuilder)
		{
			if ( ! $command->getPrefix())
			{
				$command->setPrefix($this->getOption('ffmpeg.path'));
			}
			
			return $command->getProcess();
		}
		
		if ( ! $command instanceof SymfonyProcessBuilder)
		{
			throw new \InvalidArgumentException("The options must be an array or instance of 'ProcessBuilder'.");
		}
		
		return new Process($command);
	}
	
}
