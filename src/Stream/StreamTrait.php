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

use Arhitector\Transcoder\AdapterInterface;

/**
 * Class StreamTrait
 *
 * @package Arhitector\Transcoder\FFMpeg\Stream
 */
trait StreamTrait
{
	
	/**
	 * @var AdapterInterface Executor instance.
	 */
	protected $adapter;
	
	/**
	 * Set Adapter instance.
	 *
	 * @param AdapterInterface $adapter
	 *
	 * @return $this
	 */
	protected function setAdapter(AdapterInterface $adapter)
	{
		if ( ! $this->adapter)
		{
			$this->adapter = $adapter;
		}

		return $this;
	}
	
}
