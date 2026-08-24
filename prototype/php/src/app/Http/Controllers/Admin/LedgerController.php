<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Escrow\LedgerBalance;
use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Escrow\LedgerMovement;
use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class LedgerController extends Controller
{
    public function __invoke(Request $request): View
    {
        $sellerId = $request->filled('seller') ? $request->string('seller')->toString() : null;
        $type = $request->enum('type', LedgerEntryType::class);

        $entries = LedgerEntry::query()
            ->ofSeller($sellerId)
            ->ofType($type)
            ->with('seller')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        return view('admin.ledger.index', [
            'entries' => $entries,
            // The filtered set's own balance, not the platform's — a
            // partial ledger reads as a partial balance.
            'totals' => LedgerBalance::from(array_values($entries->map(fn (LedgerEntry $entry): LedgerMovement => $entry->toMovement())->all())),
            'sellers' => Seller::query()->orderBy('shop_name')->orderBy('email')->get(),
            'entryTypes' => LedgerEntryType::cases(),
            'selectedSeller' => $sellerId,
            'selectedType' => $type,
        ]);
    }
}
