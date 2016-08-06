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
use Arhitector\Transcoder\FFMpeg\Parser\FFProbe;
use Arhitector\Transcoder\FFMpeg\Parser\ParserInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

/**
 * Class Executor.
 *
 * @package Arhitector\Transcoder\FFMpeg
 */
class Executor
{

	/**
	 * @var OptionsResolver Configurations.
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
		$this->setParser(new FFProbe);
	}

	/**
	 * Get parsed data from file.
	 *
	 * @param $filePath
	 *
	 * @return \stdClass
	 * @throws \InvalidArgumentException
	 */
	public function parse($filePath)
	{
		$parsed = $this->parser->parse($this->parser->read($filePath));

		if (isset($parsed->error))
		{
			throw new \InvalidArgumentException($parsed->error);
		}

		return $parsed;
	}

	/**
	 * Execute ffmpeg command.
	 *
	 * @param $command
	 *
	 * @return Process
	 * @throws ExecutionFailureException
	 */
	public function execute($command)
	{
		if (is_array($command))
		{
			$command = new ProcessBuilder($command);
		}

		$process = $command->setPrefix($this->options['ffmpeg.path'])
			->setTimeout(0)
			->getProcess();

		try
		{
			//echo $process->getCommandLine();

			$process->mustRun(function ($type, $buffer) {



			});
		}
		catch (\Exception $exc)
		{
			throw new ExecutionFailureException($exc->getMessage(), $process, $exc->getCode(), $exc);
		}

		return $process;
	}

	/**
	 * Set parser
	 *
	 * @param ParserInterface $parser
	 *
	 * @return Adapter
	 */
	protected function setParser(ParserInterface $parser)
	{
		$this->parser = $parser;
		$this->parser->setBinaryPath($this->options['ffprobe.path']);

		return $this;
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

}