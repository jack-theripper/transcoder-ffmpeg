# transcoder-ffmpeg

Это FFMpeg-адаптер для arhitector\transcoder.

# 1. Возможности адаптера

Адаптер реализует для удобства свои обёртки: Audio, Video, Subtitle.

```php
public Object:: __construct(string $filePath [, array $options = array()])
```

`$filePath` - путь до файла.

`$options` - массив опций адаптера.

```php
$options = [

	// адаптер может сам найти бинарные файлы ffmpeg из вашего окружения,
	// но вы можете самостоятельно указать путь к исполняемому файлу.
	'ffmpeg.path' => 'path/bin/ffmpeg.exe',

	// ffmpeg-опция '-threads'
	'ffmpeg.threads' => 12,
	
	// ffprobe из стандартной поставки ffmpeg используется для исзвлечения медиа-информации.
	// если не установлен ffprobe, то адаптер будет пытаться ипользовать утилиту ffmpeg.
	'ffprobe.path' => 'usr/bin/ffprobe',
	
	// время ожидания выполнения команд, по умолчанию без ограничений.
	'timeout' => 3600
]
```

**Примеры:**

```php
$audio = new Arhitector\Transcoder\FFMpeg\Audio('file path');

$video = new Arhitector\Transcoder\FFMpeg\Video('file path', ['...' => 'options']);

$subtitle = new Arhitector\Transcoder\FFMpeg\Subtitle('file path.srt');
```

## 1.1. Получение информации о медиа-файлах.

```php
$video->getWidth();

$video->getHeight();

// см. документацию по arhitector\transcoder
```

## 1.2. Получение информаии о потоках, аудио дорожках, видео ряде и субтитрах.

```php
$audio->getStreams(); // все потоки контейнера

$subtitle->getStream(0); // первый поток, субтитры

// см. документацию по arhitector\transcoder
```

## 1.3. Извлечение потока из медиа-файла.

Адаптер поддерживает разделение медиа-файла на потоки.

```php
$format = new Aac();
$stream = $video->getStream(1);

$stream->save($format, 'file.aac');

// см. документацию по arhitector\transcoder
```

## 1.4. Добавление новых потоков в медиа-контейнер.

Адаптер поддерживает добавление потоков в вывод.

```php
$streams = $audio->getStreams(); // все потоки в текущем файле
$format = $audio->getFormat(); // использовать тот же формат

// выбираем обложку для аудио-файла
$picture = new Video('picture.jpg');

// если существует обложка, ее нужно удалить
foreach ($streams as $key => $stream)
{
	if ($stream instanceof VideoInterface)
	{
		unset($streams[$key]);
	}
}

// добавить поток (изображение) в качестве обложки
$audio->addStream($picture->getStream(0));

$audio->save($format, 'new file path');
```

## 1.5. Кодирование потока своим кодеком.

## 1.6. Разложить видеоряд на кадры

```php
$video->save(new Jpeg, 'picture.jpg');

$video->save(new Png, 'picture-%05d.jpg');
```

1.6. Подерживается модель событий.

1.7. Транскодирование в различные форматы.

1.8. Поддержка фильтров и цепочек фильтров.

1.9. Поддерживаются пресеты.


