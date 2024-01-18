<?php declare(strict_types=1);
/**
 * (c) 2005-2023 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra image package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace ultra\image;

use ultra\Code;
use ultra\Error;
use ultra\IO;
use ultra\Status;

enum Cut {
	case Bottom;
	case Center;
	case Left;
	case Right;
	case Top;
}

abstract class Image {
	abstract public function extension(): string;
	abstract protected function create(string $file);
	abstract protected function save($image, string|null $file = null): void;

	/**
	 * Информация о файле изображения
	 */
	private array $info;

	/**
	 * Идентификатор изображения
	 */
	private $image;

	/**
	 * Использовать imagecopyresized вместо imagecopyresampled
	 */
	private bool $sharp;

	/**
	 * Создать миниатюру из изображения $source, вписав его в прямоугольник
	 * с шириной $width и соотношением ширины к высоте $ratio.
	 *
	 * $ratio нужно указать в виде строки в которой два целых числа разделены
	 * произвольным разделителем или пробелами, например: [w x h] или [w : h] и т.п.
	 * Где, w и h целые числа задающие пропорцию ширины к высоте.
	 *
	 * Возвращает, в случае успеха, относительный или полный путь до миниатюры вида:
	 * 
	 * $source_dirname/$source_filename-thumb[$w]x[$h]w[$width].$source_extension
	 * 
	 * где $w и $h числа пропорции ширины к высоте указанные в $ratio.
	 * 
	 * В случае неудачи возвращает FALSE.
	 * 
	 * Если нужно вернуть не полный путь а только часть пути, например, относительно DOCUMENT_ROOT,
	 * то в $source указывается только относительная часть от DOCUMENT_ROOT,
	 * а путь до DOCUMENT_ROOT нужно указать в $prefix
	 */
	public static function thumbnail(
		string $source,
		int    $width,
		string $prefix = '',
		string $ratio  = '3 : 2',
		bool   $check  = false
	): string|null {
		list($w, $h) = preg_split('/\D+/', $ratio);
		$path = pathinfo($source);
		$tmb = $path['dirname'].'/'.$path['filename'].'-thumb'.$w.'x'.$h.'w'.$width.'.'.$path['extension'];

		if (file_exists($prefix.$tmb)) {
			if (!$check) {
				return $tmb;
			}

			$size = getimagesize($prefix.$tmb);

			if ($size[0] == $width && round($size[0] / $w * $h, 0) == $size[1]) {
				return $tmb;
			}
		}

		if (!file_exists($prefix.$source)) {
			Error::log('File "'.$prefix.$source.'" not exists.', Code::Nofile);
			return null;
		}

		if (!$img = Image::make($prefix.$source)) {
			Error::log('Image object not created.', Status::Noobject);
			return null;
		}

		if (!$img->thumb($width, (int)round($width / $w * $h, 0), $prefix.$tmb)) {
			Error::log('Thumbnail file not generated.', Code::Makefile);
			return null;
		}

		return $tmb;
	}

	/**
	 * Заполнить изображением $source прямоугольник шириной $width
	 * и соотношением ширины к высоте $ratio, подгоняя его в нужный размер
	 * по возможности используя большую часть изображения и обрезая лишнюю высоту или ширину,
	 * если соотношение сторон исходного изображения не соответствует соотношению сторон
	 * указанному в $ratio.
	 * 
	 * $ratio нужно указать в виде строки в которой два целых числа разделены
	 * произвольным разделителем или пробелами, например: 'w x h' или 'w : h' и т.п.
	 * Где, w и h целые числа задающие пропорцию ширины к высоте.
	 * 
	 * Возвращает, в случае успеха, относительный или полный путь до миниатюры вида:
	 * 
	 * $source_dirname/$source_filename{$w}x{$h}w{$width}.$source_extension
	 * 
	 * где $w и $h числа пропорции ширины к высоте указанные в $ratio.
	 * 
	 * В случае неудачи возвращает FALSE.
	 * 
	 * Если нужно вернуть не полный путь а только часть пути, например, относительно DOCUMENT_ROOT,
	 * то в $source указывается только относительная часть от DOCUMENT_ROOT,
	 * а часть пути до DOCUMENT_ROOT нужно указать в $prefix
	 */
	public static function crop(
		string $source,
		int    $width,
		string $prefix = '',
		string $ratio  = '3 : 2',
		bool   $check  = false
	): ?string {
		list($w, $h) = preg_split('/\D+/', $ratio);
		$path = pathinfo($source);
		$tmb = $path['dirname'].'/'.$path['filename'].$w.'x'.$h.'w'.$width.'.'.$path['extension'];

		if (file_exists($prefix.$tmb)) {
			if (!$check) {
				return $tmb;
			}

			$size = getimagesize($prefix.$tmb);

			if ($size[0] == $width && round($size[0] / $w * $h, 0) == $size[1]) {
				return $tmb;
			}
		}

		if (!file_exists($prefix.$source)) {
			return null;
		}

		if (!$img = Image::make($prefix.$source)) {
			return null;
		}

		if (!$img->adapt($width, (int)round($width / $w * $h, 0), $prefix.$tmb)) {
			return null;
		}

		return $tmb;
	}

	/**
	 * Проверяет является ли файл файлом изображения и если это так, то возвращает
	 * массив с размером изображения, если нет вернёт FALSE.
	 */
	public static function exists(string $file): array|null {
		if (!file_exists($file) || !is_readable($file)) {
			return null;
		}

		if (!$type = mime_content_type($file)) {
			return null;
		}

		if (!str_starts_with($type, 'image')) {
			return null;
		}

		if (!$size = getimagesize($file)) {
			return null;
		}

		if (0 == $size[0] || 0 == $size[1]) {
			return null;
		}

		return $size;
	}

	public static function nonformat(string $file, int $width = 0, int $height = 0, string $ratio = ''): array {
		if (!$size = self::exists($file)) {
			return [1];
		}

		if ('' == $ratio) {
			if ($width > 0 && 0 == $height) {
				if ($size[0] > $width) {
					return [$size[0], $size[1], 2];
				}
			}
			elseif (0 == $width && $height > 0) {
				if ($size[1] > $height) {
					return [$size[0], $size[1], 3];
				}
			}
			elseif ($width > 0 && $height > 0) {
				if ($size[0] > $width || $size[1] > $height) {
					return [$size[0], $size[1], 4];
				}
			}
		}
		else {
			list($w, $h) = preg_split('/\D+/', $ratio);

			if ($size[0] * $h == $size[1] * $w) {
				if ($width > 0 && 0 == $height) {
					if ($size[0] > $width) {
						return [$size[0], $size[1], 5, $w, $h];
					}
				}
				elseif (0 == $width && $height > 0) {
					if ($size[1] > $height) {
						return [$size[0], $size[1], 6, $w, $h];
					}
				}
				elseif ($width > 0 && $height > 0) {
					if ($size[0] > $width || $size[1] > $height) {
						return [$size[0], $size[1], 7, $w, $h];
					}
				}
			}
			elseif ($size[0] / $w > $size[1] / $h) {
				if ($width > 0 && 0 == $height) {
					if ($size[0] > $width) {
						return [$size[0], $size[1], 10, $w, $h];
					}
				}
				elseif (0 == $width && $height > 0) {
					if ($size[1] > $height) {
						return [$size[0], $size[1], 11, $w, $h];
					}
				}
				elseif ($width > 0 && $height > 0) {
					if ($size[0] > $width || $size[1] > $height) {
						return [$size[0], $size[1], 12, $w, $h];
					}
				}
				else {
					return [$size[0], $size[1], 8, $w, $h];
				}
			}
			else {
				if ($width > 0 && 0 == $height) {
					if ($size[0] > $width) {
						return [$size[0], $size[1], 13, $w, $h];
					}
				}
				elseif (0 == $width && $height > 0) {
					if ($size[1] > $height) {
						return [$size[0], $size[1], 14, $w, $h];
					}
				}
				elseif ($width > 0 && $height > 0) {
					if ($size[0] > $width || $size[1] > $height) {
						return [$size[0], $size[1], 15, $w, $h];
					}
				}
				else {
					return [$size[0], $size[1], 9, $w, $h];
				}
			}
		}

		return [0];
	}

	/**
	 * Трансформировать изображение [$file] в рамках заданного формата [$width, $height, $ratio].
	 * При необходимости [$src] сохранить копию изображения в исходном формате,
	 * добавив суффикс .src к имени копии.
	 * Если [$width] (и\или) [$height] не нулевые, то формат подразумевает,
	 * что исходное изображение не должно превышать указанные ширину (и\или) высоту.
	 * Если [$ratio] не является пустой строкой, то изображение необходимо трансформировать
	 * в соответствии с указанной в [$ratio] пропорцией.
	 * Пропорцию в [$ratio] нужно указать в виде строки, в которой два целых числа разделены
	 * произвольным разделителем или пробелами, например: [w x h] или [w : h] и т.п.
	 * Где, w и h целые числа задающие пропорцию ширины к высоте.
	 *
	 * Возвращает исходное имя файла [$file] в случае успеха или FALSE в случае неудачи.
	 *
	 * Если нужно вернуть не полный путь а только часть пути, например, относительно DOCUMENT_ROOT,
	 * то в [$file] указывается только относительная часть от DOCUMENT_ROOT,
	 * а часть пути до DOCUMENT_ROOT нужно указать в [$prefix].
	 */
	public static function format(
		string $file,
		int    $width  = 0,
		int    $height = 0,
		string $ratio  = '',
		string $prefix = '',
		bool   $src    = false,
		bool   $suffix = false
	): string|null {
		$path = pathinfo($file);
		$source = $path['dirname'].'/'.$path['filename'].'.src.'.$path['extension'];

		if ($suffix) {
			if ($ratio) {
				$newfile = $path['dirname'].'/'.$path['filename'].'-'.$width.'x'.$height.'-'.preg_replace('/\D+/', 'x' ,trim($ratio)).'.'.$path['extension'];
			}
			else {
				$newfile = $path['dirname'].'/'.$path['filename'].'-'.$width.'x'.$height.'.'.$path['extension'];
			}

			if (file_exists($prefix.$newfile)) {
				return $newfile;
			}

			if (file_exists($prefix.$source)) {
				$format = self::nonformat($prefix.$source, $width, $height, $ratio);

				if (0 == $format[0] && 1 == count($format)) {
					copy($prefix.$source, $prefix.$newfile);
					return $newfile;
				}

				if (1 == $format[0] && 1 == count($format)) {
					return null;
				}

				if (!$img = Image::make($prefix.$source)) {
					return null;
				}
			}
			else {
				$format = self::nonformat($prefix.$file, $width, $height, $ratio);

				if (0 == $format[0] && 1 == count($format)) {
					copy($prefix.$file, $prefix.$newfile);
					return $newfile;
				}

				if (1 == $format[0] && 1 == count($format)) {
					return null;
				}

				if (!$img = Image::make($prefix.$file)) {
					return null;
				}
			}
		}
		else {
			$newfile = $file;
			$format = self::nonformat($prefix.$file, $width, $height, $ratio);

			if (0 == $format[0] && 1 == count($format)) {
				return $file;
			}

			if (1 == $format[0] && 1 == count($format)) {
				return null;
			}

			if (!$img = Image::make($prefix.$file)) {
				return null;
			}

			if ($src && !file_exists($prefix.$source)) {
				copy($prefix.$file, $prefix.$source);
			}
		}

		switch ($format[2]) {
		case 2:
		case 5:
			$img->resampled($width, (int)round($width * $format[1] / $format[0], 0), $prefix.$newfile);
			break;

		case 3:
		case 6:
			$img->resampled((int)round($height * $format[0] / $format[1], 0), $height, $prefix.$newfile);
			break;

		case 4:
		case 7:
			$w = $width / $format[0];
			$h = $height / $format[1];

			if ($w < $h) {
				$img->resampled($width, (int)round($width * $format[1] / $format[0], 0), $prefix.$newfile);
			}
			elseif ($w > $h) {
				$img->resampled((int)round($height * $format[0] / $format[1], 0), $height, $prefix.$newfile);
			}
			else {
				$img->resampled($width, $height, $prefix.$newfile);
			}

			break;

		case 8:
			$img->adapt($format[0], (int)round($format[0] * $format[4] / $format[3], 0), $prefix.$newfile);
			break;

		case 9:
			$img->adapt((int)round($format[1] * $format[3] / $format[4], 0), $format[1], $prefix.$newfile);
			break;

		case 10:
		case 13:
		case 15:
			$img->adapt($width, (int)round($width * $format[4] / $format[3], 0), $prefix.$newfile);
			break;

		case 11:
		case 12:
		case 14:
			$img->adapt((int)round($height * $format[3] / $format[4], 0), $height, $prefix.$newfile);
			break;
		}

		return $newfile;
	}

	public static function view(
		string $source,
		int    $width,
		string $prefix = '',
		string $suffix = '',
		string $ratio  = '',
		bool   $check  = false
	): ?string {
		if ('' == $suffix) {
			$suffix = 'i'.$width.'.';
		}
		elseif (!str_ends_with($suffix, '.')) {
			$suffix.= '.';
		}

		$path = pathinfo($source);
		$view = $path['dirname'].'/'.$path['filename'].$suffix.$path['extension'];

		if (file_exists($prefix.$view)) {
			if (!$check) {
				return $view;
			}

			$size = getimagesize($prefix.$view);

			if ('' == $ratio) {
				if ($size[0] == $width) {
					return $view;
				}
			}
			else {
				list($w, $h) = preg_split('/\D+/', $ratio);

				if ($size[0] == $width && round($size[0] / $w * $h, 0) == $size[1]) {
					return $view;
				}
			}
		}

		if (!file_exists($prefix.$source)) {
			return null;
		}

		if (!$img = Image::make($prefix.$source)) {
			return null;
		}

		if ('' == $ratio) {
			$img->fit($width, 0, $prefix.$view);
		}
		else {
			list($w, $h) = preg_split('/\D+/', $ratio);
			$img->fit($width, (int)round($width / $w * $h, 0), $prefix.$view);
		}

		return $view;
	}

	final protected function __construct(string $file, array $info) {
		$this->image = $this->create($file);
		$this->info  = $info;
		$this->sharp = false;
	}

	public function sharp(bool $sharp = true): void {
		$this->sharp = $sharp;
	}

	public static function rounded(&$img, int $radius = 8, int $rate = 80): void {
		$width = imagesx($img);
		$height = imagesy($img);

		// для получения прозрачности
		imagealphablending($img, false);
		imagesavealpha($img, true);

		$rs_radius = $radius * $rate;
		$rs_size = $rs_radius * 2;

		$corner = imagecreatetruecolor($rs_size, $rs_size);
		imagealphablending($corner, false);

		$trans = imagecolorallocatealpha($corner, 255, 255, 255, 127);
		imagefill($corner, 0, 0, $trans);

		$positions = [
			[0, 0, 0, 0],
			[$rs_radius, 0, $width - $radius, 0],
			[$rs_radius, $rs_radius, $width - $radius, $height - $radius],
			[0, $rs_radius, 0, $height - $radius]
		];

		foreach ($positions as $pos) {
			imagecopyresampled(
				$corner,
				$img,
				$pos[0],
				$pos[1],
				$pos[2],
				$pos[3],
				$rs_radius,
				$rs_radius,
				$radius,
				$radius
			);
		}

		$lx  = $ly = 0;
		$i   = -$rs_radius;
		$y2  = -$i;
		$r_2 = $rs_radius * $rs_radius;

		for (; $i <= $y2; $i++) {
			$y = $i;
			$x = (int)round(sqrt($r_2 - $y * $y), 0);

			$y += $rs_radius;
			$x += $rs_radius;

			imageline($corner, $x, $y, $rs_size, $y, $trans);
			imageline($corner, 0, $y, $rs_size - $x, $y, $trans);

			$lx = $x;
			$ly = $y;
		}

		foreach ($positions as $i => $pos) {
			imagecopyresampled(
				$img,
				$corner,
				$pos[2],
				$pos[3],
				$pos[0],
				$pos[1],
				$radius,
				$radius,
				$rs_radius,
				$rs_radius
			);
		}
	}

	public static function make(string $file): ?self {
		if (!extension_loaded('gd')) {
			Error::log('gd', Status::Mode);
			return null;
		}

		$info = getimagesize($file);

		switch ($info[2]) {
		case IMAGETYPE_JPEG:
			return new Jpeg($file, $info);
		case IMAGETYPE_GIF:
			return new Gif($file, $info);
		case IMAGETYPE_PNG:
			return new Png($file, $info);
		case IMAGETYPE_WBMP:
			return new Wbmp($file, $info);
		default:
			Error::log($info[2], Status::Domain);
			return null;
		}
	}

	/**
	 * Вывести изображение в стандартный поток или в файл
	 * с уменьшением размера изображения до заданной ширины.
	 */
	public function reduce(int $width, string|null $file = null): void {
		if ($this->info[0] > $width) {
			$this->resize($width, $file);
		}
		else {
			$this->send($this->image, $file);
		}
	}

	/**
	 * Вывести изображение в стандартный поток или в файл
	 * с изменением размера (уменьшение, увеличение) изображения на заданную ширину.
	 */
	public function resize(int $width, string|null $file = null): void {
		$height = (int)round($this->info[1] / ($this->info[0] / $width), 0);
		$this->resampled($width, $height, $file);
	}

	public function fit(int $width, int $height = 0, ?string $file = null): void {
		if (0 == $height) {
			$this->reduce($width, $file);
			return;
		}

		if ($width >= $this->info[0] && $height >= $this->info[1]) {
			$this->send($this->image, $file);
			return;
		}

		$w = $this->info[0] / $width;
		$h = $this->info[1] / $height;

		if ($w > $h) {
			$height = (int)round($this->info[1] / $w, 0);
		}
		elseif ($w < $h) {
			$width = (int)round($this->info[0] / $h, 0);
		}

		$this->resampled($width, $height, $file);
	}

	/**
	 * Создать миниатюру для текущего объекта вписав его в заданные размеры,
	 * результат вывести в стандартный поток или в файл.
	 */
	public function thumb(int $w, int $h, string|null $file = null): bool {
		$wh = $w / $h;

		$iw = $this->info[0];
		$ih = $this->info[1];

		$iwh = $iw / $ih;

		if (!$image = imagecreatetruecolor($w, $h)) {
			return false;
		}

		imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 127));

		if ($w > $iw && $h > $ih) {
			$nw = $iw;
			$nh = $ih;
			$x  = (int) round(($w - $iw) / 2.01, 0);
			$y  = (int) round(($h - $ih) / 2.01, 0);
		}
		elseif ($wh > $iwh) {
			$nw = (int) round($h * $iwh, 0);
			$nh = $h;
			$x  = (int) round(($w - $nw) / 2.01, 0);
			$y  = 0;
		}
		elseif ($wh < $iwh) {
			$nw = $w;
			$nh = (int) round($w / $iwh, 0);
			$x  = 0;
			$y  = (int) round(($h - $nh) / 2.01, 0);
		}
		else {
			$nw = $w;
			$nh = $h;
			$x  = 0;
			$y  = 0;
		}

		if (!imagecopyresampled($image, $this->image, $x, $y, 0, 0, $nw, $nh, $iw, $ih)) {
			return false;
		}

		$this->send($image, $file);
		return true;
	}

	/**
	 * Создать на основе текущего объекта новое изображение с заданными размерами,
	 * по возможности поместив весь объект или максимально возможную его часть в новом изображении без искажений,
	 * результат вывести в стандартный поток или в файл.
	 */
	public function adapt(
		int $w,
		int $h,
		string|null $file = null,
		Cut $ch = Cut::Center
	): bool {
		$wh = $w / $h;

		$iw = $this->info[0];
		$ih = $this->info[1];

		$iwh = $iw / $ih;

		if (!$image = imagecreatetruecolor($w, $h)) {
			return false;
		}

		imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 127));

		if ($w > $iw && $h > $ih) {
			$x  = (int)round(($w - $iw) / 2.01, 0);
			$y  = (int)round(($h - $ih) / 2.01, 0);
			$ix = 0;
			$iy = 0;
			$nw = $sw = $iw;
			$nh = $sh = $ih;
		}
		elseif ($w > $iw) {
			$x  = (int)round(($w - $iw) / 2, 0);
			$y  = 0;
			$ix = 0;

			$iy = match ($ch) {
				Cut::Top   => 0,
				Cut::Bottm => $ih - $h,
				default    => (int) round(($ih - $h) / 2, 0),
			};

			$nw = $sw = $iw;
			$nh = $sh = $h;
		}
		elseif ($h > $ih) {
			$x  = 0;
			$y  = (int)round(($h - $ih) / 2, 0);

			$ix = match ($ch) {
				Cut::Left  => 0,
				Cut::Right => $iw - $w,
				default    => (int) round(($iw - $w) / 2, 0),
			};

			$iy = 0;
			$nw = $sw = $w;
			$nh = $sh = $ih;
		}
		elseif ($wh > $iwh) {
			$x  = 0;
			$y  = 0;
			$ix = 0;

			$iy = match ($ch) {
				Cut::Top   => 0,
				Cut::Bottm => (int) round($ih - $iw * $h / $w, 0),
				default    => (int) round(($ih - $iw * $h / $w) / 2, 0),
			};

			$nw = $w;
			$nh = $h;
			$sw = $iw;
			$sh = (int)round($iw * $h / $w, 0);
		}
		elseif ($wh < $iwh) {
			$x  = 0;
			$y  = 0;

			$ix = match ($ch) {
				Cut::Left  => 0,
				Cut::Right => (int) round($iw - $ih * $w / $h, 0),
				default    => (int) round(($iw - $ih * $w / $h) / 2, 0),
			};

			$iy = 0;
			$nw = $w;
			$nh = $h;
			$sw = (int)round($ih * $w / $h, 0);
			$sh = $ih;
		}
		else {
			$x  = 0;
			$y  = 0;
			$ix = 0;
			$iy = 0;
			$nw = $w;
			$nh = $h;
			$sw = $iw;
			$sh = $ih;
		}

		if (!imagecopyresampled($image, $this->image, $x, $y, $ix, $iy, $nw, $nh, $sw, $sh)) {
			return false;
		}

		$this->send($image, $file);
		return true;
	}

	public function resampled(int $width, int $height, string|null $file = null): void {
		$resize = imagecreatetruecolor($width, $height);
		imagefill($resize, 0, 0, imagecolorallocatealpha($resize, 255, 255, 255, 127));

		if ($this->sharp) {
			imagecopyresized(
				$resize, $this->image, 0, 0, 0, 0,
				$width, $height, $this->info[0], $this->info[1]
			);
		}
		else {
			imagecopyresampled(
				$resize, $this->image, 0, 0, 0, 0,
				$width, $height, $this->info[0], $this->info[1]
			);
		}

		$this->send($resize, $file);
	}

	public function resizeRotate(int $width, int $prop = 1, ?string $file = null): void {
		if ($this->info[1] > ($this->info[0] * $prop)) {
			$rotate = imagerotate($this->image, 90, 0);
			$height = (int)round($this->info[0] / ($this->info[1] / $width), 0);
		}
		else {
			$rotate = $this->image;
			$height = (int)round($this->info[1] / ($this->info[0] / $width), 0);
		}

		$resize = imagecreatetruecolor($width, $height);
		imagefill($resize, 0, 0, imagecolorallocatealpha($resize, 255, 255, 255, 127));

		imagecopyresized(
			$resize, $rotate, 0, 0, 0, 0,
			$width, $height, $this->info[0], $this->info[1]
		);

		$this->send($resize, $file);
	}

	protected function send($image, string|null $file = null): void {
		if (is_null($file)) {
			header('Content-type: '.$this->info['mime']);
		}

		IO::indir($file);
		$this->save($image, $file);
	}

	public function rotate(int $deg, int $bg = 0, string|null $file = null): void {
		$rotate = imagerotate($this->image, $deg, $bg);
		$this->send($rotate, $file);
	}

	public function show(): void {
		$this->send($this->image);
	}

	public function inWidth(int $width): bool {
		if ($width >= $this->info[0]) {
			return true;
		}

		return false;
	}

	public function inHeight(int $height): bool {
		if ($height >= $this->info[1]) {
			return true;
		}

		return false;
	}

	public function isWidth(int $width): bool {
		if ($width == $this->info[0]) {
			return true;
		}

		return false;
	}

	public function isHeight(int $height): bool {
		if ($height == $this->info[1]) {
			return true;
		}

		return false;
	}

	public function width(): int{
		return $this->info[0];
	}

	public function height(): int {
		return $this->info[1];
	}
}
