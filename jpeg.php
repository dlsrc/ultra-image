<?php declare(strict_types=1);
/**
 * (c) 2005-2023 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra image package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace ultra\image;

final class Jpeg extends Image {
	public function extension(): string {
		return '.jpg';
	}

	protected function create(string $file) {
		return \imagecreatefromjpeg($file);
	}

	protected function save($image, string|null $file = null): void {
		if (\is_null($file)) {
			\imagejpeg($image);
		}
		else {
			\imagejpeg($image, $file);
		}
	}
}

