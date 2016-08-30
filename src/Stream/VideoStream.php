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
namespace Arhitector\Transcoder\FFMpeg\Stream;

use Arhitector\Transcoder\DecoratorTrait;
use Arhitector\Transcoder\FFMpeg\CommandExecutor;
use Arhitector\Transcoder\FFMpeg\Option\ForceFormatOptionTrait;
use Arhitector\Transcoder\Format\FormatInterface;
use Arhitector\Transcoder\Format\Jpeg;
use Arhitector\Transcoder\Format\VideoFormatInterface;
use Arhitector\Transcoder\Stream\VideoStreamInterface;

/**
 * Class Video.
 *
 * @package Arhitector\Transcoder\FFMpeg\Stream
 */
class VideoStream extends \Arhitector\Transcoder\Stream\VideoStream implements VideoStreamInterface
{
	use StreamTrait;
	
	/**
	 * Stream save.
	 *
	 * @param FormatInterface $format
	 * @param string          $filePath
	 * @param bool            $overwrite
	 *
	 * @return bool
	 */
	public function save(FormatInterface $format, $filePath, $overwrite = true)
	{
		
	}
	
	/**
	 * Stream save.
	 *
	 * @param FormatInterface $format
	 * @param string          $filePath
	 *
	 * @return bool
	 * @throws \InvalidArgumentException
	 */
	public function save2(FormatInterface $format, $filePath)
	{
		/*if ( ! $format instanceof VideoFormatInterface)
		{
			throw new \InvalidArgumentException('This stream supports only video format.');
		}


		$options[] = '-i';
		$options[] = $this->getFilePath();

		$options = array_merge($options, $this->getForceFormatOption($format));

		var_dump($options);*/


		/*
		$command[] = '-i';
		$command[] = $this->getFilePath();
		$command[] = '-map';
		$command[] = sprintf('0:%s', $this->getIndex());

		if ($format instanceof Jpeg)
		{
			$command[] = '-f';
			$command[] = 'image2';
		}

		$command[] = $filePath;



		$this->decorator->execute($command);


	*/


	}


}
