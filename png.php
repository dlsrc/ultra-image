<?php declare(strict_types=1);
/**
 * (c) 2005-2023 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra image package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace ultra\image;

final class Png extends Image {
	public function extension(): string {
		return '.png';
	}

	protected function create(string $file) {
		return \imagecreatefrompng($file);
	}

	protected function save($image, string|null $file = NULL): void {
		\imagealphablending($image, false);
		\imagesavealpha($image, true);

		if (\is_null($file)) {
			\imagepng($image);
		}
		else {
			\imagepng($image, $file);
		}
	}
}
