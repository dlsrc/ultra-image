<?php declare(strict_types=1);
/**
 * (c) 2005-2025 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra image package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Image;

use GdImage;

final class Wbmp extends Image {
	public function extension(): string	{
		return '.bmp';
	}

	protected function create(string $file): GdImage|false {
		return imagecreatefromwbmp($file);
	}

	protected function save(GdImage $image, string|null $file = NULL): void {
		if (is_null($file)) {
			imagewbmp($image);
		}
		else {
			imagewbmp($image, $file);
		}
	}
}
