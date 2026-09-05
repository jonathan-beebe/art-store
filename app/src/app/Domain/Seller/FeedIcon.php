<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * The glyph a feed row or a dashboard focus group wears. The choice of
 * glyph is a business fact — which event or panel gets which icon; the
 * heroicon path each name draws is a design fact, held in
 * {@see \App\Seller\FeedIconPath}.
 */
enum FeedIcon: string
{
    case Eye = 'eye';
    case Heart = 'heart';
    case Cart = 'cart';
    case Bag = 'bag';
    case Card = 'card';
    case Cash = 'cash';
    case Printer = 'printer';
    case Truck = 'truck';
    case Check = 'check';
    case Undo = 'undo';
    case Chat = 'chat';
    case Pencil = 'pencil';
    case Users = 'users';
}
