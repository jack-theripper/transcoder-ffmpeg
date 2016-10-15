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

use Arhitector\Transcoder\Process;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Process\ProcessUtils;

/**
 * Class ProcessBuilder
 *
 * @package Arhitector\Transcoder\FFMpeg
 */
class ProcessBuilder extends \Symfony\Component\Process\ProcessBuilder
{
	
	/**
	 * @var array
	 */
	protected $arguments;
	
	/**
	 * @var string
	 */
	protected $cwd;
	
	/**
	 * @var array
	 */
	protected $env = [];
	
	/**
	 * @var static
	 */
	protected $input;
	
	/**
	 * @var int
	 */
	protected $timeout = 60;
	
	/**
	 * @var array
	 */
	protected $options = [];
	
	/**
	 * @var bool
	 */
	protected $inheritEnv = true;
	
	/**
	 * @var array
	 */
	protected $prefix = [];
	
	/**
	 * @var bool
	 */
	protected $outputDisabled = false;
	
	/**
	 * Constructor.
	 *
	 * @param string[] $arguments An array of arguments
	 */
	public function __construct(array $arguments = [])
	{
		foreach ($arguments as $key => $argument)
		{
			if (is_integer($key))
			{
				$this->add(sprintf("-%s", $argument));
				
				continue;
			}
			
			$this->add(sprintf("-%s", ltrim($key, '-')));
			$this->add($argument);
		}
		
		$this->setArguments(array_filter($this->arguments, function ($value) {
			return is_scalar($value) ? strlen($value) : ! empty($value);
		}));
	}
	
	/**
	 * Adds an unescaped argument to the command string.
	 *
	 * @param string $argument A command argument
	 *
	 * @return ProcessBuilder
	 */
	public function add($argument)
	{
		$this->arguments[] = $argument;
		
		return $this;
	}
	
	/**
	 * Adds a prefix to the command string.
	 *
	 * The prefix is preserved when resetting arguments.
	 *
	 * @param string|array $prefix A command prefix or an array of command prefixes
	 *
	 * @return ProcessBuilder
	 */
	public function setPrefix($prefix)
	{
		$this->prefix = is_array($prefix) ? $prefix : [$prefix];
		
		return $this;
	}
	
	/**
	 * Get the prefix value.
	 *
	 * @return array
	 */
	public function getPrefix()
	{
		return $this->prefix;
	}
	
	/**
	 * Sets the arguments of the process.
	 *
	 * Arguments must not be escaped.
	 * Previous arguments are removed.
	 *
	 * @param string[] $arguments
	 *
	 * @return ProcessBuilder
	 */
	public function setArguments(array $arguments)
	{
		$this->arguments = $arguments;
		
		return $this;
	}
	
	/**
	 * Sets the working directory.
	 *
	 * @param null|string $cwd The working directory
	 *
	 * @return ProcessBuilder
	 */
	public function setWorkingDirectory($cwd)
	{
		$this->cwd = $cwd;
		
		return $this;
	}
	
	/**
	 * Sets whether environment variables will be inherited or not.
	 *
	 * @param bool $inheritEnv
	 *
	 * @return ProcessBuilder
	 */
	public function inheritEnvironmentVariables($inheritEnv = true)
	{
		$this->inheritEnv = $inheritEnv;
		
		return $this;
	}
	
	/**
	 * Sets an environment variable.
	 *
	 * Setting a variable overrides its previous value. Use `null` to unset a
	 * defined environment variable.
	 *
	 * @param string      $name  The variable name
	 * @param null|string $value The variable value
	 *
	 * @return ProcessBuilder
	 */
	public function setEnv($name, $value)
	{
		$this->env[$name] = $value;
		
		return $this;
	}
	
	/**
	 * Adds a set of environment variables.
	 *
	 * Already existing environment variables with the same name will be
	 * overridden by the new values passed to this method. Pass `null` to unset
	 * a variable.
	 *
	 * @param array $variables The variables
	 *
	 * @return ProcessBuilder
	 */
	public function addEnvironmentVariables(array $variables)
	{
		$this->env = array_replace($this->env, $variables);
		
		return $this;
	}
	
	/**
	 * Sets the input of the process.
	 *
	 * @param resource|scalar|\Traversable|null $input The input content
	 *
	 * @return ProcessBuilder
	 * @throws InvalidArgumentException In case the argument is invalid
	 */
	public function setInput($input)
	{
		$this->input = ProcessUtils::validateInput(__METHOD__, $input);
		
		return $this;
	}
	
	/**
	 * Sets the process timeout.
	 *
	 * To disable the timeout, set this value to null.
	 *
	 * @param float|null $timeout
	 *
	 * @return ProcessBuilder
	 * @throws InvalidArgumentException
	 */
	public function setTimeout($timeout)
	{
		if (null === $timeout)
		{
			$this->timeout = null;
			
			return $this;
		}
		
		$timeout = (float) $timeout;
		
		if ($timeout < 0)
		{
			throw new InvalidArgumentException('The timeout value must be a valid positive integer or float number.');
		}
		
		$this->timeout = $timeout;
		
		return $this;
	}
	
	/**
	 * Adds a proc_open option.
	 *
	 * @param string $name  The option name
	 * @param string $value The option value
	 *
	 * @return ProcessBuilder
	 */
	public function setOption($name, $value)
	{
		$this->options[$name] = $value;
		
		return $this;
	}
	
	/**
	 * Disables fetching output and error output from the underlying process.
	 *
	 * @return ProcessBuilder
	 */
	public function disableOutput()
	{
		$this->outputDisabled = true;
		
		return $this;
	}
	
	/**
	 * Enables fetching output and error output from the underlying process.
	 *
	 * @return ProcessBuilder
	 */
	public function enableOutput()
	{
		$this->outputDisabled = false;
		
		return $this;
	}
	
	/**
	 * Creates a Process instance and returns it.
	 *
	 * @return Process
	 * @throws LogicException In case no arguments have been provided
	 */
	public function getProcess()
	{
		if (0 === count($this->prefix) && 0 === count($this->arguments))
		{
			throw new LogicException('You must add() command arguments before calling getProcess().');
		}
		
		$options   = $this->options;
		$arguments = array_merge($this->prefix, $this->arguments);
		$script    = implode(' ', array_map(function ($key, $value) use ($arguments) {
			if (is_array($value))
			{
				return implode(' '.ProcessUtils::escapeArgument($arguments[$key - 1]).' ',
					array_map([ProcessUtils::class, 'escapeArgument'], $value));
			}
			
			return ProcessUtils::escapeArgument($value);
		}, array_keys($arguments), $arguments));
		
		echo '<pre>'.$script.'</pre>';
		
		if ($this->inheritEnv)
		{
			$env = array_replace($_ENV, $_SERVER, $this->env);
		}
		else
		{
			$env = $this->env;
		}
		
		$process = new Process($script, $this->cwd, $env, $this->input, $this->timeout, $options);
		
		if ($this->outputDisabled)
		{
			$process->disableOutput();
		}
		
		return $process;
	}
	
}
