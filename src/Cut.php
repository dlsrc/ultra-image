<?php declare(strict_types=1);
/**
 * (c) 2005-2026 Dmitry Lebedev <dlsrc.extra@gmail.com>
 * This source code is part of the Ultra image package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Image;

enum Cut {
	case Bottom;
	case Center;
	case Left;
	case Right;
	case Top;
}
