<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class BillingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Billing', [
            'invoices' => $user ? $this->invoicesFor($user) : [],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function invoicesFor(User $user): array
    {
        if (blank(config('cashier.secret'))) {
            return [];
        }

        try {
            $invoices = $user->invoicesIncludingPending();
        } catch (Throwable $exception) {
            return [];
        }

        return collect($invoices)
            ->map(fn ($invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'total' => $invoice->total(),
                'status' => $invoice->status ?? null,
                'date' => $invoice->date()?->toIso8601String(),
                'receipt_url' => $invoice->hosted_invoice_url ?? null,
                'invoice_pdf' => $invoice->invoice_pdf ?? null,
            ])
            ->values()
            ->all();
    }
}
