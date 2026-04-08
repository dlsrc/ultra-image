<?php declare(strict_types=1);
/**
 * (c) 2005-2025 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra image package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Image;

use GdImage;

final class Gif extends Image {
	public function extension(): string {
		return '.gif';
	}

	protected function create(string $file): GdImage|false {
		return imagecreatefromgif($file);
	}

	protected function save(GdImage $image, string|null $file = NULL): void {
		if (is_null($file)) {
			imagegif($image);
		}
		else {
			imagegif($image, $file);
		}
	}
}

