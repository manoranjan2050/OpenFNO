<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    /**
     * Dashboard summary over the user's closed trades (realized P&L only;
     * open-trade mark-to-market needs live prices — Phase 5).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $closed = $user->trades()
            ->where('status', 'closed')
            ->orderBy('closed_at')
            ->get(['id', 'underlying', 'strategy_name', 'closed_at', 'realized_pnl']);

        $openCount = $user->trades()->where('status', 'open')->count();

        $wins = $closed->where('realized_pnl', '>', 0);
        $losses = $closed->where('realized_pnl', '<', 0);

        $equityCurve = [];
        $running = 0.0;
        foreach ($closed as $trade) {
            $running += (float) $trade->realized_pnl;
            $equityCurve[] = [
                'date' => $trade->closed_at->toDateString(),
                'pnl' => round($running, 2),
            ];
        }

        return response()->json([
            'open_trades' => $openCount,
            'closed_trades' => $closed->count(),
            'total_pnl' => round((float) $closed->sum('realized_pnl'), 2),
            'win_rate' => $closed->count() > 0 ? round($wins->count() / $closed->count() * 100, 1) : null,
            'avg_win' => $wins->count() > 0 ? round((float) $wins->avg('realized_pnl'), 2) : null,
            'avg_loss' => $losses->count() > 0 ? round((float) $losses->avg('realized_pnl'), 2) : null,
            'best_trade' => round((float) ($closed->max('realized_pnl') ?? 0), 2),
            'worst_trade' => round((float) ($closed->min('realized_pnl') ?? 0), 2),
            'pnl_by_underlying' => $closed->groupBy('underlying')
                ->map(fn ($trades) => round((float) $trades->sum('realized_pnl'), 2)),
            'equity_curve' => $equityCurve,
        ]);
    }
}
