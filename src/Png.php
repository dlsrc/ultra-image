<?php declare(strict_types=1);
/**
 * (c) 2005-2025 Dmitry Lebedev <dlsrc.extra@gmail.com>
 * This source code is part of the Ultra image package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Image;

use GdImage;

final class Png extends Image {
	public function extension(): string {
		return '.png';
	}

	protected function create(string $file): GdImage|false {
		return imagecreatefrompng($file);
	}

	protected function save(GdImage $image, string|null $file = NULL): void {
		imagealphablending($image, false);
		imagesavealpha($image, true);

		if (is_null($file)) {
			imagepng($image);
		}
		else {
			imagepng($image, $file);
		}
	}
}
