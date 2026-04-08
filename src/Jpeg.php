<?php declare(strict_types=1);
/**
 * (c) 2005-2025 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra image package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Image;

use GdImage;

final class Jpeg extends Image {
	public function extension(): string {
		return '.jpg';
	}

	protected function create(string $file): GdImage|false {
		return imagecreatefromjpeg($file);
	}

	protected function save(GdImage $image, string|null $file = null): void {
		if (is_null($file)) {
			imagejpeg($image);
		}
		else {
			imagejpeg($image, $file);
		}
	}
}

