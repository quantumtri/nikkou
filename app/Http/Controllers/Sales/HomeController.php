<?php

namespace App\Http\Controllers\Sales;

use App\Enums\LoanStatus;
use App\Http\Controllers\Controller;
use App\Models\ExperienceMessage;
use App\Models\Loan;
use App\Support\WhatsApp;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        // Sales home is always "my loans", including when an admin opens it.
        $mine = fn () => Loan::query()->where('sales_id', $user->id);

        return Inertia::render('Sales/Home', [
            'today' => now()->locale('id')->translatedFormat('l, j F Y'),
            'firstName' => str($user->name)->before(' ')->toString(),
            'stats' => [
                'active' => $mine()->where('status', LoanStatus::Active)->count(),
                'due_today' => $mine()->where('status', LoanStatus::Active)->whereDate('due_on', '<=', today())->count(),
            ],
            'alerts' => ExperienceMessage::query()
                ->where('is_serious', true)
                ->whereNull('resolved_at')
                ->whereHas('loan', fn ($q) => $q->where('sales_id', $user->id))
                ->with('customer:id,name,whatsapp_number')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (ExperienceMessage $message) => [
                    'id' => $message->id,
                    'customer' => $message->customer->name,
                    'excerpt' => mb_substr($message->body, 0, 140),
                    'when' => $message->created_at->locale('id')->diffForHumans(),
                    'whatsappUrl' => WhatsApp::chatUrl($message->customer->whatsapp_number, 'Halo '.$message->customer->name.', ini tim Nikkou. Kami menerima kabar dari Anda dan ingin memastikan keadaan Anda. Bagaimana kondisi Anda sekarang?'),
                ]),
            'loans' => $mine()
                ->with(['unit:id,code', 'customer:id,name'])
                ->orderByRaw("case when status = 'active' then 0 else 1 end")
                ->orderBy('due_on')
                ->limit(10)
                ->get()
                ->map(fn (Loan $loan) => LoanController::row($loan)),
        ]);
    }
}
