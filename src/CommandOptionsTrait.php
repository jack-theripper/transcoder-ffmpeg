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

use Arhitector\Transcoder\Format\AudioFormatInterface;
use Arhitector\Transcoder\Format\FormatInterface;
use Arhitector\Transcoder\Format\SubtitleFormatInterface;
use Arhitector\Transcoder\Format\VideoFormatInterface;
use Arhitector\Transcoder\Stream\AudioStreamInterface;
use Arhitector\Transcoder\Stream\StreamInterface;
use Arhitector\Transcoder\Tools\Options;

/**
 * Class CommandOptionsTrait.
 *
 * @package Arhitector\Transcoder\FFMpeg
 */
trait CommandOptionsTrait
{
	
	/**
	 * Get options for format.
	 *
	 * @param FormatInterface $format
	 *
	 * @return array
	 */
	protected function getFormatOptions(FormatInterface $format)
	{
		$options = [];
		
		if ($format instanceof AudioFormatInterface)
		{
			$options['audio_codec'] = $format->getAudioCodecString() ?: 'copy';
			$options['video_codec'] = 'copy';
			
			if ($format->getAudioKiloBitRate() > 0)
			{
				$options['audio_bitrate'] = $format->getAudioKiloBitRate().'k';
			}
			
			if ($format->getAudioFrequency() > 0)
			{
				$options['audio_sample_frequency'] = $format->getAudioFrequency();
			}
			
			if ($format->getAudioChannels() > 0)
			{
				$options['audio_channels'] = $format->getAudioChannels();
			}
		}
		
		if ($format instanceof VideoFormatInterface)
		{
			$options['video_codec'] = $format->getVideoCodecString() ?: 'copy';
			
			if ($format->getVideoFrameRate() > 0)
			{
				$options['video_frame_rate'] = $format->getVideoFrameRate();
			}
			
			if ($format->getVideoKiloBitRate() > 0)
			{
				$options['video_bitrate'] = $format->getVideoKiloBitRate().'k';
			}
		}
		
		if ($format instanceof SubtitleFormatInterface)
		{
			
		}
		
		return $options;
	}
	
	/**
	 * Get force format options.
	 *
	 * @param $format
	 *
	 * @return array
	 */
	protected function getForceFormatOptions(FormatInterface $format)
	{
		if ( ! ($mapping = CacheStorage::get('forceFormat')))
		{
			$mapping = CacheStorage::set('forceFormat', (array) require_once __DIR__.'/bin/force_format.php');
		}
		
		$formatExtensions = $format->getExtensions();
		
		foreach ($mapping as $format => $extensions)
		{
			if (array_intersect($formatExtensions, $extensions))
			{
				return ['ffmpeg_force_format' => $format];
			}
		}
		
		return [];
	}
	
	/**
	 * Get options for streams.
	 *
	 * @param \SplFixedArray|StreamInterface[] $streams
	 * @param Options        $options
	 *
	 * @return array
	 */
	protected function getStreamOptions(\SplFixedArray $streams, Options $options)
	{
		$options = ['i' => (array) $options['i']];
		
		foreach ($streams as $stream)
		{
			if ( ! $stream) // поток удалён из вывода.
			{
				continue;
			}
			
			if (($input = array_search($stream->getFilePath(), $options['i'])) === false)
			{
				$options['i'][] = $stream->getFilePath();
				$input = sizeof($options['i']) - 1;
			}
			
			$options['map'][] = sprintf("%s:%d", $input, $stream->getIndex());
			
			if ($stream->isModified())
			{
				if ($stream instanceof AudioStreamInterface)
				{
					//	$options['codec:a:'.$stream->getIndex()] = (string) $stream->getCodec();
				}
			}
		}
		
		return $options;
	}
	
}
