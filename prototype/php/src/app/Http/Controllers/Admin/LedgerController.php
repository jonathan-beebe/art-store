<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Escrow\LedgerBalance;
use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Escrow\LedgerMovement;
use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Seller;
use App\Support\ListPaneWindow;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class LedgerController extends Controller
{
    public function __invoke(Request $request): View
    {
        $sellerId = $request->filled('seller') ? $request->string('seller')->toString() : null;
        $type = $request->enum('type', LedgerEntryType::class);

        $filtered = LedgerEntry::query()
            ->ofSeller($sellerId)
            ->ofType($type)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        $window = ListPaneWindow::of((clone $filtered)->with('seller'));

        return view('admin.ledger.index', [
            'entries' => $window->items,
            'entriesTotal' => $window->total,
            // The filtered set's own balance, not the platform's — a
            // partial ledger reads as a partial balance. The fold needs
            // every matching movement, not just the rendered window.
            'totals' => LedgerBalance::from(array_values($filtered->get()->map(fn (LedgerEntry $entry): LedgerMovement => $entry->toMovement())->all())),
            'sellers' => Seller::query()->orderedForFilter()->get(),
            'entryTypes' => LedgerEntryType::cases(),
            'selectedSeller' => $sellerId,
            'selectedType' => $type,
        ]);
    }
}
