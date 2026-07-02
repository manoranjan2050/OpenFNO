<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseTradeRequest;
use App\Http\Requests\StoreTradeRequest;
use App\Http\Requests\UpdateTradeRequest;
use App\Http\Resources\TradeResource;
use App\Models\Trade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TradeController extends Controller
{
    public function index(Request $request)
    {
        $trades = $request->user()->trades()
            ->with(['legs', 'attachments'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('underlying'), fn ($q, $u) => $q->where('underlying', $u))
            ->when($request->query('tag'), fn ($q, $tag) => $q->whereJsonContains('tags', $tag))
            ->orderByDesc('opened_at')
            ->paginate($request->integer('per_page', 25));

        return TradeResource::collection($trades);
    }

    public function store(StoreTradeRequest $request)
    {
        $data = $request->validated();

        $trade = DB::transaction(function () use ($request, $data) {
            $trade = $request->user()->trades()->create([
                ...collect($data)->except('legs')->all(),
                'status' => 'open',
            ]);

            foreach ($data['legs'] as $leg) {
                $trade->legs()->create([
                    ...$leg,
                    'entry_at' => $leg['entry_at'] ?? $data['opened_at'],
                ]);
            }

            return $trade;
        });

        return (new TradeResource($trade->load(['legs', 'attachments'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Trade $trade)
    {
        $this->authorize('view', $trade);

        return new TradeResource($trade->load(['legs', 'attachments']));
    }

    public function update(UpdateTradeRequest $request, Trade $trade)
    {
        $this->authorize('update', $trade);

        $data = $request->validated();

        if (isset($data['legs']) && ! $trade->isOpen()) {
            throw ValidationException::withMessages([
                'legs' => 'Legs of a closed trade cannot be modified. Reopen the trade first.',
            ]);
        }

        DB::transaction(function () use ($trade, $data) {
            $trade->update(collect($data)->except('legs')->all());

            if (isset($data['legs'])) {
                $trade->legs()->delete();
                foreach ($data['legs'] as $leg) {
                    $trade->legs()->create([
                        ...$leg,
                        'entry_at' => $leg['entry_at'] ?? $trade->opened_at,
                    ]);
                }
            }
        });

        return new TradeResource($trade->refresh()->load(['legs', 'attachments']));
    }

    public function destroy(Request $request, Trade $trade)
    {
        $this->authorize('delete', $trade);

        $trade->delete();

        return response()->noContent();
    }

    /**
     * Close a trade: record exit price for every open leg, freeze realized P&L.
     */
    public function close(CloseTradeRequest $request, Trade $trade)
    {
        $this->authorize('update', $trade);

        if (! $trade->isOpen()) {
            throw ValidationException::withMessages(['status' => 'Trade is already closed.']);
        }

        $data = $request->validated();
        $closedAt = $data['closed_at'] ?? now();
        $exits = collect($data['legs'])->keyBy('id');

        $trade->load('legs');
        $openLegs = $trade->legs->whereNull('exit_price');

        $missing = $openLegs->pluck('id')->diff($exits->keys());
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'legs' => 'Exit prices missing for leg(s): '.$missing->implode(', '),
            ]);
        }

        DB::transaction(function () use ($trade, $openLegs, $exits, $closedAt) {
            foreach ($openLegs as $leg) {
                $exit = $exits[$leg->id];
                $leg->update([
                    'exit_price' => $exit['exit_price'],
                    'exit_at' => $exit['exit_at'] ?? $closedAt,
                ]);
            }

            $trade->update([
                'status' => 'closed',
                'closed_at' => $closedAt,
                'realized_pnl' => $trade->refresh()->load('legs')->bookedPnl(),
            ]);
        });

        return new TradeResource($trade->refresh()->load(['legs', 'attachments']));
    }

    /**
     * Reopen a closed trade (e.g. closed by mistake). Keeps exits; clears frozen P&L.
     */
    public function reopen(Request $request, Trade $trade)
    {
        $this->authorize('update', $trade);

        $trade->update(['status' => 'open', 'closed_at' => null, 'realized_pnl' => null]);

        return new TradeResource($trade->load(['legs', 'attachments']));
    }
}
