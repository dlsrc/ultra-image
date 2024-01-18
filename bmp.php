<?php declare(strict_types=1);
/**
 * (c) 2005-2023 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra image package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace ultra\image;

final class Wbmp extends Image {
	public function extension(): string	{
		return '.bmp';
	}

	protected function create(string $file) {
		return imagecreatefromwbmp($file);
	}

	protected function save($image, string|null $file = NULL): void {
		//imagewbmp($image, $file);
		if (is_null($file)) {
			imagewbmp($image);
		}
		else {
			imagewbmp($image, $file);
		}
	}
}
