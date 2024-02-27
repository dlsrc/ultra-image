<?php declare(strict_types=1);
/**
 * (c) 2005-2024 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra image package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Image;

final class Gif extends Image {
	public function extension(): string {
		return '.gif';
	}

	protected function create(string $file) {
		return imagecreatefromgif($file);
	}

	protected function save($image, string|null $file = NULL): void {
		//imagegif($image, $file);
		if (is_null($file)) {
			imagegif($image);
		}
		else {
			imagegif($image, $file);
		}
	}
}

