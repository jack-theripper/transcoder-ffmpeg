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

use Arhitector\Transcoder\Exception\ExecutionFailureException;
use Arhitector\Transcoder\FFMpeg\Parser\FFMpeg;
use Arhitector\Transcoder\FFMpeg\Parser\FFProbe;
use Arhitector\Transcoder\FFMpeg\Parser\ParserInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

/**
 * Class Executor
 *
 * @package Arhitector\Transcoder\FFMpeg
 */
class CommandExecutor
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
		
		if ( ! empty($options['ffprobe.path']))
		{
			$this->setParser(new FFProbe($options['ffprobe.path']));
		}
		else
		{
			$this->setParser(new FFMpeg($options['ffmpeg.path']));
		}
	}
	
	/**
	 * Get parsed data from file.
	 *
	 * @param $filePath
	 *
	 * @return \stdClass
	 * @throws \RuntimeException
	 */
	public function parse($filePath)
	{
		$parsed = array_merge([
			'format'     => null,
			'streams'    => null,
			'properties' => []
		], $this->parser->parse($filePath));
		
		if ( ! empty($parsed['error']))
		{
			throw new \RuntimeException($parsed['error']);
		}
		
		return (object) $parsed;
	}
	
	/**
	 * Execute ffmpeg command.
	 *
	 * @param string[]|ProcessBuilder $options
	 * @param callable                $callback
	 *
	 * @return Process
	 */
	public function execute($options, callable $callback = null)
	{
		$this->executeAsync($options)
			->wait($callback);
	}
	
	/**
	 * Run command line.
	 *
	 * @param string[]|ProcessBuilder $options
	 * @param callable                $callback
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
	 * Process builder.
	 *
	 * @param string[]|ProcessBuilder $builder
	 *
	 * @return Process
	 */
	protected function ensureProcess($builder)
	{
		if ( ! $builder instanceof ProcessBuilder)
		{
			$builder = new ProcessBuilder($builder);
		}
		
		return $builder->setPrefix($this->options['ffmpeg.path'])
			->setTimeout($this->options['timeout'])
			->getProcess();
	}
	
	/**
	 * Set parser
	 *
	 * @param ParserInterface $parser
	 *
	 * @return CommandExecutor
	 */
	protected function setParser(ParserInterface $parser)
	{
		$this->parser = $parser;
		
		return $this;
	}
	
	/**
	 * Replace options.
	 *
	 * @param array $options
	 *
	 * @return CommandExecutor
	 */
	protected function setOptions(array $options)
	{
		$this->options = $options;
		
		return $this;
	}
	
}
