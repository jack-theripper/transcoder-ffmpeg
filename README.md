
## 1. Введение [![GitHub release](https://img.shields.io/github/release/jack-theripper/transcoder-ffmpeg.svg?maxAge=2592000?style=flat-square)](https://github.com/jack-theripper/transcoder-ffmpeg/releases) [![license](https://img.shields.io/github/license/mashape/apistatus.svg?maxAge=2592000?style=flat-square)](https://github.com/jack-theripper/transcoder-ffmpeg/blob/develop/LICENSE)

FFMpeg-адаптер для [arhitector\transcoder](https://github.com/jack-theripper/transcoder). В своей работе использует 
утилиты `ffmpeg` и `ffprobe` из стандартного пакета ffmpeg.

## 1.1. Требования

- PHP 5.5 или новее
- Установленный `FFMPEG` и `FFPROBE`

## 1.2. Установка

```bash
$ composer require arhitector/transcoder-ffmpeg
```

## 2. Возможности адаптера

Поддерживается большинство возможностей, предоставляемых `arhitector\transcoder` (чтение информации, запись метаданных, 
транскодирование и прочее).

Адаптер реализует для удобства свои обёртки над `Audio`, `Video`, `Subtitle`.

```php
public *::__construct(string $filePath [, array $options = array()])
```

Создаёт новое объектно ориентированное представление для конкретного медиа-файла.

Список параметров

- `$filePath` допускает значение типа string, путь до существующего аудио, видео или файла субтитров.

- `$options` принимает значение `array`, массив опций адаптера.

**Примеры**

Пример \#1: Общий пример.

```php
// для аудио
$audio = new Arhitector\Transcoder\FFMpeg\Audio('audio.mp3', [/* опции */]);

// видео или изображение
$video = new Arhitector\Transcoder\FFMpeg\Video('video.mp4', [/* опции */]);

// и для субтитров
$subtitle = new Arhitector\Transcoder\FFMpeg\Subtitle('subtitles.ass');
```

## 3. Опции адаптера

- `ffmpeg.path` путь до бинарного файла `ffmpeg`, принимает тип `string`. Чаще всего, когда `FFMPEG` установлен, адаптер 
может самостоятельно найти расположение бинарных файлов на основе вашего окружения.

- `ffmpeg.threads` устанавливает значение опции `-threads`, принимает `integer`. По умолчанию `0` (ноль).

- `ffprobe.path` путь до бинарного файла `ffprobe`, принимает тип `string`.

- `timeout` время ожидания выполнения команд в секундах, тип `integer`, по умолчанию без ограничений.

**Примеры**

Пример \#1: Пример массива.

```php
$options = [
	'ffmpeg.path' => 'path/bin/ffmpeg.exe',
	'ffmpeg.threads' => 12,
	'ffprobe.path' => 'usr/bin/ffprobe',
	'timeout' => 3600
];
```

Пример \#2: Использование опций.

```php
$audio = new Arhitector\Transcoder\FFMpeg\Audio('audio.mp3', [
	'timeout' => 300,
	// 'ffmpeg.path' => 'ffmpeg',
	// ...
]);
```

Пример \#3: Создание экземпляра адаптера.

```php
$adapter = new Arhitector\Transcoder\FFMpeg\Adapter([
	/* опции */
]);
```

## 4. Фильтры

Список поддерживаемых фильтров:

- ....
- ....

## 5. Пресеты

.....

## 6. Примеры

Эти примеры характерны только для `transcoder-ffmpeg` адаптера.

## 6.1. Разложить видеоряд на кадры

Пример \#1: Извлечь 1 кадр.

```php
$video->save(new Jpeg, 'picture.jpg');
```

Пример \#2: Сохранить множество кадров.

```php
$video->save(new Png, 'picture-%05d.jpg');
```

## 7. Лицензия (License)

[MIT License (MIT)](LICENSE)

